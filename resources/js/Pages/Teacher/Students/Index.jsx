import { Head, Link, router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import Pagination, { usePageRows } from '@/Components/Pagination';

export default function TeacherStudentsIndex({ students, canAddStudents, canArchiveStudents }) {
    const { rows, paginator } = usePageRows(students);
    const archive = (student) => confirm(`Move ${student.first_name} ${student.last_name} to the archive?`) && router.delete(route('teacher.students.destroy', student.id), { preserveScroll: true });
    return <TeacherLayout title="Students" actions={canAddStudents && <Link href={route('teacher.students.create')} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">+ Add Student</Link>}>
        <Head title="Students" />
        {!canAddStudents && !canArchiveStudents && <div className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Your administrator has not granted student-management permissions.</div>}
        <div className="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><table className="min-w-full divide-y divide-gray-200"><thead className="bg-gray-50"><tr>{['Student', 'LRN', 'Section', 'Status', ...(canArchiveStudents ? ['Actions'] : [])].map((heading) => <th key={heading} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{heading}</th>)}</tr></thead><tbody className="divide-y divide-gray-100">
            {rows.length === 0 && <tr><td colSpan={canArchiveStudents ? 5 : 4} className="px-4 py-8 text-center text-sm text-gray-400">No students in your assigned sections.</td></tr>}
            {rows.map((student) => <tr key={student.id} className="hover:bg-gray-50"><td className="px-4 py-3 text-sm text-gray-700">{student.first_name} {student.last_name}</td><td className="px-4 py-3 text-sm text-gray-700">{student.lrn || '—'}</td><td className="px-4 py-3 text-sm text-gray-700">{student.section ? `${student.section.grade_level} - ${student.section.name}` : '—'}</td><td className="px-4 py-3 text-sm text-gray-700">{student.is_active ? 'Active' : 'Inactive'}</td>{canArchiveStudents && <td className="px-4 py-3 text-right text-sm"><button onClick={() => archive(student)} className="text-red-600 hover:underline">Archive</button></td>}</tr>)}
        </tbody></table></div><Pagination paginator={paginator} />
    </TeacherLayout>;
}
