<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Services\BiometricPrivacyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(private BiometricPrivacyService $biometricPrivacy)
    {
    }

    public function index(): Response
    {
        $students = Student::with('section:id,name')
            ->orderBy('last_name')
            ->get();

        return Inertia::render('Admin/Students/Index', ['students' => $students]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Students/Form', [
            'sections' => $this->sectionOptions(),
            'guardians' => $this->guardianOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $student = Student::create($this->studentAttributes($data));
        $student->guardians()->sync($this->guardianPivot($data));

        return redirect()->route('admin.students.index')->with('success', 'Student added successfully.');
    }

    public function edit(Student $student): Response
    {
        $student->load('guardians:id');

        return Inertia::render('Admin/Students/Form', [
            'student' => $student,
            'guardianIds' => $student->guardians->pluck('id'),
            'sections' => $this->sectionOptions(),
            'guardians' => $this->guardianOptions(),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $this->validateData($request, $student);

        $hadConsent = $student->consent_biometric;

        $student->update($this->studentAttributes($data));
        $student->guardians()->sync($this->guardianPivot($data));

        if ($hadConsent && ! $student->consent_biometric) {
            $this->biometricPrivacy->purgeForStudent($student->fresh());
        }

        return redirect()->route('admin.students.index')->with('success', 'Student updated successfully.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->delete();

        return redirect()->route('admin.students.index')->with('success', 'Student deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'lrn' => ['nullable', 'string', 'max:20', Rule::unique('students', 'lrn')->ignore($student?->id)],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birthdate' => ['nullable', 'date'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'consent_biometric' => ['boolean'],
            'guardian_ids' => ['array'],
            'guardian_ids.*' => ['exists:guardians,id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function studentAttributes(array $data): array
    {
        return [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'lrn' => $data['lrn'] ?? null,
            'gender' => $data['gender'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'section_id' => $data['section_id'] ?? null,
            'consent_biometric' => $data['consent_biometric'] ?? false,
        ];
    }

    /**
     * Build the pivot payload; the first guardian is marked primary.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function guardianPivot(array $data): array
    {
        $ids = $data['guardian_ids'] ?? [];
        $pivot = [];

        foreach (array_values($ids) as $i => $id) {
            $pivot[$id] = ['is_primary' => $i === 0];
        }

        return $pivot;
    }

    private function sectionOptions()
    {
        return Section::orderBy('name')->get(['id', 'name', 'grade_level']);
    }

    private function guardianOptions()
    {
        return Guardian::orderBy('last_name')->get(['id', 'first_name', 'last_name']);
    }
}
