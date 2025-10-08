<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Mail\BankTransferBooking;
use App\Mail\BookingConfirmation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmationEmail 
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        // Ensure relationships are loaded
        if (!$booking->relationLoaded('service')) {
            $booking->load('service');
        }
        if (!$booking->relationLoaded('therapist')) {
            $booking->load('therapist');
        }
        if (!$booking->relationLoaded('location')) {
            $booking->load('location');
        }

        try {
            Log::info('Attempting to send booking confirmation email', [
                'booking_id' => $booking->id,
                'email' => $booking->email,
                'payment_method' => $booking->payment_method,
                'status' => $booking->status
            ]);

            // Send appropriate email based on payment method
            if ($booking->payment_method === 'bank') {
                // Bank transfer - send bank details email
                Mail::to($booking->email)->send(new BankTransferBooking($booking));
                
                Log::info('Bank transfer confirmation email sent', [
                    'booking_id' => $booking->id,
                    'customer_email' => $booking->email,
                ]);
                
            } elseif ($booking->payment_method === 'card' && $booking->status === 'confirmed') {
                // Card payment confirmed - send confirmation email
                Mail::to($booking->email)->send(new BookingConfirmation($booking));
                
                Log::info('Card payment confirmation email sent', [
                    'booking_id' => $booking->id,
                    'customer_email' => $booking->email,
                ]);
            }

        } catch (\Exception $e) {
            // Email failed but don't let it break the booking process
            Log::error('Failed to send booking confirmation email', [
                'booking_id' => $booking->id,
                'email' => $booking->email,
                'payment_method' => $booking->payment_method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Optionally: Store failed email in a queue for retry
            // Or: Send notification to admin about failed email
        }
    }
}