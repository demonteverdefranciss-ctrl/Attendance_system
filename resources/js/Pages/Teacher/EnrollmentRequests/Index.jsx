import { router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function EnrollmentRequestsIndex({ requests }) {
    const review = (id, action) => {
        const routeName = action === 'approve'
            ? 'teacher.enrollment-requests.approve'
            : 'teacher.enrollment-requests.reject';

        router.post(route(routeName, id), {}, { preserveScroll: true });
    };

    const fmt = (value) => {
        if (!value) return '—';
        const d = new Date(value);
        return Number.isNaN(d.getTime()) ? value : d.toLocaleString();
    };

    return (
        <TeacherLayout title="Enrollment Requests">
            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            {['Student', 'LRN', 'Section', 'Guardian', 'Relationship', 'Requested'].map((h) => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {h}
                                </th>
                            ))}
                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {requests.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-8 text-center text-sm text-gray-400">
                                    No pending enrollment requests.
                                </td>
                            </tr>
                        )}
                        {requests.map((item) => (
                            <tr key={item.id} className="hover:bg-gray-50">
                                <td className="px-4 py-2 text-sm text-gray-700">{item.student ?? 'Unknown student'}</td>
                                <td className="px-4 py-2 text-sm text-gray-700">{item.lrn}</td>
                                <td className="px-4 py-2 text-sm text-gray-700">{item.section}</td>
                                <td className="px-4 py-2 text-sm text-gray-700">
                                    {item.guardian}
                                    {item.guardian_phone ? <div className="text-xs text-gray-500">{item.guardian_phone}</div> : null}
                                </td>
                                <td className="px-4 py-2 text-sm text-gray-700 capitalize">{item.relationship || '—'}</td>
                                <td className="px-4 py-2 text-sm text-gray-700">{fmt(item.created_at)}</td>
                                <td className="px-4 py-2">
                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            onClick={() => review(item.id, 'approve')}
                                            className="rounded-lg bg-green-600 px-3 py-1 text-xs font-medium text-white hover:bg-green-700"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => review(item.id, 'reject')}
                                            className="rounded-lg bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </TeacherLayout>
    );
}
