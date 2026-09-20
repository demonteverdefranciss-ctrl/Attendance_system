<?php

namespace App\Support;

use Illuminate\Http\Request;

class AttendancePeriod
{
    public static function fromRequest(Request $request): string
    {
        return $request->validate(['period' => 'sometimes|in:all,week,month'])['period'] ?? 'all';
    }

    public static function apply($query, string $period): void
    {
        if ($period === 'all') {
            return;
        }

        $today = today();
        $from = $today->copy()->subDays($period === 'week' ? 6 : 29)->toDateString();
        $query->whereHas('session', fn ($session) => $session
            ->whereDate('session_date', '>=', $from)
            ->whereDate('session_date', '<=', $today->toDateString()));
    }
}
