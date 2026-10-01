import { router, usePage } from '@inertiajs/react';

export default function SortableHeading({ column, children, prefix = '', pageName = 'page', className = 'px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500' }) {
    const { url } = usePage();
    const params = new URLSearchParams(url.split('?')[1] || '');
    const active = params.get(`${prefix}sort`) === column;
    const descending = active && params.get(`${prefix}direction`) === 'desc';
    const sort = () => {
        params.set(`${prefix}sort`, column);
        params.set(`${prefix}direction`, active && !descending ? 'desc' : 'asc');
        params.delete(pageName);
        router.get(url.split('?')[0], Object.fromEntries(params), { preserveState: true, preserveScroll: true });
    };
    return <th scope="col" className={className} aria-sort={active ? (descending ? 'descending' : 'ascending') : 'none'}>
        <button type="button" onClick={sort} className="inline-flex min-h-9 items-center gap-2 text-left hover:text-blue-700 focus-visible:outline-blue-600" title={`Sort ${active && !descending ? 'descending' : 'ascending'}`}>
            {children}<span aria-hidden="true">{active ? (descending ? '↓' : '↑') : '↕'}</span>
        </button>
    </th>;
}
