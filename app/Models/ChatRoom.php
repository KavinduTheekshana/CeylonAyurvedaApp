<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'therapist_id',
        'last_message_at',
        'is_active'
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Get the patient (user) that owns the chat room
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * Get the therapist that owns the chat room
     */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class, 'therapist_id');
    }

    /**
     * Get all messages for this chat room
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('sent_at', 'asc');
    }

    /**
     * Get the latest message
     */
    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class)->latest('sent_at');
    }

    /**
     * Get unread messages count for a specific user
     */
    public function getUnreadCountForUser($userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Check if user has access to this chat room
     * Based on existing booking relationship
     */
    public function hasAccess($userId): bool
    {
        // Check if this user is the patient in this chat room
        if ($this->patient_id == $userId) {
            return true;
        }

        // For future: Check if user is therapist (if therapists have user accounts)
        // Currently therapists don't have user_id in your DB structure
        return false;
    }

    /**
     * Verify patient has booking with therapist
     */
    public static function canCreateChatBetween($patientId, $therapistId): bool
    {
        return \App\Models\Booking::where('user_id', $patientId)
            ->where('therapist_id', $therapistId)
            ->exists();
    }
}