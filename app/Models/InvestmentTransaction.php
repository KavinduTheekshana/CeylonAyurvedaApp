<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'investment_id',
        'type',
        'amount',
        'stripe_transaction_id',
        'status',
        'stripe_response',
        'failure_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'stripe_response' => 'array',
    ];

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}

// Add these relationships to existing models
