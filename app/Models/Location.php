<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'address',
        'city',
        'postcode',
        'latitude',
        'longitude',
        'phone',
        'email',
        'description',
        'operating_hours',
        'image',
        'status',
        'service_radius_miles'
    ];

    protected $casts = [
        'status' => 'boolean',
        'operating_hours' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function therapists()
    {
        return $this->belongsToMany(Therapist::class)->withTimestamps();
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function getImageUrlAttribute()
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function investments()
    {
        return $this->hasMany(Investment::class);
    }

    public function locationInvestment()
    {
        return $this->hasOne(LocationInvestment::class);
    }

    public function getTotalInvestedAttribute()
    {
        return $this->investments()->where('status', 'completed')->sum('amount');
    }
}
