import { Head } from '@inertiajs/react';
import AuthenticatedLayout, { StatCard } from '@/Layouts/AuthenticatedLayout';

export default function ParentDashboard({ stats }) {
    return (
        <AuthenticatedLayout title="Parent Dashboard">
            <Head title="Parent Dashboard" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard label="My Children" value={stats.children} />
            </div>

            <p className="mt-8 text-sm text-gray-400">
                Attendance history and notifications for your children will appear here in the next phase.
            </p>
        </AuthenticatedLayout>
    );
}
