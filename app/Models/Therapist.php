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
    // public function services(): BelongsToMany
    // {
    //     return $this->belongsToMany(Service::class)->withTimestamps();
    // }

    /**
     * Get the bookings for this therapist.
     */
   

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function services()
    {
        return $this->belongsToMany(
            Service::class,
            'service_therapist', // Specify the correct table name
            'therapist_id',      // Foreign key for therapist
            'service_id'         // Foreign key for service
        )->withTimestamps();
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