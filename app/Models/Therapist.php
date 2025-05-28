<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Therapist extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'image',
        'bio',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    /**
     * Scope for active therapists
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}