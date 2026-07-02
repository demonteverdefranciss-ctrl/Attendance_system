<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /**
     * Status totals + attendance rate for the given sections and date range.
     *
     * @param  array<int, int>|null  $sectionIds  null = all sections
     * @return array<string, int|float>
     */
    public function summary(?array $sectionIds, string $from, string $to): array
    {
        $counts = $this->baseQuery($sectionIds, $from, $to)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $present = (int) ($counts['present'] ?? 0);
        $late = (int) ($counts['late'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);
        $excused = (int) ($counts['excused'] ?? 0);
        $total = $present + $late + $absent + $excused;

        return [
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'excused' => $excused,
            'total' => $total,
            'rate' => $total ? round(($present + $late) / $total * 100, 1) : 0,
        ];
    }

    /**
     * Daily attendance-rate trend (for a line chart).
     *
     * @param  array<int, int>|null  $sectionIds
     * @return array<int, array<string, mixed>>
     */
    public function dailyTrend(?array $sectionIds, string $from, string $to): array
    {
        return $this->baseQuery($sectionIds, $from, $to)
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.session_id')
            ->selectRaw("attendance_sessions.session_date as day,
                SUM(CASE WHEN attendance_records.status IN ('present','late') THEN 1 ELSE 0 END) as present,
                COUNT(*) as total")
            ->groupBy('attendance_sessions.session_date')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day' => Carbon::parse($r->day)->format('M j'),
                'rate' => $r->total ? round($r->present / $r->total * 100, 1) : 0,
            ])
            ->all();
    }

    /**
     * Attendance rate per section (for a bar chart / breakdown).
     *
     * @param  array<int, int>|null  $sectionIds
     * @return array<int, array<string, mixed>>
     */
    public function perSection(?array $sectionIds, string $from, string $to): array
    {
        return $this->baseQuery($sectionIds, $from, $to)
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.session_id')
            ->join('sections', 'sections.id', '=', 'attendance_sessions.section_id')
            ->selectRaw("sections.name as section,
                SUM(CASE WHEN attendance_records.status IN ('present','late') THEN 1 ELSE 0 END) as present,
                COUNT(*) as total")
            ->groupBy('sections.id', 'sections.name')
            ->orderBy('sections.name')
            ->get()
            ->map(fn ($r) => [
                'section' => $r->section,
                'rate' => $r->total ? round($r->present / $r->total * 100, 1) : 0,
            ])
            ->all();
    }

    /**
     * Detailed records for the report table / exports.
     *
     * @param  array<int, int>|null  $sectionIds
     */
    public function records(?array $sectionIds, string $from, string $to, ?int $sectionId = null): Collection
    {
        return $this->baseQuery($sectionIds, $from, $to)
            ->when($sectionId, fn ($q) => $q->whereHas('session', fn ($s) => $s->where('section_id', $sectionId)))
            ->with(['student:id,first_name,last_name', 'session:id,section_id,session_date', 'session.section:id,name'])
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn ($r) => [
                'date' => $r->session?->session_date?->toDateString(),
                'section' => $r->session?->section?->name,
                'student' => $r->student ? "{$r->student->last_name}, {$r->student->first_name}" : '—',
                'status' => $r->status,
                'time_in' => $r->time_in?->format('H:i'),
                'method' => $r->method,
            ]);
    }

    /**
     * Base query constrained to sections + date range.
     *
     * @param  array<int, int>|null  $sectionIds
     */
    private function baseQuery(?array $sectionIds, string $from, string $to)
    {
        return AttendanceRecord::query()
            ->whereHas('session', function ($s) use ($sectionIds, $from, $to) {
                $s->whereBetween('session_date', [$from, $to]);
                if ($sectionIds !== null) {
                    $s->whereIn('section_id', $sectionIds);
                }
            });
    }
}
