<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TherapistFcmToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TherapistFCMTokenController extends Controller
{
    /**
     * Store or update FCM token
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
            'device_type' => 'required|in:android,ios',
            'device_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $therapist = Auth::user();
            
            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Deactivate old tokens for this device if device_id provided
            if ($request->device_id) {
                TherapistFcmToken::where('therapist_id', $therapist->id)
                    ->where('device_id', $request->device_id)
                    ->update(['is_active' => false]);
            }

            // Create or update token
            $token = TherapistFcmToken::updateOrCreate(
                [
                    'therapist_id' => $therapist->id,
                    'fcm_token' => $request->fcm_token
                ],
                [
                    'device_type' => $request->device_type,
                    'device_id' => $request->device_id,
                    'is_active' => true,
                    'last_used_at' => now()
                ]
            );

            Log::info('Therapist FCM token registered', [
                'therapist_id' => $therapist->id,
                'token_id' => $token->id,
                'device_type' => $token->device_type
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token registered successfully',
                'data' => [
                    'token_id' => $token->id,
                    'device_type' => $token->device_type
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to register therapist FCM token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to register FCM token',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Deactivate FCM token
     */
    public function destroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $therapist = Auth::user();
            
            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // Deactivate the token
            $updated = TherapistFcmToken::where('therapist_id', $therapist->id)
                ->where('fcm_token', $request->fcm_token)
                ->update(['is_active' => false]);

            Log::info('Therapist FCM token deactivated', [
                'therapist_id' => $therapist->id,
                'tokens_updated' => $updated
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token deactivated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to deactivate therapist FCM token', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate FCM token'
            ], 500);
        }
    }
}