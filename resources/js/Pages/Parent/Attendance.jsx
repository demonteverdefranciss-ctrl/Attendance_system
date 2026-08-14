import { Head } from '@inertiajs/react';
import { useState } from 'react';
import ParentLayout from '@/Layouts/ParentLayout';
import { formatDateTime } from '@/Pages/Parent/shared';

const STATUS_COLORS = {
    present: 'text-green-700',
    late: 'text-amber-600',
    absent: 'text-red-600',
    excused: 'text-blue-600',
};

export default function AttendanceIndex({ children = [], records = [] }) {
    const [studentId, setStudentId] = useState('all');
    const filtered = studentId === 'all'
        ? records
        : records.filter((r) => String(r.student_id) === String(studentId));

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
                            onClick={() => setStudentId('all')}
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 ${
                                studentId === 'all'
                                    ? 'bg-blue-600 text-white ring-blue-600'
                                    : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'
                            }`}
                        >
                            All children
                        </button>
                        {children.map((child) => (
                            <button
                                key={child.id}
                                type="button"
                                onClick={() => setStudentId(child.id)}
                                className={`rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 ${
                                    String(studentId) === String(child.id)
                                        ? 'bg-blue-600 text-white ring-blue-600'
                                        : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50'
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
                                {['Date', 'Child', 'Section', 'Status', 'Time In', 'Time Out'].map((h) => (
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
                            {filtered.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-400">
                                        No attendance records yet.
                                    </td>
                                </tr>
                            )}
                            {filtered.map((r) => (
                                <tr key={r.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-2 text-sm text-gray-700">{r.date || '—'}</td>
                                    <td className="px-4 py-2 text-sm text-gray-700">{r.student}</td>
                                    <td className="px-4 py-2 text-sm text-gray-700">{r.section}</td>
                                    <td className={`px-4 py-2 text-sm font-medium capitalize ${STATUS_COLORS[r.status] || 'text-gray-700'}`}>
                                        {r.status}
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-700">{r.time_in ? formatDateTime(r.time_in) : '—'}</td>
                                    <td className="px-4 py-2 text-sm text-gray-700">{r.time_out ? formatDateTime(r.time_out) : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </ParentLayout>
    );
}
