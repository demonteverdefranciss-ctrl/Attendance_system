<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private AnalyticsService $analytics)
    {
    }

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
        [$from, $to, $trendFrom] = $this->range();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'students' => DB::table('students')->count(),
                'sections' => DB::table('sections')->count(),
                'teachers' => DB::table('teachers')->count(),
                'guardians' => DB::table('guardians')->count(),
            ],
            'summary' => $this->analytics->summary(null, $from, $to),
            'trend' => $this->analytics->dailyTrend(null, $trendFrom, $to),
            'perSection' => $this->analytics->perSection(null, $from, $to),
            'range' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function teacher(): Response
    {
        [$from, $to, $trendFrom] = $this->range();

        $teacher = DB::table('teachers')->where('user_id', Auth::id())->first();
        $sectionIds = $teacher
            ? DB::table('sections')->where('adviser_id', $teacher->id)->pluck('id')->all()
            : [];

        return Inertia::render('Teacher/Dashboard', [
            'stats' => [
                'sections' => count($sectionIds),
                'students' => $sectionIds
                    ? DB::table('students')->whereIn('section_id', $sectionIds)->count()
                    : 0,
            ],
            'summary' => $this->analytics->summary($sectionIds ?: [0], $from, $to),
            'trend' => $this->analytics->dailyTrend($sectionIds ?: [0], $trendFrom, $to),
            'range' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function parent(): Response
    {
        $guardian = DB::table('guardians')->where('user_id', Auth::id())->first();
        $children = $guardian
            ? DB::table('student_guardian')->where('guardian_id', $guardian->id)->count()
            : 0;

        return Inertia::render('Parent/Dashboard', [
            'stats' => ['children' => $children],
        ]);
    }

    /**
     * @return array{0:string,1:string,2:string}  [from, to, trendFrom]
     */
    private function range(): array
    {
        return [
            now()->subDays(29)->toDateString(),
            now()->toDateString(),
            now()->subDays(13)->toDateString(),
        ];
    }
}
