import { Head, Link, router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import AtRiskStudentsTable from '@/Components/AtRiskStudentsTable';
import { StatCard } from '@/Layouts/AuthenticatedLayout';
import { Doughnut, Line, ChartCard, noAspect } from '@/Components/Charts';

export default function TeacherDashboard({ stats, summary, trend, atRisk = [], methodBreakdown, range, notifications = [] }) {
    const statusData = {
        labels: ['Present', 'Late', 'Absent', 'Excused'],
        datasets: [{
            data: [summary.present, summary.late, summary.absent, summary.excused],
            backgroundColor: ['#16a34a', '#f59e0b', '#dc2626', '#3b82f6'],
        }],
    };
    const trendData = {
        labels: trend.map((t) => t.day),
        datasets: [{
            label: 'Attendance rate %',
            data: trend.map((t) => t.rate),
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,0.15)',
            tension: 0.3,
            fill: true,
        }],
    };
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

    return (
        <TeacherLayout
            title="Teacher Dashboard"
            actions={
                <Link href={route('teacher.attendance.index')} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Mark Attendance
                </Link>
            }
        >
            <Head title="Teacher Dashboard" />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="My Sections" value={stats.sections} />
                <StatCard label="My Students" value={stats.students} />
                <StatCard label="Attendance Rate" value={`${summary.rate}%`} />
                <StatCard label="Absent (30d)" value={summary.absent} />
            </div>

            <p className="mb-3 text-sm text-gray-500">
                Attendance overview · {range.from} to {range.to}
                {' · '}
                <Link href={route('reports.index')} className="text-blue-600 hover:underline">Open Reports</Link>
            </p>

            <div className="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <ChartCard title="Status breakdown (last 30 days)">
                    <Doughnut data={statusData} options={noAspect} />
                </ChartCard>
                <ChartCard title="Daily attendance rate (last 14 days)">
                    <Line data={trendData} options={{ ...noAspect, scales: { y: { min: 0, max: 100 } } }} />
                </ChartCard>
                <ChartCard title="Face vs manual marking">
                    <Doughnut data={methodData} options={noAspect} />
                </ChartCard>
            </div>

            <AtRiskStudentsTable students={atRisk} />

            <div className="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div className="border-b border-gray-100 px-4 py-3">
                    <h2 className="text-base font-semibold text-gray-900">Notifications</h2>
                    <p className="text-xs text-gray-500">Session reminders and other teacher alerts</p>
                </div>
                <div className="divide-y divide-gray-100">
                    {notifications.length === 0 && (
                        <div className="px-4 py-8 text-center text-sm text-gray-400">No notifications yet.</div>
                    )}
                    {notifications.map((n) => (
                        <div key={n.id} className="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="text-sm font-semibold text-gray-900">{n.title}</h3>
                                    {n.read_at ? (
                                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Read</span>
                                    ) : (
                                        <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">New</span>
                                    )}
                                </div>
                                {n.body && <p className="mt-1 text-sm text-gray-600">{n.body}</p>}
                            </div>
                            {!n.read_at && (
                                <button
                                    type="button"
                                    onClick={() => router.post(route('teacher.notifications.read', n.id), {}, { preserveScroll: true })}
                                    className="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                                >
                                    Dismiss
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </TeacherLayout>
    );
}
