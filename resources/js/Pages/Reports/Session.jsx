import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { StatCard } from '@/Components/AppSidebarLayout';
import { Doughnut, ChartCard, noAspect } from '@/Components/Charts';

const STATUS_COLORS = {
    present: 'text-green-700',
    late: 'text-amber-600',
    absent: 'text-red-600',
    excused: 'text-blue-600',
};

export default function ReportsSession({ session, summary, methodBreakdown, records }) {
    const { auth } = usePage().props;
    const Layout = auth?.user?.role === 'admin' ? AdminLayout : TeacherLayout;

    const methodData = {
        labels: ['Face', 'Manual', 'Other'],
        datasets: [{
            data: [
                methodBreakdown?.face ?? 0,
                methodBreakdown?.manual ?? 0,
                methodBreakdown?.other ?? 0,
            ],
            backgroundColor: ['#7c3aed', '#64748b', '#94a3b8'],
        }],
    };

    const exportParams = { session_id: session.id, from: session.session_date, to: session.session_date };

    return (
        <Layout
            title="Session Report"
            actions={
                <Link href={route('reports.index')} className="text-sm text-gray-500 hover:underline">
                    ← All reports
                </Link>
            }
        >
            <Head title={`Session ${session.session_date}`} />

            <div className="mb-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">{session.section}</h2>
                        <p className="text-sm text-gray-500">
                            {session.session_date}
                            {' · '}
                            <span className="capitalize">{session.status}</span>
                            {session.is_adhoc ? ' · manual' : ' · schedule'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <a
                            href={route('reports.csv', exportParams)}
                            className="rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
                        >
                            Export CSV
                        </a>
                        <a
                            href={route('reports.pdf', exportParams)}
                            className="rounded-lg bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
                        >
                            Export PDF
                        </a>
                        {auth?.user?.role === 'teacher' && (
                            <Link
                                href={route('teacher.attendance.show', session.id)}
                                className="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                            >
                                Open Mark page
                            </Link>
                        )}
                    </div>
                </div>
            </div>

            <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <StatCard label="Rate" value={`${summary.rate}%`} />
                <StatCard label="Present" value={summary.present} />
                <StatCard label="Late" value={summary.late} />
                <StatCard label="Absent" value={summary.absent} />
                <StatCard label="Excused" value={summary.excused} />
                <StatCard label="Total" value={summary.total} />
            </div>

            <div className="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <ChartCard title="Face vs manual marking">
                    <Doughnut data={methodData} options={noAspect} />
                </ChartCard>
                <div className="rounded-xl bg-white p-5 text-sm text-gray-600 shadow-sm ring-1 ring-gray-200">
                    <h3 className="mb-2 text-sm font-semibold text-gray-700">Method totals</h3>
                    <ul className="space-y-1">
                        <li>Face recognition: <strong>{methodBreakdown?.face ?? 0}</strong></li>
                        <li>Manual: <strong>{methodBreakdown?.manual ?? 0}</strong></li>
                        <li>Other / unknown: <strong>{methodBreakdown?.other ?? 0}</strong></li>
                    </ul>
                </div>
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            {['Student', 'Status', 'Time In', 'Time Out', 'Method'].map((h) => (
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
                        {records.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-sm text-gray-400">
                                    No records in this session.
                                </td>
                            </tr>
                        )}
                        {records.map((r, i) => (
                            <tr key={i} className="hover:bg-gray-50">
                                <td className="px-4 py-2 text-sm text-gray-700">
                                    {r.student_id ? (
                                        <Link
                                            href={route('reports.student', r.student_id)}
                                            className="text-blue-600 hover:underline"
                                        >
                                            {r.student}
                                        </Link>
                                    ) : (
                                        r.student
                                    )}
                                </td>
                                <td
                                    className={`px-4 py-2 text-sm font-medium capitalize ${STATUS_COLORS[r.status] || 'text-gray-700'}`}
                                >
                                    {r.status}
                                </td>
                                <td className="px-4 py-2 text-sm text-gray-700">{r.time_in ?? '—'}</td>
                                <td className="px-4 py-2 text-sm text-gray-700">{r.time_out ?? '—'}</td>
                                <td className="px-4 py-2 text-sm capitalize text-gray-500">{r.method}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Layout>
    );
}
