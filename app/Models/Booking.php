<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_id',
        'location_id', 
        'date',
        'time',
        'name',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'postcode',
        'therapist_id',
        'notes',
        'price',
        'original_price',
        'discount_amount',
        'coupon_id',
        'coupon_code',
        'reference',
        'status',
        'stripe_payment_intent_id',
        'payment_status',
        'payment_method',
        'paid_at',
        'visit_type',      
        'home_visit_fee' 
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i:s', // Store as HH:MM:SS
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'home_visit_fee' => 'decimal:2',  
        'visit_type' => 'string' 
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function canBeCancelled(): bool
    {
        $cancellableStatuses = ['confirmed', 'pending'];
        return in_array($this->status, $cancellableStatuses);
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->date->format('l, F j, Y');
    }

    public function getFormattedTimeAttribute(): string
    {
        return date('g:i A', strtotime($this->time));
    }

    public function getCanCancelAttribute(): bool
    {
        return $this->canBeCancelled();
    }

    /**
     * Get the therapist of the booking.
     */
    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponUsage()
    {
        return $this->hasOne(CouponUsage::class);
    }

    /**
     * Get the actual price after discount
     */
    public function getFinalPriceAttribute()
    {
        return $this->price;
    }

    /**
     * Get the discount percentage if applicable
     */
    public function getDiscountPercentageAttribute()
    {
        if (!$this->original_price || $this->original_price == 0) {
            return 0;
        }

        return round((($this->original_price - $this->price) / $this->original_price) * 100, 2);
    }
    // In app/Models/Booking.php - Add this method  
    public function treatmentHistory()
    {
        return $this->hasOne(TreatmentHistory::class);
    }
    // Optional: Add this helper method to Booking model to check if treatment history exists
    public function hasTreatmentHistory()
    {
        return $this->treatmentHistory()->exists();
    }

    // Optional: Add this helper method to Booking model to get treatment history if exists
    public function getTreatmentHistoryAttribute()
    {
        return $this->treatmentHistory;
    }

    /**
     * Check if this is a home visit booking
     */
    public function isHomeVisit(): bool
    {
        return $this->visit_type === 'home';
    }

    /**
     * Check if this is a branch visit booking
     */
    public function isBranchVisit(): bool
    {
        return $this->visit_type === 'branch';
    }

    /**
     * Get formatted visit type for display
     */
    public function getFormattedVisitTypeAttribute(): string
    {
        return $this->visit_type === 'home' ? 'Home Visit' : 'Branch Visit';
    }

    /**
     * Check if booking has home visit fee
     */
    public function hasHomeVisitFee(): bool
    {
        return $this->home_visit_fee !== null && $this->home_visit_fee > 0;
    }

    /**
     * Get formatted home visit fee
     */
    public function getFormattedHomeVisitFeeAttribute(): string
    {
        if (!$this->hasHomeVisitFee()) {
            return '£0.00';
        }
        return '£' . number_format($this->home_visit_fee, 2);
    }

    /**
     * Get total price including home visit fee
     * (This should already match the 'price' field, but useful for clarity)
     */
    public function getTotalPriceAttribute(): float
    {
        return (float) $this->price;
    }

    /**
     * Get price breakdown as array
     */
    public function getPriceBreakdown(): array
    {
        return [
            'original_price' => (float) $this->original_price,
            'discount_amount' => (float) $this->discount_amount,
            'service_price' => (float) ($this->original_price - $this->discount_amount),
            'home_visit_fee' => (float) ($this->home_visit_fee ?? 0),
            'total_price' => (float) $this->price,
        ];
    }
}
