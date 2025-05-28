<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Therapist;
use App\Models\TherapistAvailability;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TimeSlotController extends Controller
{

    public function getServiceTherapists($serviceId)
    {
        try {
            Log::info("Fetching therapists for service ID: " . $serviceId);

            // Find the service first
            $service = Service::find($serviceId);

            if (!$service) {
                Log::warning("Service not found: " . $serviceId);
                return response()->json([
                    'success' => false,
                    'message' => 'Service not found'
                ], 404);
            }

            // Try to get therapists with proper relationship and their availability
            $therapists = $service->therapists()
                ->where('status', true)
                ->with(['availabilities' => function($query) {
                    $query->where('is_active', true)->orderBy('day_of_week');
                }])
                ->orderBy('name')
                ->get();

            Log::info("Found " . $therapists->count() . " therapists for service " . $serviceId);

            // Format therapist data with availability information
            $therapistData = $therapists->map(function($therapist) {
                // Get available days for the next 3 months
                $availableDates = $this->getTherapistAvailableDates($therapist->id, 3);
                
                return [
                    'id' => $therapist->id,
                    'name' => $therapist->name,
                    'email' => $therapist->email,
                    'phone' => $therapist->phone,
                    'image' => $therapist->image ? url('storage/' . $therapist->image) : null,
                    'bio' => $therapist->bio,
                    'status' => $therapist->status,
                    'available_slots_count' => $this->countAvailableSlotsToday($therapist->id),
                    'available_dates_count' => count($availableDates),
                    'schedule' => $therapist->availabilities->map(function($availability) {
                        return [
                            'day_of_week' => $availability->day_of_week,
                            'start_time' => $availability->start_time->format('H:i'),
                            'end_time' => $availability->end_time->format('H:i'),
                            'is_active' => $availability->is_active,
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $therapistData
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching therapists: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch therapists',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available dates for a specific therapist
     */
    public function getTherapistAvailableDates($therapistId, $months = 3)
    {
        try {
            $therapist = Therapist::with('activeAvailabilities')->find($therapistId);
            
            if (!$therapist || $therapist->activeAvailabilities->isEmpty()) {
                return [];
            }

            // Get the days of the week when therapist is available
            $availableDaysOfWeek = $therapist->activeAvailabilities->pluck('day_of_week')->unique()->toArray();
            
            // Generate available dates for the next X months
            $availableDates = [];
            $startDate = Carbon::today();
            $endDate = $startDate->copy()->addMonths($months);
            
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $dayOfWeek = strtolower($currentDate->format('l'));
                
                // Check if therapist works on this day of the week
                if (in_array($dayOfWeek, $availableDaysOfWeek)) {
                    $availableDates[] = $currentDate->toDateString();
                }
                
                $currentDate->addDay();
            }

            return $availableDates;

        } catch (\Exception $e) {
            Log::error('Error getting available dates for therapist ' . $therapistId . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Count available slots for today
     */
    private function countAvailableSlotsToday($therapistId)
    {
        $today = Carbon::today()->toDateString();
        $dayOfWeek = strtolower(Carbon::today()->format('l'));
        
        // Get therapist availability for today
        $availabilities = TherapistAvailability::where('therapist_id', $therapistId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        if ($availabilities->isEmpty()) {
            return 0;
        }

        // Get existing bookings for today
        $existingBookings = \App\Models\Booking::where('therapist_id', $therapistId)
            ->where('date', $today)
            ->whereIn('status', ['confirmed', 'pending'])
            ->with('service')
            ->get();

        $totalSlots = 0;
        $intervalMinutes = 30;
        $defaultDuration = 60;

        foreach ($availabilities as $availability) {
            $startTime = Carbon::parse($availability->start_time);
            $endTime = Carbon::parse($availability->end_time);
            $currentTime = $startTime->copy();

            while ($currentTime->copy()->addMinutes($defaultDuration)->lte($endTime)) {
                $slotEndTime = $currentTime->copy()->addMinutes($defaultDuration);
                $available = true;

                foreach ($existingBookings as $booking) {
                    $bookingStart = Carbon::parse($booking->time);
                    $bookingDuration = $booking->service ? (int)$booking->service->duration : 60;
                    $bookingEnd = $bookingStart->copy()->addMinutes($bookingDuration);

                    if ($currentTime->lt($bookingEnd) && $slotEndTime->gt($bookingStart)) {
                        $available = false;
                        break;
                    }
                }

                if ($available) {
                    $totalSlots++;
                }

                $currentTime->addMinutes($intervalMinutes);
            }
        }

        return $totalSlots;
    }

    // Keep all your other existing methods...
    public function index()
    {
        try {
            $therapists = Therapist::with('services')->orderBy('name')->get();

            $therapistData = $therapists->map(function($therapist) {
                return [
                    'id' => $therapist->id,
                    'name' => $therapist->name,
                    'email' => $therapist->email,
                    'phone' => $therapist->phone,
                    'image' => $therapist->image ? asset('storage/' . $therapist->image) : null,
                    'bio' => $therapist->bio,
                    'status' => $therapist->status,
                    'services' => $therapist->services->map(function($service) {
                        return [
                            'id' => $service->id,
                            'title' => $service->title,
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $therapistData
            ]);
        } catch (\Exception $e) {
            Log::error("Error fetching all therapists: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch therapists',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    // ... rest of your existing methods (store, update, destroy, etc.)


    
    /**
     * Get available time slots for a specific therapist on a specific date
     */
    public function getAvailableSlots(Request $request)
    {
        $request->validate([
            'serviceId' => 'required|exists:services,id',
            'therapistId' => 'required|exists:therapists,id',
            'date' => 'required|date|after_or_equal:today',
            'duration' => 'required|integer|min:15'
        ]);

        $serviceId = $request->serviceId;
        $therapistId = $request->therapistId;
        $date = $request->date;
        $duration = (int)$request->duration;

        try {
            // Get the day of the week for the requested date
            $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

            // Get therapist availability for this day
            $therapistAvailabilities = TherapistAvailability::where('therapist_id', $therapistId)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->orderBy('start_time')
                ->get();

            if ($therapistAvailabilities->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No availability for this therapist on this day'
                ]);
            }

            // Get existing bookings for this therapist on this date
            $existingBookings = Booking::where('therapist_id', $therapistId)
                ->where('date', $date)
                ->whereIn('status', ['confirmed', 'pending'])
                ->with('service')
                ->get();

            $timeSlots = [];
            $slotId = 1;
            $intervalMinutes = 30; // 30 minute intervals

            // Generate time slots based on therapist availability
            foreach ($therapistAvailabilities as $availability) {
                $startTime = Carbon::parse($availability->start_time);
                $endTime = Carbon::parse($availability->end_time);

                // Generate slots within this availability window
                $currentTime = $startTime->copy();
                
                while ($currentTime->copy()->addMinutes($duration)->lte($endTime)) {
                    $slotTime = $currentTime->format('H:i');
                    $slotEndTime = $currentTime->copy()->addMinutes($duration);

                    // Check if this slot overlaps with any existing booking
                    $available = true;

                    foreach ($existingBookings as $booking) {
                        $bookingStart = Carbon::parse($booking->time);
                        $bookingDuration = $booking->service ? (int)$booking->service->duration : 60;
                        $bookingEnd = $bookingStart->copy()->addMinutes($bookingDuration);

                        // Check for overlap
                        if ($currentTime->lt($bookingEnd) && $slotEndTime->gt($bookingStart)) {
                            $available = false;
                            break;
                        }
                    }

                    $timeSlots[] = [
                        'id' => 'slot-' . $slotId++,
                        'time' => $slotTime,
                        'available' => $available,
                        'therapist_id' => $therapistId
                    ];

                    $currentTime->addMinutes($intervalMinutes);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $timeSlots,
                'therapist_schedule' => $therapistAvailabilities->map(function($availability) {
                    return [
                        'start_time' => $availability->start_time->format('H:i'),
                        'end_time' => $availability->end_time->format('H:i'),
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting available slots: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available time slots',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available therapists for a service on a specific date
     */
    public function getAvailableTherapists(Request $request)
    {
        $request->validate([
            'serviceId' => 'required|exists:services,id',
            'date' => 'required|date|after_or_equal:today'
        ]);

        $serviceId = $request->serviceId;
        $date = $request->date;
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));

        try {
            // Get therapists who can perform this service and have availability on this day
            $availableTherapists = Therapist::whereHas('services', function($query) use ($serviceId) {
                    $query->where('service_id', $serviceId);
                })
                ->whereHas('availabilities', function($query) use ($dayOfWeek) {
                    $query->where('day_of_week', $dayOfWeek)
                          ->where('is_active', true);
                })
                ->where('status', true)
                ->with(['availabilities' => function($query) use ($dayOfWeek) {
                    $query->where('day_of_week', $dayOfWeek)
                          ->where('is_active', true)
                          ->orderBy('start_time');
                }])
                ->get();

            $therapistData = $availableTherapists->map(function($therapist) use ($date) {
                // Count available slots for this therapist on this date
                $availableSlots = $this->countAvailableSlots($therapist->id, $date);
                
                return [
                    'id' => $therapist->id,
                    'name' => $therapist->name,
                    'email' => $therapist->email,
                    'phone' => $therapist->phone,
                    'image' => $therapist->image ? url('storage/' . $therapist->image) : null,
                    'bio' => $therapist->bio,
                    'available_slots_count' => $availableSlots,
                    'schedule' => $therapist->availabilities->map(function($availability) {
                        return [
                            'day_of_week' => $availability->day_of_week,
                            'start_time' => $availability->start_time->format('H:i'),
                            'end_time' => $availability->end_time->format('H:i'),
                            'is_active' => $availability->is_active,
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $therapistData
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting available therapists: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available therapists',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get available dates for a specific therapist in the next 3 months
     */
    public function getAvailableDates(Request $request, $therapistId)
    {
        $request->validate([
            'serviceId' => 'sometimes|exists:services,id',
            'months' => 'sometimes|integer|min:1|max:6'
        ]);

        $months = $request->get('months', 3); // Default to 3 months

        try {
            $therapist = Therapist::findOrFail($therapistId);
            
            // Get therapist's availability schedule
            $availabilities = $therapist->activeAvailabilities;
            
            if ($availabilities->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'available_dates' => [],
                    'message' => 'No availability schedule found for this therapist'
                ]);
            }

            // Get the days of the week when therapist is available
            $availableDaysOfWeek = $availabilities->pluck('day_of_week')->unique()->toArray();
            
            // Generate available dates for the next X months
            $availableDates = [];
            $startDate = Carbon::today();
            $endDate = $startDate->copy()->addMonths($months);
            
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $dayOfWeek = strtolower($currentDate->format('l'));
                
                // Check if therapist works on this day of the week
                if (in_array($dayOfWeek, $availableDaysOfWeek)) {
                    // Check if therapist has any available slots on this specific date
                    $hasAvailableSlots = $this->countAvailableSlots($therapistId, $currentDate->toDateString()) > 0;
                    
                    if ($hasAvailableSlots) {
                        $availableDates[] = $currentDate->toDateString();
                    }
                }
                
                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'available_dates' => $availableDates,
                'therapist_name' => $therapist->name,
                'total_available_days' => count($availableDates)
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting available dates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving available dates',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get therapist's weekly schedule
     */
    public function getTherapistSchedule($therapistId)
    {
        try {
            $therapist = Therapist::with('activeAvailabilities')->findOrFail($therapistId);
            
            $schedule = $therapist->activeAvailabilities->groupBy('day_of_week')->map(function($dayAvailabilities) {
                return $dayAvailabilities->map(function($availability) {
                    return [
                        'start_time' => $availability->start_time->format('H:i'),
                        'end_time' => $availability->end_time->format('H:i'),
                        'is_active' => $availability->is_active,
                    ];
                });
            });

            return response()->json([
                'success' => true,
                'therapist' => [
                    'id' => $therapist->id,
                    'name' => $therapist->name,
                    'schedule' => $schedule
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting therapist schedule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving therapist schedule',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Check if a specific time slot is available for a therapist
     */
    public function checkSlotAvailability(Request $request)
    {
        $request->validate([
            'therapistId' => 'required|exists:therapists,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|string',
            'duration' => 'required|integer|min:15'
        ]);

        $therapistId = $request->therapistId;
        $date = $request->date;
        $time = $request->time;
        $duration = $request->duration;

        try {
            $isAvailable = $this->isSlotAvailable($therapistId, $date, $time, $duration);

            return response()->json([
                'success' => true,
                'available' => $isAvailable,
                'slot' => [
                    'therapist_id' => $therapistId,
                    'date' => $date,
                    'time' => $time,
                    'duration' => $duration
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking slot availability: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking slot availability',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Count available slots for a therapist on a specific date
     */
    private function countAvailableSlots($therapistId, $date)
    {
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
        
        $availabilities = TherapistAvailability::where('therapist_id', $therapistId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        if ($availabilities->isEmpty()) {
            return 0;
        }

        $existingBookings = Booking::where('therapist_id', $therapistId)
            ->where('date', $date)
            ->whereIn('status', ['confirmed', 'pending'])
            ->with('service')
            ->get();

        $totalSlots = 0;
        $intervalMinutes = 30;
        $defaultDuration = 60; // Default service duration

        foreach ($availabilities as $availability) {
            $startTime = Carbon::parse($availability->start_time);
            $endTime = Carbon::parse($availability->end_time);
            $currentTime = $startTime->copy();

            while ($currentTime->copy()->addMinutes($defaultDuration)->lte($endTime)) {
                $slotEndTime = $currentTime->copy()->addMinutes($defaultDuration);
                $available = true;

                foreach ($existingBookings as $booking) {
                    $bookingStart = Carbon::parse($booking->time);
                    $bookingDuration = $booking->service ? (int)$booking->service->duration : 60;
                    $bookingEnd = $bookingStart->copy()->addMinutes($bookingDuration);

                    if ($currentTime->lt($bookingEnd) && $slotEndTime->gt($bookingStart)) {
                        $available = false;
                        break;
                    }
                }

                if ($available) {
                    $totalSlots++;
                }

                $currentTime->addMinutes($intervalMinutes);
            }
        }

        return $totalSlots;
    }

    /**
     * Check if a specific slot is available
     */
    private function isSlotAvailable($therapistId, $date, $time, $duration)
    {
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
        $requestedStart = Carbon::parse("$date $time");
        $requestedEnd = $requestedStart->copy()->addMinutes($duration);

        // Check if therapist is available at this time
        $therapistAvailable = TherapistAvailability::where('therapist_id', $therapistId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>=', $requestedEnd->format('H:i:s'))
            ->exists();

        if (!$therapistAvailable) {
            return false;
        }

        // Check for conflicting bookings
        $conflictingBookings = Booking::where('therapist_id', $therapistId)
            ->where('date', $date)
            ->whereIn('status', ['confirmed', 'pending'])
            ->with('service')
            ->get();

        foreach ($conflictingBookings as $booking) {
            $bookingStart = Carbon::parse($booking->time);
            $bookingDuration = $booking->service ? (int)$booking->service->duration : 60;
            $bookingEnd = $bookingStart->copy()->addMinutes($bookingDuration);

            // Check for overlap
            if ($requestedStart->lt($bookingEnd) && $requestedEnd->gt($bookingStart)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get therapist workload for a specific date range
     */
    public function getTherapistWorkload(Request $request, $therapistId)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $startDate = $request->start_date;
            $endDate = $request->end_date;

            $therapist = Therapist::findOrFail($therapistId);

            $workload = [];
            $currentDate = Carbon::parse($startDate);
            $endDateCarbon = Carbon::parse($endDate);

            while ($currentDate->lte($endDateCarbon)) {
                $dateString = $currentDate->toDateString();
                $totalSlots = $this->getTotalSlotsForDate($therapistId, $dateString);
                $bookedSlots = $this->getBookedSlotsForDate($therapistId, $dateString);
                $availableSlots = $totalSlots - $bookedSlots;

                $workload[] = [
                    'date' => $dateString,
                    'day_of_week' => strtolower($currentDate->format('l')),
                    'total_slots' => $totalSlots,
                    'booked_slots' => $bookedSlots,
                    'available_slots' => $availableSlots,
                    'utilization_percentage' => $totalSlots > 0 ? round(($bookedSlots / $totalSlots) * 100, 2) : 0
                ];

                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'therapist' => [
                    'id' => $therapist->id,
                    'name' => $therapist->name
                ],
                'workload' => $workload,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting therapist workload: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving therapist workload',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get total possible slots for a therapist on a specific date
     */
    private function getTotalSlotsForDate($therapistId, $date)
    {
        $dayOfWeek = strtolower(Carbon::parse($date)->format('l'));
        
        $availabilities = TherapistAvailability::where('therapist_id', $therapistId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        $totalSlots = 0;
        $intervalMinutes = 30;
        $defaultDuration = 60;

        foreach ($availabilities as $availability) {
            $startTime = Carbon::parse($availability->start_time);
            $endTime = Carbon::parse($availability->end_time);
            $currentTime = $startTime->copy();

            while ($currentTime->copy()->addMinutes($defaultDuration)->lte($endTime)) {
                $totalSlots++;
                $currentTime->addMinutes($intervalMinutes);
            }
        }

        return $totalSlots;
    }

    /**
     * Get booked slots for a therapist on a specific date
     */
    private function getBookedSlotsForDate($therapistId, $date)
    {
        return Booking::where('therapist_id', $therapistId)
            ->where('date', $date)
            ->whereIn('status', ['confirmed', 'pending'])
            ->count();
    }
}