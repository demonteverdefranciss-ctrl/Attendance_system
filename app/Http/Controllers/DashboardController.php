<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\AnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
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

        $notifications = $guardian
            ? Notification::where('guardian_id', $guardian->id)
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'body' => $n->body,
                    'status' => $n->status,
                    'sent_at' => $n->sent_at?->toDateTimeString(),
                    'read_at' => $n->read_at?->toDateTimeString(),
                ])
                ->values()
            : collect();

        $unreadCount = $guardian
            ? Notification::where('guardian_id', $guardian->id)->whereNull('read_at')->count()
            : 0;

        return Inertia::render('Parent/Dashboard', [
            'stats' => ['children' => $children],
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'notifyPref' => $guardian?->notify_pref ?? 'push',
        ]);
    }

    public function markParentNotificationRead(Request $request, Notification $notification): RedirectResponse
    {
        $guardian = $request->user()->guardian;

        if (! $guardian || $notification->guardian_id !== $guardian->id) {
            abort(403);
        }

        if (! $notification->read_at) {
            $notification->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return redirect()->route('parent.dashboard')->with('success', 'Notification marked as read.');
    }

    public function updateParentNotificationPreference(Request $request): RedirectResponse
    {
        $guardian = $request->user()->guardian;
        if (! $guardian) {
            abort(403);
        }

        $data = $request->validate([
            'notify_pref' => ['required', 'in:push,none'],
        ]);

        $guardian->update(['notify_pref' => $data['notify_pref']]);

        return redirect()->route('parent.dashboard')->with('success', 'Notification preference updated.');
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
