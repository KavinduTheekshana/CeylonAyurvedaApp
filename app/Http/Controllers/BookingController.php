<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|array',
            'address.address_line1' => 'required|string|max:255',
            'address.address_line2' => 'nullable|string|max:255',
            'address.city' => 'required|string|max:255',
            'address.postcode' => 'required|string|max:20',
            'notes' => 'nullable|string',
            'save_address' => 'boolean'
        ]);

        $service = Service::findOrFail($request->service_id);
        $userId = Auth::id(); // Will be null for guest bookings

        // Check if time slot is still available
        $isSlotAvailable = $this->checkSlotAvailability(
            $request->date,
            $request->time,
            $service->duration
        );

        if (!$isSlotAvailable) {
            return response()->json([
                'success' => false,
                'message' => 'This time slot is no longer available. Please select another time.'
            ], 422);
        }

        // Save address if requested (and user is logged in)
        if ($userId && $request->save_address) {
            $address = Address::updateOrCreate(
                [
                    'user_id' => $userId,
                    'address_line1' => $request->address['address_line1'],
                    'postcode' => $request->address['postcode']
                ],
                [
                    'name' => $request->name,
                    'phone' => $request->phone,
                    'email' => $request->email,
                    'address_line2' => $request->address['address_line2'] ?? null,
                    'city' => $request->address['city']
                ]
            );
        }

        // Generate a unique reference number
        $reference = 'BK' . strtoupper(Str::random(8));

        // Create the booking
        $booking = Booking::create([
            'user_id' => $userId,
            'service_id' => $service->id,
            'date' => $request->date,
            'time' => $request->time,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address_line1' => $request->address['address_line1'],
            'address_line2' => $request->address['address_line2'] ?? null,
            'city' => $request->address['city'],
            'postcode' => $request->address['postcode'],
            'notes' => $request->notes,
            'price' => $service->price,
            'reference' => $reference,
            'status' => 'confirmed'
        ]);

        // Send confirmation email
        // Mail::to($request->email)->send(new BookingConfirmation($booking));

        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => [
                'id' => $booking->id,
                'reference' => $booking->reference
            ]
        ]);
    }

    public function show($id)
    {
        $booking = Booking::findOrFail($id);

        // For security, if the booking belongs to a user, only allow that user to see it
        if ($booking->user_id && Auth::id() != $booking->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $service = $booking->service;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $booking->id,
                'service_name' => $service->title,
                'date' => $booking->date,
                'time' => $booking->time,
                'duration' => $service->duration,
                'price' => $booking->price,
                'reference' => $booking->reference,
                'status' => $booking->status,
                'created_at' => $booking->created_at
            ]
        ]);
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
}
