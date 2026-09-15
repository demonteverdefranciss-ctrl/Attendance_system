import { Link, router } from '@inertiajs/react';
import Pagination, { usePageRows } from '@/Components/Pagination';

/**
 * Generic admin table.
 *
 * columns: [{ key, label, render? }]
 * rows: array of objects with an `id`, OR a Laravel paginator object
 * editRoute / destroyRoute: Ziggy route names taking the row id
 */
export default function DataTable({ columns, rows, editRoute, destroyRoute, emptyText = 'No records yet.' }) {
    const { rows: items, paginator } = usePageRows(rows);

    const remove = (id) => {
        if (confirm('Move this record to the archive? You can restore it later from Archive.')) {
            router.delete(route(destroyRoute, id), { preserveScroll: true });
        }
    };

    return (
        <div>
            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            {columns.map((c) => (
                                <th key={c.key} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {c.label}
                                </th>
                            ))}
                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {items.length === 0 && (
                            <tr>
                                <td colSpan={columns.length + 1} className="px-4 py-8 text-center text-sm text-gray-400">
                                    {emptyText}
                                </td>
                            </tr>
                        )}
                        {items.map((row) => (
                            <tr key={row.id} className="hover:bg-gray-50">
                                {columns.map((c) => (
                                    <td key={c.key} className="px-4 py-3 text-sm text-gray-700">
                                        {c.render ? c.render(row) : row[c.key]}
                                    </td>
                                ))}
                                <td className="px-4 py-3 text-right text-sm">
                                    <Link href={route(editRoute, row.id)} className="text-blue-600 hover:underline">
                                        Edit
                                    </Link>
                                    <button onClick={() => remove(row.id)} className="ml-4 text-red-600 hover:underline">
                                        Archive
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <Pagination paginator={paginator} />
        </div>
    );
}
