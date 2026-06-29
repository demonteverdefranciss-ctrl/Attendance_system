<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $guardian = $request->user()->guardian;

        if (! $guardian) {
            return $this->fail('Only guardian accounts have notifications.', 'FORBIDDEN', 403);
        }

        $items = Notification::where('guardian_id', $guardian->id)
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'status' => $n->status,
                'sent_at' => $n->sent_at?->toDateTimeString(),
                'read_at' => $n->read_at?->toDateTimeString(),
            ]);

        return $this->ok($items);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $guardian = $request->user()->guardian;

        if (! $guardian || $notification->guardian_id !== $guardian->id) {
            return $this->fail('Forbidden.', 'FORBIDDEN', 403);
        }

        $notification->update(['status' => 'read', 'read_at' => now()]);

        return $this->ok(['message' => 'Notification marked as read.']);
    }
}
