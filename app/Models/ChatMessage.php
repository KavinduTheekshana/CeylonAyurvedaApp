<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_room_id',
        'sender_id',
        'sender_type',  // ADDED: New field
        'message_content',
        'encrypted_content',
        'encryption_algorithm',
        'key_id',
        'initialization_vector',
        'message_type',
        'is_read',
        'sent_at',
        'edited_at'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'sent_at' => 'datetime',
        'edited_at' => 'datetime',
        'sender_type' => 'string'  // ADDED: Cast sender_type
    ];

    /**
     * Get the chat room this message belongs to
     */
    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }

    /**
     * Get the user who sent this message (for patient messages)
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * ADDED: Get the patient sender (when sender_type is 'patient')
     */
    public function patientSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * ADDED: Get the therapist sender (when sender_type is 'therapist')
     */
    public function therapistSender(): BelongsTo
    {
        return $this->belongsTo(Therapist::class, 'sender_id');
    }

    /**
     * ADDED: Get sender information based on sender_type
     * This is a helper method to get the correct sender model
     */
    public function getSenderInfoAttribute()
    {
        if ($this->sender_type === 'patient') {
            $user = User::find($this->sender_id);
            return [
                'id' => $user->id,
                'name' => $user->name,
                'type' => 'patient',
                'image' => $user->profile_photo_path ? asset('storage/' . $user->profile_photo_path) : null
            ];
        } elseif ($this->sender_type === 'therapist') {
            $therapist = Therapist::find($this->sender_id);
            return [
                'id' => $therapist->id,
                'name' => $therapist->name,
                'type' => 'therapist',
                'image' => $therapist->image ? asset('storage/' . $therapist->image) : null
            ];
        }
        
        return null;
    }

    /**
     * Automatically encrypt message content when saving
     */
    protected static function booted()
    {
        static::creating(function ($message) {
            if ($message->message_content) {
                $message->encrypted_content = Crypt::encryptString($message->message_content);
                $message->encryption_algorithm = 'AES-256-CBC';
                $message->key_id = 'app_master_key';
                $message->initialization_vector = 'laravel_default';
            }
        });
    }

    /**
     * Get decrypted message content
     */
    public function getDecryptedContentAttribute(): string
    {
        if ($this->encrypted_content) {
            try {
                return Crypt::decryptString($this->encrypted_content);
            } catch (\Exception $e) {
                return $this->message_content ?? 'Message could not be decrypted';
            }
        }
        
        return $this->message_content ?? '';
    }
}