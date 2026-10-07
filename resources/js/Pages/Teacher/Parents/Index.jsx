import { Head, Link, router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import Pagination, { usePageRows } from '@/Components/Pagination';

export default function TeacherParentsIndex({ guardians, canAddParents, canEditParents, canArchiveParents }) {
    const { rows, paginator } = usePageRows(guardians);
    const hasActions = canEditParents || canArchiveParents;
    const archive = (guardian) => confirm(`Move ${guardian.first_name} ${guardian.last_name} to the archive?`)
        && router.delete(route('teacher.parents.destroy', guardian.id), { preserveScroll: true });

    return (
        <TeacherLayout
            title="Parents / Guardians"
            actions={canAddParents && (
                <Link href={route('teacher.parents.create')} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    + Add Parent
                </Link>
            )}
        >
            <Head title="Parents / Guardians" />
            <div className="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            {['Parent / Guardian', 'Username', 'Phone', 'Students in assigned sections', ...(hasActions ? ['Actions'] : [])].map((heading) => (
                                <th key={heading} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{heading}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {rows.length === 0 && (
                            <tr><td colSpan={hasActions ? 5 : 4} className="px-4 py-8 text-center text-sm text-gray-400">No parents/guardians are linked to students in your assigned sections.</td></tr>
                        )}
                        {rows.map((guardian) => (
                            <tr key={guardian.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3 text-sm font-medium text-gray-700">{guardian.first_name} {guardian.last_name}</td>
                                <td className="px-4 py-3 text-sm text-gray-700">{guardian.user?.username || '—'}</td>
                                <td className="px-4 py-3 text-sm text-gray-700">{guardian.phone || '—'}</td>
                                <td className="px-4 py-3 text-sm text-gray-700">
                                    {guardian.students?.map((student) => `${student.first_name} ${student.last_name}`).join(', ') || '—'}
                                </td>
                                {hasActions && (
                                    <td className="px-4 py-3 text-right text-sm">
                                        <div className="flex justify-end gap-3">
                                            {canEditParents && <Link href={route('teacher.parents.edit', guardian.id)} className="text-blue-700 hover:underline">Edit</Link>}
                                            {canArchiveParents && <button type="button" onClick={() => archive(guardian)} className="text-red-600 hover:underline">Archive</button>}
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination paginator={paginator} />
        </TeacherLayout>
    );
}
