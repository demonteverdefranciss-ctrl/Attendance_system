import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import DataTable from '@/Components/DataTable';

export default function CamerasIndex({ cameras }) {
    const flash = usePage().props.flash ?? {};

    const columns = [
        { key: 'name', label: 'Camera' },
        { key: 'location', label: 'Location', render: (c) => c.location || '—' },
        {
            key: 'sections',
            label: 'Assigned sections',
            render: (c) =>
                c.sections?.length
                    ? c.sections.map((s) => `${s.grade_level} - ${s.name}`).join(', ')
                    : 'None (shared until assigned)',
        },
        {
            key: 'status',
            label: 'Status',
            render: (c) =>
                c.is_active ? (
                    <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">Active</span>
                ) : (
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Inactive</span>
                ),
        },
        { key: 'id', label: 'Camera ID', render: (c) => c.id },
    ];

    return (
        <AdminLayout
            title="Cameras"
            actions={
                <Link href={route('admin.cameras.create')} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    + Add Camera
                </Link>
            }
        >
            <Head title="Cameras" />

            {flash.device_key && (
                <div className="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                    <p className="font-semibold">Device key (save now)</p>
                    <p className="mt-1 break-all font-mono text-base">{flash.device_key}</p>
                    <p className="mt-2 text-xs">
                        Put this in the school PC <code>recognition-service/.env</code> as <code>DEVICE_KEY</code>, with{' '}
                        <code>CAMERA_ID</code> matching the Camera ID column. It will not be shown again.
                    </p>
                </div>
            )}

            <DataTable
                columns={columns}
                rows={cameras}
                editRoute="admin.cameras.edit"
                destroyRoute="admin.cameras.destroy"
                emptyText="No cameras yet. Add one, then assign it to a section."
            />
        </AdminLayout>
    );
}
