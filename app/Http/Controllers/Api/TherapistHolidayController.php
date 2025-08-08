<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TherapistHolidayRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TherapistHolidayController extends Controller
{
    /**
     * Get all holiday requests for the authenticated therapist
     */
    public function index(Request $request)
    {
        try {
            $therapist = $request->user();
            
            $holidayRequests = TherapistHolidayRequest::where('therapist_id', $therapist->id)
                ->orderBy('date', 'desc')
                ->get()
                ->map(function ($request) {
                    return [
                        'id' => $request->id,
                        'date' => $request->date->format('Y-m-d'),
                        'formatted_date' => $request->date->format('M d, Y'),
                        'reason' => $request->reason,
                        'status' => $request->status,
                        'admin_notes' => $request->admin_notes,
                        'reviewed_at' => $request->reviewed_at?->format('Y-m-d H:i:s'),
                        'reviewed_by' => $request->reviewedBy?->name,
                        'created_at' => $request->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $holidayRequests,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get holiday requests: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get holiday requests for calendar (simplified data)
     */
    public function getCalendarHolidays(Request $request)
    {
        try {
            $therapist = $request->user();
            
            // Get pending and approved holidays for calendar display
            $holidays = TherapistHolidayRequest::where('therapist_id', $therapist->id)
                ->whereIn('status', ['pending', 'approved'])
                ->get(['id', 'date', 'status'])
                ->map(function ($holiday) {
                    return [
                        'date' => $holiday->date->format('Y-m-d'),
                        'status' => $holiday->status,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'pending_holidays' => $holidays->where('status', 'pending')->pluck('date')->toArray(),
                    'approved_holidays' => $holidays->where('status', 'approved')->pluck('date')->toArray(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get calendar holidays: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit a new holiday request
     */
    public function store(Request $request)
    {
        try {
            $therapist = $request->user();

            $validator = Validator::make($request->all(), [
                'date' => 'required|date|after:today',
                'reason' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $date = Carbon::parse($request->date);

            // Check if already requested for this date
            $existingRequest = TherapistHolidayRequest::where('therapist_id', $therapist->id)
                ->where('date', $date)
                ->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Holiday request already exists for this date',
                ], 409);
            }

            // Check if there are bookings on this date
            $hasBookings = $therapist->bookings()
                ->where('date', $date)
                ->whereIn('status', ['confirmed', 'pending'])
                ->exists();

            if ($hasBookings) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot request holiday on a date with existing bookings',
                ], 409);
            }

            $holidayRequest = TherapistHolidayRequest::create([
                'therapist_id' => $therapist->id,
                'date' => $date,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Holiday request submitted successfully',
                'data' => [
                    'id' => $holidayRequest->id,
                    'date' => $holidayRequest->date->format('Y-m-d'),
                    'formatted_date' => $holidayRequest->date->format('M d, Y'),
                    'reason' => $holidayRequest->reason,
                    'status' => $holidayRequest->status,
                    'created_at' => $holidayRequest->created_at->format('Y-m-d H:i:s'),
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit holiday request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a pending holiday request
     */
    public function destroy(Request $request, $id)
    {
        try {
            $therapist = $request->user();

            $holidayRequest = TherapistHolidayRequest::where('id', $id)
                ->where('therapist_id', $therapist->id)
                ->first();

            if (!$holidayRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Holiday request not found',
                ], 404);
            }

            if ($holidayRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only cancel pending holiday requests',
                ], 400);
            }

            $holidayRequest->delete();

            return response()->json([
                'success' => true,
                'message' => 'Holiday request cancelled successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel holiday request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific holiday request
     */
    public function show(Request $request, $id)
    {
        try {
            $therapist = $request->user();

            $holidayRequest = TherapistHolidayRequest::where('id', $id)
                ->where('therapist_id', $therapist->id)
                ->with('reviewedBy:id,name')
                ->first();

            if (!$holidayRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Holiday request not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $holidayRequest->id,
                    'date' => $holidayRequest->date->format('Y-m-d'),
                    'formatted_date' => $holidayRequest->date->format('M d, Y'),
                    'reason' => $holidayRequest->reason,
                    'status' => $holidayRequest->status,
                    'admin_notes' => $holidayRequest->admin_notes,
                    'reviewed_at' => $holidayRequest->reviewed_at?->format('Y-m-d H:i:s'),
                    'reviewed_by' => $holidayRequest->reviewedBy?->name,
                    'created_at' => $holidayRequest->created_at->format('Y-m-d H:i:s'),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get holiday request: ' . $e->getMessage(),
            ], 500);
        }
    }
}