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
     * UPDATED: Get unread messages count for a specific user (patient)
     * Now filters by sender_type to only count messages from therapist
     */
    public function getUnreadCountForUser($userId): int
    {
        return $this->messages()
            ->where('sender_type', 'therapist')  // UPDATED: Only count therapist messages
            ->where('is_read', false)
            ->count();
    }

    /**
     * ADDED: Get unread messages count for therapist
     * Counts only messages from patient that therapist hasn't read
     */
    public function getUnreadCountForTherapist($therapistId): int
    {
        return $this->messages()
            ->where('sender_type', 'patient')  // ADDED: Only count patient messages
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

        return false;
    }

    /**
     * ADDED: Check if therapist has access to this chat room
     */
    public function hasTherapistAccess($therapistId): bool
    {
        // Check if this therapist is the therapist in this chat room
        if ($this->therapist_id == $therapistId) {
            return true;
        }

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