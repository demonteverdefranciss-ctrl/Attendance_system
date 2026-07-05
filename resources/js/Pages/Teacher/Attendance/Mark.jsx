import { Head, Link, router, useForm } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';

const STATUSES = ['present', 'late', 'absent', 'excused'];
const COLORS = {
    present: 'bg-green-600',
    late: 'bg-amber-500',
    absent: 'bg-red-600',
    excused: 'bg-blue-500',
};

export default function Mark({ session, students, records }) {
    const initial = {};
    students.forEach((s) => {
        initial[s.id] = records[s.id]?.status ?? '';
    });

    const { data, setData, post, processing } = useForm({ records: initial });

    const setStatus = (id, status) => {
        setData('records', { ...data.records, [id]: status });
    };

    const markAll = (status) => {
        const all = {};
        students.forEach((s) => (all[s.id] = status));
        setData('records', all);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('teacher.attendance.store', session.id), { preserveScroll: true });
    };

    const closeSession = () => {
        if (confirm('Close this session? Unmarked students will be recorded absent.')) {
            router.post(route('teacher.attendance.close', session.id));
        }
    };

    const recordTimeOutNow = (studentId) => {
        router.post(route('teacher.attendance.time-out', [session.id, studentId]), {}, { preserveScroll: true });
    };

    const displayTime = (value) => {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const closed = session.status === 'closed';

    return (
        <TeacherLayout
            title={`${session.section.grade_level} - ${session.section.name}`}
            actions={
                <Link href={route('teacher.attendance.index')} className="text-sm text-gray-500 hover:underline">
                    ← Back
                </Link>
            }
        >
            <Head title="Mark Attendance" />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-gray-500">
                    Session {session.session_date} ·{' '}
                    <span className={closed ? 'text-gray-500' : 'text-green-600'}>{session.status}</span>
                </p>
                {!closed && (
                    <button onClick={() => markAll('present')} className="text-sm text-blue-600 hover:underline">
                        Mark all present
                    </button>
                )}
            </div>

            <form onSubmit={submit}>
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Student</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Time In</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Time Out</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {students.map((s) => (
                                <tr key={s.id}>
                                    <td className="px-4 py-3 text-sm text-gray-700">{s.last_name}, {s.first_name}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-2">
                                            {STATUSES.map((status) => {
                                                const active = data.records[s.id] === status;
                                                return (
                                                    <button
                                                        type="button"
                                                        key={status}
                                                        disabled={closed}
                                                        onClick={() => setStatus(s.id, status)}
                                                        className={`rounded-lg px-3 py-1 text-xs font-medium capitalize ${
                                                            active ? `${COLORS[status]} text-white` : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                                        } disabled:opacity-50`}
                                                    >
                                                        {status}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">{displayTime(records[s.id]?.time_in)}</td>
                                    <td className="px-4 py-3 text-sm text-gray-700">{displayTime(records[s.id]?.time_out)}</td>
                                    <td className="px-4 py-3">
                                        {!closed && ['present', 'late'].includes(data.records[s.id]) && !records[s.id]?.time_out ? (
                                            <button
                                                type="button"
                                                onClick={() => recordTimeOutNow(s.id)}
                                                className="rounded-lg bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200"
                                            >
                                                Record time-out now
                                            </button>
                                        ) : (
                                            <span className="text-xs text-gray-400">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {!closed && (
                    <div className="mt-4 flex items-center gap-3">
                        <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                            Save Attendance
                        </button>
                        <button type="button" onClick={closeSession} className="rounded-lg bg-gray-100 px-4 py-2 font-medium text-gray-700 hover:bg-gray-200">
                            Close session
                        </button>
                    </div>
                )}
            </form>
        </TeacherLayout>
    );
}
