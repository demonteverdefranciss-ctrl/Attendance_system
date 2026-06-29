import { Head, Link } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { StatCard } from '@/Layouts/AuthenticatedLayout';

export default function TeacherDashboard({ stats }) {
    return (
        <TeacherLayout
            title="Teacher Dashboard"
            actions={
                <Link href={route('teacher.attendance.index')} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Mark Attendance
                </Link>
            }
        >
            <Head title="Teacher Dashboard" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard label="My Sections" value={stats.sections} />
                <StatCard label="My Students" value={stats.students} />
            </div>
        </TeacherLayout>
    );
}
