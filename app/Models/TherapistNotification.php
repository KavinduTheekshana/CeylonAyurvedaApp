<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'therapist_id',
        'booking_id',
        'notification_type',
        'title',
        'message',
        'sent_at',
        'delivery_status',
        'fcm_message_id',
        'error_message'
    ];

    protected $casts = [
        'sent_at' => 'datetime'
    ];

    /**
     * Get the therapist that owns the notification
     */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    /**
     * Get the booking associated with the notification
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Scope to get only sent notifications
     */
    public function scopeSent($query)
    {
        return $query->where('delivery_status', 'sent');
    }

    /**
     * Scope to get only failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('delivery_status', 'failed');
    }

    /**
     * Scope to get recent notifications
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('sent_at', 'desc');
    }
}