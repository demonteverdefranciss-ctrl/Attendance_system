<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NoClassDay;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NoClassDayController extends Controller
{
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
            ->get(['id', 'date', 'name']);

        $byDate = $days
            ->filter(fn (NoClassDay $d) => (int) $d->date->month === $month)
            ->mapWithKeys(fn (NoClassDay $d) => [
                $d->date->toDateString() => [
                    'id' => $d->id,
                    'name' => $d->name,
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
            'upcoming' => NoClassDay::query()
                ->whereDate('date', '>=', now()->toDateString())
                ->orderBy('date')
                ->limit(20)
                ->get(['id', 'date', 'name'])
                ->map(fn (NoClassDay $d) => [
                    'id' => $d->id,
                    'date' => $d->date->toDateString(),
                    'name' => $d->name,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        $day = Carbon::parse($data['date']);
        if ($day->isoWeekday() >= 6) {
            return back()->with('error', 'Weekends already skip auto-open. Mark a weekday instead.');
        }

        NoClassDay::query()->updateOrCreate(
            ['date' => $day->toDateString()],
            ['name' => $data['name'] ?: null],
        );

        return back()->with('success', 'No-class day saved. Sessions will not auto-open on that date.');
    }

    public function destroy(NoClassDay $noClassDay): RedirectResponse
    {
        $noClassDay->delete();

        return back()->with('success', 'No-class day removed.');
    }
}
