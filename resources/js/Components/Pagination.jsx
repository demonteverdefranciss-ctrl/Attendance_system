import { router } from '@inertiajs/react';

/**
 * Laravel LengthAwarePaginator controls for Inertia pages.
 * Expects the paginator shape Inertia serializes (data + links/meta).
 */
export default function Pagination({ paginator, className = '' }) {
    if (!paginator) return null;

    const current = paginator.current_page ?? paginator.meta?.current_page;
    const last = paginator.last_page ?? paginator.meta?.last_page;
    const total = paginator.total ?? paginator.meta?.total;
    const from = paginator.from ?? paginator.meta?.from;
    const to = paginator.to ?? paginator.meta?.to;
    const links = paginator.links ?? paginator.meta?.links;

    if (!last || last <= 1) {
        return null;
    }

    const go = (url) => {
        if (!url) return;
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const pageButtons = [];
    if (Array.isArray(links) && links.length > 0) {
        links.forEach((link, index) => {
            const label = String(link.label ?? '')
                .replace('&laquo; Previous', 'Prev')
                .replace('Next &raquo;', 'Next')
                .replace(/&laquo;/g, '«')
                .replace(/&raquo;/g, '»');
            const isPrevNext = index === 0 || index === links.length - 1;
            pageButtons.push(
                <button
                    key={`${label}-${index}`}
                    type="button"
                    disabled={!link.url || link.active}
                    onClick={() => go(link.url)}
                    className={`min-w-[2.25rem] rounded-lg px-2.5 py-1.5 text-sm font-medium transition ${
                        link.active
                            ? 'bg-blue-600 text-white'
                            : link.url
                              ? 'bg-sky-50 text-sky-800 ring-1 ring-inset ring-sky-200 hover:bg-sky-100'
                              : 'cursor-not-allowed bg-gray-50 text-gray-400'
                    } ${isPrevNext ? 'px-3' : ''}`}
                >
                    {label}
                </button>,
            );
        });
    } else {
        for (let page = 1; page <= last; page += 1) {
            pageButtons.push(
                <button
                    key={page}
                    type="button"
                    disabled={page === current}
                    onClick={() => {
                        const path = paginator.path ?? paginator.meta?.path ?? window.location.pathname;
                        const params = new URLSearchParams(window.location.search);
                        params.set('page', String(page));
                        go(`${path}?${params.toString()}`);
                    }}
                    className={`min-w-[2.25rem] rounded-lg px-2.5 py-1.5 text-sm font-medium ${
                        page === current
                            ? 'bg-blue-600 text-white'
                            : 'bg-sky-50 text-sky-800 ring-1 ring-inset ring-sky-200 hover:bg-sky-100'
                    }`}
                >
                    {page}
                </button>,
            );
        }
    }

    return (
        <div className={`mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between ${className}`}>
            <p className="text-sm text-gray-500">
                Showing {from ?? 0}–{to ?? 0} of {total ?? 0}
            </p>
            <div className="flex flex-wrap items-center gap-1.5">{pageButtons}</div>
        </div>
    );
}

/** Normalize either a plain array or a Laravel paginator into rows + paginator. */
export function usePageRows(payload) {
    if (Array.isArray(payload)) {
        return { rows: payload, paginator: null };
    }
    if (payload && Array.isArray(payload.data)) {
        return { rows: payload.data, paginator: payload };
    }
    return { rows: [], paginator: null };
}
