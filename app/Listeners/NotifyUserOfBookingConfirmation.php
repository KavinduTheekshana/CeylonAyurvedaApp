<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\Log;

class NotifyUserOfBookingConfirmation
{
    protected $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(UserNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(BookingCreated $event): void
    {
        try {
            $booking = $event->booking;

            // Load relationships if not already loaded
            if (!$booking->relationLoaded('user')) {
                $booking->load('user');
            }
            if (!$booking->relationLoaded('service')) {
                $booking->load('service');
            }
            if (!$booking->relationLoaded('therapist')) {
                $booking->load('therapist');
            }

            // Only send notification if booking is confirmed or completed
            // Skip if status is pending_payment or pending
            if (!in_array($booking->status, ['confirmed', 'completed'])) {
                Log::info('Skipping user notification - booking not confirmed yet', [
                    'booking_id' => $booking->id,
                    'status' => $booking->status
                ]);
                return;
            }

            // Check if user exists
            if (!$booking->user_id) {
                Log::info('Booking has no associated user', [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->reference
                ]);
                return;
            }

            // Send notification
            $result = $this->notificationService->sendBookingConfirmedNotification($booking);

            if ($result['success']) {
                Log::info('User booking notification sent successfully', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'tokens_sent' => $result['tokens_sent']
                ]);
            } else {
                Log::info('User booking notification not sent', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'reason' => $result['message']
                ]);
            }

        } catch (\Exception $e) {
            // Log error but don't throw - we don't want to break booking creation
            Log::error('Failed to send user booking notification', [
                'booking_id' => $booking->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
