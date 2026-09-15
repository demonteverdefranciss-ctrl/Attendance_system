import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import Pagination, { usePageRows } from '@/Components/Pagination';

export default function ArchiveIndex({ category, categories = [], rows }) {
    const { rows: items, paginator } = usePageRows(rows);

    const restore = (id) => {
        if (!confirm('Restore this record to the active list?')) return;
        router.post(route('admin.archive.restore', { category, id }), {}, { preserveScroll: true });
    };

    const purge = (id) => {
        if (!confirm('Permanently delete this record? This cannot be undone.')) return;
        router.delete(route('admin.archive.destroy', { category, id }), { preserveScroll: true });
    };

    return (
        <AdminLayout title="Archive">
            <Head title="Archive" />

            <p className="mb-4 text-sm text-gray-500">
                Deleted admin records are kept here by category. Restore to use them again, or permanently delete when you no longer need them.
            </p>

            <div className="mb-4 flex flex-wrap gap-2">
                {categories.map((c) => {
                    const active = c.key === category;
                    return (
                        <Link
                            key={c.key}
                            href={route('admin.archive.index', { category: c.key })}
                            className={`rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 ${
                                active
                                    ? 'bg-blue-600 text-white ring-blue-600'
                                    : 'bg-sky-50 text-sky-800 ring-sky-200 hover:bg-sky-100'
                            }`}
                        >
                            {c.label}
                            <span className={`ml-1.5 ${active ? 'text-blue-100' : 'text-sky-600'}`}>({c.count})</span>
                        </Link>
                    );
                })}
            </div>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            {['Record', 'Details', 'Archived', 'Actions'].map((h) => (
                                <th
                                    key={h}
                                    className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    {h}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {items.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-8 text-center text-sm text-gray-400">
                                    No archived records in this category.
                                </td>
                            </tr>
                        )}
                        {items.map((row) => (
                            <tr key={row.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3 text-sm text-gray-900">
                                    <div className="font-medium">{row.title}</div>
                                    {row.subtitle && <div className="text-xs text-gray-500">{row.subtitle}</div>}
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-600">{row.meta || '—'}</td>
                                <td className="px-4 py-3 text-sm text-gray-600">
                                    {row.deleted_at ? new Date(row.deleted_at).toLocaleString() : '—'}
                                </td>
                                <td className="px-4 py-3 text-right text-sm">
                                    <button
                                        type="button"
                                        onClick={() => restore(row.id)}
                                        className="font-medium text-emerald-700 hover:underline"
                                    >
                                        Restore
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => purge(row.id)}
                                        className="ml-4 font-medium text-rose-700 hover:underline"
                                    >
                                        Delete forever
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination paginator={paginator} />
        </AdminLayout>
    );
}
