<?php
// app/Models/Coupon.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'minimum_amount',
        'usage_limit',
        'usage_count',
        'usage_limit_per_user',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    /**
     * Get the services that this coupon applies to
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'coupon_service')->withTimestamps();
    }

    /**
     * Get the usage records for this coupon
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Get the bookings that used this coupon
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Check if the coupon is valid
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if the coupon is valid for a specific user
     */
    public function isValidForUser($userId): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->usage_limit_per_user) {
            $userUsageCount = $this->usages()
                ->where('user_id', $userId)
                ->count();

            if ($userUsageCount >= $this->usage_limit_per_user) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the coupon is valid for a specific service
     */
    public function isValidForService($serviceId): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        // If no services are specified, coupon is valid for all services
        if ($this->services()->count() === 0) {
            return true;
        }

        return $this->services()->where('service_id', $serviceId)->exists();
    }

    /**
     * Calculate discount amount for a given price
     */
    public function calculateDiscount($price): float
    {
        if ($this->type === 'percentage') {
            return round($price * ($this->value / 100), 2);
        }

        // For fixed amount, ensure we don't give more discount than the price
        return min($this->value, $price);
    }

    /**
     * Apply the coupon and increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Get validation message for invalid coupon
     */
    public function getValidationMessage(): string
    {
        if (!$this->is_active) {
            return 'This coupon is no longer active.';
        }

        $now = Carbon::now();

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return 'This coupon is not yet valid.';
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return 'This coupon has expired.';
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return 'This coupon has reached its usage limit.';
        }

        return 'Invalid coupon.';
    }

    /**
     * Scope for active coupons
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            });
    }

    /**
     * Generate a unique coupon code
     */
    public static function generateUniqueCode($prefix = 'COUP', $length = 8): string
    {
        do {
            $code = $prefix . strtoupper(substr(md5(uniqid()), 0, $length));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}