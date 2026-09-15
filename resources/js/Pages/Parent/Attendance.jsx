import { Head, router } from '@inertiajs/react';
import ParentLayout from '@/Layouts/ParentLayout';
import Pagination, { usePageRows } from '@/Components/Pagination';
import { formatDateTime } from '@/Pages/Parent/shared';

const STATUS_COLORS = {
    present: 'text-green-700',
    late: 'text-amber-600',
    absent: 'text-red-600',
    excused: 'text-blue-600',
};

export default function AttendanceIndex({ children = [], records = [], filters = {} }) {
    const { rows, paginator } = usePageRows(records);
    const studentId = filters.student_id ?? 'all';

    const selectChild = (id) => {
        const params = id === 'all' ? {} : { student_id: id };
        router.get(route('parent.attendance.index'), params, { preserveState: true, preserveScroll: true });
    };

    return (
        <ParentLayout title="Attendance">
            <Head title="Attendance" />

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div className="border-b border-gray-100 px-4 py-3">
                    <h2 className="text-base font-semibold text-gray-900">Attendance records</h2>
                    <p className="text-xs text-gray-500">Time in and time out for each linked child.</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => selectChild('all')}
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 ${
                                studentId === 'all'
                                    ? 'bg-blue-600 text-white ring-blue-600'
                                    : 'bg-sky-50 text-sky-800 ring-sky-200 hover:bg-sky-100'
                            }`}
                        >
                            All children
                        </button>
                        {children.map((child) => (
                            <button
                                key={child.id}
                                type="button"
                                onClick={() => selectChild(child.id)}
                                className={`rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 ${
                                    String(studentId) === String(child.id)
                                        ? 'bg-blue-600 text-white ring-blue-600'
                                        : 'bg-sky-50 text-sky-800 ring-sky-200 hover:bg-sky-100'
                                }`}
                            >
                                {child.name}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                {['Date', 'Child', 'Section', 'Status', 'Time In', 'Time Out', ''].map((h) => (
                                    <th
                                        key={h}
                                        className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                                    >
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-sm text-gray-400">
                                        No attendance records yet.
                                    </td>
                                </tr>
                            )}
                            {rows.map((r) => (
                                <tr key={r.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-2 text-sm text-gray-700">{r.date || '—'}</td>
                                    <td className="px-4 py-2 text-sm text-gray-700">{r.student}</td>
                                    <td className="px-4 py-2 text-sm text-gray-700">{r.section}</td>
                                    <td className={`px-4 py-2 text-sm font-medium capitalize ${STATUS_COLORS[r.status] || 'text-gray-700'}`}>
                                        {r.status}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-700">{r.time_in ? formatDateTime(r.time_in) : '—'}</td>
                                    <td className="px-4 py-2 text-sm text-gray-700">{r.time_out ? formatDateTime(r.time_out) : '—'}</td>
                                    <td className="px-4 py-2 text-right">
                                        {r.can_explain ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(route('parent.excuse-requests.store'), {
                                                        attendance_record_id: r.id,
                                                    })
                                                }
                                                className="text-xs font-semibold text-blue-700 hover:underline"
                                            >
                                                Explain
                                            </button>
                                        ) : null}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
            <Pagination paginator={paginator} />
        </ParentLayout>
    );
}
