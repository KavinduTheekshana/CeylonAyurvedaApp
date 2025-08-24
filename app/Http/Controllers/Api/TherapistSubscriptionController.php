<?php
// app/Http/Controllers/Api/TherapistSubscriptionController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TherapistSubscription;
use App\Models\TherapistSubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Carbon\Carbon;

class TherapistSubscriptionController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Get therapist's current subscription status
     */
    public function getSubscriptionStatus(Request $request)
    {
        try {
            $therapist = $request->user();
            $subscription = $therapist->subscription;

            if (!$subscription) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'has_subscription' => false,
                        'subscription_status' => 'none',
                        'can_accept_bookings' => false,
                        'message' => 'No active subscription. Subscribe to start accepting bookings.',
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'has_subscription' => true,
                    'subscription_status' => $subscription->status,
                    'subscription_display' => $subscription->status_display,
                    'can_accept_bookings' => $therapist->canAcceptBookings(),
                    'amount' => $subscription->amount,
                    'currency' => $subscription->currency,
                    'current_period_start' => $subscription->current_period_start,
                    'current_period_end' => $subscription->current_period_end,
                    'days_until_expiry' => $subscription->daysUntilExpiry(),
                    'is_active' => $subscription->isActive(),
                    'is_trial' => $subscription->isOnTrial(),
                    'is_past_due' => $subscription->isPastDue(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting subscription status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get subscription status'
            ], 500);
        }
    }

    /**
     * Create setup intent for payment method
     */
    public function createSetupIntent(Request $request)
    {
     
        try {
            $therapist = $request->user();

            // Create or get Stripe customer
            $stripeCustomer = $this->getOrCreateStripeCustomer($therapist);

            // Create setup intent
            $setupIntent = SetupIntent::create([
                'customer' => $stripeCustomer->id,
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
                'metadata' => [
                    'therapist_id' => $therapist->id,
                    'therapist_email' => $therapist->email,
                ],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'setup_intent_client_secret' => $setupIntent->client_secret,
                    'stripe_customer_id' => $stripeCustomer->id,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating setup intent: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment setup'
            ], 500);
        }
    }

    /**
     * Create subscription
     */
    public function createSubscription(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        try {
            $therapist = $request->user();
            $paymentMethodId = $request->payment_method_id;

            // Check if already has active subscription
            if ($therapist->hasActiveSubscription()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription'
                ], 400);
            }

            // Create or get Stripe customer
            $stripeCustomer = $this->getOrCreateStripeCustomer($therapist);

            // Attach payment method to customer
            $paymentMethod = PaymentMethod::retrieve($paymentMethodId);
            $paymentMethod->attach(['customer' => $stripeCustomer->id]);

            // Set as default payment method
            Customer::update($stripeCustomer->id, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            // Create subscription
            $subscription = Subscription::create([
                'customer' => $stripeCustomer->id,
                'items' => [
                    [
                        'price' => config('services.stripe.therapist_price_id'), // £199/month price ID
                    ],
                ],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'payment_method_types' => ['card'],
                    'save_default_payment_method' => 'on_subscription',
                ],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'therapist_id' => $therapist->id,
                    'therapist_email' => $therapist->email,
                ],
            ]);

            // Save subscription to database
            $therapistSubscription = TherapistSubscription::create([
                'therapist_id' => $therapist->id,
                'stripe_subscription_id' => $subscription->id,
                'stripe_customer_id' => $stripeCustomer->id,
                'stripe_price_id' => config('services.stripe.therapist_price_id'),
                'status' => $subscription->status,
                'amount' => 199.00,
                'currency' => 'GBP',
                'interval' => 'month',
                'current_period_start' => Carbon::createFromTimestamp($subscription->current_period_start),
                'current_period_end' => Carbon::createFromTimestamp($subscription->current_period_end),
                'stripe_metadata' => $subscription->metadata->toArray(),
            ]);

            // Update therapist subscription status
            $therapist->update([
                'subscription_status' => $subscription->status,
                'subscription_ends_at' => Carbon::createFromTimestamp($subscription->current_period_end),
                'can_accept_bookings' => $subscription->status === 'active',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription_id' => $subscription->id,
                    'status' => $subscription->status,
                    'client_secret' => $subscription->latest_invoice->payment_intent->client_secret ?? null,
                ],
                'message' => 'Subscription created successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating subscription: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Request $request)
    {
        try {
            $therapist = $request->user();
            $subscription = $therapist->subscription;

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found'
                ], 404);
            }

            // Cancel subscription in Stripe (at period end)
            $stripeSubscription = Subscription::update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => true,
            ]);

            // Update local subscription
            $subscription->update([
                'status' => $stripeSubscription->status,
                'canceled_at' => now(),
                'ends_at' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription will be cancelled at the end of the current period',
                'data' => [
                    'ends_at' => $subscription->ends_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error cancelling subscription: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription'
            ], 500);
        }
    }

    /**
     * Reactivate cancelled subscription
     */
    public function reactivateSubscription(Request $request)
    {
        try {
            $therapist = $request->user();
            $subscription = $therapist->subscription;

            if (!$subscription || $subscription->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'No cancellable subscription found'
                ], 404);
            }

            // Reactivate subscription in Stripe
            $stripeSubscription = Subscription::update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => false,
            ]);

            // Update local subscription
            $subscription->update([
                'status' => $stripeSubscription->status,
                'canceled_at' => null,
                'ends_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription reactivated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error reactivating subscription: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reactivate subscription'
            ], 500);
        }
    }

    /**
     * Get subscription payment history
     */
    public function getPaymentHistory(Request $request)
    {
        try {
            $therapist = $request->user();
            $subscription = $therapist->subscription;

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No subscription found'
                ], 404);
            }

            $payments = $subscription->payments()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'paid_at' => $payment->paid_at,
                        'failed_at' => $payment->failed_at,
                        'failure_reason' => $payment->failure_reason,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $payments
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting payment history: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment history'
            ], 500);
        }
    }

    /**
     * Get or create Stripe customer
     */
    private function getOrCreateStripeCustomer($therapist)
    {
        try {
            // Check if customer already exists
            $subscription = $therapist->subscription;
            if ($subscription && $subscription->stripe_customer_id) {
                try {
                    return Customer::retrieve($subscription->stripe_customer_id);
                } catch (\Exception $e) {
                    // Customer doesn't exist in Stripe, create new one
                    Log::warning('Stripe customer not found, creating new one: ' . $e->getMessage());
                }
            }

            // Create new customer
            return Customer::create([
                'email' => $therapist->email,
                'name' => $therapist->name,
                'phone' => $therapist->phone,
                'metadata' => [
                    'therapist_id' => $therapist->id,
                    'therapist_email' => $therapist->email,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating Stripe customer: ' . $e->getMessage());
            throw $e;
        }
    }
}