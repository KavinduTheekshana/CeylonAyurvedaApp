<?php
// app/Http/Controllers/Api/TherapistPreferencesController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TherapistPreferencesController extends Controller
{
    /**
     * Get therapist preferences
     */
    public function getPreferences(Request $request)
    {

        try {
            $therapist = $request->user();
            // return $therapist;
            
            $preferences = [
                'service_user_preferences' => [
                    'preferred_gender' => $therapist->preferred_gender,
                    'age_range' => [
                        'start' => $therapist->age_range_start,
                        'end' => $therapist->age_range_end
                    ],
                    'preferred_language' => $therapist->preferred_language,
                ],
                'service_delivery' => [
                    'accept_new_patients' => $therapist->accept_new_patients,
                    'home_visits_only' => $therapist->home_visits_only,
                    'clinic_visits_only' => $therapist->clinic_visits_only,
                    'max_travel_distance' => $therapist->max_travel_distance,
                    'weekends_available' => $therapist->weekends_available,
                    'evenings_available' => $therapist->evenings_available,
                ],
                'last_updated' => $therapist->preferences_updated_at?->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Preferences retrieved successfully',
                'data' => $preferences
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve preferences',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update therapist preferences
     */
    public function updatePreferences(Request $request)
    {
        try {
            $therapist = $request->user();
            
            // Validation rules
            $validator = Validator::make($request->all(), [
                // Service User Preferences
                'preferred_gender' => [
                    'sometimes',
                    'string',
                    Rule::in(['all', 'male', 'female'])
                ],
                'age_range_start' => [
                    'sometimes',
                    'integer',
                    'min:16',
                    'max:80',
                    'lt:age_range_end'
                ],
                'age_range_end' => [
                    'sometimes',
                    'integer',
                    'min:16',
                    'max:80',
                    'gt:age_range_start'
                ],
                'preferred_language' => [
                    'sometimes',
                    'string',
                    Rule::in([
                        'english', 'hindi', 'tamil', 'telugu', 'bengali',
                        'gujarati', 'malayalam', 'kannada', 'punjabi', 'marathi'
                    ])
                ],
                
                // Service Delivery Preferences
                'accept_new_patients' => 'sometimes|boolean',
                'home_visits_only' => 'sometimes|boolean',
                'clinic_visits_only' => 'sometimes|boolean',
                'max_travel_distance' => [
                    'sometimes',
                    'integer',
                    Rule::in([5, 10, 15, 20, 25, 30, 50])
                ],
                'weekends_available' => 'sometimes|boolean',
                'evenings_available' => 'sometimes|boolean',
            ]);

            // Custom validation: home_visits_only and clinic_visits_only cannot both be true
            $validator->after(function ($validator) use ($request, $therapist) {
                $homeOnly = $request->get('home_visits_only', $therapist->home_visits_only);
                $clinicOnly = $request->get('clinic_visits_only', $therapist->clinic_visits_only);
                
                if ($homeOnly && $clinicOnly) {
                    $validator->errors()->add('visit_type', 'Cannot select both home visits only and clinic visits only');
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update preferences
            $preferences = $validator->validated();
            $therapist->updatePreferences($preferences);

            // Reload therapist to get updated data
            $therapist->refresh();

            $updatedPreferences = [
                'service_user_preferences' => [
                    'preferred_gender' => $therapist->preferred_gender,
                    'age_range' => [
                        'start' => $therapist->age_range_start,
                        'end' => $therapist->age_range_end
                    ],
                    'preferred_language' => $therapist->preferred_language,
                ],
                'service_delivery' => [
                    'accept_new_patients' => $therapist->accept_new_patients,
                    'home_visits_only' => $therapist->home_visits_only,
                    'clinic_visits_only' => $therapist->clinic_visits_only,
                    'max_travel_distance' => $therapist->max_travel_distance,
                    'weekends_available' => $therapist->weekends_available,
                    'evenings_available' => $therapist->evenings_available,
                ],
                'last_updated' => $therapist->preferences_updated_at->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'data' => $updatedPreferences
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update preferences',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Reset preferences to default
     */
    public function resetPreferences(Request $request)
    {
        try {
            $therapist = $request->user();
            
            $defaultPreferences = [
                'preferred_gender' => 'all',
                'age_range_start' => 18,
                'age_range_end' => 65,
                'preferred_language' => 'english',
                'accept_new_patients' => true,
                'home_visits_only' => false,
                'clinic_visits_only' => false,
                'max_travel_distance' => 10,
                'weekends_available' => false,
                'evenings_available' => false,
            ];

            $therapist->updatePreferences($defaultPreferences);
            $therapist->refresh();

            $resetPreferences = [
                'service_user_preferences' => [
                    'preferred_gender' => $therapist->preferred_gender,
                    'age_range' => [
                        'start' => $therapist->age_range_start,
                        'end' => $therapist->age_range_end
                    ],
                    'preferred_language' => $therapist->preferred_language,
                ],
                'service_delivery' => [
                    'accept_new_patients' => $therapist->accept_new_patients,
                    'home_visits_only' => $therapist->home_visits_only,
                    'clinic_visits_only' => $therapist->clinic_visits_only,
                    'max_travel_distance' => $therapist->max_travel_distance,
                    'weekends_available' => $therapist->weekends_available,
                    'evenings_available' => $therapist->evenings_available,
                ],
                'last_updated' => $therapist->preferences_updated_at->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Preferences reset to defaults successfully',
                'data' => $resetPreferences
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset preferences',
                'error' => config('app.debug') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}