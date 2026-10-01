export default function LocalSortHeading({ column, sorting, children }) {
    const active = sorting.sort.key === column;
    return <th scope="col" aria-sort={active ? (sorting.sort.descending ? 'descending' : 'ascending') : 'none'} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
        <button type="button" onClick={() => sorting.onSort(column)} className="inline-flex min-h-9 items-center gap-2 text-left hover:text-blue-700 focus-visible:outline-blue-600">
            {children}<span aria-hidden="true">{active ? (sorting.sort.descending ? '↓' : '↑') : '↕'}</span>
        </button>
    </th>;
}
