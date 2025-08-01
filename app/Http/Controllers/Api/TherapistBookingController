<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TherapistBookingController extends Controller
{
    /**
     * Get therapist's bookings with optional date filtering
     */
    public function getBookings(Request $request)
    {
        try {
            $therapist = $request->user();
            $date = $request->query('date');
            $status = $request->query('status');
            $perPage = min($request->query('per_page', 15), 50);

            $query = $therapist->bookings()
                ->with(['service:id,title,duration', 'user:id,name']);

            // Filter by date if provided
            if ($date) {
                $query->whereDate('date', $date);
            }

            // Filter by status if provided
            if ($status) {
                $query->where('status', $status);
            }

            // Default to upcoming bookings if no specific date
            if (!$date) {
                $query->where('date', '>=', today());
            }

            $bookings = $query->orderBy('date', 'asc')
                ->orderBy('time', 'asc')
                ->paginate($perPage);

            // Transform bookings data
            $transformedBookings = $bookings->getCollection()->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'reference' => $booking->reference,
                    'date' => $booking->date,
                    'time' => $booking->time,
                    'status' => $booking->status,
                    'customer_name' => $booking->name,
                    'customer_email' => $booking->email,
                    'customer_phone' => $booking->phone,
                    'address' => [
                        'line1' => $booking->address_line1,
                        'line2' => $booking->address_line2,
                        'city' => $booking->city,
                        'postcode' => $booking->postcode,
                    ],
                    'service' => [
                        'id' => $booking->service->id,
                        'title' => $booking->service->title,
                        'duration' => $booking->service->duration,
                    ],
                    'price' => (float) $booking->price,
                    'notes' => $booking->notes,
                    'created_at' => $booking->created_at->toISOString(),
                    'can_update_status' => in_array($booking->status, ['confirmed', 'pending']),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedBookings,
                'pagination' => [
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching therapist bookings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bookings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get today's bookings for therapist
     */
    public function getTodayBookings(Request $request)
    {
        try {
            $therapist = $request->user();
            $today = Carbon::today()->toDateString();

            $bookings = $therapist->bookings()
                ->whereDate('date', $today)
                ->whereIn('status', ['confirmed', 'pending', 'completed'])
                ->with(['service:id,title,duration'])
                ->orderBy('time')
                ->get()
                ->map(function ($booking) {
                    return [
                        'id' => $booking->id,
                        'reference' => $booking->reference,
                        'time' => $booking->time,
                        'status' => $booking->status,
                        'customer_name' => $booking->name,
                        'customer_phone' => $booking->phone,
                        'service' => [
                            'title' => $booking->service->title,
                            'duration' => $booking->service->duration,
                        ],
                        'price' => (float) $booking->price,
                        'notes' => $booking->notes,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $bookings,
                'meta' => [
                    'date' => $today,
                    'total_bookings' => $bookings->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching today\'s bookings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch today\'s bookings',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get specific booking details
     */
    public function getBookingDetails(Request $request, $bookingId)
    {
        try {
            $therapist = $request->user();

            $booking = $therapist->bookings()
                ->with(['service', 'user'])
                ->find($bookingId);

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found'
                ], 404);
            }

            $bookingData = [
                'id' => $booking->id,
                'reference' => $booking->reference,
                'date' => $booking->date,
                'time' => $booking->time,
                'status' => $booking->status,
                'customer' => [
                    'name' => $booking->name,
                    'email' => $booking->email,
                    'phone' => $booking->phone,
                ],
                'address' => [
                    'line1' => $booking->address_line1,
                    'line2' => $booking->address_line2,
                    'city' => $booking->city,
                    'postcode' => $booking->postcode,
                ],
                'service' => [
                    'id' => $booking->service->id,
                    'title' => $booking->service->title,
                    'duration' => $booking->service->duration,
                    'description' => $booking->service->description,
                ],
                'pricing' => [
                    'price' => (float) $booking->price,
                    'original_price' => (float) $booking->original_price,
                    'discount_amount' => (float) $booking->discount_amount,
                ],
                'notes' => $booking->notes,
                'payment_method' => $booking->payment_method,
                'payment_status' => $booking->payment_status,
                'created_at' => $booking->created_at->toISOString(),
                'updated_at' => $booking->updated_at->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $bookingData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching booking details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch booking details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update booking status
     */
    public function updateBookingStatus(Request $request, $bookingId)
    {
        $request->validate([
            'status' => 'required|in:confirmed,completed,cancelled,no_show',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $therapist = $request->user();

            $booking = $therapist->bookings()->find($bookingId);

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found'
                ], 404);
            }

            // Check if status change is allowed
            $allowedTransitions = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['completed', 'cancelled', 'no_show'],
            ];

            $currentStatus = $booking->status;
            $newStatus = $request->status;

            if (!isset($allowedTransitions[$currentStatus]) || 
                !in_array($newStatus, $allowedTransitions[$currentStatus])) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot change status from {$currentStatus} to {$newStatus}"
                ], 400);
            }

            // Update booking
            $booking->update([
                'status' => $newStatus,
                'notes' => $request->notes ?? $booking->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking status updated successfully',
                'data' => [
                    'id' => $booking->id,
                    'status' => $booking->status,
                    'notes' => $booking->notes,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating booking status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update booking status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get booking statistics for therapist
     */
    public function getBookingStats(Request $request)
    {
        try {
            $therapist = $request->user();
            $startDate = $request->query('start_date', Carbon::now()->startOfMonth());
            $endDate = $request->query('end_date', Carbon::now()->endOfMonth());

            $stats = [
                'total_bookings' => $therapist->bookings()
                    ->whereBetween('date', [$startDate, $endDate])
                    ->count(),
                'confirmed_bookings' => $therapist->bookings()
                    ->whereBetween('date', [$startDate, $endDate])
                    ->where('status', 'confirmed')
                    ->count(),
                'completed_bookings' => $therapist->bookings()
                    ->whereBetween('date', [$startDate, $endDate])
                    ->where('status', 'completed')
                    ->count(),
                'cancelled_bookings' => $therapist->bookings()
                    ->whereBetween('date', [$startDate, $endDate])
                    ->where('status', 'cancelled')
                    ->count(),
                'total_revenue' => $therapist->bookings()
                    ->whereBetween('date', [$startDate, $endDate])
                    ->whereIn('status', ['completed', 'confirmed'])
                    ->sum('price'),
                'today_bookings' => $therapist->bookings()
                    ->whereDate('date', Carbon::today())
                    ->whereIn('status', ['confirmed', 'pending'])
                    ->count(),
                'upcoming_bookings' => $therapist->bookings()
                    ->where('date', '>', Carbon::today())
                    ->whereIn('status', ['confirmed', 'pending'])
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching booking stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch booking statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get therapist's schedule for a specific date range
     */
    public function getSchedule(Request $request)
    {
        try {
            $therapist = $request->user();
            $startDate = $request->query('start_date', Carbon::now()->startOfWeek());
            $endDate = $request->query('end_date', Carbon::now()->endOfWeek());

            // Get bookings for the date range
            $bookings = $therapist->bookings()
                ->whereBetween('date', [$startDate, $endDate])
                ->whereIn('status', ['confirmed', 'pending', 'completed'])
                ->with(['service:id,title,duration'])
                ->orderBy('date')
                ->orderBy('time')
                ->get()
                ->groupBy('date')
                ->map(function ($dayBookings) {
                    return $dayBookings->map(function ($booking) {
                        return [
                            'id' => $booking->id,
                            'time' => $booking->time,
                            'duration' => $booking->service->duration ?? 60,
                            'service' => $booking->service->title,
                            'customer' => $booking->name,
                            'status' => $booking->status,
                        ];
                    });
                });

            // Get availability for the date range
            $availabilities = $therapist->availabilities()
                ->where('is_active', true)
                ->get()
                ->keyBy('day_of_week');

            // Build schedule
            $schedule = [];
            $currentDate = Carbon::parse($startDate);
            
            while ($currentDate->lte(Carbon::parse($endDate))) {
                $dayOfWeek = strtolower($currentDate->format('l'));
                $dateStr = $currentDate->toDateString();
                
                $daySchedule = [
                    'date' => $dateStr,
                    'day_of_week' => $dayOfWeek,
                    'is_available' => isset($availabilities[$dayOfWeek]),
                    'availability' => isset($availabilities[$dayOfWeek]) ? [
                        'start_time' => $availabilities[$dayOfWeek]->start_time,
                        'end_time' => $availabilities[$dayOfWeek]->end_time,
                    ] : null,
                    'bookings' => $bookings->get($dateStr, collect())->toArray(),
                ];
                
                $schedule[] = $daySchedule;
                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => $schedule,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching therapist schedule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch schedule',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}