<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserPreferencesController extends Controller
{
    /**
     * Get user preferences
     */
    public function getPreferences(Request $request)
    {
        try {
            $user = $request->user();
            
            $preferences = [
                'service_user_preferences' => [
                    'preferred_therapist_gender' => $user->preferred_therapist_gender,
                    'preferred_language' => $user->preferred_language,
                    'age_range_therapist' => [
                        'start' => $user->preferred_age_range_therapist_start,
                        'end' => $user->preferred_age_range_therapist_end
                    ],
                ],
                'last_updated' => $user->preferences_updated_at?->toISOString(),
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
     * Update user preferences
     */
    public function updatePreferences(Request $request)
    {
        try {
            $user = $request->user();
            
            // Validation rules
            $validator = Validator::make($request->all(), [
                'preferred_therapist_gender' => [
                    'sometimes',
                    'string',
                    Rule::in(['any', 'male', 'female'])
                ],
                'preferred_language' => [
                    'sometimes',
                    'string',
                    Rule::in([
                        'english', 'french', 'german', 'tamil', 'polish', 
                        'romanian', 'spanish', 'italian'
                    ])
                ],
                'preferred_age_range_therapist_start' => [
                    'sometimes',
                    'integer',
                    'min:18',
                    'max:80',
                    'lt:preferred_age_range_therapist_end'
                ],
                'preferred_age_range_therapist_end' => [
                    'sometimes',
                    'integer',
                    'min:18',
                    'max:80',
                    'gt:preferred_age_range_therapist_start'
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update preferences
            $preferences = $validator->validated();
            $user->updatePreferences($preferences);

            // Reload user to get updated data
            $user->refresh();

            $updatedPreferences = [
                'service_user_preferences' => [
                    'preferred_therapist_gender' => $user->preferred_therapist_gender,
                    'preferred_language' => $user->preferred_language,
                    'age_range_therapist' => [
                        'start' => $user->preferred_age_range_therapist_start,
                        'end' => $user->preferred_age_range_therapist_end
                    ],
                ],
                'last_updated' => $user->preferences_updated_at->toISOString(),
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
            $user = $request->user();
            
            $defaultPreferences = [
                'preferred_therapist_gender' => 'any',
                'preferred_language' => 'english',
                'preferred_age_range_therapist_start' => 25,
                'preferred_age_range_therapist_end' => 65,
            ];

            $user->updatePreferences($defaultPreferences);
            $user->refresh();

            $resetPreferences = [
                'service_user_preferences' => [
                    'preferred_therapist_gender' => $user->preferred_therapist_gender,
                    'preferred_language' => $user->preferred_language,
                    'age_range_therapist' => [
                        'start' => $user->preferred_age_range_therapist_start,
                        'end' => $user->preferred_age_range_therapist_end
                    ],
                ],
                'last_updated' => $user->preferences_updated_at->toISOString(),
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