<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance)
    {
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
            ->unique('section_id')
            ->keyBy('section_id');

        $rows = $sections->map(fn ($section) => [
            'section' => $section,
            'session' => $sessions->get($section->id),
        ]);

        return Inertia::render('Teacher/Attendance/Index', [
            'rows' => $rows,
            'today' => $today,
        ]);
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

        return redirect()->route('teacher.attendance.show', $session->id);
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
