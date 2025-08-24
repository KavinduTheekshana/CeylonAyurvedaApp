<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TherapistSubPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'therapist_subscription_id',
        'stripe_payment_intent_id',
        'stripe_invoice_id',
        'amount',
        'currency',
        'status',
        'failure_reason',
        'paid_at',
        'failed_at',
        'stripe_payment_data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'timestamp',
        'failed_at' => 'timestamp',
        'stripe_payment_data' => 'array',
    ];

    // Relationships
    public function subscription()
    {
        return $this->belongsTo(TherapistSubscription::class, 'therapist_subscription_id');
    }

    // Helper methods
    public function isSuccessful()
    {
        return $this->status === 'succeeded';
    }

    public function isFailed()
    {
        return in_array($this->status, ['failed', 'canceled']);
    }
}
