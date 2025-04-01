<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Service;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TimeSlotController extends Controller
{
    public function getAvailableSlots(Request $request)
    {
        $request->validate([
            'serviceId' => 'required|exists:services,id',
            'date' => 'required|date|after_or_equal:today',
            'duration' => 'required|integer|min:15'
        ]);

        $serviceId = $request->serviceId;
        $date = $request->date;
        $duration = (int)$request->duration; // Ensure duration is an integer

        // Get business hours
        $startHour = 9; // 9 AM
        $endHour = 17; // 5 PM
        $intervalMinutes = 30; // 30 minute intervals

        // Get existing bookings for this date
        $bookings = Booking::where('date', $date)
            ->where('status', 'confirmed')
            ->get();

        // Generate all time slots for the day
        $timeSlots = [];
        $slotId = 1;

        for ($hour = $startHour; $hour < $endHour; $hour++) {
            for ($minute = 0; $minute < 60; $minute += $intervalMinutes) {
                $slotTime = sprintf('%02d:%02d', $hour, $minute);

                // Create Carbon instances for calculations
                $slotStartCarbon = Carbon::createFromFormat('H:i', $slotTime);
                $slotEndCarbon = (clone $slotStartCarbon)->addMinutes($duration);
                $endTime = $slotEndCarbon->format('H:i');

                // Check if this slot overlaps with any existing booking
                $available = true;

                foreach ($bookings as $booking) {
                    $bookingStartTime = substr($booking->time, 0, 5); // Get HH:MM format
                    $bookingStart = Carbon::createFromFormat('H:i', $bookingStartTime);

                    // Get service duration - make sure it's an integer
                    $bookingDuration = 0;
                    if ($booking->service) {
                        $bookingDuration = (int)$booking->service->duration;
                    }

                    $bookingEnd = (clone $bookingStart)->addMinutes($bookingDuration);

                    // Check for overlap
                    if ($slotStartCarbon < $bookingEnd && $slotEndCarbon > $bookingStart) {
                        $available = false;
                        break;
                    }
                }

                // Check if the slot ends before business hours end
                $businessEnd = Carbon::createFromFormat('H:i', sprintf('%02d:%02d', $endHour, 0));
                if ($slotEndCarbon > $businessEnd) {
                    $available = false;
                }

                $timeSlots[] = [
                    'id' => 'slot-' . $slotId++,
                    'time' => $slotTime,
                    'available' => $available
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $timeSlots
        ]);
    }
}