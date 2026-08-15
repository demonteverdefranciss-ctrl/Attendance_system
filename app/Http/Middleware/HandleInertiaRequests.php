<?php

namespace App\Http\Middleware;

use App\Models\TeacherNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role?->name,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'device_key' => fn () => $request->session()->get('device_key'),
            ],
            'teacherAlerts' => fn () => $this->teacherAlerts($request),
            'assetBase' => rtrim((string) env('ASSET_URL', ''), '/'),
        ];
    }

    /**
     * Unread in-app alerts for the signed-in teacher (empty for other roles).
     *
     * @return array<int, array<string, mixed>>
     */
    private function teacherAlerts(Request $request): array
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('teacher')) {
            return [];
        }

        $teacher = $user->teacher;
        if (! $teacher) {
            return [];
        }

        try {
            return TeacherNotification::query()
                ->where('teacher_id', $teacher->id)
                ->whereNull('read_at')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (TeacherNotification $n) => [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $n->title,
                    'body' => $n->body,
                    'created_at' => $n->created_at?->toDateTimeString(),
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
