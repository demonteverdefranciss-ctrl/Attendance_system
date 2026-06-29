<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Teacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function index(): Response
    {
        $sections = Section::with('adviser:id,first_name,last_name')
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Sections/Index', ['sections' => $sections]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Sections/Form', ['teachers' => $this->teacherOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Section::create($this->validateData($request));

        return redirect()->route('admin.sections.index')->with('success', 'Section created successfully.');
    }

    public function edit(Section $section): Response
    {
        return Inertia::render('Admin/Sections/Form', [
            'section' => $section,
            'teachers' => $this->teacherOptions(),
        ]);
    }

    public function update(Request $request, Section $section): RedirectResponse
    {
        $section->update($this->validateData($request, $section));

        return redirect()->route('admin.sections.index')->with('success', 'Section updated successfully.');
    }

    public function destroy(Section $section): RedirectResponse
    {
        $section->delete();

        return redirect()->route('admin.sections.index')->with('success', 'Section deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?Section $section = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'grade_level' => ['required', 'string', 'max:20'],
            'school_year' => ['required', 'string', 'max:20'],
            'adviser_id' => ['nullable', 'exists:teachers,id'],
        ]);
    }

    private function teacherOptions()
    {
        return Teacher::orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);
    }
}
