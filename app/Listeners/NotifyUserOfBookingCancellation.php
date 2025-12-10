<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\Log;

class NotifyUserOfBookingCancellation
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
    public function handle(BookingCancelled $event): void
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

            // Check if user exists
            if (!$booking->user_id) {
                Log::info('Cancelled booking has no associated user', [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->reference
                ]);
                return;
            }

            // Send notification
            $result = $this->notificationService->sendBookingCancelledNotification($booking);

            if ($result['success']) {
                Log::info('User cancellation notification sent successfully', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'tokens_sent' => $result['tokens_sent']
                ]);
            } else {
                Log::info('User cancellation notification not sent', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'reason' => $result['message']
                ]);
            }

        } catch (\Exception $e) {
            // Log error but don't throw
            Log::error('Failed to send user cancellation notification', [
                'booking_id' => $booking->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
