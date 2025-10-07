<?php
// app/Services/FCMService.php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Illuminate\Support\Facades\Log;
use App\Models\UserFcmToken;

class FCMService
{
    private $messaging;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(storage_path('app/firebase-service-account.json'));
        
        $this->messaging = $factory->createMessaging();
    }

    public function sendNotificationToAll($notification)
    {
        $tokens = UserFcmToken::active()->pluck('fcm_token')->toArray();
        
        if (empty($tokens)) {
            Log::warning('No FCM tokens found for broadcasting notification');
            return 0;
        }

        $message = $this->createMessage($notification);
        $successCount = 0;
        $failedTokens = [];

        // Send in batches of 500 (FCM limit)
        $chunks = array_chunk($tokens, 500);

        foreach ($chunks as $tokenChunk) {
            try {
                $report = $this->messaging->sendMulticast($message, $tokenChunk);
                $successCount += $report->successes()->count();

                // Handle failed tokens
                foreach ($report->failures()->getItems() as $failure) {
                    $failedTokens[] = $failure->target()->value();
                    Log::warning('FCM delivery failed', [
                        'token' => $failure->target()->value(),
                        'error' => $failure->error()->getMessage()
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('FCM batch send failed', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($tokenChunk)
                ]);
            }
        }

        // Deactivate failed tokens
        if (!empty($failedTokens)) {
            UserFcmToken::whereIn('fcm_token', $failedTokens)
                ->update(['is_active' => false]);
        }

        return $successCount;
    }

    private function createMessage($notification): CloudMessage
    {
        $firebaseNotification = FirebaseNotification::create(
            $notification->title,
            $notification->message
        );

        if ($notification->image_url) {
            $firebaseNotification = $firebaseNotification->withImageUrl($notification->image_url);
        }

        $message = CloudMessage::new()->withNotification($firebaseNotification);

        // Android specific configuration
        $androidConfig = AndroidConfig::fromArray([
            'priority' => 'high',
            'notification' => [
                'icon' => 'ic_notification',
                'color' => '#9A563A', // Your app's primary color
                'sound' => 'default',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'channel_id' => 'ceylon_ayurveda_notifications'
            ],
            'data' => [
                'notification_id' => (string) $notification->id,
                'type' => $notification->type,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
            ]
        ]);

        // iOS specific configuration
        $apnsConfig = ApnsConfig::fromArray([
            'headers' => [
                'apns-priority' => '10',
            ],
            'payload' => [
                'aps' => [
                    'alert' => [
                        'title' => $notification->title,
                        'body' => $notification->message,
                    ],
                    'badge' => 1,
                    'sound' => 'default',
                    'mutable-content' => 1
                ],
                'notification_id' => (string) $notification->id,
                'type' => $notification->type
            ]
        ]);

        return $message
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig);
    }


    /**
     * Send notification to a specific device
     */
    public function sendToDevice(string $token, array $notification, array $data = [])
    {
        try {
            // Create notification
            $firebaseNotification = FirebaseNotification::create(
                $notification['title'],
                $notification['body']
            );

            // Create base message
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($firebaseNotification)
                ->withData($data);

            // Android configuration
            $androidConfig = AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                    'channel_id' => 'booking_notifications',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'notification_priority' => 'PRIORITY_HIGH',
                ],
            ]);

            $message = $message->withAndroidConfig($androidConfig);

            // iOS configuration
            $apnsConfig = ApnsConfig::fromArray([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $notification['title'],
                            'body' => $notification['body'],
                        ],
                        'sound' => 'default',
                        'badge' => 1,
                        'mutable-content' => 1,
                    ],
                ],
            ]);

            $message = $message->withApnsConfig($apnsConfig);

            // Send message
            $result = $this->messaging->send($message);

            Log::info('FCM notification sent successfully', [
                'token' => substr($token, 0, 20) . '...',
                'message_id' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('FCM send failed', [
                'token' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}