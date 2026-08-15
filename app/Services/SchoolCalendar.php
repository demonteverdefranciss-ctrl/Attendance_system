<?php

namespace App\Services;

use App\Models\NoClassDay;
use Carbon\CarbonInterface;

class SchoolCalendar
{
    /**
     * Saturday or Sunday (ISO 6–7).
     */
    public function isWeekend(CarbonInterface $date): bool
    {
        return $date->isoWeekday() >= 6;
    }

    public function isNoClass(CarbonInterface $date): bool
    {
        try {
            return NoClassDay::query()
                ->whereDate('date', $date->toDateString())
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether schedules should auto-open sessions on this date.
     * Teachers can still open a session by hand.
     */
    public function shouldAutoOpenSessions(CarbonInterface $date): bool
    {
        return ! $this->isWeekend($date) && ! $this->isNoClass($date);
    }
}
