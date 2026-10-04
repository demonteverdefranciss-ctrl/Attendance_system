import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import FlashMessages from '@/Components/FlashMessages';
import SidebarIcon from '@/Components/SidebarIcon';
import { notificationRecordTime } from '@/lib/notificationTime';

const THEMES = [
    { value: 'default', label: 'Default' },
    { value: 'dark', label: 'Dark mode' },
    { value: 'facebook', label: 'Facebook blue' },
    { value: 'youtube', label: 'YouTube red' },
];

function BrandMark({ logoUrl, compact = false }) {
    return (
        <div className="flex min-w-0 select-none items-center gap-2 pointer-events-none">
            <img
                src={logoUrl}
                alt="Bigaa Elementary School"
                className="h-8 w-8 shrink-0 rounded-full bg-white object-contain"
            />
            <div className={`min-w-0 flex-col leading-tight ${compact ? 'hidden sm:flex' : 'flex'}`}>
                <span className="text-sm font-bold text-blue-600">
                    Bigaa Elementary School
                </span>
                <span className="text-xs text-gray-500">
                    Attendance Management System
                </span>
            </div>
        </div>
    );
}

export default function AppSidebarLayout({ nav = [], title, actions, children }) {
    const { auth, teacherAlerts = [], assetBase } = usePage().props;
    const logoUrl = `${assetBase || ''}/branding/bigaa-logo.png`;
    const [open, setOpen] = useState(false);
    const [theme, setTheme] = useState(() => {
        if (typeof window === 'undefined') return 'default';
        return THEMES.some((item) => item.value === window.localStorage.getItem('attendance-theme'))
            ? window.localStorage.getItem('attendance-theme')
            : 'default';
    });

    useEffect(() => {
        window.localStorage.setItem('attendance-theme', theme);
    }, [theme]);

    useEffect(() => {
        const desktop = window.matchMedia('(min-width: 1024px)');
        const closeOnDesktop = () => { if (desktop.matches) setOpen(false); };
        desktop.addEventListener('change', closeOnDesktop);
        return () => desktop.removeEventListener('change', closeOnDesktop);
    }, []);

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
        <nav className="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain px-3 py-2">
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
                        className={`flex min-h-11 items-center rounded-lg px-3 py-2 text-sm font-medium hover:bg-blue-500 hover:text-white ${
                            active ? 'bg-blue-50 text-blue-700' : 'text-gray-600'
                        }`}
                    >
                        <span className="mr-3 flex items-center"><SidebarIcon destination={item.route} /></span>
                        <span>{item.label}</span>
                    </Link>
                );
            })}
        </nav>
    );

    return (
        <div className={`theme-${theme} min-h-screen bg-gray-100`}>
            <div className="flex">
                {/* Desktop sidebar */}
                <aside className="theme-sidebar hidden lg:flex lg:w-72 lg:flex-col lg:fixed lg:inset-y-0 bg-blue-200 border-r border-gray-200">
                    <div className="flex h-16 items-center px-4">
                        <BrandMark logoUrl={logoUrl} />
                    </div>
                    <NavLinks />
                </aside>

                {/* Mobile drawer */}
                {open && (
                    <div id="mobile-navigation" className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Navigation menu">
                        <button
                            type="button"
                            className="absolute inset-0 bg-gray-900/40"
                            aria-label="Close menu"
                            onClick={() => setOpen(false)}
                        />
                        <aside className="theme-sidebar relative flex h-full w-72 max-w-[85vw] flex-col bg-blue-200 shadow-xl">
                            <div className="flex min-h-20 shrink-0 items-center justify-between gap-2 border-b border-gray-100 px-3 py-3">
                                <BrandMark logoUrl={logoUrl} />
                                <button
                                    type="button"
                                    onClick={() => setOpen(false)}
                                    className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100"
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

                <div className="min-w-0 flex-1 lg:pl-72">
                    <header className="flex h-16 items-center justify-between gap-3 bg-white px-4 shadow-sm sm:px-6">
                        <div className="flex min-w-0 items-center gap-2">
                            <button
                                type="button"
                                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100 lg:hidden"
                                aria-label="Open menu"
                                aria-expanded={open}
                                aria-controls="mobile-navigation"
                                onClick={() => setOpen(true)}
                            >
                                <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            <div className="min-w-0 lg:hidden">
                                <BrandMark logoUrl={logoUrl} compact />
                            </div>
                        </div>
                        <div className="ml-auto flex min-w-0 items-center gap-2 sm:gap-4">
                            <label className="hidden items-center gap-2 text-xs text-gray-500 sm:flex">
                                <span>Theme</span>
                                <select
                                    value={theme}
                                    onChange={(event) => setTheme(event.target.value)}
                                    className="rounded-lg border-gray-300 bg-white py-1.5 text-xs text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    aria-label="Colour theme"
                                >
                                    {THEMES.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                </select>
                            </label>
                            <div className="min-w-0 text-right">
                                <div className="max-w-28 truncate text-sm font-medium text-gray-800 sm:max-w-48" title={auth?.user?.name}>{auth?.user?.name}</div>
                                <div className="text-xs uppercase tracking-wide text-gray-400">{auth?.user?.role}</div>
                            </div>
                            <button
                                type="button"
                                onClick={logout}
                                className="min-h-11 shrink-0 rounded-lg bg-sky-50 px-3 py-1.5 text-sm font-medium text-sky-800 ring-1 ring-inset ring-sky-200 hover:bg-sky-100"
                            >
                                Logout
                            </button>
                        </div>
                    </header>

                    <main className="min-w-0 p-3 sm:p-6">
                        <FlashMessages />

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
                                            <p className="mt-1 text-xs text-blue-700">{notificationRecordTime(alert.created_at)}</p>
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
                            {title && <h1 className="min-w-0 break-words text-xl font-bold text-gray-900 sm:text-2xl">{title}</h1>}
                            {actions}
                        </div>

                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}

export function StatCard({ label, value, href }) {
    const Component = href ? Link : 'div';
    return (
        <Component
            {...(href ? { href } : {})}
            className={`block min-w-0 break-words rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-6 ${href ? 'group transition hover:bg-blue-50 hover:ring-blue-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2' : ''}`}
        >
            <div className="text-3xl font-bold text-gray-900">{value}</div>
            <div className="mt-1 text-sm text-gray-500">{label}</div>
        </Component>
    );
}
