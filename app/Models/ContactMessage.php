<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'message',
        'name',
        'email',
        'branch_id',
        'branch_name',
        'is_guest',
        'user_id',
        'status',
        'admin_response',
        'responded_at',
        'responded_by',
        'metadata',
    ];

    protected $casts = [
        'is_guest' => 'boolean',
        'metadata' => 'array',
        'responded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'branch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    // Accessors
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'in_progress' => 'info',
            'resolved' => 'success',
            'closed' => 'gray',
            default => 'gray',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending' => '🟡 Pending',
            'in_progress' => '🔵 In Progress',
            'resolved' => '🟢 Resolved',
            'closed' => '⚫ Closed',
            default => '⚪ Unknown',
        };
    }

    public function getUserTypeAttribute(): string
    {
        return $this->is_guest ? 'Guest' : 'Registered User';
    }
}