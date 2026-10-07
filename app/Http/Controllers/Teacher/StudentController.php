<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Student;
use App\Support\InputRules;
use App\Support\SoftDeleteUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $this->teacher($request);
        $sectionIds = $teacher->sections()->pluck('id');

        $students = Student::with('section:id,name,grade_level')
            ->whereIn('section_id', $sectionIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Teacher/Students/Index', [
            'students' => $students,
            'canAddStudents' => (bool) $teacher->can_add_students,
            'canEditStudents' => (bool) $teacher->can_edit_students,
            'canArchiveStudents' => (bool) $teacher->can_archive_students,
        ]);
    }

    public function create(Request $request): Response
    {
        $teacher = $this->teacher($request);
        abort_unless($teacher->can_add_students, 403);

        return Inertia::render('Teacher/Students/Form', [
            'sections' => $teacher->sections()->orderBy('grade_level')->orderBy('name')->get(['id', 'name', 'grade_level']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = $this->teacher($request);
        abort_unless($teacher->can_add_students, 403);

        $data = $request->validate([
            'first_name' => InputRules::personName(),
            'last_name' => InputRules::personName(),
            'lrn' => InputRules::lrn(false, Rule::unique('students', 'lrn')),
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'section_id' => ['required', Rule::exists('sections', 'id')],
        ], InputRules::messages());

        abort_unless($teacher->sections()->whereKey($data['section_id'])->exists(), 403);
        Student::create($data + ['consent_biometric' => false, 'is_active' => true]);

        return redirect()->route('teacher.students.index')->with('success', 'Student added successfully. A parent or administrator can record biometric consent later.');
    }

    public function edit(Request $request, Student $student): Response
    {
        $teacher = $this->teacher($request);
        abort_unless($teacher->can_edit_students && $teacher->sections()->whereKey($student->section_id)->exists(), 403);

        return Inertia::render('Teacher/Students/Form', [
            'student' => $student,
            'sections' => $teacher->sections()->orderBy('grade_level')->orderBy('name')->get(['id', 'name', 'grade_level']),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $teacher = $this->teacher($request);
        abort_unless($teacher->can_edit_students && $teacher->sections()->whereKey($student->section_id)->exists(), 403);

        $data = $request->validate([
            'first_name' => InputRules::personName(),
            'last_name' => InputRules::personName(),
            'lrn' => InputRules::lrn(false, Rule::unique('students', 'lrn')->ignore($student->id)),
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'section_id' => ['required', Rule::exists('sections', 'id')],
        ], InputRules::messages());

        abort_unless($teacher->sections()->whereKey($data['section_id'])->exists(), 403);
        $student->update($data);

        return redirect()->route('teacher.students.index')->with('success', 'Student updated successfully.');
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $teacher = $this->teacher($request);
        abort_unless($teacher->can_archive_students && $student->section_id && $teacher->sections()->whereKey($student->section_id)->exists(), 403);

        SoftDeleteUnique::archive($student, ['lrn']);
        $student->update(['is_active' => false]);
        $student->delete();

        return redirect()->route('teacher.students.index')->with('success', 'Student moved to archive.');
    }

    private function teacher(Request $request): \App\Models\Teacher
    {
        return $request->user()->teacher()->firstOrFail();
    }
}
