<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\BiometricPhotoSubmission;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\BiometricPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BiometricPhotoController extends Controller
{
    public function __construct(
        private BiometricPhotoService $photos,
        private AuditService $audit
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $guardian = $request->user()->guardian;
        if (! $guardian) {
            abort(403);
        }

        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'consent_acknowledged' => ['accepted'],
            'photos' => ['required', 'array', 'min:1', 'max:'.BiometricPhotoService::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png', 'max:2048'],
        ]);

        $student = Student::findOrFail($data['student_id']);

        if (! $guardian->students()->where('students.id', $student->id)->exists()) {
            return redirect()->route('parent.dashboard')
                ->with('error', 'You can only upload photos for your linked children.');
        }

        $pendingExists = BiometricPhotoSubmission::where('student_id', $student->id)
            ->where('status', 'pending')
            ->exists();

        if ($pendingExists) {
            return redirect()->route('parent.dashboard')
                ->with('error', 'A photo submission for this child is already pending teacher review.');
        }

        $approvedExists = BiometricPhotoSubmission::where('student_id', $student->id)
            ->where('status', 'approved')
            ->whereNull('synced_at')
            ->exists();

        if ($approvedExists) {
            return redirect()->route('parent.dashboard')
                ->with('error', 'Approved photos for this child are awaiting import at school.');
        }

        $submission = $this->photos->createSubmission(
            $student,
            $guardian->id,
            $data['photos'],
            true
        );

        $this->audit->log(
            action: 'biometric_photos_submitted',
            userId: $request->user()->id,
            entity: $submission,
            newValues: [
                'student_id' => $student->id,
                'photo_count' => count($data['photos']),
                'status' => 'pending',
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()->route('parent.dashboard')
            ->with('success', 'Face photos submitted. A teacher will review them before enrollment.');
    }
}
