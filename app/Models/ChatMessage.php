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
        'edited_at' => 'datetime'
    ];

    /**
     * Get the chat room this message belongs to
     */
    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }

    /**
     * Get the user who sent this message
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
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
                // Log::warning('Failed to decrypt message: ' . $e->getMessage());
                return $this->message_content ?? 'Message could not be decrypted';
            }
        }
        
        return $this->message_content ?? '';
    }
}
