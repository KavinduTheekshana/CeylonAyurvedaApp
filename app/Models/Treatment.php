<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'image', 'status', 'description', 'offers'];

    /**
     * Get the services for the treatment
     */
    public function services()
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Get the locations that have this treatment through services
     */
    public function locations()
    {
        return $this->hasManyThrough(
            Location::class,
            Service::class,
            'treatment_id',
            'id',
            'id',
            'location_id'
        )->distinct();
    }
}
