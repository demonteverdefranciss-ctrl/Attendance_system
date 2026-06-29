import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import DataTable from '@/Components/DataTable';

export default function SectionsIndex({ sections }) {
    const columns = [
        { key: 'name', label: 'Section', render: (s) => `${s.grade_level} - ${s.name}` },
        { key: 'school_year', label: 'School Year' },
        { key: 'adviser', label: 'Adviser', render: (s) => (s.adviser ? `${s.adviser.first_name} ${s.adviser.last_name}` : '—') },
        { key: 'students_count', label: 'Students', render: (s) => s.students_count },
    ];

    return (
        <AdminLayout
            title="Sections"
            actions={
                <Link href={route('admin.sections.create')} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    + Add Section
                </Link>
            }
        >
            <Head title="Sections" />
            <DataTable
                columns={columns}
                rows={sections}
                editRoute="admin.sections.edit"
                destroyRoute="admin.sections.destroy"
                emptyText="No sections yet."
            />
        </AdminLayout>
    );
}
