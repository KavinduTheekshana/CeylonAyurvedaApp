<?php

namespace App\Models;

use Hash;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Carbon\Carbon;

class Therapist extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'image',
        'bio',
        'work_start_date',
        'status',
        'online_status',
        'otp_code',
        'otp_expires_at',
        'is_verified',
        'password',
        'email_verified_at',
        'profile_photo_path',
        'last_login_at',

        // Service User Preferences
        'preferred_gender',
        'age_range_start',
        'age_range_end',
        'preferred_language',

        // Service Delivery Preferences
        'accept_new_patients',
        'home_visits_only',
        'clinic_visits_only',
        'max_travel_distance',
        'weekends_available',
        'evenings_available',
        'preferences_updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'preferences_updated_at' => 'datetime',
            'work_start_date' => 'date',
            'status' => 'boolean',
            'online_status' => 'boolean',
            'accept_new_patients' => 'boolean',
            'home_visits_only' => 'boolean',
            'clinic_visits_only' => 'boolean',
            'weekends_available' => 'boolean',
            'evenings_available' => 'boolean',
            'age_range_start' => 'integer',
            'age_range_end' => 'integer',
            'max_travel_distance' => 'integer',
            'is_verified' => 'boolean',
            'otp_expires_at' => 'datetime',
        ];
    }

    // Mutator for password hashing
    // public function setPasswordAttribute($value)
    // {
    //     $this->attributes['password'] = Hash::make($value);
    // }

    // Generate OTP
    public function generateOtp()
    {
        $this->otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->otp_expires_at = Carbon::now()->addMinutes(10); // OTP expires in 10 minutes
        $this->save();
        
        return $this->otp_code;
    }

    // Verify OTP
    public function verifyOtp($otp)
    {
        if ($this->otp_code === $otp && $this->otp_expires_at > Carbon::now()) {
            $this->is_verified = true;
            $this->email_verified_at = Carbon::now();
            $this->otp_code = null;
            $this->otp_expires_at = null;
            $this->save();
            
            return true;
        }
        
        return false;
    }

    // Check if OTP is expired
    public function isOtpExpired()
    {
        return $this->otp_expires_at && $this->otp_expires_at < Carbon::now();
    }
    
    /**
     * Determine if the user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'therapist') {
            return $this->status === true; // Only active therapists can login
        }

        return false;
    }

    /**
     * Get the services that this therapist can perform.
     */
    public function services()
    {
        return $this->belongsToMany(
            Service::class,
            'service_therapist',
            'therapist_id',
            'service_id'
        )->withTimestamps();
    }

    /**
     * Get the bookings for this therapist.
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get the availability schedules for this therapist.
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(TherapistAvailability::class);
    }

    /**
     * Get active availability schedules for this therapist.
     */
    public function activeAvailabilities(): HasMany
    {
        return $this->hasMany(TherapistAvailability::class)->where('is_active', true);
    }

    /**
     * Get availability for a specific day
     */
    public function getAvailabilityForDay($day): \Illuminate\Database\Eloquent\Collection
    {
        return $this->activeAvailabilities()
            ->where('day_of_week', strtolower($day))
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Check if therapist is available on a specific day and time
     */
    public function isAvailableAt($dayOfWeek, $time): bool
    {
        return $this->activeAvailabilities()
            ->where('day_of_week', strtolower($dayOfWeek))
            ->where('start_time', '<=', $time)
            ->where('end_time', '>', $time)
            ->exists();
    }

    public function getAvailabilityCountAttribute()
    {
        return $this->availabilities()->where('is_active', true)->count();
    }

    /**
     * Scope to filter by preferences
     */
    public function scopeWithPreferences($query)
    {
        return $query->select([
            '*',
            'preferred_gender',
            'age_range_start',
            'age_range_end',
            'preferred_language',
            'accept_new_patients',
            'home_visits_only',
            'clinic_visits_only',
            'max_travel_distance',
            'weekends_available',
            'evenings_available',
            'preferences_updated_at'
        ]);
    }

    public function updatePreferences(array $preferences)
    {
        $validPreferences = [
            'preferred_gender',
            'age_range_start',
            'age_range_end',
            'preferred_language',
            'accept_new_patients',
            'home_visits_only',
            'clinic_visits_only',
            'max_travel_distance',
            'weekends_available',
            'evenings_available',
        ];

        $filteredPreferences = array_intersect_key(
            $preferences,
            array_flip($validPreferences)
        );

        $filteredPreferences['preferences_updated_at'] = now();

        return $this->update($filteredPreferences);
    }


    public function getFormattedPreferencesAttribute()
    {
        return [
            'service_user_preferences' => [
                'preferred_gender' => $this->preferred_gender,
                'age_range' => [
                    'start' => $this->age_range_start,
                    'end' => $this->age_range_end
                ],
                'preferred_language' => $this->preferred_language,
            ],
            'service_delivery' => [
                'accept_new_patients' => $this->accept_new_patients,
                'home_visits_only' => $this->home_visits_only,
                'clinic_visits_only' => $this->clinic_visits_only,
                'max_travel_distance' => $this->max_travel_distance,
                'weekends_available' => $this->weekends_available,
                'evenings_available' => $this->evenings_available,
            ],
            'last_updated' => $this->preferences_updated_at?->toISOString(),
        ];
    }


    /**
     * Get all available days for this therapist
     */
    public function getAvailableDays(): array
    {
        return $this->activeAvailabilities()
            ->distinct('day_of_week')
            ->pluck('day_of_week')
            ->map(function ($day) {
                return ucfirst($day);
            })
            ->toArray();
    }

    /**
     * Calculate years of experience based on work start date
     */
    public function getYearsOfExperienceAttribute(): ?int
    {
        if (!$this->work_start_date) {
            return null;
        }

        return Carbon::parse($this->work_start_date)->diffInYears(Carbon::now());
    }

    /**
     * Get formatted work start date
     */
    public function getFormattedWorkStartDateAttribute(): ?string
    {
        if (!$this->work_start_date) {
            return null;
        }

        return Carbon::parse($this->work_start_date)->format('M Y');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class)->withTimestamps();
    }

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    /**
     * Get profile photo URL
     */
    public function getProfilePhotoUrlAttribute()
    {
        if ($this->profile_photo_path) {
            return asset('storage/' . $this->profile_photo_path);
        }

        if ($this->image) {
            return asset('storage/' . $this->image);
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }

    /**
     * Scope for active therapists
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get today's bookings
     */
    public function todaysBookings()
    {
        return $this->bookings()
            ->whereDate('date', today())
            ->whereIn('status', ['confirmed', 'pending'])
            ->orderBy('time');
    }

    /**
     * Get upcoming bookings
     */
    public function upcomingBookings()
    {
        return $this->bookings()
            ->where('date', '>=', today())
            ->whereIn('status', ['confirmed', 'pending'])
            ->orderBy('date')
            ->orderBy('time');
    }


    // Add this relationship method to your existing app/Models/Therapist.php file

    /**
     * Get the holiday requests for this therapist.
     */
    public function holidayRequests(): HasMany
    {
        return $this->hasMany(TherapistHolidayRequest::class);
    }

    /**
     * Get pending holiday requests
     */
    public function pendingHolidayRequests(): HasMany
    {
        return $this->hasMany(TherapistHolidayRequest::class)->where('status', 'pending');
    }

    /**
     * Get approved holidays
     */
    public function approvedHolidays(): HasMany
    {
        return $this->hasMany(TherapistHolidayRequest::class)->where('status', 'approved');
    }

    /**
     * Check if therapist has holiday on specific date
     */
    public function hasHolidayOn($date): bool
    {
        return $this->holidayRequests()
            ->where('date', $date)
            ->where('status', 'approved')
            ->exists();
    }
}