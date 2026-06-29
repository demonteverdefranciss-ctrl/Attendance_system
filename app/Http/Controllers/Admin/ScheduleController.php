<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(): Response
    {
        $schedules = Schedule::with('section:id,name')
            ->orderBy('section_id')
            ->orderBy('day_of_week')
            ->get();

        return Inertia::render('Admin/Schedules/Index', ['schedules' => $schedules]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Schedules/Form', ['sections' => $this->sectionOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Schedule::create($this->validateData($request));

        return redirect()->route('admin.schedules.index')->with('success', 'Schedule added successfully.');
    }

    public function edit(Schedule $schedule): Response
    {
        return Inertia::render('Admin/Schedules/Form', [
            'schedule' => $schedule,
            'sections' => $this->sectionOptions(),
        ]);
    }

    public function update(Request $request, Schedule $schedule): RedirectResponse
    {
        $schedule->update($this->validateData($request));

        return redirect()->route('admin.schedules.index')->with('success', 'Schedule updated successfully.');
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->route('admin.schedules.index')->with('success', 'Schedule deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'section_id' => ['required', 'exists:sections,id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'late_after' => ['nullable', 'date_format:H:i'],
            'type' => ['required', Rule::in(['am', 'pm', 'custom'])],
            'is_active' => ['boolean'],
        ]);
    }

    private function sectionOptions()
    {
        return Section::orderBy('name')->get(['id', 'name', 'grade_level']);
    }
}
