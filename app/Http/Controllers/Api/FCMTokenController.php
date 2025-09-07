<?php
// app/Http/Controllers/Api/FCMTokenController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFcmToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FCMTokenController extends Controller
{
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
            $userId = Auth::id();
            
            // Deactivate old tokens for this device
            if ($request->device_id) {
                UserFcmToken::where('user_id', $userId)
                    ->where('device_id', $request->device_id)
                    ->update(['is_active' => false]);
            }

            // Create or update token
            $token = UserFcmToken::updateOrCreate(
                [
                    'user_id' => $userId,
                    'fcm_token' => $request->fcm_token
                ],
                [
                    'device_type' => $request->device_type,
                    'device_id' => $request->device_id,
                    'is_active' => true,
                    'last_used_at' => now()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'FCM token registered successfully',
                'data' => [
                    'token_id' => $token->id,
                    'device_type' => $token->device_type
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register FCM token',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            UserFcmToken::where('user_id', $userId)
                ->where('fcm_token', $request->fcm_token)
                ->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate FCM token'
            ], 500);
        }
    }
}