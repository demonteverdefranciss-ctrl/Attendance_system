import { Head, Link, usePage } from '@inertiajs/react';
import SortableHeading from '@/Components/SortableHeading';
import AdminLayout from '@/Layouts/AdminLayout';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function ReportPreview({ format, filters, records }) {
    const { auth } = usePage().props;
    const Layout = auth?.user?.role === 'admin' ? AdminLayout : TeacherLayout;
    const back = filters.session_id
        ? route('reports.session', filters.session_id)
        : route('reports.index', filters);
    const columns = [['date', 'Date'], ['section', 'Section'], ['student', 'Student'], ['status', 'Status'], ['time_in', 'Time In'], ['time_out', 'Time Out'], ['method', 'Method']];

    return (
        <Layout title="Report preview">
            <Head title="Report preview" />
            <div className="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <div>
                    <h2 className="font-semibold text-gray-900">Attendance report · {format.toUpperCase()}</h2>
                    <p className="mt-1 text-sm text-gray-600">{filters.from} to {filters.to} · {records.length} records</p>
                    <p className="mt-1 text-xs text-gray-500">Review the report before downloading. Exports include up to 1,000 records.</p>
                </div>
                <div className="flex flex-wrap gap-3">
                    <Link href={back} className="rounded-lg px-4 py-2 text-sm font-semibold text-blue-700 ring-1 ring-blue-200 hover:bg-blue-50">Back to report</Link>
                    <a href={route(format === 'pdf' ? 'reports.pdf' : 'reports.csv', filters)} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Download {format.toUpperCase()}</a>
                </div>
            </div>
            {format === 'pdf' ? (
                <div className="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-200">
                    <iframe title="Attendance PDF preview" src={route('reports.pdf', { ...filters, inline: 1 })} className="h-[75vh] w-full rounded-lg border-0" />
                    <a href={route('reports.pdf', { ...filters, inline: 1 })} target="_blank" rel="noopener noreferrer" className="mt-3 inline-block text-sm text-blue-700 underline">Open PDF preview in a new tab</a>
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead className="bg-gray-50"><tr>{columns.map(([key, label]) => <SortableHeading key={key} column={key} prefix="records_">{label}</SortableHeading>)}</tr></thead>
                        <tbody className="divide-y divide-gray-100">
                            {records.map((record, index) => <tr key={record.id ?? index}>{columns.map(([key]) => <td key={key} className="whitespace-nowrap px-4 py-3 text-gray-700">{record[key] ?? '—'}</td>)}</tr>)}
                            {records.length === 0 && <tr><td colSpan={columns.length} className="px-4 py-8 text-center text-gray-500">No records for these filters.</td></tr>}
                        </tbody>
                    </table>
                </div>
            )}
        </Layout>
    );
}
