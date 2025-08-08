<?php

namespace App\Models;

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
        'password',
        'phone',
        'image',
        'bio',
        'work_start_date',
        'status',
        'email_verified_at',
        'profile_photo_path',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'status' => 'boolean',
        'work_start_date' => 'date',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'password' => 'hashed',
    ];

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