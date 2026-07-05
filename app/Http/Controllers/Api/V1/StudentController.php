<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $students = $this->scopedQuery($request->user())
            ->with('section:id,name')
            ->orderBy('last_name')
            ->get();

        return $this->ok($students->map(fn ($s) => $this->payload($s)));
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        if (! $request->user()->canAccessStudent($student)) {
            return $this->fail('You cannot access this student.', 'FORBIDDEN', 403);
        }

        return $this->ok($this->payload($student->load('section:id,name')));
    }

    public function attendance(Request $request, Student $student): JsonResponse
    {
        if (! $request->user()->canAccessStudent($student)) {
            return $this->fail('You cannot access this student.', 'FORBIDDEN', 403);
        }

        $records = $student->attendanceRecords()
            ->with('session:id,session_date')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn ($r) => [
                'date' => $r->session?->session_date?->toDateString(),
                'status' => $r->status,
                'time_in' => $r->time_in?->toDateTimeString(),
                'time_out' => $r->time_out?->toDateTimeString(),
                'method' => $r->method,
            ]);

        return $this->ok($records);
    }

    /**
     * Restrict the student set to what the user is allowed to see.
     */
    private function scopedQuery(User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return Student::query();
        }

        if ($user->hasRole('teacher')) {
            $sectionIds = $user->teacher ? $user->teacher->sections()->pluck('id') : collect();

            return Student::whereIn('section_id', $sectionIds);
        }

        $studentIds = $user->guardian ? $user->guardian->students()->pluck('students.id') : collect();

        return Student::whereIn('id', $studentIds);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Student $s): array
    {
        return [
            'id' => $s->id,
            'first_name' => $s->first_name,
            'last_name' => $s->last_name,
            'lrn' => $s->lrn,
            'section' => $s->section?->name,
        ];
    }
}
