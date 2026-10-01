import { Link } from '@inertiajs/react';
import useTableSort from '@/Components/useTableSort';
import LocalSortHeading from '@/Components/LocalSortHeading';

export default function AtRiskStudentsTable({ students = [], threshold = 80 }) {
    const keys = { Student: 'name', Section: 'section', Attended: 'attended', Total: 'total', Rate: 'rate' };
    const sorting = useTableSort(students, Object.fromEntries(Object.values(keys).map((key) => [key, (s) => s[key]])));
    return (
        <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div className="border-b border-gray-100 px-5 py-3">
                <h3 className="text-sm font-semibold text-gray-700">
                    At-risk students (below {threshold}% attendance)
                </h3>
            </div>
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        {['Student', 'Section', 'Attended', 'Total', 'Rate', ''].map((h) => (
                            h ? <LocalSortHeading key={h} column={keys[h]} sorting={sorting}>{h}</LocalSortHeading> : <th key="action" />
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {students.length === 0 && (
                        <tr>
                            <td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-400">
                                No at-risk students in this period.
                            </td>
                        </tr>
                    )}
                    {sorting.rows.map((s) => (
                        <tr key={s.student_id} className="hover:bg-gray-50">
                            <td className="px-4 py-2 text-sm text-gray-800">{s.name}</td>
                            <td className="px-4 py-2 text-sm text-gray-600">{s.section}</td>
                            <td className="px-4 py-2 text-sm text-gray-700">{s.attended}</td>
                            <td className="px-4 py-2 text-sm text-gray-700">{s.total}</td>
                            <td className="px-4 py-2 text-sm font-semibold text-red-600">{s.rate}%</td>
                            <td className="px-4 py-2 text-right text-sm">
                                <Link
                                    href={route('reports.student', s.student_id)}
                                    className="font-medium text-blue-600 hover:text-blue-800"
                                >
                                    View
                                </Link>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
