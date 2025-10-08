<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Mail\BookingCancellationEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBookingCancellationEmail
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
    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking;

        // Ensure relationships are loaded
        if (!$booking->relationLoaded('service')) {
            $booking->load('service');
        }
        if (!$booking->relationLoaded('therapist')) {
            $booking->load('therapist');
        }

        try {
            Log::info('Attempting to send booking cancellation email', [
                'booking_id' => $booking->id,
                'email' => $booking->email,
                'reference' => $booking->reference
            ]);

            Mail::to($booking->email)->send(new BookingCancellationEmail($booking));

            Log::info('Booking cancellation email sent successfully', [
                'booking_id' => $booking->id,
                'customer_email' => $booking->email,
            ]);

        } catch (\Exception $e) {
            // Email failed but don't let it break the cancellation process
            Log::error('Failed to send booking cancellation email', [
                'booking_id' => $booking->id,
                'email' => $booking->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}