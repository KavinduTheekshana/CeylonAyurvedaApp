<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TherapistSub extends Model
{
    use HasFactory;

    protected $fillable = [
        'therapist_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'stripe_price_id',
        'status',
        'amount',
        'currency',
        'interval',
        'current_period_start',
        'current_period_end',
        'trial_start',
        'trial_end',
        'canceled_at',
        'ends_at',
        'stripe_metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'current_period_start' => 'timestamp',
        'current_period_end' => 'timestamp',
        'trial_start' => 'timestamp',
        'trial_end' => 'timestamp',
        'canceled_at' => 'timestamp',
        'ends_at' => 'timestamp',
        'stripe_metadata' => 'array',
    ];

    // Relationships
    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }

    public function payments()
    {
        return $this->hasMany(TherapistSubscriptionPayment::class);
    }

    // Helper methods
    public function isActive()
    {
        return $this->status === 'active' &&
            ($this->current_period_end === null || $this->current_period_end->isFuture());
    }

    public function isOnTrial()
    {
        return $this->status === 'trialing' &&
            $this->trial_end &&
            $this->trial_end->isFuture();
    }

    public function isPastDue()
    {
        return $this->status === 'past_due';
    }

    public function daysUntilExpiry()
    {
        if (!$this->current_period_end)
            return null;

        return Carbon::now()->diffInDays($this->current_period_end, false);
    }

    public function getStatusDisplayAttribute()
    {
        switch ($this->status) {
            case 'active':
                return 'Active';
            case 'trialing':
                return 'Trial';
            case 'past_due':
                return 'Payment Due';
            case 'canceled':
                return 'Cancelled';
            case 'unpaid':
                return 'Unpaid';
            case 'incomplete':
                return 'Incomplete';
            default:
                return ucfirst($this->status);
        }
    }
}
