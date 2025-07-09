<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingCollection;
use App\Http\Resources\BookingResource;
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

class BookingController extends Controller
{
    public function store(Request $request)
    {
        // Debug to check what authentication headers are present
        Log::info('Auth header', [
            'authorization' => $request->header('Authorization'),
            'content_type' => $request->header('Content-Type')
        ]);
        
        // Validate the booking data - directly accepting address fields or nested object
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
            'save_address' => 'boolean'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // IMPORTANT FIX: Get user if authenticated - use auth()->user() as an alternative
        $user = auth()->user();
        
        // Debug user authentication status
        Log::info('User authentication check', [
            'is_authenticated' => $user ? 'Yes' : 'No',
            'user_id' => $user ? $user->id : 'Not authenticated'
        ]);
        
        // If user is not authenticated but token is provided, try to get user from token directly
        if (!$user && $request->bearerToken()) {
            Log::info('Attempting to get user from token directly');
            // Try to get the user from the token using Sanctum's methods
            try {
                // For Sanctum
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
        // Fix: Properly check for save_address as a boolean value (could be true, false, 1, 0, "true", "false")
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
                    'is_default' => false // Default to not default
                ];
                
                // Check if it's the user's first address
                $isFirstAddress = Address::where('user_id', $user->id)->count() === 0;
                if ($isFirstAddress) {
                    $addressData['is_default'] = true;
                }
                
                Log::info('Creating new address', $addressData);
                // Create the address
                $address = Address::create($addressData);
                // Log the address creation for debugging
                Log::info('Address created', ['address_id' => $address->id, 'user_id' => $user->id]);
            } else {
                Log::info('Using existing address', ['address_id' => $existingAddress->id]);
            }
        } else {
            // Log why the address wasn't saved
            if (!$user) {
                Log::info('Address not saved - User not authenticated');
            } else if (!$saveAddress) {
                Log::info('Address not saved - save_address flag is false', ['save_address_value' => $request->input('save_address')]);
            }
        }
        
        // Create a unique reference
        $reference = strtoupper(Str::random(8));
        // Ensure reference is unique
        while (Booking::where('reference', $reference)->exists()) {
            $reference = strtoupper(Str::random(8));
        }
        
        // Create the booking with direct address fields
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
        $booking->status = 'confirmed'; // Default status based on your schema
        $booking->price = $service->discount_price ?? $service->price;
        $booking->reference = $reference;
        $booking->save();
        
        // Send confirmation email to the customer
        try {
            Mail::to($booking->email)->send(new BookingConfirmation($booking));
            
            // Optionally, send a notification to the admin
            // $adminEmail = config('mail.admin_address', 'admin@example.com');
            // Mail::to($adminEmail)->send(new AdminBookingNotification($booking));
            
            // Log email sent
            Log::info('Booking confirmation emails sent', [
                'booking_id' => $booking->id, 
                'customer_email' => $booking->email,
                // 'admin_email' => $adminEmail
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the booking process
            Log::error('Failed to send booking confirmation email', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage()
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => $booking
        ], 201);
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
