import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function AppSidebarLayout({ nav = [], title, actions, children }) {
    const { auth, flash, teacherAlerts = [] } = usePage().props;
    const [open, setOpen] = useState(false);

    const logout = (e) => {
        e.preventDefault();
        router.post(route('logout'));
    };

    const dismissAlert = (id) => {
        router.post(route('teacher.notifications.read', id), {}, { preserveScroll: true });
    };

    useEffect(() => {
        if (!open) return undefined;

        const onKey = (e) => {
            if (e.key === 'Escape') setOpen(false);
        };
        window.addEventListener('keydown', onKey);
        document.body.classList.add('overflow-hidden');

        return () => {
            window.removeEventListener('keydown', onKey);
            document.body.classList.remove('overflow-hidden');
        };
    }, [open]);

    const NavLinks = ({ onNavigate }) => (
        <nav className="flex-1 space-y-1 px-3 py-2">
            {nav.map((item) => {
                const active =
                    route().current(item.route) ||
                    (item.route.endsWith('.index') && route().current(item.route.replace(/\.index$/, '.*'))) ||
                    (item.route === 'reports.index' && route().current('reports.*'));
                return (
                    <Link
                        key={item.route}
                        href={route(item.route)}
                        onClick={() => onNavigate?.()}
                        className={`block rounded-lg px-3 py-2 text-sm font-medium ${
                            active ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'
                        }`}
                    >
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );

    return (
        <div className="min-h-screen bg-gray-100">
            <div className="flex">
                {/* Desktop sidebar */}
                <aside className="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 bg-white border-r border-gray-200">
                    <div className="flex h-16 items-center gap-2 px-6 font-bold text-gray-800">
                        <span className="text-blue-600">Attendance</span>
                    </div>
                    <NavLinks />
                </aside>

                {/* Mobile drawer */}
                {open && (
                    <div className="fixed inset-0 z-40 md:hidden" role="dialog" aria-modal="true">
                        <button
                            type="button"
                            className="absolute inset-0 bg-gray-900/40"
                            aria-label="Close menu"
                            onClick={() => setOpen(false)}
                        />
                        <aside className="relative flex h-full w-72 max-w-[85vw] flex-col bg-white shadow-xl">
                            <div className="flex h-16 items-center justify-between border-b border-gray-100 px-4">
                                <div className="font-bold text-gray-800">Attendance</div>
                                <button
                                    type="button"
                                    onClick={() => setOpen(false)}
                                    className="rounded-lg p-2 text-gray-500 hover:bg-gray-100"
                                    aria-label="Close sidebar"
                                >
                                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <NavLinks onNavigate={() => setOpen(false)} />
                        </aside>
                    </div>
                )}

                <div className="flex-1 md:pl-64">
                    <header className="flex h-16 items-center justify-between gap-3 bg-white px-4 shadow-sm sm:px-6">
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                className="rounded-lg p-2 text-gray-700 hover:bg-gray-100 md:hidden"
                                aria-label="Open menu"
                                aria-expanded={open}
                                onClick={() => setOpen(true)}
                            >
                                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            <div className="font-bold text-gray-800 md:hidden">Attendance</div>
                        </div>
                        <div className="ml-auto flex items-center gap-3 sm:gap-4">
                            <div className="text-right">
                                <div className="text-sm font-medium text-gray-800">{auth?.user?.name}</div>
                                <div className="text-xs uppercase tracking-wide text-gray-400">{auth?.user?.role}</div>
                            </div>
                            <button
                                type="button"
                                onClick={logout}
                                className="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200"
                            >
                                Logout
                            </button>
                        </div>
                    </header>

                    <main className="p-4 sm:p-6">
                        {flash?.success && (
                            <div className="mb-4 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">{flash.success}</div>
                        )}
                        {flash?.error && (
                            <div className="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{flash.error}</div>
                        )}
                        {flash?.warning && (
                            <div className="mb-4 rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-800">{flash.warning}</div>
                        )}

                        {Array.isArray(teacherAlerts) && teacherAlerts.length > 0 && (
                            <div className="mb-4 space-y-2">
                                {teacherAlerts.map((alert) => (
                                    <div
                                        key={alert.id}
                                        className="flex flex-wrap items-start justify-between gap-3 rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-900 ring-1 ring-blue-200"
                                    >
                                        <div>
                                            <p className="font-semibold">{alert.title}</p>
                                            {alert.body && <p className="mt-0.5 text-blue-800">{alert.body}</p>}
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => dismissAlert(alert.id)}
                                            className="rounded-lg bg-white px-3 py-1 text-xs font-medium text-blue-800 ring-1 ring-blue-200 hover:bg-blue-100"
                                        >
                                            Dismiss
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}

                        <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                            {title && <h1 className="text-2xl font-bold text-gray-900">{title}</h1>}
                            {actions}
                        </div>

                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}

export function StatCard({ label, value }) {
    return (
        <div className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div className="text-3xl font-bold text-gray-900">{value}</div>
            <div className="mt-1 text-sm text-gray-500">{label}</div>
        </div>
    );
}
