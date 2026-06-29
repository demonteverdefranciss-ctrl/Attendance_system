import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { StatCard } from '@/Layouts/AuthenticatedLayout';

export default function AdminDashboard({ stats }) {
    return (
        <AdminLayout title="Admin Dashboard">
            <Head title="Admin Dashboard" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Students" value={stats.students} />
                <StatCard label="Sections" value={stats.sections} />
                <StatCard label="Teachers" value={stats.teachers} />
                <StatCard label="Parents / Guardians" value={stats.guardians} />
            </div>
        </AdminLayout>
    );
}
