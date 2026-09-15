<?php

namespace App\Http\Controllers;

use App\Models\NoClassDay;
use Inertia\Inertia;
use Inertia\Response;

class NoClassDayListController extends Controller
{
    public function parent(): Response
    {
        return Inertia::render('Parent/NoClassDays', [
            'days' => $this->payload(),
        ]);
    }

    public function teacher(): Response
    {
        return Inertia::render('Teacher/NoClassDays', [
            'days' => $this->payload(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function payload(): array
    {
        $today = now()->toDateString();

        $upcoming = NoClassDay::query()
            ->whereDate('date', '>=', $today)
            ->orderBy('date')
            ->limit(60)
            ->get(['id', 'date', 'name', 'source']);

        $recent = NoClassDay::query()
            ->whereDate('date', '<', $today)
            ->whereDate('date', '>=', now()->subDays(45)->toDateString())
            ->orderByDesc('date')
            ->limit(30)
            ->get(['id', 'date', 'name', 'source']);

        return [
            'upcoming' => $upcoming->map(fn (NoClassDay $d) => $this->row($d))->values()->all(),
            'recent' => $recent->map(fn (NoClassDay $d) => $this->row($d))->values()->all(),
            'today' => $today,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(NoClassDay $d): array
    {
        return [
            'id' => $d->id,
            'date' => $d->date?->toDateString(),
            'weekday' => $d->date?->format('l'),
            'label' => $d->date?->format('F j, Y'),
            'name' => $d->name ?: 'No class',
            'source' => $d->source ?: NoClassDay::SOURCE_MANUAL,
        ];
    }
}
