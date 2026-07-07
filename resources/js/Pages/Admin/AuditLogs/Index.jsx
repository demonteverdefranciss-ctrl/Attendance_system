import { useState } from 'react';
import { router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function AuditLogsIndex({ logs, actions, users, filters }) {
    const [form, setForm] = useState({
        action: filters.action ?? '',
        user_id: filters.user_id ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });

    const applyFilters = (e) => {
        e.preventDefault();
        router.get(route('admin.audit-logs.index'), form, { preserveState: true, preserveScroll: true });
    };

    const resetFilters = () => {
        const next = { action: '', user_id: '', from: '', to: '' };
        setForm(next);
        router.get(route('admin.audit-logs.index'), next, { preserveState: true, preserveScroll: true });
    };

    const fmt = (value) => {
        if (!value) return '—';
        const d = new Date(value);
        return Number.isNaN(d.getTime()) ? value : d.toLocaleString();
    };

    const preview = (values) => {
        if (!values || Object.keys(values).length === 0) return '—';
        const text = JSON.stringify(values);
        return text.length > 120 ? `${text.slice(0, 120)}...` : text;
    };

    return (
        <AdminLayout title="Audit Logs">
            <form onSubmit={applyFilters} className="mb-6 grid grid-cols-1 gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:grid-cols-5">
                <select
                    value={form.action}
                    onChange={(e) => setForm({ ...form, action: e.target.value })}
                    className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
                    <option value="">All actions</option>
                    {actions.map((a) => (
                        <option key={a} value={a}>{a}</option>
                    ))}
                </select>

                <select
                    value={form.user_id}
                    onChange={(e) => setForm({ ...form, user_id: e.target.value })}
                    className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
                    <option value="">All users</option>
                    {users.map((u) => (
                        <option key={u.id} value={u.id}>{u.name} ({u.username})</option>
                    ))}
                </select>

                <input
                    type="date"
                    value={form.from}
                    onChange={(e) => setForm({ ...form, from: e.target.value })}
                    className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />

                <input
                    type="date"
                    value={form.to}
                    onChange={(e) => setForm({ ...form, to: e.target.value })}
                    className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                />

                <div className="flex gap-2">
                    <button type="submit" className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Apply
                    </button>
                    <button type="button" onClick={resetFilters} className="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                        Reset
                    </button>
                </div>
            </form>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            {['When', 'User', 'Action', 'Entity', 'IP', 'Old Values', 'New Values'].map((h) => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {logs.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-8 text-center text-sm text-gray-400">
                                    No audit logs found for the selected filters.
                                </td>
                            </tr>
                        )}
                        {logs.map((log) => (
                            <tr key={log.id} className="hover:bg-gray-50">
                                <td className="px-4 py-2 text-xs text-gray-700">{fmt(log.created_at)}</td>
                                <td className="px-4 py-2 text-xs text-gray-700">
                                    {log.user ? `${log.user.name} (${log.user.username})` : 'System'}
                                </td>
                                <td className="px-4 py-2 text-xs font-medium text-gray-800">{log.action}</td>
                                <td className="px-4 py-2 text-xs text-gray-700">{log.entity ? `${log.entity} #${log.entity_id ?? '—'}` : '—'}</td>
                                <td className="px-4 py-2 text-xs text-gray-700">{log.ip_address || '—'}</td>
                                <td className="px-4 py-2 text-xs text-gray-600">{preview(log.old_values)}</td>
                                <td className="px-4 py-2 text-xs text-gray-600">{preview(log.new_values)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
