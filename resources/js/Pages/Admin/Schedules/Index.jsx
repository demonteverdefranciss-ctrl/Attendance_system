import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import DataTable from '@/Components/DataTable';

const DAYS = { 1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun' };

export default function SchedulesIndex({ schedules }) {
    const columns = [
        { key: 'section', label: 'Section', render: (s) => s.section?.name || '—' },
        { key: 'day', label: 'Day', render: (s) => DAYS[s.day_of_week] },
        { key: 'time', label: 'Time', render: (s) => `${s.start_time?.substring(0, 5)} – ${s.end_time?.substring(0, 5)}` },
        { key: 'late_after', label: 'Late After', render: (s) => (s.late_after ? s.late_after.substring(0, 5) : '—') },
        { key: 'type', label: 'Type', render: (s) => s.type.toUpperCase() },
        {
            key: 'is_active',
            label: 'Active',
            render: (s) =>
                s.is_active ? (
                    <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">Yes</span>
                ) : (
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">No</span>
                ),
        },
    ];

    return (
        <AdminLayout
            title="Attendance Schedules"
            actions={
                <Link href={route('admin.schedules.create')} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    + Add Schedule
                </Link>
            }
        >
            <Head title="Schedules" />
            <DataTable
                columns={columns}
                rows={schedules}
                editRoute="admin.schedules.edit"
                destroyRoute="admin.schedules.destroy"
                emptyText="No schedules yet."
            />
        </AdminLayout>
    );
}
