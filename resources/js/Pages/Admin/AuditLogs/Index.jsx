import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import Pagination, { usePageRows } from '@/Components/Pagination';

const FIELD_LABELS = {
    method: 'Method',
    status: 'Status',
    time_in: 'Time in',
    time_out: 'Time out',
    student_id: 'Student ID',
    section_id: 'Section ID',
    session_id: 'Session ID',
    guardian_id: 'Guardian ID',
    teacher_id: 'Teacher ID',
    user_id: 'User ID',
    first_name: 'First name',
    last_name: 'Last name',
    lrn: 'LRN',
    phone: 'Phone',
    email: 'Email',
    username: 'Username',
    name: 'Name',
    notes: 'Notes',
    relationship: 'Relationship',
    grade_level: 'Grade level',
    gender: 'Gender',
    employee_no: 'Employee no.',
    is_active: 'Active',
    consent_biometric: 'Biometric consent',
    notify_pref: 'Notification preference',
    password: 'Password',
    opened_at: 'Opened at',
    closed_at: 'Closed at',
    session_date: 'Session date',
    schedule_id: 'Schedule ID',
    camera_id: 'Camera ID',
    location: 'Location',
    rtsp_url: 'Camera URL',
    day_of_week: 'Day of week',
    start_time: 'Start time',
    end_time: 'End time',
    late_after: 'Late after',
    type: 'Type',
    source: 'Source',
    date: 'Date',
};

function humanLabel(key) {
    if (FIELD_LABELS[key]) return FIELD_LABELS[key];
    return String(key)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatValue(key, value) {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (typeof value === 'object') {
        try {
            return JSON.stringify(value, null, 2);
        } catch {
            return String(value);
        }
    }

    const text = String(value);
    if (['status', 'method', 'gender', 'type', 'source', 'notify_pref'].includes(key)) {
        return text.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }

    if (/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?/.test(text)) {
        const d = new Date(text.includes('T') || text.includes(' ') ? text : `${text}T00:00:00`);
        if (!Number.isNaN(d.getTime())) {
            return text.length > 10 ? d.toLocaleString() : d.toLocaleDateString();
        }
    }

    return text;
}

function hasValues(values) {
    return values && typeof values === 'object' && Object.keys(values).length > 0;
}

function ValuesButton({ label, values, tone, onOpen }) {
    const empty = !hasValues(values);
    return (
        <button
            type="button"
            disabled={empty}
            onClick={() => onOpen(label, values)}
            className={`rounded-lg px-2.5 py-1 text-xs font-semibold ring-1 ${
                empty
                    ? 'cursor-not-allowed bg-gray-50 text-gray-400 ring-gray-200'
                    : tone === 'old'
                      ? 'bg-amber-50 text-amber-900 ring-amber-200 hover:bg-amber-100'
                      : 'bg-emerald-50 text-emerald-900 ring-emerald-200 hover:bg-emerald-100'
            }`}
        >
            {empty ? 'None' : 'View'}
        </button>
    );
}

function ValuesModal({ title, values, onClose }) {
    const entries = useMemo(() => {
        if (!hasValues(values)) return [];
        return Object.entries(values);
    }, [values]);

    useEffect(() => {
        const onKey = (e) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', onKey);
        document.body.classList.add('overflow-hidden');
        return () => {
            window.removeEventListener('keydown', onKey);
            document.body.classList.remove('overflow-hidden');
        };
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button
                type="button"
                className="absolute inset-0 bg-gray-900/40"
                aria-label="Close"
                onClick={onClose}
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="audit-values-title"
                className="relative z-10 w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-gray-200"
            >
                <div className="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div>
                        <h3 id="audit-values-title" className="text-base font-semibold text-gray-900">
                            {title}
                        </h3>
                        <p className="mt-0.5 text-xs text-gray-500">Readable change details</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800 ring-1 ring-inset ring-sky-200 hover:bg-sky-100"
                    >
                        Close
                    </button>
                </div>

                <div className="max-h-[70vh] overflow-y-auto px-5 py-4">
                    {entries.length === 0 ? (
                        <p className="text-sm text-gray-400">No values recorded.</p>
                    ) : (
                        <dl className="space-y-3">
                            {entries.map(([key, value]) => (
                                <div key={key} className="rounded-xl bg-gray-50 px-3 py-2.5 ring-1 ring-gray-100">
                                    <dt className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        {humanLabel(key)}
                                    </dt>
                                    <dd className="mt-1 whitespace-pre-wrap break-words text-sm font-medium text-gray-900">
                                        {formatValue(key, value)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function AuditLogsIndex({ logs, actions, users, filters }) {
    const { rows, paginator } = usePageRows(logs);
    const [form, setForm] = useState({
        action: filters.action ?? '',
        user_id: filters.user_id ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });
    const [modal, setModal] = useState(null);

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

    const openValues = (label, values) => {
        setModal({ title: label, values });
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
                    <button type="button" onClick={resetFilters} className="rounded-lg bg-sky-50 px-4 py-2 text-sm font-medium text-sky-800 ring-1 ring-inset ring-sky-200 hover:bg-sky-100">
                        Reset
                    </button>
                </div>
            </form>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            {['When', 'User', 'Action', 'Entity', 'IP', 'Before', 'After'].map((h) => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-8 text-center text-sm text-gray-400">
                                    No audit logs found for the selected filters.
                                </td>
                            </tr>
                        )}
                        {rows.map((log) => (
                            <tr key={log.id} className="hover:bg-gray-50">
                                <td className="px-4 py-2 text-xs text-gray-700">{fmt(log.created_at)}</td>
                                <td className="px-4 py-2 text-xs text-gray-700">
                                    {log.user ? `${log.user.name} (${log.user.username})` : 'System'}
                                </td>
                                <td className="px-4 py-2 text-xs font-medium text-gray-800">{log.action}</td>
                                <td className="px-4 py-2 text-xs text-gray-700">{log.entity ? `${log.entity} #${log.entity_id ?? '—'}` : '—'}</td>
                                <td className="px-4 py-2 text-xs text-gray-700">{log.ip_address || '—'}</td>
                                <td className="px-4 py-2">
                                    <ValuesButton
                                        label="Before change"
                                        values={log.old_values}
                                        tone="old"
                                        onOpen={openValues}
                                    />
                                </td>
                                <td className="px-4 py-2">
                                    <ValuesButton
                                        label="After change"
                                        values={log.new_values}
                                        tone="new"
                                        onOpen={openValues}
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination paginator={paginator} />

            {modal && (
                <ValuesModal
                    title={modal.title}
                    values={modal.values}
                    onClose={() => setModal(null)}
                />
            )}
        </AdminLayout>
    );
}
