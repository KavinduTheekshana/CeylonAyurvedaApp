<?php

namespace App\Http\Controllers;

use App\Models\Booking;
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
        $duration = $request->duration;

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
                $endTime = Carbon::createFromFormat('H:i', $slotTime)->addMinutes($duration)->format('H:i');

                // Check if this slot overlaps with any existing booking
                $available = true;

                foreach ($bookings as $booking) {
                    $bookingStart = Carbon::createFromFormat('H:i:s', $booking->time);
                    $bookingEnd = (clone $bookingStart)->addMinutes($booking->service->duration);

                    $slotStart = Carbon::createFromFormat('H:i', $slotTime);
                    $slotEnd = Carbon::createFromFormat('H:i', $endTime);

                    // Check for overlap
                    if ($slotStart < $bookingEnd && $slotEnd > $bookingStart) {
                        $available = false;
                        break;
                    }
                }

                // Check if the slot ends before business hours end
                if ($endTime > sprintf('%02d:%02d', $endHour, 0)) {
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
