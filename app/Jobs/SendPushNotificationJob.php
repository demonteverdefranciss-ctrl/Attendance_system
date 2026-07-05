<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 180];

    public function __construct(public int $notificationId)
    {
    }

    public function handle(): void
    {
        $notification = Notification::with('guardian')->find($this->notificationId);
        if (! $notification || $notification->status !== 'pending') {
            return;
        }

        $guardian = $notification->guardian;
        $token = $guardian?->fcm_token;
        if (! $token) {
            $notification->update(['status' => 'failed']);
            return;
        }

        $serverKey = config('services.fcm.server_key');
        if (! $serverKey) {
            Log::warning('FCM server key is missing; push notification not sent.', [
                'notification_id' => $notification->id,
            ]);
            $notification->update(['status' => 'failed']);

            return;
        }

        $endpoint = config('services.fcm.endpoint', 'https://fcm.googleapis.com/fcm/send');

        $response = Http::withHeaders([
            'Authorization' => "key={$serverKey}",
        ])
            ->acceptJson()
            ->post($endpoint, [
                'to' => $token,
                'notification' => [
                    'title' => $notification->title,
                    'body' => $notification->body,
                ],
                'data' => $notification->payload ?? [],
            ]);

        if (! $response->successful()) {
            $notification->update(['status' => 'failed']);
            throw new \RuntimeException("FCM send failed: HTTP {$response->status()}");
        }

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
