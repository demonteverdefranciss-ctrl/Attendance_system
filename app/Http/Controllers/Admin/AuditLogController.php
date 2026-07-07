<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'action' => $request->query('action'),
            'user_id' => $request->query('user_id'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        $query = AuditLog::with('user:id,name,username')
            ->when($filters['action'], fn ($q, $action) => $q->where('action', $action))
            ->when($filters['user_id'], fn ($q, $userId) => $q->where('user_id', (int) $userId))
            ->when($filters['from'], fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'], fn ($q, $to) => $q->whereDate('created_at', '<=', $to));

        $logs = $query->latest('id')
            ->limit(500)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'username' => $log->user->username,
                ] : null,
                'entity' => $log->entity,
                'entity_id' => $log->entity_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values();

        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'actions' => $actions,
            'users' => $users,
            'filters' => $filters,
        ]);
    }
}
