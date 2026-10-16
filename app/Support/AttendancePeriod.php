<?php

namespace App\Support;

use Illuminate\Http\Request;

class AttendancePeriod
{
    public static function fromRequest(Request $request): string
    {
        return $request->validate(['period' => 'sometimes|in:all,week,this_week,month'])['period'] ?? 'all';
    }

    public static function apply($query, string $period): void
    {
        if ($period === 'all') {
            return;
        }

        $today = today();
        $from = match ($period) {
            'week' => $today->copy()->subDays(6),
            'this_week' => $today->copy()->startOfWeek(\Carbon\Carbon::MONDAY),
            default => $today->copy()->subDays(29),
        };
        $query->whereHas('session', fn ($session) => $session
            ->whereDate('session_date', '>=', $from->toDateString())
            ->whereDate('session_date', '<=', $today->toDateString()));
    }
}
