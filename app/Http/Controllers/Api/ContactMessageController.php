<?php

// app/Http/Controllers/Api/ContactMessageController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ContactMessageController extends Controller
{
    /**
     * Store a new contact message from mobile app
     */
    public function store(Request $request): JsonResponse
    {
        // Validation rules
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'branch_id' => 'required|exists:locations,id',
            'branch_name' => 'required|string|max:255',
            'is_guest' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Get validated data
            $validatedData = $validator->validated();
            
            // Get user ID if authenticated
            $userId = null;
            if (Auth::check() && !$request->boolean('is_guest', false)) {
                $userId = Auth::id();
            }

            // Get user agent and IP for metadata
            $metadata = [
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'submitted_from' => 'mobile_app',
                'app_version' => $request->header('App-Version'),
                'device_info' => $request->header('Device-Info'),
            ];

            // Create contact message
            $contactMessage = ContactMessage::create([
                'subject' => $validatedData['subject'],
                'message' => $validatedData['message'],
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'branch_id' => $validatedData['branch_id'],
                'branch_name' => $validatedData['branch_name'],
                'is_guest' => $request->boolean('is_guest', false),
                'user_id' => $userId,
                'status' => 'pending',
                'metadata' => $metadata,
            ]);

            // Load relationships for response
            $contactMessage->load(['branch', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Your message has been sent successfully. Our admin team will respond within 24 hours.',
                'data' => [
                    'message_id' => $contactMessage->id,
                    'status' => $contactMessage->status,
                    'branch' => $contactMessage->branch->name,
                    'created_at' => $contactMessage->created_at->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating contact message: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's contact messages (for logged-in users)
     */
    public function getUserMessages(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        try {
            $messages = ContactMessage::where('user_id', Auth::id())
                ->with(['branch', 'respondedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $messages->items(),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user messages: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch messages',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a specific message by ID (for logged-in users)
     */
    public function show($id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        try {
            $message = ContactMessage::where('id', $id)
                ->where('user_id', Auth::id())
                ->with(['branch', 'respondedBy'])
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching message: ' . $e->getMessage(), [
                'message_id' => $id,
                'user_id' => Auth::id(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch message',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}