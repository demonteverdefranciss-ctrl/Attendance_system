<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\NoClassDay;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleCalendarController extends Controller
{
    /** A read-only school schedule calendar available to every signed-in role. */
    public function index(Request $request): Response
    {
        $month = $request->integer('month', now()->month);
        $year = $request->integer('year', now()->year);
        $month = $month >= 1 && $month <= 12 ? $month : now()->month;
        $year = $year >= 2000 && $year <= 2100 ? $year : now()->year;
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $schedules = Schedule::query()
            ->where('is_active', true)
            ->with('section:id,name,grade_level')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn (Schedule $schedule) => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => substr((string) $schedule->start_time, 0, 5),
                'end_time' => substr((string) $schedule->end_time, 0, 5),
                'late_after' => $schedule->late_after ? substr((string) $schedule->late_after, 0, 5) : null,
                'type' => $schedule->type,
                'section' => $schedule->section ? [
                    'name' => $schedule->section->name,
                    'grade_level' => $schedule->section->grade_level,
                ] : null,
            ]);

        $noClassDays = NoClassDay::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date', 'name'])
            ->mapWithKeys(fn (NoClassDay $day) => [$day->date->toDateString() => $day->name ?: 'No class']);

        return Inertia::render('Schedules/Calendar', [
            'schedules' => $schedules,
            'year' => $year,
            'month' => $month,
            'monthLabel' => $start->format('F Y'),
            'startWeekday' => $start->dayOfWeekIso,
            'daysInMonth' => $start->daysInMonth,
            'noClassDays' => $noClassDays,
            'today' => now()->toDateString(),
        ]);
    }
}
