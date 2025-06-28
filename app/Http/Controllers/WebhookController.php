<?php

// app/Http/Controllers/WebhookController.php
namespace App\Http\Controllers;

use App\Services\InvestmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class WebhookController extends Controller
{
    private InvestmentService $investmentService;

    public function __construct(InvestmentService $investmentService)
    {
        $this->investmentService = $investmentService;
    }

    /**
     * Handle Stripe webhooks
     */
    public function handleStripe(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpoint_secret);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage()
            ]);
            return response('Webhook signature verification failed', 400);
        }

        try {
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;

                case 'payment_intent.canceled':
                    $this->handlePaymentIntentCanceled($event->data->object);
                    break;

                case 'charge.dispute.created':
                    $this->handleChargeDispute($event->data->object);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event', [
                        'type' => $event->type,
                        'id' => $event->id
                    ]);
            }

            return response('Webhook handled successfully', 200);

        } catch (\Exception $e) {
            Log::error('Error processing Stripe webhook', [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response('Webhook processing failed', 500);
        }
    }

    /**
     * Handle successful payment intent
     */
    private function handlePaymentIntentSucceeded($paymentIntent): void
    {
        $this->investmentService->handleSuccessfulPayment($paymentIntent->id);
        
        Log::info('Payment intent succeeded', [
            'payment_intent_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount,
        ]);
    }

    /**
     * Handle failed payment intent
     */
    private function handlePaymentIntentFailed($paymentIntent): void
    {
        $failureReason = $paymentIntent->last_payment_error->message ?? 'Unknown error';
        
        $this->investmentService->handleFailedPayment($paymentIntent->id, $failureReason);
        
        Log::warning('Payment intent failed', [
            'payment_intent_id' => $paymentIntent->id,
            'failure_reason' => $failureReason,
        ]);
    }

    /**
     * Handle canceled payment intent
     */
    private function handlePaymentIntentCanceled($paymentIntent): void
    {
        $this->investmentService->handleFailedPayment($paymentIntent->id, 'Payment canceled');
        
        Log::info('Payment intent canceled', [
            'payment_intent_id' => $paymentIntent->id,
        ]);
    }

    /**
     * Handle charge dispute
     */
    private function handleChargeDispute($dispute): void
    {
        Log::alert('Charge dispute created', [
            'dispute_id' => $dispute->id,
            'charge_id' => $dispute->charge,
            'amount' => $dispute->amount,
            'reason' => $dispute->reason,
        ]);

        // You might want to notify administrators about disputes
        // Mail::to(config('mail.admin'))->send(new DisputeNotification($dispute));
    }
}