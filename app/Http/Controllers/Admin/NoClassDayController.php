<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NoClassDay;
use App\Services\GoogleHolidaySync;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class NoClassDayController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request): Response
    {
        $year = (int) $request->integer('year', now()->year);
        $month = (int) $request->integer('month', now()->month);
        if ($month < 1 || $month > 12) {
            $month = now()->month;
        }
        if ($year < 2020 || $year > 2100) {
            $year = now()->year;
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $days = NoClassDay::query()
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get(['id', 'date', 'name', 'source']);

        $byDate = $days
            ->filter(fn (NoClassDay $d) => (int) $d->date->month === $month)
            ->mapWithKeys(fn (NoClassDay $d) => [
                $d->date->toDateString() => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'source' => $d->source ?: NoClassDay::SOURCE_MANUAL,
                ],
            ]);

        return Inertia::render('Admin/NoClassDays/Index', [
            'year' => $year,
            'month' => $month,
            'monthLabel' => $start->format('F Y'),
            'startWeekday' => $start->isoWeekday(), // 1=Mon
            'daysInMonth' => $start->daysInMonth,
            'today' => now()->toDateString(),
            'marked' => $byDate->all(),
            'googleSyncEnabled' => (bool) config('school_calendar.google_sync_enabled', true),
            'googleCalendarLabel' => (string) config(
                'school_calendar.google_calendar_id',
                'en.philippines.official#holiday@group.v.calendar.google.com'
            ),
            'upcoming' => NoClassDay::query()
                ->whereDate('date', '>=', now()->toDateString())
                ->orderBy('date')
                ->limit(30)
                ->get(['id', 'date', 'name', 'source'])
                ->map(fn (NoClassDay $d) => [
                    'id' => $d->id,
                    'date' => $d->date->toDateString(),
                    'name' => $d->name,
                    'source' => $d->source ?: NoClassDay::SOURCE_MANUAL,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        $day = Carbon::parse($data['date'])->startOfDay();
        if ($day->lt(now()->startOfDay())) {
            return back()->with('error', 'Past dates cannot be marked as no-class days.');
        }
        if ($day->isoWeekday() >= 6) {
            return back()->with('error', 'Weekends already skip auto-open. Mark a weekday instead.');
        }

        $existing = NoClassDay::withTrashed()
            ->whereDate('date', $day->toDateString())
            ->first();

        $shouldNotify = false;

        if ($existing) {
            $wasTrashed = $existing->trashed();
            if ($wasTrashed) {
                $existing->restore();
                $shouldNotify = true;
            }
            $existing->update([
                'name' => $data['name'] ?: null,
                'source' => NoClassDay::SOURCE_MANUAL,
            ]);
            $record = $existing->fresh();
        } else {
            $record = NoClassDay::create([
                'date' => $day->toDateString(),
                'name' => $data['name'] ?: null,
                'source' => NoClassDay::SOURCE_MANUAL,
            ]);
            $shouldNotify = true;
        }

        if ($shouldNotify && $record) {
            $this->notifications->queueNoClassDayNotice($record);
        }

        return back()->with(
            'success',
            $shouldNotify
                ? 'No-class day saved. Parents and teachers were notified.'
                : 'No-class day updated.'
        );
    }

    public function destroy(NoClassDay $noClassDay): RedirectResponse
    {
        $noClassDay->delete();

        return back()->with('success', 'No-class day moved to archive.');
    }

    public function syncGoogle(GoogleHolidaySync $sync): RedirectResponse
    {
        try {
            $result = $sync->sync();
        } catch (Throwable $e) {
            return back()->with('error', 'Google Calendar sync failed: '.$e->getMessage());
        }

        return back()->with(
            'success',
            sprintf(
                'Synced Google holidays: %d total (%d new, %d updated, %d removed).',
                $result['total'],
                $result['imported'],
                $result['updated'],
                $result['removed'],
            )
        );
    }
}
