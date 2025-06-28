<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationInvestment extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'total_invested',
        'investment_limit',
        'total_investors',
        'is_open_for_investment',
    ];

    protected $casts = [
        'total_invested' => 'decimal:2',
        'investment_limit' => 'decimal:2',
        'is_open_for_investment' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get remaining investment amount
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->investment_limit - $this->total_invested;
    }

    /**
     * Get investment progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->investment_limit == 0) return 0;
        return ($this->total_invested / $this->investment_limit) * 100;
    }

    /**
     * Check if location can accept new investments
     */
    public function canAcceptInvestment(float $amount): bool
    {
        return $this->is_open_for_investment && 
               ($this->total_invested + $amount) <= $this->investment_limit;
    }

    /**
     * Add investment amount
     */
    public function addInvestment(float $amount): void
    {
        $this->increment('total_invested', $amount);
        $this->increment('total_investors');
    }
}