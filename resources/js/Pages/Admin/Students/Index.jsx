import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import DataTable from '@/Components/DataTable';

export default function StudentsIndex({ students }) {
    const columns = [
        { key: 'name', label: 'Name', render: (s) => `${s.last_name}, ${s.first_name}` },
        { key: 'lrn', label: 'LRN', render: (s) => s.lrn || '—' },
        { key: 'section', label: 'Section', render: (s) => s.section?.name || '—' },
        { key: 'gender', label: 'Gender', render: (s) => s.gender || '—' },
        {
            key: 'consent',
            label: 'Biometric Consent',
            render: (s) =>
                s.consent_biometric ? (
                    <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">Yes</span>
                ) : (
                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">No</span>
                ),
        },
    ];

    return (
        <AdminLayout
            title="Students"
            actions={
                <Link href={route('admin.students.create')} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    + Add Student
                </Link>
            }
        >
            <Head title="Students" />
            <DataTable
                columns={columns}
                rows={students}
                editRoute="admin.students.edit"
                destroyRoute="admin.students.destroy"
                emptyText="No students yet."
            />
        </AdminLayout>
    );
}
