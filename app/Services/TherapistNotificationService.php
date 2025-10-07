<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Therapist;
use App\Models\TherapistNotification;
use App\Models\TherapistFcmToken;
use App\Services\FCMService;
use Illuminate\Support\Facades\Log;
use Exception;

class TherapistNotificationService
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Send notification when new booking is created
     */
    public function sendBookingCreatedNotification(Booking $booking, Therapist $therapist): array
    {
        // Validate inputs
        if (!$booking || !$therapist) {
            Log::warning('Invalid booking or therapist for notification');
            return ['success' => false, 'message' => 'Invalid inputs'];
        }

        // Get therapist's active FCM tokens
        $tokens = $therapist->getActiveFcmTokens();

        if (empty($tokens)) {
            Log::info('Therapist has no active FCM tokens', [
                'therapist_id' => $therapist->id,
                'booking_id' => $booking->id
            ]);
            return ['success' => false, 'message' => 'No active tokens'];
        }

        // Build notification content
        $title = 'New Booking Received';
        $message = sprintf(
            '%s booked %s for %s at %s',
            $booking->name,
            $booking->service->title ?? 'Service',
            $booking->date->format('M d'),
            \Carbon\Carbon::parse($booking->time)->format('g:i A')
        );

        // Build data payload
        $data = [
            'type' => 'booking_created',
            'booking_id' => (string) $booking->id,
            'booking_reference' => $booking->reference,
            'customer_name' => $booking->name,
            'service_name' => $booking->service->title ?? '',
            'date' => $booking->date->format('Y-m-d'),
            'time' => $booking->time,
            'address' => $booking->address_line1,
            'city' => $booking->city,
            'phone' => $booking->phone,
        ];

        // Create notification log
        $log = TherapistNotification::create([
            'therapist_id' => $therapist->id,
            'booking_id' => $booking->id,
            'notification_type' => 'booking_created',
            'title' => $title,
            'message' => $message,
            'sent_at' => now(),
            'delivery_status' => 'sending',
        ]);

        $sentCount = 0;
        $failedCount = 0;

        // Send to each token
        foreach ($tokens as $token) {
            try {
                $result = $this->fcmService->sendToDevice(
                    $token,
                    ['title' => $title, 'body' => $message],
                    $data
                );

                $sentCount++;

                // Update log status
                $log->update(['delivery_status' => 'sent']);

                // Mark token as used
                TherapistFcmToken::where('fcm_token', $token)
                    ->update(['last_used_at' => now()]);

                Log::info('Notification sent to therapist', [
                    'therapist_id' => $therapist->id,
                    'booking_id' => $booking->id,
                    'token' => substr($token, 0, 20) . '...'
                ]);

            } catch (Exception $e) {
                $failedCount++;

                Log::error('Failed to send notification to therapist', [
                    'therapist_id' => $therapist->id,
                    'booking_id' => $booking->id,
                    'token' => substr($token, 0, 20) . '...',
                    'error' => $e->getMessage()
                ]);

                // Update log with error
                $log->update([
                    'delivery_status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);

                // Deactivate token if invalid
                if (str_contains($e->getMessage(), 'invalid') || 
                    str_contains($e->getMessage(), 'not found')) {
                    TherapistFcmToken::where('fcm_token', $token)
                        ->update(['is_active' => false]);
                    
                    Log::info('Deactivated invalid FCM token', [
                        'token' => substr($token, 0, 20) . '...'
                    ]);
                }
            }
        }

        return [
            'success' => $sentCount > 0,
            'tokens_sent' => $sentCount,
            'tokens_failed' => $failedCount,
            'notification_id' => $log->id
        ];
    }

    /**
     * Send notification when booking is cancelled
     */
    public function sendBookingCancelledNotification(Booking $booking, Therapist $therapist): array
    {
        if (!$booking || !$therapist) {
            return ['success' => false, 'message' => 'Invalid inputs'];
        }

        $tokens = $therapist->getActiveFcmTokens();

        if (empty($tokens)) {
            return ['success' => false, 'message' => 'No active tokens'];
        }

        $title = 'Booking Cancelled';
        $message = sprintf(
            'Booking %s for %s at %s has been cancelled',
            $booking->reference,
            $booking->date->format('M d'),
            \Carbon\Carbon::parse($booking->time)->format('g:i A')
        );

        $data = [
            'type' => 'booking_cancelled',
            'booking_id' => (string) $booking->id,
            'booking_reference' => $booking->reference,
            'date' => $booking->date->format('Y-m-d'),
            'time' => $booking->time,
        ];

        $log = TherapistNotification::create([
            'therapist_id' => $therapist->id,
            'booking_id' => $booking->id,
            'notification_type' => 'booking_cancelled',
            'title' => $title,
            'message' => $message,
            'sent_at' => now(),
            'delivery_status' => 'sending',
        ]);

        $sentCount = 0;

        foreach ($tokens as $token) {
            try {
                $this->fcmService->sendToDevice(
                    $token,
                    ['title' => $title, 'body' => $message],
                    $data
                );
                $sentCount++;
                $log->update(['delivery_status' => 'sent']);
                TherapistFcmToken::where('fcm_token', $token)
                    ->update(['last_used_at' => now()]);
            } catch (Exception $e) {
                Log::error('Failed to send cancellation notification', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'success' => $sentCount > 0,
            'tokens_sent' => $sentCount,
            'notification_id' => $log->id
        ];
    }
}