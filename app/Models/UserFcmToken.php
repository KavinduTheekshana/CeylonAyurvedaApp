<?php
// app/Models/UserFcmToken.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFcmToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAndroid($query)
    {
        return $query->where('device_type', 'android');
    }

    public function scopeIos($query)
    {
        return $query->where('device_type', 'ios');
    }

    public function markAsUsed()
    {
        $this->update(['last_used_at' => now()]);
    }
}