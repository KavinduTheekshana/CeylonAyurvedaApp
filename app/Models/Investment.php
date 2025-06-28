<?php

// app/Models/Investment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'location_id',
        'amount',
        'currency',
        'status',
        'stripe_payment_intent_id',
        'stripe_payment_method_id',
        'reference',
        'notes',
        'invested_at',
        'stripe_metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'invested_at' => 'datetime',
        'stripe_metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestmentTransaction::class);
    }

    /**
     * Generate unique investment reference
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'INV-' . strtoupper(uniqid());
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Check if investment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark investment as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'invested_at' => now(),
        ]);
    }
    
}
