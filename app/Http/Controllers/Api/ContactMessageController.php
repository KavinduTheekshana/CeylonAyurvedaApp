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
    
            // REMOVE THIS EARLY RETURN - it was preventing the message from being saved!
            // return response()->json([
            //     'success' => true,
            //     'message' => $userId
            // ]);
    
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
                    'user_id' => $userId, // Debug: include user_id in response
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
            $userId = Auth::id();
  
            // Debug: Log the current user ID
            Log::info('Fetching messages for user ID: ' . $userId);
            
            // Debug: Check if any messages exist for this user
            $totalMessages = ContactMessage::where('user_id', $userId)->count();
      
            Log::info('Total messages for user ' . $userId . ': ' . $totalMessages);
            
            // Debug: Check all messages in the table (limit to 5 for debugging)
            $allMessages = ContactMessage::select('id', 'user_id', 'email', 'name', 'is_guest', 'subject')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
            Log::info('Recent messages in database:', $allMessages->toArray());
            
            // Try to get messages for the current user
            $messages = ContactMessage::where('user_id', $userId)
                ->with(['branch:id,name,city,address', 'respondedBy:id,name,email'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
                
            Log::info('Query result count: ' . $messages->count());
    
            // If no messages found, let's try a broader search
            if ($messages->count() === 0) {
                $userEmail = Auth::user()->email;
                Log::info('No messages found for user_id. Checking by email: ' . $userEmail);
                
                // Check if there are messages with this email but no user_id (guest messages)
                $guestMessages = ContactMessage::where('email', $userEmail)
                    ->where(function($query) {
                        $query->whereNull('user_id')->orWhere('is_guest', true);
                    })
                    ->count();
                Log::info('Guest messages with this email: ' . $guestMessages);
            }
    
            // Transform the data to include formatted dates
            $transformedMessages = $messages->getCollection()->map(function ($message) {
                return [
                    'id' => $message->id,
                    'subject' => $message->subject,
                    'message' => $message->message,
                    'name' => $message->name,
                    'email' => $message->email,
                    'branch_id' => $message->branch_id,
                    'branch_name' => $message->branch_name,
                    'is_guest' => $message->is_guest,
                    'user_id' => $message->user_id,
                    'status' => $message->status,
                    'admin_response' => $message->admin_response,
                    'responded_at' => $message->responded_at?->toISOString(),
                    'responded_by' => $message->responded_by,
                    'created_at' => $message->created_at->toISOString(),
                    'updated_at' => $message->updated_at->toISOString(),
                    // Include relationship data
                    'branch' => $message->branch ? [
                        'id' => $message->branch->id,
                        'name' => $message->branch->name,
                        'city' => $message->branch->city,
                        'address' => $message->branch->address,
                    ] : null,
                    'responded_by_user' => $message->respondedBy ? [
                        'id' => $message->respondedBy->id,
                        'name' => $message->respondedBy->name,
                        'email' => $message->respondedBy->email,
                    ] : null,
                    // Add the computed attributes from your model
                    'status_color' => $message->status_color,
                    'status_badge' => $message->status_badge,
                    'user_type' => $message->user_type,
                ];
            });
    
            return response()->json([
                'success' => true,
                'data' => $transformedMessages,
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ],
                // Debug info
                'debug' => [
                    'user_id' => $userId,
                    'total_messages_for_user' => $totalMessages,
                    'query_result_count' => $messages->count(),
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
                ->with(['branch:id,name,city,address', 'respondedBy:id,name,email'])
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }

            // Transform the data
            $transformedMessage = [
                'id' => $message->id,
                'subject' => $message->subject,
                'message' => $message->message,
                'name' => $message->name,
                'email' => $message->email,
                'branch_id' => $message->branch_id,
                'branch_name' => $message->branch_name,
                'is_guest' => $message->is_guest,
                'user_id' => $message->user_id,
                'status' => $message->status,
                'admin_response' => $message->admin_response,
                'responded_at' => $message->responded_at?->toISOString(),
                'responded_by' => $message->responded_by,
                'created_at' => $message->created_at->toISOString(),
                'updated_at' => $message->updated_at->toISOString(),
                'branch' => $message->branch ? [
                    'id' => $message->branch->id,
                    'name' => $message->branch->name,
                    'city' => $message->branch->city,
                    'address' => $message->branch->address,
                ] : null,
                'responded_by_user' => $message->respondedBy ? [
                    'id' => $message->respondedBy->id,
                    'name' => $message->respondedBy->name,
                    'email' => $message->respondedBy->email,
                ] : null,
                // Add the computed attributes from your model
                'status_color' => $message->status_color,
                'status_badge' => $message->status_badge,
                'user_type' => $message->user_type,
            ];

            return response()->json([
                'success' => true,
                'data' => $transformedMessage
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

    /**
     * Get messages statistics for the user
     */
    public function getMessageStats(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        try {
            $userId = Auth::id();
            
            $stats = [
                'total_messages' => ContactMessage::where('user_id', $userId)->count(),
                'pending_messages' => ContactMessage::where('user_id', $userId)->where('status', 'pending')->count(),
                'resolved_messages' => ContactMessage::where('user_id', $userId)->where('status', 'resolved')->count(),
                'messages_with_response' => ContactMessage::where('user_id', $userId)->whereNotNull('admin_response')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching message stats: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch message statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}