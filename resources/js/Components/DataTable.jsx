import { Link, router } from '@inertiajs/react';

/**
 * Generic admin table.
 *
 * columns: [{ key, label, render? }]
 * rows: array of objects with an `id`
 * editRoute / destroyRoute: Ziggy route names taking the row id
 */
export default function DataTable({ columns, rows, editRoute, destroyRoute, emptyText = 'No records yet.' }) {
    const remove = (id) => {
        if (confirm('Are you sure you want to delete this record?')) {
            router.delete(route(destroyRoute, id), { preserveScroll: true });
        }
    };

    return (
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
                    {rows.length === 0 && (
                        <tr>
                            <td colSpan={columns.length + 1} className="px-4 py-8 text-center text-sm text-gray-400">
                                {emptyText}
                            </td>
                        </tr>
                    )}
                    {rows.map((row) => (
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
                                    Delete
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
