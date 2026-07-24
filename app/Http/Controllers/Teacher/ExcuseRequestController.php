<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceExcuseRequest;
use App\Models\Teacher;
use App\Services\ExcuseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExcuseRequestController extends Controller
{
    public function __construct(private ExcuseRequestService $excuses)
    {
    }

    public function index(Request $request): Response
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();
        $sectionIds = $teacher->sections()->pluck('id')->all();

        $items = AttendanceExcuseRequest::with([
            'student:id,first_name,last_name,lrn,section_id',
            'student.section:id,name,grade_level',
            'guardian:id,first_name,last_name,phone',
        ])
            ->where('status', 'pending')
            ->whereHas('student', fn ($q) => $q->whereIn('section_id', $sectionIds))
            ->latest('id')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'student' => $r->student?->full_name,
                'lrn' => $r->student?->lrn,
                'section' => $r->student?->section
                    ? "{$r->student->section->grade_level} - {$r->student->section->name}"
                    : '—',
                'guardian' => $r->guardian?->full_name,
                'guardian_phone' => $r->guardian?->phone,
                'streak_count' => $r->streak_count,
                'streak_summary' => $r->streak_summary,
                'letter_body' => $r->letter_body,
                'submitted_at' => $r->submitted_at?->toDateTimeString(),
            ]);

        return Inertia::render('Teacher/ExcuseRequests/Index', [
            'requests' => $items,
        ]);
    }

    public function approve(Request $request, AttendanceExcuseRequest $excuseRequest): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();

        try {
            $this->excuses->approve($teacher, $excuseRequest, $data['notes'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('teacher.excuse-requests.index')->with('error', $e->getMessage());
        }

        return redirect()->route('teacher.excuse-requests.index')
            ->with('success', 'Explanation approved. Related absences/lates were marked excused.');
    }

    public function reject(Request $request, AttendanceExcuseRequest $excuseRequest): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();

        try {
            $this->excuses->reject($teacher, $excuseRequest, $data['notes'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('teacher.excuse-requests.index')->with('error', $e->getMessage());
        }

        return redirect()->route('teacher.excuse-requests.index')
            ->with('success', 'Explanation letter rejected.');
    }
}
