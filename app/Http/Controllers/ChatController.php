<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Therapist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * Get all chat rooms for the authenticated user (patient)
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            $chatRooms = ChatRoom::where('patient_id', $user->id)
                ->where('is_active', true)
                ->with([
                    'therapist:id,name,image,bio',
                    'latestMessage:id,chat_room_id,sender_id,sender_type,message_content,sent_at',
                    'latestMessage.patientSender:id,name',
                    'latestMessage.therapistSender:id,name'
                ])
                ->orderBy('last_message_at', 'desc')
                ->get();

            $formattedRooms = $chatRooms->map(function ($room) use ($user) {
                $unreadCount = $room->getUnreadCountForUser($user->id);
                
                $lastMessage = null;
                if ($room->latestMessage) {
                    $senderName = $room->latestMessage->sender_type === 'patient' 
                        ? $room->latestMessage->patientSender->name 
                        : $room->latestMessage->therapistSender->name;
                    
                    $lastMessage = [
                        'content' => $room->latestMessage->message_content,
                        'sent_at' => $room->latestMessage->sent_at->format('Y-m-d H:i:s'),
                        'sender_name' => $senderName,
                        'sender_type' => $room->latestMessage->sender_type  // ADDED
                    ];
                }
                
                return [
                    'id' => $room->id,
                    'therapist' => [
                        'id' => $room->therapist->id,
                        'name' => $room->therapist->name,
                        'image' => $room->therapist->image ? 
                                  asset('storage/' . $room->therapist->image) : null,
                        'bio' => $room->therapist->bio
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
            Log::error('Error fetching chat rooms: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chat rooms'
            ], 500);
        }
    }

    /**
     * Create or access a chat room with a therapist
     */
    public function createOrAccessChat(Request $request, $therapistId)
    {
        try {
            $user = Auth::user();
            
            // Verify therapist exists
            $therapist = Therapist::find($therapistId);
            if (!$therapist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Therapist not found'
                ], 404);
            }

            // Verify patient has booking with this therapist
            if (!ChatRoom::canCreateChatBetween($user->id, $therapistId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must have a booking with this therapist to start a chat'
                ], 403);
            }

            // Find or create chat room
            $chatRoom = ChatRoom::firstOrCreate(
                [
                    'patient_id' => $user->id,
                    'therapist_id' => $therapistId
                ],
                [
                    'is_active' => true,
                    'last_message_at' => now()
                ]
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'chat_room_id' => $chatRoom->id,
                    'therapist' => [
                        'id' => $therapist->id,
                        'name' => $therapist->name,
                        'image' => $therapist->image ? 
                                  asset('storage/' . $therapist->image) : null,
                        'bio' => $therapist->bio
                    ],
                    'created_at' => $chatRoom->created_at->format('Y-m-d H:i:s')
                ],
                'message' => $chatRoom->wasRecentlyCreated ? 'Chat room created successfully' : 'Chat room accessed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating/accessing chat room: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to access chat'
            ], 500);
        }
    }

    /**
     * Get messages for a specific chat room with pagination
     */
    public function getMessages(Request $request, $chatRoomId)
    {
        try {
            $user = Auth::user();
            
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
            
            if (!$chatRoom->hasAccess($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to chat room'
                ], 403);
            }

            // Get messages with pagination (newest first, then reverse for display)
            $messages = $chatRoom->messages()
                ->with([
                    'patientSender:id,name',
                    'therapistSender:id,name'
                ])
                ->orderBy('sent_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $formattedMessages = $messages->map(function ($message) {
                // UPDATED: Get sender info based on sender_type
                $senderInfo = $message->sender_type === 'patient'
                    ? [
                        'id' => $message->patientSender->id,
                        'name' => $message->patientSender->name,
                        'type' => 'patient'  // ADDED
                    ]
                    : [
                        'id' => $message->therapistSender->id,
                        'name' => $message->therapistSender->name,
                        'type' => 'therapist'  // ADDED
                    ];

                return [
                    'id' => $message->id,
                    'content' => $message->message_content,
                    'sender' => $senderInfo,  // UPDATED: Now includes type
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
            Log::error('Error fetching messages: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch messages'
            ], 500);
        }
    }

    /**
     * Send a new message
     */
    public function sendMessage(Request $request, $chatRoomId)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:1|max:2000',
            'message_type' => 'in:text,image,file'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            
            // Find chat room and verify access
            $chatRoom = ChatRoom::find($chatRoomId);
            
            if (!$chatRoom) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat room not found'
                ], 404);
            }
            
            if (!$chatRoom->hasAccess($user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to chat room'
                ], 403);
            }

            // Create message
            $message = ChatMessage::create([
                'chat_room_id' => $chatRoom->id,
                'sender_id' => $user->id,
                'sender_type' => 'patient',  // ADDED: Specify sender type
                'message_content' => trim($request->message),
                'message_type' => $request->get('message_type', 'text'),
                'is_read' => false,
                'sent_at' => now()
            ]);

            // Update chat room's last message timestamp
            $chatRoom->update([
                'last_message_at' => now()
            ]);

            // Load sender information
            $message->load('patientSender:id,name');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $message->id,
                    'content' => $message->message_content,
                    'sender' => [
                        'id' => $message->patientSender->id,
                        'name' => $message->patientSender->name,
                        'type' => 'patient'  // ADDED
                    ],
                    'message_type' => $message->message_type,
                    'is_read' => $message->is_read,
                    'sent_at' => $message->sent_at->format('Y-m-d H:i:s')
                ],
                'message' => 'Message sent successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error sending message: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message'
            ], 500);
        }
    }

    /**
     * Get chat statistics for the user
     */
    public function getStats(Request $request)
    {
        try {
            $user = Auth::user();
            
            $totalChats = ChatRoom::where('patient_id', $user->id)
                ->where('is_active', true)
                ->count();

            // UPDATED: Count unread messages from therapists only
            $unreadCount = ChatMessage::whereIn('chat_room_id', function ($query) use ($user) {
                $query->select('id')
                    ->from('chat_rooms')
                    ->where('patient_id', $user->id);
            })
            ->where('sender_type', 'therapist')  // ADDED: Only count therapist messages
            ->where('is_read', false)
            ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_chats' => $totalChats,
                    'unread_messages' => $unreadCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching chat stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }
}