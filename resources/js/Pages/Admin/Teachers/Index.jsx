import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import DataTable from '@/Components/DataTable';

export default function TeachersIndex({ teachers }) {
    const columns = [
        { key: 'name', label: 'Name', render: (t) => `${t.first_name} ${t.last_name}` },
        { key: 'employee_no', label: 'Employee No.', render: (t) => t.employee_no || '—' },
        { key: 'username', label: 'Username', render: (t) => t.user?.username },
        { key: 'email', label: 'Email', render: (t) => t.user?.email || '—' },
        {
            key: 'status',
            label: 'Status',
            render: (t) =>
                t.user?.is_active ? (
                    <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">Active</span>
                ) : (
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Inactive</span>
                ),
        },
    ];

    return (
        <AdminLayout
            title="Teachers"
            actions={
                <Link href={route('admin.teachers.create')} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    + Add Teacher
                </Link>
            }
        >
            <Head title="Teachers" />
            <DataTable
                columns={columns}
                rows={teachers}
                editRoute="admin.teachers.edit"
                destroyRoute="admin.teachers.destroy"
                emptyText="No teachers yet."
            />
        </AdminLayout>
    );
}
