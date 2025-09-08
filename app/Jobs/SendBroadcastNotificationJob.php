<?php
// app/Jobs/SendBroadcastNotificationJob.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Notification;
use App\Services\FCMService;
use Illuminate\Support\Facades\Log;

class SendBroadcastNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    public function handle(FCMService $fcmService)
    {
        try {
            Log::info('Starting broadcast notification', [
                'notification_id' => $this->notification->id,
                'title' => $this->notification->title
            ]);

            $successCount = $fcmService->sendNotificationToAll($this->notification);

            // Update notification as sent
            $this->notification->update([
                'sent_at' => now(),
                'total_sent' => $successCount
            ]);

            Log::info('Broadcast notification completed', [
                'notification_id' => $this->notification->id,
                'success_count' => $successCount
            ]);

        } catch (\Exception $e) {
            Log::error('Broadcast notification failed', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage()
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Broadcast notification job failed permanently', [
            'notification_id' => $this->notification->id,
            'error' => $exception->getMessage()
        ]);
    }
}