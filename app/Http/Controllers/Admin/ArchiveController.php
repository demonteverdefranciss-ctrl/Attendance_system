<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use App\Models\Guardian;
use App\Models\NoClassDay;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SoftDeleteUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ArchiveController extends Controller
{
    /** @var array<string, array{label: string, model: class-string}> */
    public const CATEGORIES = [
        'students' => ['label' => 'Students', 'model' => Student::class],
        'teachers' => ['label' => 'Teachers', 'model' => Teacher::class],
        'guardians' => ['label' => 'Parents / Guardians', 'model' => Guardian::class],
        'sections' => ['label' => 'Sections', 'model' => Section::class],
        'cameras' => ['label' => 'Cameras', 'model' => Camera::class],
        'schedules' => ['label' => 'Schedules', 'model' => Schedule::class],
        'no-class-days' => ['label' => 'No-class days', 'model' => NoClassDay::class],
    ];

    public function index(Request $request): Response
    {
        $category = $request->string('category')->toString();
        if (! array_key_exists($category, self::CATEGORIES)) {
            $category = 'students';
        }

        $counts = [];
        foreach (self::CATEGORIES as $key => $meta) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = $meta['model'];
            $counts[$key] = $model::onlyTrashed()->count();
        }

        $rows = $this->archivedRows($category);

        return Inertia::render('Admin/Archive/Index', [
            'category' => $category,
            'categories' => collect(self::CATEGORIES)->map(fn ($meta, $key) => [
                'key' => $key,
                'label' => $meta['label'],
                'count' => $counts[$key],
            ])->values(),
            'rows' => $rows,
        ]);
    }

    public function restore(Request $request, string $category, int $id): RedirectResponse
    {
        $this->assertCategory($category);

        DB::transaction(function () use ($category, $id) {
            match ($category) {
                'students' => $this->restoreStudent($id),
                'teachers' => $this->restoreTeacher($id),
                'guardians' => $this->restoreGuardian($id),
                'sections' => $this->restoreSection($id),
                'cameras' => $this->restoreCamera($id),
                'schedules' => $this->restoreSchedule($id),
                'no-class-days' => $this->restoreNoClassDay($id),
            };
        });

        return redirect()
            ->route('admin.archive.index', ['category' => $category])
            ->with('success', 'Record restored from archive.');
    }

    public function forceDestroy(Request $request, string $category, int $id): RedirectResponse
    {
        $this->assertCategory($category);

        DB::transaction(function () use ($category, $id) {
            match ($category) {
                'students' => Student::onlyTrashed()->findOrFail($id)->forceDelete(),
                'teachers' => $this->forceDeleteTeacher($id),
                'guardians' => $this->forceDeleteGuardian($id),
                'sections' => Section::onlyTrashed()->findOrFail($id)->forceDelete(),
                'cameras' => Camera::onlyTrashed()->findOrFail($id)->forceDelete(),
                'schedules' => Schedule::onlyTrashed()->findOrFail($id)->forceDelete(),
                'no-class-days' => NoClassDay::onlyTrashed()->findOrFail($id)->forceDelete(),
            };
        });

        return redirect()
            ->route('admin.archive.index', ['category' => $category])
            ->with('success', 'Record permanently deleted.');
    }

    private function assertCategory(string $category): void
    {
        if (! array_key_exists($category, self::CATEGORIES)) {
            abort(404);
        }
    }

    private function archivedRows(string $category)
    {
        return match ($category) {
            'students' => Student::onlyTrashed()
                ->with('section:id,name')
                ->orderByDesc('deleted_at')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Student $s) => [
                    'id' => $s->id,
                    'title' => "{$s->last_name}, {$s->first_name}",
                    'subtitle' => 'LRN: '.($this->displayUnique($s->lrn) ?: '—'),
                    'meta' => $s->section?->name ? 'Section: '.$s->section->name : 'No section',
                    'deleted_at' => $s->deleted_at?->toDateTimeString(),
                ]),
            'teachers' => Teacher::onlyTrashed()
                ->with(['user' => fn ($q) => $q->withTrashed()->select('id', 'username', 'email')])
                ->orderByDesc('deleted_at')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Teacher $t) => [
                    'id' => $t->id,
                    'title' => "{$t->last_name}, {$t->first_name}",
                    'subtitle' => 'Username: '.$this->displayUnique($t->user?->username),
                    'meta' => $t->employee_no ? 'Employee #: '.$this->displayUnique($t->employee_no) : null,
                    'deleted_at' => $t->deleted_at?->toDateTimeString(),
                ]),
            'guardians' => Guardian::onlyTrashed()
                ->with(['user' => fn ($q) => $q->withTrashed()->select('id', 'username', 'email')])
                ->orderByDesc('deleted_at')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Guardian $g) => [
                    'id' => $g->id,
                    'title' => "{$g->last_name}, {$g->first_name}",
                    'subtitle' => 'Username: '.$this->displayUnique($g->user?->username),
                    'meta' => $g->phone ? 'Phone: '.$g->phone : null,
                    'deleted_at' => $g->deleted_at?->toDateTimeString(),
                ]),
            'sections' => Section::onlyTrashed()
                ->orderByDesc('deleted_at')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Section $s) => [
                    'id' => $s->id,
                    'title' => $this->displayUnique($s->name),
                    'subtitle' => trim($s->grade_level.' · '.$s->school_year),
                    'meta' => null,
                    'deleted_at' => $s->deleted_at?->toDateTimeString(),
                ]),
            'cameras' => Camera::onlyTrashed()
                ->orderByDesc('deleted_at')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Camera $c) => [
                    'id' => $c->id,
                    'title' => $c->name,
                    'subtitle' => $c->location ?: 'No location',
                    'meta' => null,
                    'deleted_at' => $c->deleted_at?->toDateTimeString(),
                ]),
            'schedules' => Schedule::onlyTrashed()
                ->with(['section' => fn ($q) => $q->withTrashed()->select('id', 'name', 'grade_level')])
                ->orderByDesc('deleted_at')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Schedule $s) => [
                    'id' => $s->id,
                    'title' => $s->section
                        ? $s->section->name.' ('.$s->section->grade_level.')'
                        : 'Unknown section',
                    'subtitle' => (['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][(int) $s->day_of_week] ?? 'Day '.$s->day_of_week)
                        .' · '.$s->start_time.'–'.$s->end_time,
                    'meta' => $s->type ? 'Type: '.$s->type : null,
                    'deleted_at' => $s->deleted_at?->toDateTimeString(),
                ]),
            'no-class-days' => NoClassDay::onlyTrashed()
                ->orderByDesc('deleted_at')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (NoClassDay $d) => [
                    'id' => $d->id,
                    'title' => $d->date?->toDateString() ?? '—',
                    'subtitle' => $d->name ?: 'No label',
                    'meta' => $d->source ? 'Source: '.$d->source : null,
                    'deleted_at' => $d->deleted_at?->toDateTimeString(),
                ]),
            default => Student::onlyTrashed()->paginate(20),
        };
    }

    private function displayUnique(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return preg_replace('/__archived_\d+$/', '', $value) ?? $value;
    }

    private function restoreStudent(int $id): void
    {
        $student = Student::onlyTrashed()->findOrFail($id);
        SoftDeleteUnique::restore($student, ['lrn']);
        $student->restore();
        $student->update(['is_active' => true]);
    }

    private function restoreTeacher(int $id): void
    {
        $teacher = Teacher::onlyTrashed()->findOrFail($id);
        $user = User::onlyTrashed()->find($teacher->user_id);
        SoftDeleteUnique::restore($teacher, ['employee_no']);
        $teacher->restore();
        if ($user) {
            SoftDeleteUnique::restore($user, ['username', 'email']);
            $user->restore();
            $user->update(['is_active' => true]);
        }
    }

    private function restoreGuardian(int $id): void
    {
        $guardian = Guardian::onlyTrashed()->findOrFail($id);
        $user = User::onlyTrashed()->find($guardian->user_id);
        $guardian->restore();
        if ($user) {
            SoftDeleteUnique::restore($user, ['username', 'email']);
            $user->restore();
            $user->update(['is_active' => true]);
        }
    }

    private function restoreSection(int $id): void
    {
        $section = Section::onlyTrashed()->findOrFail($id);
        SoftDeleteUnique::restore($section, ['name']);

        $conflict = Section::query()
            ->where('name', $section->name)
            ->where('grade_level', $section->grade_level)
            ->where('school_year', $section->school_year)
            ->whereKeyNot($section->id)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'section' => 'Cannot restore: an active section already uses this name, grade, and school year.',
            ]);
        }

        $section->restore();
    }

    private function restoreCamera(int $id): void
    {
        $camera = Camera::onlyTrashed()->findOrFail($id);
        $camera->restore();
        $camera->update(['is_active' => true]);
    }

    private function restoreSchedule(int $id): void
    {
        Schedule::onlyTrashed()->findOrFail($id)->restore();
    }

    private function restoreNoClassDay(int $id): void
    {
        $day = NoClassDay::onlyTrashed()->findOrFail($id);

        $conflict = NoClassDay::query()
            ->whereDate('date', $day->date?->toDateString())
            ->whereKeyNot($day->id)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'date' => 'Cannot restore: that date is already marked as a no-class day.',
            ]);
        }

        $day->restore();
    }

    private function forceDeleteTeacher(int $id): void
    {
        $teacher = Teacher::onlyTrashed()->findOrFail($id);
        $userId = $teacher->user_id;
        $teacher->forceDelete();
        User::withTrashed()->whereKey($userId)->forceDelete();
    }

    private function forceDeleteGuardian(int $id): void
    {
        $guardian = Guardian::onlyTrashed()->findOrFail($id);
        $userId = $guardian->user_id;
        $guardian->forceDelete();
        User::withTrashed()->whereKey($userId)->forceDelete();
    }
}
