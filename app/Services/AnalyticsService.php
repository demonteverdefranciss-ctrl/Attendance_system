<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Student;
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
     * Students whose attendance rate is below the threshold (default 80%).
     *
     * @param  array<int, int>|null  $sectionIds
     * @return array<int, array<string, mixed>>
     */
    public function atRiskStudents(?array $sectionIds, string $from, string $to, float $threshold = 80.0, int $limit = 15): array
    {
        $rows = $this->baseQuery($sectionIds, $from, $to)
            ->join('students', 'students.id', '=', 'attendance_records.student_id')
            ->leftJoin('sections', 'sections.id', '=', 'students.section_id')
            ->selectRaw("students.id as student_id,
                students.first_name,
                students.last_name,
                sections.name as section,
                SUM(CASE WHEN attendance_records.status IN ('present','late') THEN 1 ELSE 0 END) as attended,
                COUNT(*) as total")
            ->groupBy('students.id', 'students.first_name', 'students.last_name', 'sections.name')
            ->havingRaw('COUNT(*) > 0')
            ->get()
            ->map(function ($r) {
                $rate = $r->total ? round($r->attended / $r->total * 100, 1) : 0;

                return [
                    'student_id' => (int) $r->student_id,
                    'name' => "{$r->last_name}, {$r->first_name}",
                    'section' => $r->section ?? '—',
                    'attended' => (int) $r->attended,
                    'total' => (int) $r->total,
                    'rate' => $rate,
                ];
            })
            ->filter(fn ($r) => $r['rate'] < $threshold)
            ->sortBy('rate')
            ->take($limit)
            ->values()
            ->all();

        return $rows;
    }

    /**
     * Counts by marking method (face / manual / other) for a doughnut chart.
     *
     * @param  array<int, int>|null  $sectionIds
     * @return array<string, int>
     */
    public function methodBreakdown(?array $sectionIds, string $from, string $to): array
    {
        $counts = $this->baseQuery($sectionIds, $from, $to)
            ->selectRaw("COALESCE(NULLIF(method, ''), 'unknown') as method, COUNT(*) as c")
            ->groupBy('method')
            ->pluck('c', 'method');

        return [
            'face' => (int) ($counts['face'] ?? 0),
            'manual' => (int) ($counts['manual'] ?? 0),
            'other' => (int) $counts->except(['face', 'manual'])->sum(),
            'total' => (int) $counts->sum(),
        ];
    }

    /**
     * Per-student summary + daily attendance trend.
     *
     * @return array{summary: array<string, int|float>, trend: array<int, array<string, mixed>>, student: array<string, mixed>}
     */
    public function studentAnalytics(Student $student, string $from, string $to): array
    {
        $summaryQuery = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->whereHas('session', fn ($s) => $s->whereBetween('session_date', [$from, $to]));

        $counts = (clone $summaryQuery)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $present = (int) ($counts['present'] ?? 0);
        $late = (int) ($counts['late'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);
        $excused = (int) ($counts['excused'] ?? 0);
        $total = $present + $late + $absent + $excused;

        $trend = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.session_id')
            ->whereBetween('attendance_sessions.session_date', [$from, $to])
            ->selectRaw("attendance_sessions.session_date as day,
                MAX(CASE WHEN attendance_records.status IN ('present','late') THEN 1 ELSE 0 END) as attended")
            ->groupBy('attendance_sessions.session_date')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => [
                'day' => Carbon::parse($r->day)->format('M j'),
                'attended' => (int) $r->attended,
            ])
            ->all();

        $student->loadMissing('section:id,name');

        return [
            'student' => [
                'id' => $student->id,
                'name' => "{$student->last_name}, {$student->first_name}",
                'section' => $student->section?->name,
                'lrn' => $student->lrn,
            ],
            'summary' => [
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'excused' => $excused,
                'total' => $total,
                'rate' => $total ? round(($present + $late) / $total * 100, 1) : 0,
            ],
            'trend' => $trend,
        ];
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
                'student_id' => $r->student_id,
                'student' => $r->student ? "{$r->student->last_name}, {$r->student->first_name}" : '—',
                'status' => $r->status,
                'time_in' => $r->time_in?->format('H:i'),
                'time_out' => $r->time_out?->format('H:i'),
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
