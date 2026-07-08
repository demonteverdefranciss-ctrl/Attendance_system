<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\BiometricPhoto;
use App\Models\BiometricPhotoSubmission;
use App\Models\Teacher;
use App\Services\AuditService;
use App\Services\BiometricPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BiometricPhotoController extends Controller
{
    public function __construct(
        private BiometricPhotoService $photos,
        private AuditService $audit
    ) {
    }

    public function index(Request $request): Response
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();
        $sectionIds = $teacher->sections()->pluck('id')->all();

        $items = BiometricPhotoSubmission::with([
            'student:id,first_name,last_name,lrn,section_id',
            'student.section:id,name,grade_level',
            'guardian:id,first_name,last_name,phone',
            'photos:id,submission_id,original_name,sort_order',
        ])
            ->where('status', 'pending')
            ->whereHas('student', fn ($q) => $q->whereIn('section_id', $sectionIds))
            ->latest('id')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'student' => $s->student?->full_name,
                'lrn' => $s->student?->lrn,
                'section' => $s->student?->section
                    ? "{$s->student->section->grade_level} - {$s->student->section->name}"
                    : '—',
                'guardian' => $s->guardian?->full_name,
                'guardian_phone' => $s->guardian?->phone,
                'photo_count' => $s->photos->count(),
                'photos' => $s->photos->map(fn ($p) => [
                    'id' => $p->id,
                    'url' => route('teacher.biometric-photos.file', $p->id),
                    'name' => $p->original_name,
                ]),
                'created_at' => $s->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Teacher/BiometricPhotos/Index', [
            'submissions' => $items,
        ]);
    }

    public function approve(Request $request, BiometricPhotoSubmission $submission): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();
        $this->authorizeSubmission($teacher, $submission);

        if ($submission->status !== 'pending') {
            return redirect()->route('teacher.biometric-photos.index')
                ->with('error', 'This submission has already been reviewed.');
        }

        $this->photos->approve($submission, $teacher, $data['notes'] ?? null);

        $this->audit->log(
            action: 'biometric_photos_approved',
            userId: $request->user()->id,
            entity: $submission,
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'approved', 'teacher_id' => $teacher->id],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()->route('teacher.biometric-photos.index')
            ->with('success', 'Photos approved. Biometric consent recorded for the student.');
    }

    public function reject(Request $request, BiometricPhotoSubmission $submission): RedirectResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();
        $this->authorizeSubmission($teacher, $submission);

        if ($submission->status !== 'pending') {
            return redirect()->route('teacher.biometric-photos.index')
                ->with('error', 'This submission has already been reviewed.');
        }

        $this->photos->reject($submission, $teacher, $data['notes'] ?? null);

        $this->audit->log(
            action: 'biometric_photos_rejected',
            userId: $request->user()->id,
            entity: $submission,
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'rejected', 'teacher_id' => $teacher->id],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()->route('teacher.biometric-photos.index')
            ->with('success', 'Photo submission rejected.');
    }

    public function file(Request $request, BiometricPhoto $photo): StreamedResponse
    {
        $teacher = Teacher::where('user_id', $request->user()->id)->firstOrFail();
        $submission = $photo->submission()->with('student')->firstOrFail();
        $this->authorizeSubmission($teacher, $submission);

        abort_unless(Storage::disk('local')->exists($photo->storage_path), 404);

        return Storage::disk('local')->response($photo->storage_path);
    }

    private function authorizeSubmission(Teacher $teacher, BiometricPhotoSubmission $submission): void
    {
        $sectionIds = $teacher->sections()->pluck('id')->all();
        $studentSectionId = $submission->student?->section_id;

        abort_unless($studentSectionId && in_array($studentSectionId, $sectionIds, true), 403);
    }
}
