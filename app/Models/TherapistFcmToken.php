<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistFcmToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'therapist_id',
        'fcm_token',
        'device_type',
        'device_id',
        'is_active',
        'last_used_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime'
    ];

    /**
     * Get the therapist that owns the token
     */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    /**
     * Scope to get only active tokens
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only Android tokens
     */
    public function scopeAndroid($query)
    {
        return $query->where('device_type', 'android');
    }

    /**
     * Scope to get only iOS tokens
     */
    public function scopeIos($query)
    {
        return $query->where('device_type', 'ios');
    }

    /**
     * Mark token as recently used
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}