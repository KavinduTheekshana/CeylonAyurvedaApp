<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Services\TherapistNotificationService;
use Illuminate\Support\Facades\Log;

class NotifyTherapistOfNewBooking
{
    protected $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(TherapistNotificationService $notificationService)
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
            if (!$booking->relationLoaded('therapist')) {
                $booking->load('therapist');
            }
            if (!$booking->relationLoaded('service')) {
                $booking->load('service');
            }

            // Validate therapist exists
            if (!$booking->therapist) {
                Log::warning('Booking created without therapist assigned', [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->reference
                ]);
                return;
            }

            // Check if therapist has active FCM tokens
            if (!$booking->therapist->hasActiveFcmTokens()) {
                Log::info('Therapist has no active FCM tokens', [
                    'therapist_id' => $booking->therapist->id,
                    'therapist_name' => $booking->therapist->name,
                    'booking_id' => $booking->id
                ]);
                return;
            }

            // Send notification
            $result = $this->notificationService->sendBookingCreatedNotification(
                $booking,
                $booking->therapist
            );

            Log::info('Booking notification sent successfully', [
                'booking_id' => $booking->id,
                'therapist_id' => $booking->therapist->id,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            // Log error but don't throw - we don't want to break booking creation
            Log::error('Failed to send booking notification', [
                'booking_id' => $booking->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}