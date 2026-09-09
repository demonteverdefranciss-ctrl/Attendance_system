import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { StatCard } from '@/Layouts/AuthenticatedLayout';
import { Line, ChartCard, noAspect } from '@/Components/Charts';

export default function ReportStudent({ student, summary, trend, filters }) {
    const { auth } = usePage().props;
    const Layout = auth?.user?.role === 'admin' ? AdminLayout : TeacherLayout;
    const today = filters.today ?? new Date().toISOString().slice(0, 10);

    const [form, setForm] = useState({
        from: filters.from,
        to: filters.to,
    });

    const setDate = (key, value) => {
        const clamped = value && value > today ? today : value;
        setForm((prev) => {
            const next = { ...prev, [key]: clamped };
            if (key === 'from' && next.to && next.from > next.to) {
                next.to = next.from;
            }
            if (key === 'to' && next.from && next.to < next.from) {
                next.from = next.to;
            }
            return next;
        });
    };

    const apply = (e) => {
        e.preventDefault();
        router.get(route('reports.student', student.id), form, { preserveState: true, preserveScroll: true });
    };

    const trendData = {
        labels: trend.map((t) => t.day),
        datasets: [{
            label: 'Attended (1) / Missed (0)',
            data: trend.map((t) => t.attended),
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,0.15)',
            tension: 0.2,
            fill: true,
            stepped: true,
        }],
    };

    return (
        <Layout
            title={student.name}
            actions={
                <Link href={route('reports.index')} className="rounded-lg bg-sky-50 px-3 py-2 text-sm font-medium text-sky-800 ring-1 ring-inset ring-sky-200 hover:bg-sky-100">
                    â† Back to Reports
                </Link>
            }
        >
            <Head title={`${student.name} Â· Analytics`} />

            <p className="mb-4 text-sm text-gray-500">
                {student.section ? `Section: ${student.section}` : 'No section'}
                {student.lrn ? ` Â· LRN: ${student.lrn}` : ''}
            </p>

            <form onSubmit={apply} className="mb-6 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <div>
                    <label className="block text-xs font-medium text-gray-600">From</label>
                    <input
                        type="date"
                        value={form.from}
                        max={today}
                        onChange={(e) => setDate('from', e.target.value)}
                        className="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-600">To</label>
                    <input
                        type="date"
                        value={form.to}
                        max={today}
                        onChange={(e) => setDate('to', e.target.value)}
                        className="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>
                <button type="submit" className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Apply
                </button>
            </form>

            <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <StatCard label="Attendance Rate" value={`${summary.rate}%`} />
                <StatCard label="Present" value={summary.present} />
                <StatCard label="Late" value={summary.late} />
                <StatCard label="Absent" value={summary.absent} />
                <StatCard label="Excused" value={summary.excused} />
            </div>

            <ChartCard title="Daily attendance trend (1 = present/late, 0 = absent/excused)">
                {trend.length === 0 ? (
                    <p className="flex h-full items-center justify-center text-sm text-gray-400">No attendance days in this range.</p>
                ) : (
                    <Line
                        data={trendData}
                        options={{
                            ...noAspect,
                            scales: {
                                y: { min: 0, max: 1, ticks: { stepSize: 1 } },
                            },
                        }}
                    />
                )}
            </ChartCard>
        </Layout>
    );
}
