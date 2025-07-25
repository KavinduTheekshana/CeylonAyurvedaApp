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
        'paid_at'
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i:s', // Store as HH:MM:SS
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
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
}
