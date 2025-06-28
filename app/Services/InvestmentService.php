<?php

// app/Services/InvestmentService.php
namespace App\Services;

use App\Models\Investment;
use App\Models\Location;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class InvestmentService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a new investment
     */
    public function createInvestment(User $user, Location $location, float $amount, array $data = []): Investment
    {
        // Validate investment amount
        if ($amount < 10 || $amount > 10000) {
            throw new \InvalidArgumentException('Investment amount must be between £10 and £10,000');
        }

        // Check if location accepts investments
        if (!$location->locationInvestment || !$location->locationInvestment->canAcceptInvestment($amount)) {
            throw new \InvalidArgumentException('This location cannot accept the requested investment amount');
        }

        return DB::transaction(function () use ($user, $location, $amount, $data) {
            $investment = Investment::create([
                'user_id' => $user->id,
                'location_id' => $location->id,
                'amount' => $amount,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'invested_at' => now(),
            ]);

            return $investment;
        });
    }

    /**
     * Create Stripe payment intent
     */
    public function createPaymentIntent(Investment $investment): array
    {
        try {
            // Convert amount to pence (Stripe requires amount in smallest currency unit)
            $amountInPence = (int) ($investment->amount * 100);

            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $amountInPence,
                'currency' => 'gbp', // Set currency to GBP (British Pounds)
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'metadata' => [
                    'investment_id' => $investment->id,
                    'user_id' => $investment->user_id,
                    'location_id' => $investment->location_id,
                    'amount_gbp' => $investment->amount,
                ],
                'description' => "Investment in {$investment->location->name} - £{$investment->amount}",
            ]);

            // Store payment intent ID in investment
            $investment->update([
                'stripe_payment_intent_id' => $paymentIntent->id
            ]);

            return [
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'amount' => $investment->amount,
                'currency' => 'gbp',
            ];

        } catch (ApiErrorException $e) {
            throw new \Exception('Payment processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle successful payment
     */
    public function handleSuccessfulPayment(string $paymentIntentId): Investment
    {
        try {
            // Retrieve payment intent from Stripe
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                throw new \Exception('Payment has not been completed');
            }

            // Find investment by payment intent ID
            $investment = Investment::where('stripe_payment_intent_id', $paymentIntentId)->first();

            if (!$investment) {
                throw new \Exception('Investment not found for this payment');
            }

            if ($investment->status === 'completed') {
                return $investment; // Already processed
            }

            return DB::transaction(function () use ($investment, $paymentIntent) {
                // Update investment status
                $investment->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                // Create transaction record
                Transaction::create([
                    'investment_id' => $investment->id,
                    'user_id' => $investment->user_id,
                    'type' => 'investment',
                    'amount' => $investment->amount,
                    'currency' => 'gbp',
                    'status' => 'completed',
                    'stripe_payment_intent_id' => $paymentIntent->id,
                    'stripe_charge_id' => $paymentIntent->latest_charge ?? null,
                    'processed_at' => now(),
                ]);

                // Update location investment totals
                $this->updateLocationInvestmentTotals($investment->location);

                return $investment;
            });

        } catch (ApiErrorException $e) {
            throw new \Exception('Stripe error: ' . $e->getMessage());
        }
    }

    /**
     * Get location investment statistics
     */
    public function getLocationInvestmentStats(Location $location): array
    {
        $locationInvestment = $location->locationInvestment;
        
        if (!$locationInvestment) {
            return [
                'total_invested' => 0,
                'investment_limit' => 0,
                'remaining_amount' => 0,
                'progress_percentage' => 0,
                'total_investors' => 0,
                'is_open_for_investment' => false,
            ];
        }

        $totalInvested = $location->investments()
            ->where('status', 'completed')
            ->sum('amount');

        $totalInvestors = $location->investments()
            ->where('status', 'completed')
            ->distinct('user_id')
            ->count();

        $remainingAmount = max(0, $locationInvestment->investment_limit - $totalInvested);
        $progressPercentage = $locationInvestment->investment_limit > 0 
            ? ($totalInvested / $locationInvestment->investment_limit) * 100 
            : 0;

        return [
            'total_invested' => $totalInvested,
            'investment_limit' => $locationInvestment->investment_limit,
            'remaining_amount' => $remainingAmount,
            'progress_percentage' => round($progressPercentage, 2),
            'total_investors' => $totalInvestors,
            'is_open_for_investment' => $locationInvestment->is_open && $remainingAmount > 0,
        ];
    }

    /**
     * Update location investment totals
     */
    private function updateLocationInvestmentTotals(Location $location): void
    {
        $totalInvested = $location->investments()
            ->where('status', 'completed')
            ->sum('amount');

        $totalInvestors = $location->investments()
            ->where('status', 'completed')
            ->distinct('user_id')
            ->count();

        $location->locationInvestment->update([
            'total_invested' => $totalInvested,
            'total_investors' => $totalInvestors,
        ]);
    }

    /**
     * Process webhook from Stripe
     */
    public function processStripeWebhook(array $eventData): void
    {
        $eventType = $eventData['type'];
        
        switch ($eventType) {
            case 'payment_intent.succeeded':
                $paymentIntent = $eventData['data']['object'];
                $this->handleSuccessfulPayment($paymentIntent['id']);
                break;
                
            case 'payment_intent.payment_failed':
                $paymentIntent = $eventData['data']['object'];
                $this->handleFailedPayment($paymentIntent['id']);
                break;
        }
    }

    /**
     * Handle failed payment
     */
    private function handleFailedPayment(string $paymentIntentId): void
    {
        $investment = Investment::where('stripe_payment_intent_id', $paymentIntentId)->first();
        
        if ($investment && $investment->status === 'pending') {
            $investment->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);
        }
    }
}