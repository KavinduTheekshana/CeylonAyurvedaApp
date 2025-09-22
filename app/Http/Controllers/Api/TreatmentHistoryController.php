<?php
// app/Http/Controllers/Api/TreatmentHistoryController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TreatmentHistory;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TreatmentHistoryController extends Controller
{
    /**
     * Get all treatment histories for the authenticated therapist
     */
    public function index(Request $request)
    {
        try {
            $therapist = $request->user();
            
            $histories = TreatmentHistory::forTherapist($therapist->id)
                ->with(['booking:id,reference,date,time', 'service:id,title'])
                ->orderBy('treatment_completed_at', 'desc')
                ->paginate(20);

            $data = $histories->getCollection()->map(function ($history) {
                return [
                    'id' => $history->id,
                    'booking_reference' => $history->booking->reference,
                    'patient_name' => $history->patient_name,
                    'service_title' => $history->service->title,
                    'treatment_date' => $history->booking->date,
                    'treatment_time' => $history->booking->time,
                    'patient_condition' => $history->patient_condition,
                    'pain_level_before' => $history->pain_level_before,
                    'pain_level_after' => $history->pain_level_after,
                    'is_editable' => $history->is_editable,
                    'hours_remaining_for_edit' => $history->hours_remaining_for_edit,
                    'treatment_completed_at' => $history->treatment_completed_at,
                    'created_at' => $history->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'current_page' => $histories->currentPage(),
                    'last_page' => $histories->lastPage(),
                    'per_page' => $histories->perPage(),
                    'total' => $histories->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch treatment histories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new treatment history
     */
    public function store(Request $request)
    {
        try {
            $therapist = $request->user();

            $validator = Validator::make($request->all(), [
                'booking_id' => 'required|integer|exists:bookings,id',
                'treatment_notes' => 'required|string|max:2000',
                'observations' => 'nullable|string|max:1000',
                'recommendations' => 'nullable|string|max:1000',
                'patient_condition' => 'nullable|in:improved,same,worse',
                'pain_level_before' => 'nullable|integer|between:1,10',
                'pain_level_after' => 'nullable|integer|between:1,10',
                'areas_treated' => 'nullable|array',
                'areas_treated.*' => 'string|max:100',
                'next_treatment_plan' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get booking and verify it belongs to therapist
            $booking = Booking::where('id', $request->booking_id)
                            //  ->where('therapist_id', $therapist->id)
                            //  ->where('status', 'completed')
                             ->with(['service:id,title'])
                             ->first();

            if (!$booking) {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking not found or not accessible'
                ], 404);
            }

            // Check if treatment history already exists
            $existingHistory = TreatmentHistory::where('booking_id', $booking->id)->first();
            if ($existingHistory) {
                return response()->json([
                    'success' => false,
                    'message' => 'Treatment history already exists for this booking'
                ], 409);
            }

            // Create treatment history
            $history = TreatmentHistory::create([
                'booking_id' => $booking->id,
                'therapist_id' => $therapist->id,
                'service_id' => $booking->service_id,
                'patient_name' => $booking->name,
                'treatment_notes' => $request->treatment_notes,
                'observations' => $request->observations,
                'recommendations' => $request->recommendations,
                'patient_condition' => $request->patient_condition,
                'pain_level_before' => $request->pain_level_before,
                'pain_level_after' => $request->pain_level_after,
                'areas_treated' => $request->areas_treated,
                'next_treatment_plan' => $request->next_treatment_plan,
                'treatment_completed_at' => Carbon::now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Treatment history created successfully',
                'data' => [
                    'id' => $history->id,
                    'booking_reference' => $booking->reference,
                    'patient_name' => $history->patient_name,
                    // 'is_editable' => $history->is_editable,
                    // 'hours_remaining_for_edit' => $history->hours_remaining_for_edit,
                    // 'edit_deadline_at' => $history->edit_deadline_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create treatment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show specific treatment history
     */
    public function show(Request $request, $id)
    {
        try {
            $therapist = $request->user();

            $history = TreatmentHistory::forTherapist($therapist->id)
                ->with(['booking:id,reference,date,time,address_line1,city,postcode', 'service:id,title,duration'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $history->id,
                    'booking' => [
                        'id' => $history->booking->id,
                        'reference' => $history->booking->reference,
                        'date' => $history->booking->date,
                        'time' => $history->booking->time,
                        'address' => $history->booking->address_line1,
                        'city' => $history->booking->city,
                        'postcode' => $history->booking->postcode,
                    ],
                    'service' => [
                        'title' => $history->service->title,
                        'duration' => $history->service->duration,
                    ],
                    'patient_name' => $history->patient_name,
                    'treatment_notes' => $history->treatment_notes,
                    'observations' => $history->observations,
                    'recommendations' => $history->recommendations,
                    'patient_condition' => $history->patient_condition,
                    'pain_level_before' => $history->pain_level_before,
                    'pain_level_after' => $history->pain_level_after,
                    'areas_treated' => $history->areas_treated,
                    'next_treatment_plan' => $history->next_treatment_plan,
                    'treatment_completed_at' => $history->treatment_completed_at,
                    'is_editable' => $history->is_editable,
                    'hours_remaining_for_edit' => $history->hours_remaining_for_edit,
                    'edit_deadline_at' => $history->edit_deadline_at,
                    'created_at' => $history->created_at,
                    'updated_at' => $history->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Treatment history not found'
            ], 404);
        }
    }

    /**
     * Update treatment history (only within 24 hours)
     */
    public function update(Request $request, $id)
    {
        try {
            $therapist = $request->user();

            $history = TreatmentHistory::forTherapist($therapist->id)->findOrFail($id);

            // Check if still editable
            if (!$history->is_editable) {
                return response()->json([
                    'success' => false,
                    'message' => 'Treatment history can no longer be edited (24-hour limit exceeded)'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'treatment_notes' => 'sometimes|required|string|max:2000',
                'observations' => 'nullable|string|max:1000',
                'recommendations' => 'nullable|string|max:1000',
                'patient_condition' => 'nullable|in:improved,same,worse',
                'pain_level_before' => 'nullable|integer|between:1,10',
                'pain_level_after' => 'nullable|integer|between:1,10',
                'areas_treated' => 'nullable|array',
                'areas_treated.*' => 'string|max:100',
                'next_treatment_plan' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update only provided fields
            $updateData = $request->only([
                'treatment_notes',
                'observations',
                'recommendations',
                'patient_condition',
                'pain_level_before',
                'pain_level_after',
                'areas_treated',
                'next_treatment_plan'
            ]);

            $history->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Treatment history updated successfully',
                'data' => [
                    'id' => $history->id,
                    'hours_remaining_for_edit' => $history->hours_remaining_for_edit,
                    'updated_at' => $history->fresh()->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update treatment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get treatment history for a specific booking
     */
    public function getByBooking(Request $request, $bookingId)
    {
        try {
            $therapist = $request->user();

            $history = TreatmentHistory::where('booking_id', $bookingId)
                ->forTherapist($therapist->id)
                ->with(['booking:id,reference', 'service:id,title'])
                ->first();

            if (!$history) {
                return response()->json([
                    'success' => false,
                    'message' => 'No treatment history found for this booking'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $history->id,
                    'booking_reference' => $history->booking->reference,
                    'patient_name' => $history->patient_name,
                    'service_title' => $history->service->title,
                    'treatment_notes' => $history->treatment_notes,
                    'observations' => $history->observations,
                    'recommendations' => $history->recommendations,
                    'patient_condition' => $history->patient_condition,
                    'pain_level_before' => $history->pain_level_before,
                    'pain_level_after' => $history->pain_level_after,
                    'areas_treated' => $history->areas_treated,
                    'next_treatment_plan' => $history->next_treatment_plan,
                    'is_editable' => $history->is_editable,
                    'hours_remaining_for_edit' => $history->hours_remaining_for_edit,
                    'treatment_completed_at' => $history->treatment_completed_at,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch treatment history'
            ], 500);
        }
    }
}