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
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'verification_code',
        'remember_token',
        'current_team_id',
        'profile_photo_path',
        
        // Add these new preference fields
        'preferred_therapist_gender',
        'preferred_language',
        'preferred_age_range_therapist_start',
        'preferred_age_range_therapist_end',
        'preferences_updated_at',
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
            'preferences_updated_at' => 'datetime',
            'preferred_age_range_therapist_start' => 'integer',
            'preferred_age_range_therapist_end' => 'integer',
        ];
    }

    public function investments()
    {
        return $this->hasMany(Investment::class);
    }

    public function getTotalInvestedAttribute()
    {
        return $this->investments()->where('status', 'completed')->sum('amount');
    }

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
     * Update user preferences
     */
    public function updatePreferences(array $preferences)
    {
        $preferences['preferences_updated_at'] = now();
        $this->update($preferences);
        return $this;
    }

    /**
 * Get chat rooms where user is a patient
 */
public function chatRooms()
{
    return $this->hasMany(ChatRoom::class, 'patient_id');
}

/**
 * Get all messages sent by this user
 */
public function sentMessages()
{
    return $this->hasMany(ChatMessage::class, 'sender_id');
}
}
