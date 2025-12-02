<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TherapistAddress extends Model
{
    protected $fillable = [
        'therapist_id',
        'address_line1',
        'address_line2',
        'city',
        'postcode',
        'country',
        'latitude',
        'longitude',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Get the therapist that owns the address.
     */
    public function therapist(): BelongsTo
    {
        return $this->belongsTo(Therapist::class);
    }

    /**
     * Get the full address as a string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->postcode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }
}
