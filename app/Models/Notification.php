<?php
// app/Models/Notification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',
        'image_url',
        'is_active',
        'sent_at',
        'total_sent',
        'created_by'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'is_active' => 'boolean',
        'total_sent' => 'integer'
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }

    public function scopePromotional($query)
    {
        return $query->where('type', 'promotional');
    }

    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    public function getIsSentAttribute(): bool
    {
        return !is_null($this->sent_at);
    }
}