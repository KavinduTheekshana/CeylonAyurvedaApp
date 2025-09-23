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

class TimeSlotController extends Controller
{
    /**
     * Get therapists assigned to a specific service
     * Public endpoint for booking flow
     */
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

            // Get therapists with proper relationship and their availability
            $therapists = $service->therapists()
                ->where('status', true)
                ->with(['availabilities' => function($query) {
                    $query->where('is_active', true)->orderBy('day_of_week')->orderBy('start_time');
                }])
                ->orderBy('name')
                ->get();

            Log::info("Found " . $therapists->count() . " therapists for service " . $serviceId);

            // Format therapist data with availability information
            $therapistData = $therapists->map(function($therapist) {
                // Get available dates for the next 3 months
                $availableDates = $this->getTherapistAvailableDates($therapist->id, 3);
                
                // Count available slots for today
                $todaySlots = $this->countAvailableSlotsToday($therapist->id);
                
                // Format schedule data
                $schedule = $therapist->availabilities->map(function($availability) {
                    return [
                        'day_of_week' => $availability->day_of_week,
                        'start_time' => $availability->start_time->format('H:i'),
                        'end_time' => $availability->end_time->format('H:i'),
                        'is_active' => $availability->is_active,
                    ];
                });

                Log::info("Therapist {$therapist->name} - Available dates: " . count($availableDates) . ", Today slots: {$todaySlots}, Schedule items: " . $schedule->count());
                
                return [
                    'id' => $therapist->id,
                    'name' => $therapist->name,
                    'email' => $therapist->email,
                    'phone' => $therapist->phone,
                    'image' => $therapist->image ? url('storage/' . $therapist->image) : null,
                    'bio' => $therapist->bio,
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

    /**
     * Get available dates for a specific therapist
     * MOVED FROM TimeSlotController to fix the issue
     */
    private function getTherapistAvailableDates($therapistId, $months = 3, $workStartDate = null)
    {
        try {
            // Use the same method as TimeSlotController but with proper relationship loading
            $therapist = Therapist::with(['availabilities' => function($query) {
                $query->where('is_active', true);
            }])->find($therapistId);
            
            if (!$therapist || $therapist->availabilities->isEmpty()) {
                Log::info("No availability found for therapist {$therapistId}");
                return [];
            }

            // Get the days of the week when therapist is available
            $availableDaysOfWeek = $therapist->availabilities->pluck('day_of_week')->unique()->toArray();
            
            Log::info("Therapist {$therapistId} available days: " . implode(', ', $availableDaysOfWeek));
            
            // Generate available dates for the next X months
            $availableDates = [];
            $startDate = $workStartDate ? Carbon::parse($workStartDate) : Carbon::today();
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

            Log::info("Generated " . count($availableDates) . " available dates for therapist {$therapistId}");
            return $availableDates;

        } catch (\Exception $e) {
            Log::error('Error getting available dates for therapist ' . $therapistId . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Count available slots for today
     * MOVED FROM TimeSlotController to fix the issue
     */
    private function countAvailableSlotsToday($therapistId)
    {
        try {
            $today = Carbon::today()->toDateString();
            $dayOfWeek = strtolower(Carbon::today()->format('l'));
            
            Log::info("Checking slots for therapist {$therapistId} on {$today} ({$dayOfWeek})");
            
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
            $intervalMinutes = 30;
            $defaultDuration = 60;

            foreach ($availabilities as $availability) {
                $startTime = Carbon::parse($availability->start_time);
                $endTime = Carbon::parse($availability->end_time);
                $currentTime = $startTime->copy();

                Log::info("Processing availability slot: {$startTime->format('H:i')} - {$endTime->format('H:i')}");

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