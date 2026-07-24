<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response as ResponseFactory;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private AnalyticsService $analytics)
    {
    }

    public function index(Request $request)
    {
        [$scopeIds, $sections, $from, $to, $sectionId, $sessionId] = $this->context($request);

        $effective = $sectionId ? [$sectionId] : $scopeIds;
        $sessions = $this->analytics->recentSessions($scopeIds, $sectionId);

        return Inertia::render('Reports/Index', [
            'sections' => $sections,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'section_id' => $sectionId,
                'session_id' => $sessionId,
            ],
            'sessions' => $sessions,
            'summary' => $this->analytics->summary($effective, $from, $to, $sessionId),
            'methodBreakdown' => $this->analytics->methodBreakdown($effective, $from, $to, $sessionId),
            'atRisk' => $sessionId
                ? []
                : $this->analytics->atRiskStudents($effective, $from, $to),
            'records' => $this->analytics->records($scopeIds, $from, $to, $sectionId, $sessionId)->values(),
        ]);
    }

    public function session(Request $request, AttendanceSession $session): Response
    {
        $this->authorizeSession($request, $session);

        $session->load('section:id,name,grade_level');

        $from = $session->session_date?->toDateString() ?? now()->toDateString();
        $to = $from;

        $summary = $this->analytics->summary([$session->section_id], $from, $to, $session->id);
        $methodBreakdown = $this->analytics->methodBreakdown([$session->section_id], $from, $to, $session->id);
        $records = $this->analytics->records([$session->section_id], $from, $to, null, $session->id)->values();

        return Inertia::render('Reports/Session', [
            'session' => [
                'id' => $session->id,
                'session_date' => $session->session_date?->toDateString(),
                'status' => $session->status,
                'opened_at' => $session->opened_at?->toDateTimeString(),
                'closed_at' => $session->closed_at?->toDateTimeString(),
                'section' => $session->section
                    ? "{$session->section->grade_level} - {$session->section->name}"
                    : '—',
                'section_id' => $session->section_id,
                'is_adhoc' => $session->schedule_id === null,
            ],
            'summary' => $summary,
            'methodBreakdown' => $methodBreakdown,
            'records' => $records,
        ]);
    }

    public function student(Request $request, Student $student): Response
    {
        $this->authorizeStudent($request, $student);

        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to = $request->query('to', now()->toDateString());

        $analytics = $this->analytics->studentAnalytics($student, $from, $to);

        return Inertia::render('Reports/Student', [
            'filters' => ['from' => $from, 'to' => $to],
            ...$analytics,
        ]);
    }

    public function csv(Request $request)
    {
        [$scopeIds, , $from, $to, $sectionId, $sessionId] = $this->context($request);
        $rows = $this->analytics->records($scopeIds, $from, $to, $sectionId, $sessionId);

        $filename = $sessionId
            ? "attendance_session_{$sessionId}.csv"
            : "attendance_{$from}_to_{$to}.csv";

        return ResponseFactory::streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Section', 'Student', 'Status', 'Time In', 'Time Out', 'Method']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['date'], $r['section'], $r['student'], $r['status'], $r['time_in'], $r['time_out'], $r['method']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function pdf(Request $request)
    {
        [$scopeIds, , $from, $to, $sectionId, $sessionId] = $this->context($request);
        $effective = $sectionId ? [$sectionId] : $scopeIds;

        $records = $this->analytics->records($scopeIds, $from, $to, $sectionId, $sessionId);
        $summary = $this->analytics->summary($effective, $from, $to, $sessionId);

        $filename = $sessionId
            ? "attendance_session_{$sessionId}.pdf"
            : "attendance_{$from}_to_{$to}.pdf";

        return Pdf::loadView('reports.attendance', compact('records', 'summary', 'from', 'to'))
            ->download($filename);
    }

    /**
     * Resolve scope (role-based), section options, and validated filters.
     *
     * @return array{0:array<int,int>|null,1:mixed,2:string,3:string,4:int|null,5:int|null}
     */
    private function context(Request $request): array
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            $scopeIds = null; // all sections
            $sections = Section::orderBy('name')->get(['id', 'name', 'grade_level']);
        } else {
            $teacher = Teacher::where('user_id', $user->id)->first();
            $sections = $teacher
                ? $teacher->sections()->orderBy('name')->get(['id', 'name', 'grade_level'])
                : collect();
            $scopeIds = $sections->pluck('id')->all() ?: [0];
        }

        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to = $request->query('to', now()->toDateString());
        $sectionId = $request->query('section_id') ? (int) $request->query('section_id') : null;
        $sessionId = $request->query('session_id') ? (int) $request->query('session_id') : null;

        // Prevent a teacher from querying a section outside their scope.
        if ($sectionId && $scopeIds !== null && ! in_array($sectionId, $scopeIds, true)) {
            abort(403, 'You cannot access this section.');
        }

        if ($sessionId) {
            $session = AttendanceSession::findOrFail($sessionId);
            $this->authorizeSession($request, $session);
        }

        return [$scopeIds, $sections, $from, $to, $sectionId, $sessionId];
    }

    private function authorizeSession(Request $request, AttendanceSession $session): void
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        $sectionIds = $teacher ? $teacher->sections()->pluck('id')->all() : [];

        abort_unless(in_array($session->section_id, $sectionIds, true), 403);
    }

    private function authorizeStudent(Request $request, Student $student): void
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return;
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        $sectionIds = $teacher ? $teacher->sections()->pluck('id')->all() : [];

        abort_unless($student->section_id && in_array($student->section_id, $sectionIds, true), 403);
    }
}
