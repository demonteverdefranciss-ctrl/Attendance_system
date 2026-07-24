<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AttendanceService;
use App\Services\RecognitionProcessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private RecognitionProcessService $recognition,
    ) {
    }

    /**
     * List the teacher's sections with today's session status + counts.
     */
    public function index(): Response
    {
        $sectionIds = $this->sectionIds();
        $today = now()->toDateString();

        $sections = Section::whereIn('id', $sectionIds)
            ->withCount(['students' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        $sessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->whereDate('session_date', $today)
            ->withCount([
                'records as present_count' => fn ($q) => $q->whereIn('status', ['present', 'late']),
                'records as absent_count' => fn ($q) => $q->where('status', 'absent'),
            ])
            ->orderByDesc('opened_at')
            ->get()
            ->groupBy('section_id')
            ->map(function ($group) {
                // Prefer an open session so Close/Re-open matches what the camera uses.
                return $group->firstWhere('status', 'open')
                    ?? $group->sortByDesc('opened_at')->first();
            });

        $rows = $sections->map(fn ($section) => [
            'section' => $section,
            'session' => $sessions->get($section->id),
        ]);

        $adhocMax = (int) config('attendance.adhoc_session_max_minutes', 30);

        $payloadRows = $rows->map(function ($row) use ($adhocMax) {
            $session = $row['session'];
            $sessionPayload = null;
            if ($session) {
                $autoCloseAt = null;
                if (
                    $session->status === 'open'
                    && $session->schedule_id === null
                    && $adhocMax > 0
                    && $session->opened_at
                ) {
                    $autoCloseAt = $session->opened_at->copy()->addMinutes($adhocMax)->toDateTimeString();
                }

                $sessionPayload = [
                    'id' => $session->id,
                    'status' => $session->status,
                    'present_count' => $session->present_count,
                    'absent_count' => $session->absent_count,
                    'opened_at' => $session->opened_at?->toDateTimeString(),
                    'schedule_id' => $session->schedule_id,
                    'is_adhoc' => $session->schedule_id === null,
                    'auto_close_at' => $autoCloseAt,
                ];
            }

            return [
                'section' => $row['section'],
                'session' => $sessionPayload,
            ];
        });

        return Inertia::render('Teacher/Attendance/Index', [
            'rows' => $payloadRows,
            'today' => $today,
            'adhocMaxMinutes' => $adhocMax,
            'testClearEnabled' => (bool) config('attendance.test_clear_enabled', false),
            'recognition' => $this->recognition->snapshot(),
        ]);
    }

    /**
     * TEMPORARY (dev/testing): delete today's sessions + records for the
     * teacher's sections so face recognition can be retested from a clean slate.
     * Remove this action (and the UI button) before final handover.
     */
    public function clearTodayForTesting(): RedirectResponse
    {
        abort_unless(config('attendance.test_clear_enabled'), 404);

        $sectionIds = $this->sectionIds();
        $sessions = AttendanceSession::whereIn('section_id', $sectionIds)
            ->whereDate('session_date', now()->toDateString())
            ->get();

        $sessionCount = $sessions->count();
        $recordCount = 0;

        foreach ($sessions as $session) {
            $recordCount += $session->records()->count();
            $session->delete();
        }

        $this->attendance->stopRecognitionIfIdle();

        return redirect()->route('teacher.attendance.index')
            ->with(
                'success',
                "TEST CLEAR: removed {$sessionCount} session(s) and {$recordCount} record(s) for today. Re-open Attendance to test again."
            );
    }

    /**
     * Open an ad-hoc session for one of the teacher's sections (today).
     */
    public function open(Request $request): RedirectResponse
    {
        $data = $request->validate(['section_id' => ['required', 'integer']]);
        $sectionId = (int) $data['section_id'];
        abort_unless(in_array($sectionId, $this->sectionIds(), true), 403);

        $section = Section::findOrFail($sectionId);
        $session = $this->attendance->openSession($section, now());

        $recognitionStarted = $this->recognition->ensureRunning();

        return redirect()->route('teacher.attendance.show', $session->id)
            ->with(
                $recognitionStarted || ! $this->recognition->isEnabled()
                    ? 'success'
                    : 'warning',
                $recognitionStarted || ! $this->recognition->isEnabled()
                    ? 'Attendance session opened.'
                    : 'Session opened, but face recognition could not be started automatically. Use Start recognition on the mark page.'
            );
    }

    /**
     * Show the marking screen for a session.
     */
    public function show(AttendanceSession $session): Response
    {
        $this->authorizeSession($session);

        $session->load('section');

        $students = Student::where('section_id', $session->section_id)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        $records = $session->records()
            ->get(['student_id', 'status', 'time_in', 'time_out'])
            ->mapWithKeys(fn ($r) => [
                $r->student_id => [
                    'status' => $r->status,
                    'time_in' => $r->time_in?->toDateTimeString(),
                    'time_out' => $r->time_out?->toDateTimeString(),
                ],
            ]);

        return Inertia::render('Teacher/Attendance/Mark', [
            'session' => $session,
            'students' => $students,
            'records' => $records,
            'cameraStreamUrl' => config('camera.stream_url') ? route('camera.stream') : null,
            'recognition' => $this->recognition->snapshot(),
        ]);
    }

    /**
     * Save the marked statuses (duplicate-safe upserts).
     */
    public function store(Request $request, AttendanceSession $session): RedirectResponse
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'records' => ['required', 'array'],
            'records.*' => ['in:present,late,absent,excused'],
        ]);

        $validStudentIds = Student::where('section_id', $session->section_id)
            ->pluck('id')
            ->all();

        foreach ($data['records'] as $studentId => $status) {
            if (in_array((int) $studentId, $validStudentIds, true)) {
                $this->attendance->mark($session, (int) $studentId, $status, [
                    'marked_by' => $request->user()->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
        }

        return redirect()->route('teacher.attendance.show', $session->id)
            ->with('success', 'Attendance saved.');
    }

    public function close(AttendanceSession $session): RedirectResponse
    {
        $this->authorizeSession($session);

        $this->attendance->closeSession($session);

        return redirect()->route('teacher.attendance.index')
            ->with('success', 'Session closed. Unmarked students were recorded absent.');
    }

    public function recordTimeOut(Request $request, AttendanceSession $session, Student $student): RedirectResponse
    {
        $this->authorizeSession($session);

        if ($session->status === 'closed') {
            return redirect()->route('teacher.attendance.show', $session->id)
                ->with('error', 'Cannot record time-out on a closed session.');
        }

        if ($student->section_id !== $session->section_id) {
            abort(403);
        }

        try {
            $this->attendance->recordTimeOut($session, $student->id, now(), [
                'marked_by' => $request->user()->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('teacher.attendance.show', $session->id)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('teacher.attendance.show', $session->id)
            ->with('success', 'Time-out recorded.');
    }

    /**
     * @return array<int, int>
     */
    private function sectionIds(): array
    {
        $teacher = Teacher::where('user_id', auth()->id())->first();

        return $teacher ? $teacher->sections()->pluck('id')->all() : [];
    }

    private function authorizeSession(AttendanceSession $session): void
    {
        abort_unless(in_array($session->section_id, $this->sectionIds(), true), 403);
    }
}
