<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Therapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TherapistChatController extends Controller
{
    /**
     * Send a new message as therapist
     */
    public function sendMessage(Request $request, $chatRoomId)
    {
        // Add detailed logging
        Log::info('=== THERAPIST SEND MESSAGE START ===');
        Log::info('Chat Room ID: ' . $chatRoomId);
        Log::info('Request data: ' . json_encode($request->all()));
        
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:1|max:2000',
            'message_type' => 'in:text,image,file'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed: ' . json_encode($validator->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $therapist = $request->user();
            
            if (!$therapist) {
                Log::error('No authenticated therapist');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            Log::info('Authenticated therapist ID: ' . $therapist->id);
            Log::info('Authenticated therapist name: ' . $therapist->name);
            
            // Find chat room and verify access
            $chatRoom = ChatRoom::find($chatRoomId);
            
            if (!$chatRoom) {
                Log::error('Chat room not found: ' . $chatRoomId);
                return response()->json([
                    'success' => false,
                    'message' => 'Chat room not found'
                ], 404);
            }
            
            Log::info('Chat room found. Therapist ID in room: ' . $chatRoom->therapist_id);
            
            if (!$chatRoom->hasTherapistAccess($therapist->id)) {
                Log::error('Unauthorized access. Therapist ID: ' . $therapist->id . ' vs Room Therapist ID: ' . $chatRoom->therapist_id);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to chat room'
                ], 403);
            }

            Log::info('Creating message...');
            
            // Create message with sender_type as 'therapist'
            $message = ChatMessage::create([
                'chat_room_id' => $chatRoom->id,
                'sender_id' => $therapist->id,
                'sender_type' => 'therapist',  // CRITICAL: Set as therapist
                'message_content' => trim($request->message),
                'message_type' => $request->get('message_type', 'text'),
                'is_read' => false,
                'sent_at' => now()
            ]);

            Log::info('Message created with ID: ' . $message->id);

            // Update chat room's last message timestamp
            $chatRoom->update([
                'last_message_at' => now()
            ]);

            Log::info('Chat room updated');

            // Manually build the response instead of using relationships
            $responseData = [
                'id' => $message->id,
                'content' => $message->message_content,
                'sender' => [
                    'id' => $therapist->id,
                    'name' => $therapist->name,
                    'type' => 'therapist'
                ],
                'message_type' => $message->message_type,
                'is_read' => $message->is_read,
                'sent_at' => $message->sent_at->format('Y-m-d H:i:s')
            ];

            Log::info('Response prepared successfully');
            Log::info('=== THERAPIST SEND MESSAGE SUCCESS ===');

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'message' => 'Message sent successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('=== THERAPIST SEND MESSAGE ERROR ===');
            Log::error('Error message: ' . $e->getMessage());
            Log::error('Error file: ' . $e->getFile() . ' Line: ' . $e->getLine());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get all chat rooms for the authenticated therapist
     */
    public function index(Request $request)
    {
        try {
            $therapist = $request->user();
            
            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            $chatRooms = ChatRoom::where('therapist_id', $therapist->id)
                ->where('is_active', true)
                ->with([
                    'patient:id,name,profile_photo_path',
                    'latestMessage:id,chat_room_id,sender_id,sender_type,message_content,sent_at'
                ])
                ->orderBy('last_message_at', 'desc')
                ->get();

            $formattedRooms = $chatRooms->map(function ($room) use ($therapist) {
                $unreadCount = $room->getUnreadCountForTherapist($therapist->id);
                
                $lastMessage = null;
                if ($room->latestMessage) {
                    // Get sender name based on sender_type
                    if ($room->latestMessage->sender_type === 'patient') {
                        $senderName = $room->patient->name;
                    } else {
                        $senderName = $therapist->name; // It's from the therapist
                    }
                    
                    $lastMessage = [
                        'content' => $room->latestMessage->message_content,
                        'sent_at' => $room->latestMessage->sent_at->format('Y-m-d H:i:s'),
                        'sender_name' => $senderName,
                        'sender_type' => $room->latestMessage->sender_type
                    ];
                }
                
                return [
                    'id' => $room->id,
                    'patient' => [
                        'id' => $room->patient->id,
                        'name' => $room->patient->name,
                        'image' => $room->patient->profile_photo_path ? 
                                  asset('storage/' . $room->patient->profile_photo_path) : null
                    ],
                    'last_message' => $lastMessage,
                    'unread_count' => $unreadCount,
                    'last_message_at' => $room->last_message_at?->format('Y-m-d H:i:s'),
                    'created_at' => $room->created_at->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedRooms
            ]);

        } catch (\Exception $e) {
            Log::error('Therapist: Error fetching chat rooms: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chat rooms'
            ], 500);
        }
    }

    /**
     * Get a specific chat room details
     */
    public function show(Request $request, $chatRoomId)
    {
        try {
            $therapist = $request->user();
            
            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            $chatRoom = ChatRoom::with(['patient:id,name,profile_photo_path'])
                ->find($chatRoomId);
            
            if (!$chatRoom) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat room not found'
                ], 404);
            }
            
            if (!$chatRoom->hasTherapistAccess($therapist->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to chat room'
                ], 403);
            }

            $unreadCount = $chatRoom->getUnreadCountForTherapist($therapist->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $chatRoom->id,
                    'patient' => [
                        'id' => $chatRoom->patient->id,
                        'name' => $chatRoom->patient->name,
                        'image' => $chatRoom->patient->profile_photo_path ? 
                                  asset('storage/' . $chatRoom->patient->profile_photo_path) : null
                    ],
                    'unread_count' => $unreadCount,
                    'last_message_at' => $chatRoom->last_message_at?->format('Y-m-d H:i:s'),
                    'created_at' => $chatRoom->created_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Therapist: Error fetching chat room: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chat room'
            ], 500);
        }
    }

    /**
     * Get messages for a specific chat room with pagination
     */
    public function getMessages(Request $request, $chatRoomId)
    {
        try {
            $therapist = $request->user();
            
            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            // Validate pagination parameters
            $page = max(1, $request->get('page', 1));
            $perPage = min(50, max(10, $request->get('per_page', 20)));
            
            // Find chat room and verify access
            $chatRoom = ChatRoom::find($chatRoomId);
            
            if (!$chatRoom) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat room not found'
                ], 404);
            }
            
            if (!$chatRoom->hasTherapistAccess($therapist->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to chat room'
                ], 403);
            }

            // Get messages with pagination (newest first, then reverse for display)
            $messages = $chatRoom->messages()
                ->orderBy('sent_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $formattedMessages = $messages->map(function ($message) use ($chatRoom, $therapist) {
                // Get sender info based on sender_type
                if ($message->sender_type === 'patient') {
                    $senderInfo = [
                        'id' => $chatRoom->patient->id,
                        'name' => $chatRoom->patient->name,
                        'type' => 'patient'
                    ];
                } else {
                    $senderInfo = [
                        'id' => $therapist->id,
                        'name' => $therapist->name,
                        'type' => 'therapist'
                    ];
                }

                return [
                    'id' => $message->id,
                    'content' => $message->message_content,
                    'sender' => $senderInfo,
                    'message_type' => $message->message_type,
                    'is_read' => $message->is_read,
                    'sent_at' => $message->sent_at->format('Y-m-d H:i:s'),
                    'edited_at' => $message->edited_at?->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $formattedMessages,
                    'pagination' => [
                        'current_page' => $messages->currentPage(),
                        'last_page' => $messages->lastPage(),
                        'per_page' => $messages->perPage(),
                        'total' => $messages->total(),
                        'has_more_pages' => $messages->hasMorePages()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Therapist: Error fetching messages: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch messages'
            ], 500);
        }
    }

    /**
     * Mark patient messages as read
     */
    public function markAsRead(Request $request, $chatRoomId)
    {
        try {
            $therapist = $request->user();
            
            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            $chatRoom = ChatRoom::find($chatRoomId);
            
            if (!$chatRoom) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat room not found'
                ], 404);
            }
            
            if (!$chatRoom->hasTherapistAccess($therapist->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to chat room'
                ], 403);
            }

            // Mark all patient messages as read
            $updated = ChatMessage::where('chat_room_id', $chatRoomId)
                ->where('sender_type', 'patient')
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'data' => [
                    'marked_read_count' => $updated
                ],
                'message' => 'Messages marked as read'
            ]);

        } catch (\Exception $e) {
            Log::error('Therapist: Error marking messages as read: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read'
            ], 500);
        }
    }
}