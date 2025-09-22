<?php
// app/Models/TreatmentHistory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TreatmentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'therapist_id',
        'service_id',
        'patient_name',
        'treatment_notes',
        'observations',
        'recommendations',
        'patient_condition',
        'pain_level_before',
        'pain_level_after',
        'areas_treated',
        'next_treatment_plan',
        'treatment_completed_at',
        'edit_deadline_at',
        'is_editable'
    ];

    protected $casts = [
        'areas_treated' => 'array',
        'treatment_completed_at' => 'datetime',
        'edit_deadline_at' => 'datetime',
        'is_editable' => 'boolean',
        'pain_level_before' => 'integer',
        'pain_level_after' => 'integer'
    ];

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function therapist()
    {
        return $this->belongsTo(Therapist::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    // Check if record is still editable (within 24 hours)
    public function getIsEditableAttribute()
    {
        if (!$this->attributes['is_editable']) {
            return false;
        }
        
        return Carbon::now()->isBefore($this->edit_deadline_at);
    }

    // Calculate hours remaining for editing
    public function getHoursRemainingForEditAttribute()
    {
        if (!$this->is_editable) {
            return 0;
        }

        $hoursRemaining = Carbon::now()->diffInHours($this->edit_deadline_at, false);
        return max(0, $hoursRemaining);
    }

    // Scope for editable records
    public function scopeEditable($query)
    {
        return $query->where('is_editable', true)
                    ->where('edit_deadline_at', '>', Carbon::now());
    }

    // Scope for therapist's records
    public function scopeForTherapist($query, $therapistId)
    {
        return $query->where('therapist_id', $therapistId);
    }

    // Boot method to set edit deadline automatically
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->edit_deadline_at) {
                $model->edit_deadline_at = Carbon::parse($model->treatment_completed_at)->addHours(24);
            }
        });
    }
}