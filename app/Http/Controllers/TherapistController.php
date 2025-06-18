<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Therapist;
use App\Models\TherapistAvailability;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TherapistController extends Controller
{

    public function show($id)
    {
        try {
            Log::info("Fetching therapist details for ID: " . $id);

            // Find the therapist with relationships
            $therapist = Therapist::with([
                'availabilities' => function ($query) {
                    $query->where('is_active', true)
                        ->orderBy('day_of_week')
                        ->orderBy('start_time');
                },
                'services' => function ($query) {
                    $query->where('services.status', true)
                        ->select('services.id', 'services.title', 'services.price', 'services.duration');
                }
            ])->find($id);

            if (!$therapist) {
                Log::warning("Therapist not found: " . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Therapist not found'
                ], 404);
            }

            Log::info("Found therapist: " . $therapist->name);

            // Check work status
            $hasStartedWorking = $this->hasTherapistStartedWorking($therapist->work_start_date);
            $workStatus = $this->getWorkStatus($therapist->work_start_date);

            // Get total booking count for this therapist
            $totalBookingCount = $this->getTotalBookingCount($therapist->id);


            // Format availability schedule
            $schedule = $therapist->availabilities->map(function ($availability) {
                return [
                    'day_of_week' => $availability->day_of_week,
                    'start_time' => $availability->start_time->format('H:i'),
                    'end_time' => $availability->end_time->format('H:i'),
                    'is_active' => $availability->is_active,
                ];
            });

            // Get available services
            $services = $therapist->services->map(function ($service) {
                return [
                    'id' => $service->id,
                    'title' => $service->title,
                    'price' => $service->price,
                    'duration' => $service->duration,
                ];
            });

            // Get some availability stats (only if started working)
            $availableDates = $hasStartedWorking ? $this->getTherapistAvailableDates($therapist->id, 3) : [];
            $todaySlots = $hasStartedWorking ? $this->countAvailableSlotsToday($therapist->id, 60) : 0;

            // Format the therapist data
            $therapistData = [
                'id' => $therapist->id,
                'name' => $therapist->name,
                'email' => $therapist->email,
                'phone' => $therapist->phone,
                'image' => $therapist->image ? asset('storage/' . $therapist->image) : null,
                'bio' => $therapist->bio,
                'work_start_date' => $therapist->work_start_date
                    ? Carbon::parse($therapist->work_start_date)->format('Y-m-d')
                    : null,
                'has_started_working' => $hasStartedWorking,
                'work_status' => $workStatus,
                'status' => $therapist->status,
                'total_booking_count' => $totalBookingCount,
                'created_at' => $therapist->created_at->toDateTimeString(),
                'updated_at' => $therapist->updated_at->toDateTimeString(),

                // Schedule information
                'schedule' => $schedule,
                'available_days' => $this->formatAvailableDays($schedule),

                // Services they provide
                'services' => $services,

                // Availability stats
                'availability_stats' => [
                    'available_dates_count' => count($availableDates),
                    'today_slots_count' => $todaySlots,
                    'next_available_dates' => array_slice($availableDates, 0, 7), // Next 7 available dates
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $therapistData,
                'message' => 'Therapist details retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching therapist details: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving therapist details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    private function getTotalBookingCount($therapistId, $statuses = ['confirmed', 'pending', 'completed'])
    {
        try {
            $count = Booking::where('therapist_id', $therapistId)
                ->whereIn('status', $statuses)
                ->count();

            Log::info("Total booking count for therapist {$therapistId}: {$count}");
            return $count;

        } catch (\Exception $e) {
            Log::error('Error getting total booking count for therapist ' . $therapistId . ': ' . $e->getMessage());
            return 0;
        }
    }

    private function formatAvailableDays($schedule)
    {
        if ($schedule->isEmpty()) {
            return 'No availability';
        }

        $daysOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $dayAbbreviations = [
            'monday' => 'Mon',
            'tuesday' => 'Tue',
            'wednesday' => 'Wed',
            'thursday' => 'Thu',
            'friday' => 'Fri',
            'saturday' => 'Sat',
            'sunday' => 'Sun'
        ];

        $availableDays = $schedule
            ->pluck('day_of_week')
            ->unique()
            ->sort(function ($a, $b) use ($daysOrder) {
                return array_search($a, $daysOrder) - array_search($b, $daysOrder);
            })
            ->map(function ($day) use ($dayAbbreviations) {
                return $dayAbbreviations[$day] ?? ucfirst($day);
            })
            ->values()
            ->toArray();

        return implode(', ', $availableDays);
    }

    public function getTherapistBookings($therapistId, Request $request)
    {
        try {
            // Validate the therapist exists
            $therapist = Therapist::find($therapistId);

            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Therapist not found'
                ], 404);
            }

            // Get the date parameter from the query string
            $date = $request->query('date');

            if (!$date) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date parameter is required'
                ], 400);
            }

            // Validate the date format
            try {
                $parsedDate = Carbon::createFromFormat('Y-m-d', $date);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format. Use YYYY-MM-DD'
                ], 400);
            }

            // Check if therapist has started working by the requested date
            $hasStartedByDate = $this->hasTherapistStartedWorkingByDate($therapist->work_start_date, $date);

            Log::info("Fetching bookings for therapist {$therapistId} on {$date}. Has started by date: " . ($hasStartedByDate ? 'Yes' : 'No'));

            // Get bookings for the therapist on the specified date
            $bookings = Booking::where('therapist_id', $therapistId)
                ->where('date', $date)
                ->whereIn('status', ['confirmed', 'pending']) // Only active bookings
                ->with(['service:id,title,duration', 'user:id,name']) // Include related data
                ->orderBy('time')
                ->get();

            Log::info("Found " . $bookings->count() . " bookings for therapist {$therapistId} on {$date}");

            // Format the booking data for the response
            $formattedBookings = $bookings->map(function ($booking) {
                // Format time as HH:MM string instead of full datetime
                $timeFormatted = is_string($booking->time)
                    ? $booking->time
                    : Carbon::parse($booking->time)->format('H:i');

                return [
                    'id' => $booking->id,
                    'date' => $booking->date,
                    'time' => $timeFormatted,
                    'status' => $booking->status,
                    'service' => [
                        'id' => $booking->service->id,
                        'title' => $booking->service->title,
                        'duration' => $booking->service->duration ?? 60,
                    ],
                    'customer' => [
                        'name' => $booking->name,
                        'email' => $booking->email,
                        'phone' => $booking->phone,
                    ],
                    'user' => $booking->user ? [
                        'id' => $booking->user->id,
                        'name' => $booking->user->name,
                    ] : null,
                    'reference' => $booking->reference,
                    'created_at' => $booking->created_at->toDateTimeString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedBookings,
                'meta' => [
                    'therapist_id' => (int) $therapistId,
                    'therapist_name' => $therapist->name,
                    'therapist_work_start_date' => $therapist->work_start_date
                        ? Carbon::parse($therapist->work_start_date)->format('Y-m-d')
                        : null,
                    'has_started_by_date' => $hasStartedByDate,
                    'date' => $date,
                    'total_bookings' => $bookings->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching therapist bookings: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch therapist bookings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    /**
     * Get therapists assigned to a specific service with availability data
     * Public endpoint for booking flow
     */
    public function getServiceTherapists($serviceId, Request $request)
    {
        try {
            Log::info("Fetching therapists for service ID: " . $serviceId);
    
            // Get location_id from request parameter
            $locationId = $request->query('location_id');
            
            if ($locationId) {
                Log::info("Filtering therapists by location ID: " . $locationId);
            }
    
            // Find the service first
            $service = Service::find($serviceId);
    
            if (!$service) {
                Log::warning("Service not found: " . $serviceId);
                return response()->json([
                    'success' => false,
                    'message' => 'Service not found'
                ], 404);
            }
    
            // Get therapists with proper relationship and their availability
            // Filter by location if location_id is provided
            $therapistsQuery = $service->therapists()
                ->where('status', true)
                ->with([
                    'availabilities' => function ($query) {
                        $query->where('is_active', true)->orderBy('day_of_week')->orderBy('start_time');
                    }
                ]);
    
            // Add location filter if location_id is provided
            if ($locationId) {
                $therapistsQuery->whereHas('locations', function ($query) use ($locationId) {
                    $query->where('locations.id', $locationId);
                });
            }
    
            $therapists = $therapistsQuery->orderBy('name')->get();
    
            Log::info("Found " . $therapists->count() . " therapists for service " . $serviceId . 
                     ($locationId ? " in location " . $locationId : ""));
    
            // Format therapist data with availability information
            $therapistData = $therapists->map(function ($therapist) use ($service) {
                // Check if therapist has started working
                $hasStartedWorking = $this->hasTherapistStartedWorking($therapist->work_start_date);
    
                // Check if therapist will start within next 3 months
                $willStartWithinThreeMonths = $this->willTherapistStartWithinPeriod($therapist->work_start_date, 3);
    
                // Get availability data - if they've started OR will start within 3 months
                $availableDates = [];
                $todaySlots = 0;
    
                if ($hasStartedWorking || $willStartWithinThreeMonths) {
                    // Get available dates for the next 3 months (considering work start date)
                    $availableDates = $this->getTherapistAvailableDates($therapist->id, 3);
    
                    // Count available slots for today (only if they've already started)
                    if ($hasStartedWorking) {
                        $todaySlots = $this->countAvailableSlotsToday($therapist->id, $service->duration ?? 60);
                    }
                }
    
                // Format schedule data
                $schedule = $therapist->availabilities->map(function ($availability) {
                    return [
                        'day_of_week' => $availability->day_of_week,
                        'start_time' => $availability->start_time->format('H:i'),
                        'end_time' => $availability->end_time->format('H:i'),
                        'is_active' => $availability->is_active,
                    ];
                });
    
                // Get work status information
                $workStatus = $this->getWorkStatus($therapist->work_start_date);
    
                Log::info("Therapist {$therapist->name} - Work Status: {$workStatus}, Has started: " . ($hasStartedWorking ? 'Yes' : 'No') . ", Will start within 3 months: " . ($willStartWithinThreeMonths ? 'Yes' : 'No') . ", Available dates: " . count($availableDates) . ", Today slots: {$todaySlots}, Schedule items: " . $schedule->count());
    
                return [
                    'id' => $therapist->id,
                    'name' => $therapist->name,
                    'email' => $therapist->email,
                    'phone' => $therapist->phone,
                    'image' => $therapist->image ? url('storage/' . $therapist->image) : null,
                    'bio' => $therapist->bio,
                    'work_start_date' => $therapist->work_start_date
                        ? Carbon::parse($therapist->work_start_date)->format('Y-m-d')
                        : null,
                    'has_started_working' => $hasStartedWorking,
                    'will_start_within_three_months' => $willStartWithinThreeMonths,
                    'work_status' => $workStatus,
                    'status' => $therapist->status,
                    'available_slots_count' => $todaySlots,
                    'available_dates_count' => count($availableDates),
                    'schedule' => $schedule
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

    private function willTherapistStartWithinPeriod($workStartDate, $months = 3): bool
    {
        if (!$workStartDate) {
            return false; // No start date means they're already active
        }

        $startDate = Carbon::parse($workStartDate);
        $now = Carbon::now();
        $futureLimit = $now->copy()->addMonths($months);

        // Check if start date is in the future AND within the specified period
        return $startDate->isFuture() && $startDate->lte($futureLimit);
    }

    private function hasTherapistStartedWorking($workStartDate): bool
    {
        if (!$workStartDate) {
            return true; // No start date means available
        }

        $startDate = Carbon::parse($workStartDate);
        $now = Carbon::now();

        return $startDate->isPast() || $startDate->isToday();
    }

    private function hasTherapistStartedWorkingByDate($workStartDate, $checkDate): bool
    {
        if (!$workStartDate) {
            return true; // No start date means available
        }

        $startDate = Carbon::parse($workStartDate);
        $targetDate = Carbon::parse($checkDate);

        return $startDate->lte($targetDate);
    }



    /**
     * Helper method to get work status text
     */
    private function getWorkStatus($workStartDate): string
    {
        if (!$workStartDate) {
            return 'Active';
        }

        $startDate = Carbon::parse($workStartDate);

        if ($startDate->isFuture()) {
            return 'Starts ' . $startDate->format('M d, Y');
        }

        if ($startDate->isToday()) {
            return 'Starting Today';
        }

        return 'Active';
    }


    /**
     * Format available days helper
     */


    /**
     * Get available dates for a specific therapist
     */
    private function getTherapistAvailableDates($therapistId, $months = 3)
    {
        try {
            // Get therapist with availability and work start date
            $therapist = Therapist::with([
                'availabilities' => function ($query) {
                    $query->where('is_active', true);
                }
            ])->find($therapistId);

            if (!$therapist || $therapist->availabilities->isEmpty()) {
                Log::info("No availability found for therapist {$therapistId}");
                return [];
            }

            // Get the days of the week when therapist is available
            $availableDaysOfWeek = $therapist->availabilities->pluck('day_of_week')->unique()->toArray();

            Log::info("Therapist {$therapistId} available days: " . implode(', ', $availableDaysOfWeek));

            // Determine the start date for availability calculation
            $startDate = Carbon::today();

            if ($therapist->work_start_date) {
                $workStartDate = Carbon::parse($therapist->work_start_date);

                // If work start date is in the future, start availability from that date
                if ($workStartDate->isFuture()) {
                    $startDate = $workStartDate;
                    Log::info("Therapist {$therapistId} starts work on {$workStartDate->toDateString()}, calculating availability from that date");
                }
                // If work start date is in the past but after today, use work start date
                elseif ($workStartDate->gt(Carbon::today())) {
                    $startDate = $workStartDate;
                }
            }

            // Calculate end date (3 months from today, not from start date)
            $endDate = Carbon::today()->addMonths($months);

            // Generate available dates within the period
            $availableDates = [];
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $dayOfWeek = strtolower($currentDate->format('l'));

                // Check if therapist works on this day of the week
                if (in_array($dayOfWeek, $availableDaysOfWeek)) {
                    $availableDates[] = $currentDate->toDateString();
                }

                $currentDate->addDay();
            }

            Log::info("Generated " . count($availableDates) . " available dates for therapist {$therapistId} from {$startDate->toDateString()} to {$endDate->toDateString()}");
            return $availableDates;

        } catch (\Exception $e) {
            Log::error('Error getting available dates for therapist ' . $therapistId . ': ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Count available slots for today
     */
    private function countAvailableSlotsToday($therapistId, $serviceDuration)
    {
        try {
            $today = Carbon::today()->toDateString();
            $dayOfWeek = strtolower(Carbon::today()->format('l'));

            Log::info("Checking slots for therapist {$therapistId} on {$today} ({$dayOfWeek})");

            // Get therapist to check work start date
            $therapist = Therapist::find($therapistId);
            if (!$therapist) {
                return 0;
            }

            // Check if therapist has started working
            if (!$this->hasTherapistStartedWorking($therapist->work_start_date)) {
                Log::info("Therapist {$therapistId} hasn't started working yet");
                return 0;
            }

            // Get therapist availability for today
            $availabilities = TherapistAvailability::where('therapist_id', $therapistId)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_active', true)
                ->get();

            if ($availabilities->isEmpty()) {
                Log::info("No availability for therapist {$therapistId} on {$dayOfWeek}");
                return 0;
            }

            Log::info("Found " . $availabilities->count() . " availability slots for therapist {$therapistId} on {$dayOfWeek}");

            // Get existing bookings for today
            $existingBookings = Booking::where('therapist_id', $therapistId)
                ->where('date', $today)
                ->whereIn('status', ['confirmed', 'pending'])
                ->with('service')
                ->get();

            Log::info("Found " . $existingBookings->count() . " existing bookings for therapist {$therapistId} on {$today}");

            $totalSlots = 0;
            $intervalMinutes = $serviceDuration;
            $currentDateTime = Carbon::now();
            $todayDate = $currentDateTime->toDateString();

            foreach ($availabilities as $availability) {
                $startTime = Carbon::parse($availability->start_time);
                $endTime = Carbon::parse($availability->end_time);
                $currentTime = $startTime->copy();
                $windowSlots = 0;

                // If it's today, start from current time or availability start time, whichever is later
                if ($today === $todayDate) {
                    $now = Carbon::now();
                    $availabilityStart = Carbon::today()->setTimeFromTimeString($availability->start_time->format('H:i:s'));

                    if ($now->gt($availabilityStart)) {
                        // Round up to next 30-minute interval
                        $minutes = $now->minute;
                        $roundedMinutes = ceil($minutes / 30) * 30;
                        $currentTime = $now->setMinute($roundedMinutes)->setSecond(0);

                        // If rounded time exceeds 60 minutes, move to next hour
                        if ($currentTime->minute >= 60) {
                            $currentTime = $currentTime->addHour()->setMinute(0);
                        }
                    } else {
                        $currentTime = $availabilityStart;
                    }
                }

                Log::info("Processing availability slot: {$startTime->format('H:i')} - {$endTime->format('H:i')}");

                while ($currentTime->copy()->addMinutes($serviceDuration)->lte($endTime)) {
                    $slotStart = $currentTime->copy();
                    $slotEnd = $slotStart->copy()->addMinutes($serviceDuration);
                    $isAvailable = true;

                    // Check against existing bookings
                    foreach ($existingBookings as $booking) {
                        $bookingStart = Carbon::parse($booking->time);
                        $bookingDuration = $booking->service ? (int) $booking->service->duration : 60;
                        $bookingEnd = $bookingStart->copy()->addMinutes($bookingDuration);

                        // Check for time overlap
                        if ($slotStart->lt($bookingEnd) && $bookingStart->lt($slotEnd)) {
                            $isAvailable = false;
                            Log::debug("Slot {$slotStart->format('H:i')}-{$slotEnd->format('H:i')} conflicts with booking {$bookingStart->format('H:i')}-{$bookingEnd->format('H:i')}");
                            break;
                        }
                    }

                    if ($isAvailable) {
                        $windowSlots++;
                        Log::debug("Available slot: {$slotStart->format('H:i')}-{$slotEnd->format('H:i')}");
                    }

                    $currentTime->addMinutes($intervalMinutes);
                }

                $totalSlots += $windowSlots;
                Log::info("Window {$startTime->format('H:i')}-{$endTime->format('H:i')} has {$windowSlots} available slots");
            }

            Log::info("Total available slots for therapist {$therapistId} today: {$totalSlots}");
            return $totalSlots;
        } catch (\Exception $e) {
            Log::error('Error counting available slots for therapist ' . $therapistId . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get all therapists (Admin only)
     */
    public function index()
    {
        try {
            $therapists = Therapist::with('services')->orderBy('name')->get();

            $therapistData = $therapists->map(function ($therapist) {
                return [
                    'id' => $therapist->id,
                    'name' => $therapist->name,
                    'email' => $therapist->email,
                    'phone' => $therapist->phone,
                    'image' => $therapist->image ? asset('storage/' . $therapist->image) : null,
                    'bio' => $therapist->bio,
                    'status' => $therapist->status,
                    'services' => $therapist->services->map(function ($service) {
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

    /**
     * Create a new therapist (Admin only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:therapists,email',
            'phone' => 'required|string|max:20',
            'bio' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'boolean',
            'services' => 'array',
            'services.*' => 'exists:services,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $therapistData = $request->only(['name', 'email', 'phone', 'bio']);
            $therapistData['status'] = $request->get('status', true);

            // Handle image upload
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imagePath = $image->store('therapists', 'public');
                $therapistData['image'] = $imagePath;
            }

            $therapist = Therapist::create($therapistData);

            // Attach services if provided
            if ($request->has('services')) {
                $therapist->services()->attach($request->services);
            }

            return response()->json([
                'success' => true,
                'data' => $therapist->load('services'),
                'message' => 'Therapist created successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error("Error creating therapist: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create therapist',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update therapist (Admin only)
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:therapists,email,' . $id,
            'phone' => 'sometimes|required|string|max:20',
            'bio' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'status' => 'boolean',
            'services' => 'array',
            'services.*' => 'exists:services,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $therapist = Therapist::findOrFail($id);

            $therapistData = $request->only(['name', 'email', 'phone', 'bio', 'status']);

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if it exists
                if ($therapist->image) {
                    Storage::disk('public')->delete($therapist->image);
                }

                $image = $request->file('image');
                $imagePath = $image->store('therapists', 'public');
                $therapistData['image'] = $imagePath;
            }

            $therapist->update($therapistData);

            // Update services if provided
            if ($request->has('services')) {
                $therapist->services()->sync($request->services);
            }

            return response()->json([
                'success' => true,
                'data' => $therapist->load('services'),
                'message' => 'Therapist updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error updating therapist: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update therapist',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Delete therapist (Admin only)
     */
    public function destroy($id)
    {
        try {
            $therapist = Therapist::findOrFail($id);

            // Check if therapist has active bookings
            $hasActiveBookings = $therapist->bookings()
                ->whereIn('status', ['confirmed', 'pending'])
                ->exists();

            if ($hasActiveBookings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete therapist with active bookings. Please reassign or complete existing bookings first.'
                ], 400);
            }

            // Delete image if it exists
            if ($therapist->image) {
                Storage::disk('public')->delete($therapist->image);
            }

            // Detach from services
            $therapist->services()->detach();

            // Soft delete the therapist
            $therapist->delete();

            return response()->json([
                'success' => true,
                'message' => 'Therapist deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error deleting therapist: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete therapist',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Assign therapist to service (Admin only)
     */
    public function assignToService($therapistId, $serviceId)
    {
        try {
            $therapist = Therapist::findOrFail($therapistId);
            $service = Service::findOrFail($serviceId);

            // Check if already assigned
            if ($therapist->services()->where('service_id', $serviceId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Therapist is already assigned to this service'
                ], 400);
            }

            $therapist->services()->attach($serviceId);

            return response()->json([
                'success' => true,
                'message' => 'Therapist assigned to service successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error assigning therapist to service: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign therapist to service',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove therapist from service (Admin only)
     */
    public function removeFromService($therapistId, $serviceId)
    {
        try {
            $therapist = Therapist::findOrFail($therapistId);

            // Check if therapist has active bookings for this service
            $hasActiveBookings = $therapist->bookings()
                ->where('service_id', $serviceId)
                ->whereIn('status', ['confirmed', 'pending'])
                ->exists();

            if ($hasActiveBookings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove therapist from service with active bookings'
                ], 400);
            }

            $therapist->services()->detach($serviceId);

            return response()->json([
                'success' => true,
                'message' => 'Therapist removed from service successfully'
            ]);

        } catch (\Exception $e) {
            Log::error("Error removing therapist from service: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove therapist from service',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}