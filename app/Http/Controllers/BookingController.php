<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingCollection;
use App\Http\Resources\BookingResource;
use App\Mail\BankTransferBooking;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Mail\BookingConfirmation;
use App\Models\Coupon;
use App\Models\CouponUsage;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        // Debug to check what authentication headers are present
        Log::info('Auth header', [
            'authorization' => $request->header('Authorization'),
            'content_type' => $request->header('Content-Type')
        ]);

        // Validate the booking data - ADD payment_method validation
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
            'therapist_id' => 'required|exists:therapists,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|string',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            // Direct address fields
            'address_line1' => 'required_without:address|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required_without:address|string|max:100',
            'postcode' => 'required_without:address|string|max:20',
            // Or nested address object
            'address' => 'required_without_all:address_line1,city,postcode|array',
            'address.address_line1' => 'required_with:address|string|max:255',
            'address.address_line2' => 'nullable|string|max:255',
            'address.city' => 'required_with:address|string|max:100',
            'address.postcode' => 'required_with:address|string|max:20',
            'notes' => 'nullable|string',
            'coupon_code' => 'nullable|string',
            'payment_method' => 'required|in:card,bank_transfer', // ADD THIS LINE
            'location_id' => 'nullable|exists:locations,id', // ADD THIS IF YOU HAVE LOCATIONS
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get user if authenticated
        $user = auth()->user();

        // Debug user authentication status
        Log::info('User authentication check', [
            'is_authenticated' => $user ? 'Yes' : 'No',
            'user_id' => $user ? $user->id : 'Not authenticated'
        ]);

        // If user is not authenticated but token is provided, try to get user from token directly
        if (!$user && $request->bearerToken()) {
            Log::info('Attempting to get user from token directly');
            try {
                $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken());
                if ($token) {
                    $user = $token->tokenable;
                    Log::info('User found from token', ['user_id' => $user->id]);
                }
            } catch (\Exception $e) {
                Log::error('Error retrieving user from token: ' . $e->getMessage());
            }
        }

        // Check service availability
        $service = Service::find($request->service_id);
        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found'
            ], 404);
        }

        // Handle address fields (either directly or from nested object)
        $addressLine1 = $request->address_line1 ?? $request->input('address.address_line1', '');
        $addressLine2 = $request->address_line2 ?? $request->input('address.address_line2', '');
        $city = $request->city ?? $request->input('address.city', '');
        $postcode = $request->postcode ?? $request->input('address.postcode', '');

        // Save address if requested and user is logged in
        $saveAddress = filter_var($request->input('save_address', false), FILTER_VALIDATE_BOOLEAN);

        Log::info('Save address check', [
            'save_address' => $saveAddress,
            'user_present' => $user ? 'Yes' : 'No'
        ]);

        if ($user && $saveAddress) {
            // First check if this exact address already exists for the user
            $existingAddress = Address::where('user_id', $user->id)
                ->where('address_line1', $addressLine1)
                ->where('city', $city)
                ->where('postcode', $postcode)
                ->first();

            if (!$existingAddress) {
                $addressData = [
                    'user_id' => $user->id,
                    'name' => $request->name,
                    'phone' => $request->phone,
                    'email' => $request->email,
                    'address_line1' => $addressLine1,
                    'address_line2' => $addressLine2,
                    'city' => $city,
                    'postcode' => $postcode,
                    'is_default' => false
                ];

                // Check if it's the user's first address
                $isFirstAddress = Address::where('user_id', $user->id)->count() === 0;
                if ($isFirstAddress) {
                    $addressData['is_default'] = true;
                }

                Log::info('Creating new address', $addressData);
                $address = Address::create($addressData);
                Log::info('Address created', ['address_id' => $address->id, 'user_id' => $user->id]);
            } else {
                Log::info('Using existing address', ['address_id' => $existingAddress->id]);
            }
        }

        // Apply coupon if provided
        $originalPrice = min($service->price, $service->discount_price ?? $service->price);
        $finalPrice = $originalPrice;
        $discountAmount = 0;
        $couponId = null;

        Log::info('Coupon code provided', ['coupon_code' => $request->coupon_code]);

        if ($request->coupon_code) {
            Log::info('XXXX');
            $coupon = Coupon::where('code', strtoupper($request->coupon_code))->first();

            if ($coupon && $coupon->isValid() && $coupon->isValidForService($service->id)) {
                if ($user && !$coupon->isValidForUser($user->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You have already used this coupon the maximum number of times.'
                    ], 422);
                }

                if ($coupon->minimum_amount && $originalPrice < $coupon->minimum_amount) {
                    return response()->json([
                        'success' => false,
                        'message' => "This coupon requires a minimum purchase of £{$coupon->minimum_amount}."
                    ], 422);
                }

                $discountAmount = $coupon->calculateDiscount($originalPrice);
                $finalPrice = max(0, $originalPrice - $discountAmount);
                $couponId = $coupon->id;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired coupon code.'
                ], 422);
            }
        }

        // Create a unique reference
        $reference = strtoupper(Str::random(8));
        while (Booking::where('reference', $reference)->exists()) {
            $reference = strtoupper(Str::random(8));
        }

        // CREATE BOOKING RECORD FIRST
        $booking = new Booking();
        $booking->service_id = $request->service_id;
        $booking->therapist_id = $request->therapist_id;
        $booking->user_id = $user ? $user->id : null;
        $booking->date = $request->date;
        $booking->time = $request->time;
        $booking->name = $request->name;
        $booking->email = $request->email;
        $booking->phone = $request->phone;
        $booking->address_line1 = $addressLine1;
        $booking->address_line2 = $addressLine2;
        $booking->city = $city;
        $booking->postcode = $postcode;
        $booking->notes = $request->notes;
        $booking->price = $finalPrice;
        $booking->original_price = $originalPrice;
        $booking->discount_amount = $discountAmount;
        $booking->coupon_id = $couponId;
        $booking->coupon_code = $request->coupon_code ? strtoupper($request->coupon_code) : null;
        $booking->reference = $reference;
        $booking->location_id = $request->location_id;


        Log::info('AAAAAAAAAA', [
            'service_id' => $booking->service_id,
            'therapist_id' => $booking->therapist_id,
            'user_id' => $booking->user_id,
            'date' => $booking->date,
            'time' => $booking->time,
            'name' => $booking->name,
            'email' => $booking->email,
            'phone' => $booking->phone,
            'address_line1' => $booking->address_line1,
            'address_line2' => $booking->address_line2,
            'city' => $booking->city,
            'postcode' => $booking->postcode,
            'notes' => $booking->notes,
            'price' => $booking->price,
            'original_price' => $booking->original_price,
            'discount_amount' => $booking->discount_amount,
            'coupon_id' => $booking->coupon_id,
            'coupon_code' => $booking->coupon_code,
            'reference' => $booking->reference,
            'location_id' => $booking->location_id
        ]);
        // Set status based on payment method
        if ($request->payment_method === 'card') {
            $booking->status = 'pending_payment'; // Will be updated after successful payment
        } else if ($request->payment_method === 'bank_transfer') {
            $booking->status = 'pending'; // For bank transfer - waiting for admin contact
            $booking->payment_method = 'bank';
        } else {
            $booking->status = 'pending';
            $booking->payment_method = 'cash';
        }

        $booking->save();

        // Handle coupon usage
        if ($couponId) {
            $coupon = Coupon::find($couponId);
            $coupon->incrementUsage();

            CouponUsage::create([
                'coupon_id' => $couponId,
                'user_id' => $user ? $user->id : null,
                'booking_id' => $booking->id,
                'discount_amount' => $discountAmount,
                'original_amount' => $originalPrice,
                'final_amount' => $finalPrice,
            ]);
        }

        // HANDLE DIFFERENT PAYMENT METHODS
        if ($request->payment_method === 'card') {
            // CREATE STRIPE PAYMENT INTENT FOR CARD PAYMENTS
            try {
                // Log the booking details before payment intent creation
                Log::info('BBBBBBBBBBBBB', [
                    'booking_id' => $booking->id,
                    'amount' => $finalPrice,
                    'currency' => 'gbp',
                    'service_name' => $service->title ?? 'Service',
                    'user_id' => $booking->user_id,
                    'payment_method' => $request->payment_method
                ]);
                // Set your Stripe secret key - use full namespace
                \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                Log::info('CCCCCCCCCCC');
                // Log the stripe key to make sure it's loaded (remove this in production)
                Log::info('Stripe key loaded', [
                    'key_exists' => config('services.stripe.secret') ? 'Yes' : 'No',
                    'key_starts_with' => config('services.stripe.secret') ? substr(config('services.stripe.secret'), 0, 8) : 'No key'
                ]);

                // Create PaymentIntent - use full namespace
                $paymentIntent = \Stripe\PaymentIntent::create([
                    'amount' => round($finalPrice * 100), // Stripe uses pence/cents
                    'currency' => 'gbp',
                    'metadata' => [
                        'booking_id' => $booking->id,
                        'booking_reference' => $booking->reference,
                        'service_name' => $service->title ?? 'Service',
                    ],
                    'automatic_payment_methods' => [
                        'enabled' => true,
                    ],
                ]);



                // Log the payment intent creation
                Log::info('Stripe PaymentIntent created successfully', [
                    'payment_intent_id' => $paymentIntent->id,
                    'booking_id' => $booking->id,
                    'amount' => $finalPrice,
                    'client_secret_exists' => isset($paymentIntent->client_secret) ? 'Yes' : 'No'
                ]);

                // Store payment intent ID in booking for later reference
                $booking->stripe_payment_intent_id = $paymentIntent->id;
                $booking->save();
                return response()->json([
                    'success' => true,
                    'message' => 'Booking created successfully with payment intent',
                    'data' => [
                        'booking' => $booking,
                        'payment_intent' => [
                            'client_secret' => $paymentIntent->client_secret,
                            'payment_intent_id' => $paymentIntent->id
                        ]
                    ]
                ], 201);
            } catch (\Stripe\Exception\ApiErrorException $e) {
                Log::error('Stripe API Error', [
                    'error' => $e->getMessage(),
                    'error_code' => $e->getStripeCode(),
                    'booking_id' => $booking->id
                ]);

                // Delete the booking if payment intent creation fails
                $booking->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Stripe API Error: ' . $e->getMessage()
                ], 500);
            } catch (\Exception $e) {
                Log::error('General Stripe Error', [
                    'error' => $e->getMessage(),
                    'booking_id' => $booking->id,
                    'trace' => $e->getTraceAsString()
                ]);

                // Delete the booking if payment intent creation fails
                $booking->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment intent: ' . $e->getMessage()
                ], 500);
            }
        } else {
            // BANK TRANSFER - NO PAYMENT INTENT NEEDED
            // Send confirmation email to the customer
            Log::info('DDDDDDDDD');
            try {
                Mail::to($booking->email)->send(new BankTransferBooking($booking));
                Log::info('Booking confirmation email sent', [
                    'booking_id' => $booking->id,
                    'customer_email' => $booking->email,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send booking confirmation email', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking request submitted successfully',
                'data' => $booking
            ], 201);
        }
    }

    // ADD THIS NEW METHOD FOR CONFIRMING PAYMENTS
    public function confirmPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_intent_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment intent ID is required',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Set your Stripe secret key
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            // Stripe::setApiKey(config('services.stripe.secret'));

            // Retrieve the PaymentIntent from Stripe
            // $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);
            $paymentIntent = \Stripe\PaymentIntent::retrieve($request->payment_intent_id);

            Log::info('Payment intent retrieved', [
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status
            ]);

            if ($paymentIntent->status === 'succeeded') {
                // Find the booking by payment intent ID
                $booking = Booking::where('stripe_payment_intent_id', $paymentIntent->id)->first();

                if (!$booking) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Booking not found for this payment'
                    ], 404);
                }

                // Update booking status to confirmed
                $booking->status = 'confirmed';
                $booking->payment_status = 'paid';
                $booking->payment_method = 'card';
                $booking->paid_at = now();
                $booking->save();

                // Send confirmation email
                try {
                    Mail::to($booking->email)->send(new BookingConfirmation($booking));
                    Log::info('Payment confirmation email sent', [
                        'booking_id' => $booking->id,
                        'customer_email' => $booking->email,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send payment confirmation email', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage()
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed successfully',
                    'data' => $booking
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not completed. Status: ' . $paymentIntent->status
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Payment confirmation failed', [
                'payment_intent_id' => $request->payment_intent_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        Log::info('Auth header', [
            'authorization' => $request->header('Authorization'),
            'content_type' => $request->header('Content-Type')
        ]);

        try {
            // Find the booking with related service and therapist data
            $booking = Booking::with(['service', 'therapist'])->findOrFail($id);

            // Get the authenticated user using both methods for backup
            $user = User::where('id', $booking->user_id)->first();

            // Log authentication details for debugging
            Log::info('Booking access attempt', [
                'booking_id' => $id,
                'booking_user_id' => $booking->user_id,
                'authenticated_user' => $user ? $user->id : 'None',
                'therapist_id' => $booking->therapist_id // ADD: Log therapist info
            ]);

            // For security, if the booking belongs to a user, only allow that user to see it
            if ($booking->user_id && ($user === null || $user->id != $booking->user_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to booking'
                ], 403);
            }

            // Get the associated service
            $service = $booking->service;
            if (!$service) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service associated with this booking not found'
                ], 404);
            }

            // Get the associated therapist (if exists)
            $therapist = $booking->therapist;

            // Prepare response data
            $responseData = [
                'id' => $booking->id,
                'service_name' => $service->title,
                'date' => $booking->date,
                'time' => $booking->time,
                'duration' => $service->duration,
                'price' => $booking->price,
                'reference' => $booking->reference,
                'status' => $booking->status,
                'name' => $booking->name,
                'email' => $booking->email,
                'phone' => $booking->phone,
                'address_line1' => $booking->address_line1,
                'address_line2' => $booking->address_line2,
                'city' => $booking->city,
                'postcode' => $booking->postcode,
                'notes' => $booking->notes,
                'created_at' => $booking->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $booking->updated_at->format('Y-m-d H:i:s'),
                // ADD: Include therapist information
                'therapist_id' => $booking->therapist_id,
                'therapist_name' => $therapist ? $therapist->name : null
            ];

            // Return the booking data
            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving booking: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the booking'
            ], 500);
        }
    }

    public function cancelBooking(Request $request, $id)
    {
        try {
            // Find the booking
            $booking = Booking::findOrFail($id);

            // If user is authenticated, check if booking belongs to user
            if (Auth::check()) {
                $user = Auth::user();

                // Check if the booking belongs to the authenticated user
                if ($booking->user_id && $booking->user_id != $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are not authorized to cancel this booking'
                    ], 403);
                }
            } else {
                // For future implementation: handle guest cancellations via token or email verification
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required to cancel bookings'
                ], 401);
            }

            // Check if booking is in a cancellable state
            $cancellableStatuses = ['confirmed', 'pending'];
            if (!in_array($booking->status, $cancellableStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This booking cannot be cancelled in its current state'
                ], 400);
            }

            // Perform cancellation
            $booking->status = 'cancelled';
            $booking->save();

            // Log the cancellation
            Log::info('Booking cancelled', [
                'booking_id' => $booking->id,
                'user_id' => Auth::id() ?? 'guest',
                'reference' => $booking->reference
            ]);

            // Return success response with plain structure for better iOS compatibility
            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => [
                    'id' => $booking->id,
                    'status' => 'cancelled',
                    'can_cancel' => false
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error cancelling booking', [
                'error' => $e->getMessage(),
                'booking_id' => $id
            ]);

            // Return error response with simpler structure for better iOS compatibility
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking: ' . $e->getMessage()
            ], 500);
        }
    }

    private function checkSlotAvailability($date, $time, $duration)
    {
        // Convert the requested time to carbon instance
        $requestStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
        $requestEnd = (clone $requestStart)->addMinutes($duration);

        // Get existing bookings for this date
        $bookings = Booking::where('date', $date)
            ->where('status', 'confirmed')
            ->with('service')
            ->get();

        // Check for overlaps with existing bookings
        foreach ($bookings as $booking) {
            $bookingStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date . ' ' . $booking->time);
            $bookingEnd = (clone $bookingStart)->addMinutes($booking->service->duration);

            // Check for overlap
            if ($requestStart < $bookingEnd && $requestEnd > $bookingStart) {
                return false; // There is an overlap
            }
        }

        return true; // No overlaps found
    }

    public function showBooking($id)
    {
        $user = Auth::user();
        $booking = Booking::where('id', $id)
            ->where('user_id', $user->id)
            ->with('service')
            ->first();

        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        return new BookingResource($booking);
    }


    public function getUserBookingsList()
    {
        $user = Auth::user();

        // return $user->name;

        $bookings = Booking::where('user_id', $user->id)
            ->with('service') // Include service details
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            //    ->get();
            ->paginate(10);

        // return $bookings;
        return new BookingCollection($bookings);
    }
}
