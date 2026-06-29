<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Send the user to the dashboard for their role.
     */
    public function index(): RedirectResponse
    {
        return match (Auth::user()->role?->name) {
            'admin' => redirect()->route('admin.dashboard'),
            'teacher' => redirect()->route('teacher.dashboard'),
            'parent' => redirect()->route('parent.dashboard'),
            default => abort(403, 'No role has been assigned to your account.'),
        };
    }

    public function admin(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'students' => DB::table('students')->count(),
                'sections' => DB::table('sections')->count(),
                'teachers' => DB::table('teachers')->count(),
                'guardians' => DB::table('guardians')->count(),
            ],
        ]);
    }

    public function teacher(): Response
    {
        $teacher = DB::table('teachers')->where('user_id', Auth::id())->first();
        $sectionIds = $teacher
            ? DB::table('sections')->where('adviser_id', $teacher->id)->pluck('id')
            : collect();

        return Inertia::render('Teacher/Dashboard', [
            'stats' => [
                'sections' => $sectionIds->count(),
                'students' => $sectionIds->isEmpty()
                    ? 0
                    : DB::table('students')->whereIn('section_id', $sectionIds)->count(),
            ],
        ]);
    }

    public function parent(): Response
    {
        $guardian = DB::table('guardians')->where('user_id', Auth::id())->first();
        $children = $guardian
            ? DB::table('student_guardian')->where('guardian_id', $guardian->id)->count()
            : 0;

        return Inertia::render('Parent/Dashboard', [
            'stats' => [
                'children' => $children,
            ],
        ]);
    }
}
