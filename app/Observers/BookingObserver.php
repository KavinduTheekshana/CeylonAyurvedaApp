<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\UserNotificationService;
use Illuminate\Support\Facades\Log;

class BookingObserver
{
    protected $userNotificationService;

    public function __construct(UserNotificationService $userNotificationService)
    {
        $this->userNotificationService = $userNotificationService;
    }

    /**
     * Handle the Booking "updating" event.
     * This fires before the model is saved, so we can capture the old status
     */
    public function updating(Booking $booking): void
    {
        // Check if status is being changed
        if ($booking->isDirty('status')) {
            $oldStatus = $booking->getOriginal('status');
            $newStatus = $booking->status;

            Log::info('Booking status changing', [
                'booking_id' => $booking->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

            // Store the old status in a temporary attribute for use in updated()
            $booking->_oldStatus = $oldStatus;
        }
    }

    /**
     * Handle the Booking "updated" event.
     * This fires after the model is saved
     */
    public function updated(Booking $booking): void
    {
        // Check if status was changed and we have the old status stored
        if (isset($booking->_oldStatus)) {
            $oldStatus = $booking->_oldStatus;
            $newStatus = $booking->status;

            // Load relationships for notification
            $booking->load(['service', 'therapist', 'user']);

            // Only send notification if user exists
            if ($booking->user_id) {
                try {
                    $result = $this->userNotificationService->sendBookingStatusChangedNotification(
                        $booking,
                        $oldStatus,
                        $newStatus
                    );

                    if ($result['success']) {
                        Log::info('Status change notification sent', [
                            'booking_id' => $booking->id,
                            'user_id' => $booking->user_id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'tokens_sent' => $result['tokens_sent']
                        ]);
                    } else {
                        Log::info('Status change notification not sent', [
                            'booking_id' => $booking->id,
                            'reason' => $result['message']
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send status change notification', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Clean up temporary attribute
            unset($booking->_oldStatus);
        }
    }
}
