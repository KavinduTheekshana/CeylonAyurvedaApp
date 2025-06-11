<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'treatment_id',
        'title',
        'subtitle',
        'price',
        'discount_price',
        'duration',
        'benefits',
        'description',
        'status',
        'image',
    ];

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    // public function therapists(): BelongsToMany
    // {
    //     return $this->belongsToMany(Therapist::class, 'therapist_service');
    // }
    public function therapists()
    {
        return $this->belongsToMany(
            Therapist::class,
            'service_therapist', // Specify the correct table name
            'service_id',        // Foreign key for service
            'therapist_id'       // Foreign key for therapist
        )->withTimestamps();
    }
}
