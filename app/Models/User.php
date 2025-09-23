<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'verification_code',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get all bookings for this user
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get all addresses for this user
     */
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get all investments for this user
     */
    public function investments()
    {
        return $this->hasMany(Investment::class);
    }

    /**
     * Get the total amount invested by this user
     */
    public function getTotalInvestedAttribute()
    {
        return $this->investments()->where('status', 'completed')->sum('amount');
    }

    /**
     * Get all coupon usages for this user
     */
    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Check if user has used a specific coupon
     */
    public function hasUsedCoupon($couponId): bool
    {
        return $this->couponUsages()->where('coupon_id', $couponId)->exists();
    }

    /**
     * Get count of how many times user has used a specific coupon
     */
    public function getCouponUsageCount($couponId): int
    {
        return $this->couponUsages()->where('coupon_id', $couponId)->count();
    }

    /**
     * Get treatment histories for this user through their bookings
     * This creates a hasManyThrough relationship
     */
    public function treatmentHistories()
    {
        return $this->hasManyThrough(
            TreatmentHistory::class,
            Booking::class,
            'user_id', // Foreign key on bookings table
            'booking_id', // Foreign key on treatment_histories table
            'id', // Local key on users table
            'id' // Local key on bookings table
        );
    }

    /**
     * Get the latest treatment history for this user
     */
    public function latestTreatmentHistory()
    {
        return $this->treatmentHistories()
            ->latest('treatment_completed_at')
            ->first();
    }

    /**
     * Get treatment histories count for this user
     */
    public function getTreatmentHistoriesCountAttribute(): int
    {
        return $this->treatmentHistories()->count();
    }

    /**
     * Get unique therapists that have treated this user
     */
    public function treatedByTherapists()
    {
        return Therapist::whereHas('treatmentHistories', function ($query) {
            $query->whereHas('booking', function ($subQuery) {
                $subQuery->where('user_id', $this->id);
            });
        })->get();
    }

    /**
     * Get unique services this user has received treatment for
     */
    public function receivedServices()
    {
        return Service::whereHas('treatmentHistories', function ($query) {
            $query->whereHas('booking', function ($subQuery) {
                $subQuery->where('user_id', $this->id);
            });
        })->get();
    }

    /**
     * Get treatment history statistics for this user
     */
    public function getTreatmentStatsAttribute(): array
    {
        $histories = $this->treatmentHistories;
        
        return [
            'total_treatments' => $histories->count(),
            'improved_conditions' => $histories->where('patient_condition', 'improved')->count(),
            'same_conditions' => $histories->where('patient_condition', 'same')->count(),
            'worse_conditions' => $histories->where('patient_condition', 'worse')->count(),
            'unique_therapists' => $histories->pluck('therapist_id')->unique()->count(),
            'unique_services' => $histories->pluck('service_id')->unique()->count(),
            'average_pain_reduction' => $this->calculateAveragePainReduction($histories),
            'first_treatment' => $histories->min('treatment_completed_at'),
            'latest_treatment' => $histories->max('treatment_completed_at'),
        ];
    }

    /**
     * Calculate average pain reduction across all treatments
     */
    private function calculateAveragePainReduction($histories): ?float
    {
        $treatmentsWithPainData = $histories->filter(function ($history) {
            return $history->pain_level_before !== null && $history->pain_level_after !== null;
        });

        if ($treatmentsWithPainData->isEmpty()) {
            return null;
        }

        $totalReduction = $treatmentsWithPainData->sum(function ($history) {
            return $history->pain_level_before - $history->pain_level_after;
        });

        return round($totalReduction / $treatmentsWithPainData->count(), 1);
    }

    /**
     * Get most frequently treated areas for this user
     */
    public function getMostTreatedAreasAttribute(): array
    {
        $allAreas = $this->treatmentHistories
            ->pluck('areas_treated')
            ->flatten()
            ->filter()
            ->countBy()
            ->sortDesc();

        return $allAreas->take(5)->toArray();
    }

    /**
     * Check if user has any treatment history
     */
    public function hasTreatmentHistory(): bool
    {
        return $this->treatmentHistories()->exists();
    }

    /**
     * Get recent treatment history (last 6 months)
     */
    public function recentTreatmentHistory()
    {
        return $this->treatmentHistories()
            ->where('treatment_completed_at', '>=', now()->subMonths(6))
            ->orderBy('treatment_completed_at', 'desc');
    }
}