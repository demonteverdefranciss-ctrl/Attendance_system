import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import DataTable from '@/Components/DataTable';

export default function GuardiansIndex({ guardians }) {
    const columns = [
        { key: 'name', label: 'Name', render: (g) => `${g.first_name} ${g.last_name}` },
        { key: 'username', label: 'Username', render: (g) => g.user?.username },
        { key: 'phone', label: 'Phone', render: (g) => g.phone || '—' },
        { key: 'notify_pref', label: 'Notify', render: (g) => g.notify_pref },
        { key: 'students_count', label: 'Children', render: (g) => g.students_count },
    ];

    return (
        <AdminLayout
            title="Parents / Guardians"
            actions={
                <Link href={route('admin.guardians.create')} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    + Add Guardian
                </Link>
            }
        >
            <Head title="Parents / Guardians" />
            <DataTable
                columns={columns}
                rows={guardians}
                editRoute="admin.guardians.edit"
                destroyRoute="admin.guardians.destroy"
                emptyText="No parents/guardians yet."
            />
        </AdminLayout>
    );
}
