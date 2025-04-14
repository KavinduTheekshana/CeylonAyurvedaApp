<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service_id',
        'date',
        'time',
        'name',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'postcode',
        'notes',
        'price',
        'reference',
        'status'
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'datetime:H:i:s', // Store as HH:MM:SS
        'price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
