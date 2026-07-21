import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import AtRiskStudentsTable from '@/Components/AtRiskStudentsTable';
import { StatCard } from '@/Layouts/AuthenticatedLayout';
import { Doughnut, Line, Bar, ChartCard, noAspect } from '@/Components/Charts';

export default function AdminDashboard({ stats, summary, trend, perSection, atRisk = [], methodBreakdown, range }) {
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
    const sectionData = {
        labels: perSection.map((s) => s.section),
        datasets: [{ label: 'Rate %', data: perSection.map((s) => s.rate), backgroundColor: '#2563eb' }],
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
        <AdminLayout title="Admin Dashboard">
            <Head title="Admin Dashboard" />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Students" value={stats.students} />
                <StatCard label="Sections" value={stats.sections} />
                <StatCard label="Teachers" value={stats.teachers} />
                <StatCard label="Parents / Guardians" value={stats.guardians} />
            </div>

            <p className="mb-3 text-sm text-gray-500">
                Attendance overview · {range.from} to {range.to}
                {' · '}
                <Link href={route('reports.index')} className="text-blue-600 hover:underline">Open Reports</Link>
            </p>

            <div className="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <StatCard label="Attendance Rate" value={`${summary.rate}%`} />
                <StatCard label="Present / Late" value={summary.present + summary.late} />
                <StatCard label="Absent" value={summary.absent} />
            </div>

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
                <div className="lg:col-span-1">
                    <ChartCard title="Attendance rate by section">
                        <Bar data={sectionData} options={{ ...noAspect, scales: { y: { min: 0, max: 100 } } }} />
                    </ChartCard>
                </div>
            </div>

            <AtRiskStudentsTable students={atRisk} />
        </AdminLayout>
    );
}
