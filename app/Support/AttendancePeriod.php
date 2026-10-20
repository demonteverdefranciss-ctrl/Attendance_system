<?php

namespace App\Support;

use Illuminate\Http\Request;

class AttendancePeriod
{
    public static function fromRequest(Request $request): string
    {
        return $request->validate(['period' => 'sometimes|in:all,week,last_week,this_week,month'])['period'] ?? 'all';
    }

    public static function apply($query, string $period): void
    {
        if ($period === 'all') {
            return;
        }

        $today = today();
        [$from, $to] = match ($period) {
            'week' => [$today->copy()->subDays(6), $today->copy()],
            'last_week' => [
                $today->copy()->startOfWeek(\Carbon\Carbon::MONDAY)->subWeek(),
                $today->copy()->startOfWeek(\Carbon\Carbon::MONDAY)->subDay(),
            ],
            'this_week' => [$today->copy()->startOfWeek(\Carbon\Carbon::MONDAY), $today->copy()],
            default => [$today->copy()->subDays(29), $today->copy()],
        };
        $query->whereHas('session', fn ($session) => $session
            ->whereDate('session_date', '>=', $from->toDateString())
            ->whereDate('session_date', '<=', $to->toDateString()));
    }
}
