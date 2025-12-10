<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Services\FCMService;
use Illuminate\Support\Facades\Log;
use Exception;

class UserNotificationService
{
    protected $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Send notification when booking is confirmed
     */
    public function sendBookingConfirmedNotification(Booking $booking): array
    {
        if (!$booking->user_id) {
            Log::info('Booking has no user associated', [
                'booking_id' => $booking->id
            ]);
            return ['success' => false, 'message' => 'No user associated'];
        }

        $user = User::find($booking->user_id);
        if (!$user) {
            Log::warning('User not found for booking', [
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id
            ]);
            return ['success' => false, 'message' => 'User not found'];
        }

        // Get user's active FCM tokens
        $tokens = UserFcmToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            Log::info('User has no active FCM tokens', [
                'user_id' => $user->id,
                'booking_id' => $booking->id
            ]);
            return ['success' => false, 'message' => 'No active tokens'];
        }

        // Build notification content
        $title = 'Booking Confirmed!';
        $message = sprintf(
            'Your booking for %s on %s at %s has been confirmed.',
            $booking->service->title ?? 'Service',
            $booking->date->format('M d, Y'),
            \Carbon\Carbon::parse($booking->time)->format('g:i A')
        );

        // Build data payload
        $data = [
            'type' => 'booking_confirmed',
            'booking_id' => (string) $booking->id,
            'booking_reference' => $booking->reference,
            'service_name' => $booking->service->title ?? '',
            'therapist_name' => $booking->therapist->name ?? '',
            'date' => $booking->date->format('Y-m-d'),
            'time' => $booking->time,
            'status' => $booking->status,
        ];

        return $this->sendNotification($tokens, $title, $message, $data, $user->id, $booking->id);
    }

    /**
     * Send notification when booking is cancelled
     */
    public function sendBookingCancelledNotification(Booking $booking): array
    {
        if (!$booking->user_id) {
            return ['success' => false, 'message' => 'No user associated'];
        }

        $user = User::find($booking->user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $tokens = UserFcmToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            return ['success' => false, 'message' => 'No active tokens'];
        }

        $title = 'Booking Cancelled';
        $message = sprintf(
            'Your booking for %s on %s has been cancelled.',
            $booking->service->title ?? 'Service',
            $booking->date->format('M d, Y')
        );

        $data = [
            'type' => 'booking_cancelled',
            'booking_id' => (string) $booking->id,
            'booking_reference' => $booking->reference,
            'status' => 'cancelled',
        ];

        return $this->sendNotification($tokens, $title, $message, $data, $user->id, $booking->id);
    }

    /**
     * Send notification when booking status changes
     */
    public function sendBookingStatusChangedNotification(Booking $booking, string $oldStatus, string $newStatus): array
    {
        if (!$booking->user_id) {
            return ['success' => false, 'message' => 'No user associated'];
        }

        $user = User::find($booking->user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $tokens = UserFcmToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            return ['success' => false, 'message' => 'No active tokens'];
        }

        // Build notification based on status
        $title = 'Booking Status Updated';
        $message = $this->getStatusChangeMessage($booking, $newStatus);

        $data = [
            'type' => 'booking_status_changed',
            'booking_id' => (string) $booking->id,
            'booking_reference' => $booking->reference,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'service_name' => $booking->service->title ?? '',
            'date' => $booking->date->format('Y-m-d'),
        ];

        return $this->sendNotification($tokens, $title, $message, $data, $user->id, $booking->id);
    }

    /**
     * Helper method to send notifications to multiple tokens
     */
    private function sendNotification(array $tokens, string $title, string $message, array $data, int $userId, int $bookingId): array
    {
        $sentCount = 0;
        $failedCount = 0;

        foreach ($tokens as $token) {
            try {
                $result = $this->fcmService->sendToDevice(
                    $token,
                    ['title' => $title, 'body' => $message],
                    $data
                );

                $sentCount++;

                // Mark token as used
                UserFcmToken::where('fcm_token', $token)
                    ->update(['last_used_at' => now()]);

                Log::info('Notification sent to user', [
                    'user_id' => $userId,
                    'booking_id' => $bookingId,
                    'notification_type' => $data['type'],
                    'token' => substr($token, 0, 20) . '...'
                ]);

            } catch (Exception $e) {
                $failedCount++;

                Log::error('Failed to send notification to user', [
                    'user_id' => $userId,
                    'booking_id' => $bookingId,
                    'token' => substr($token, 0, 20) . '...',
                    'error' => $e->getMessage()
                ]);

                // Deactivate token if invalid
                if (str_contains($e->getMessage(), 'invalid') ||
                    str_contains($e->getMessage(), 'not found') ||
                    str_contains($e->getMessage(), 'unregistered')) {
                    UserFcmToken::where('fcm_token', $token)
                        ->update(['is_active' => false]);

                    Log::info('Deactivated invalid FCM token', [
                        'user_id' => $userId,
                        'token' => substr($token, 0, 20) . '...'
                    ]);
                }
            }
        }

        return [
            'success' => $sentCount > 0,
            'tokens_sent' => $sentCount,
            'tokens_failed' => $failedCount,
        ];
    }

    /**
     * Get appropriate message based on status change
     */
    private function getStatusChangeMessage(Booking $booking, string $status): string
    {
        $serviceName = $booking->service->title ?? 'your service';
        $date = $booking->date->format('M d, Y');

        return match($status) {
            'confirmed' => "Your booking for {$serviceName} on {$date} has been confirmed.",
            'cancelled' => "Your booking for {$serviceName} on {$date} has been cancelled.",
            'completed' => "Your booking for {$serviceName} has been completed. Thank you!",
            'pending' => "Your booking for {$serviceName} on {$date} is pending confirmation.",
            'pending_payment' => "Your booking for {$serviceName} is awaiting payment.",
            default => "Your booking status has been updated to: {$status}."
        };
    }
}
