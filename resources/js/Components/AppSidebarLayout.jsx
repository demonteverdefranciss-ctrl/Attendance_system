import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import FlashMessages from '@/Components/FlashMessages';
import SidebarIcon from '@/Components/SidebarIcon';
import { notificationRecordTime } from '@/lib/notificationTime';

const THEMES = [
    { value: 'default', label: 'Default' },
    { value: 'dark', label: 'Dark mode' },
    { value: 'facebook', label: 'Facebook blue' },
    { value: 'youtube', label: 'YouTube red' },
];

const CONTRASTS = [
    { value: 'standard', label: 'Standard' },
    { value: 'high', label: 'High contrast' },
    { value: 'soft', label: 'Soft contrast' },
];

const CONTENT_VISIBILITY_ROUTES = {
    attendance: new Set(['teacher.attendance.index', 'parent.attendance.index']),
    notifications: new Set(['parent.notifications.index']),
    biometrics: new Set(['teacher.biometric-photos.index', 'parent.biometrics.index']),
    enrollments: new Set(['teacher.enrollment-requests.index', 'parent.enrollment.index']),
};

const CONTENT_MANAGEMENT_OPTIONS = [
    { key: 'attendance', label: 'Attendance modules' },
    { key: 'notifications', label: 'Notification center' },
    { key: 'biometrics', label: 'Biometric portal' },
    { key: 'enrollments', label: 'Enrollment workflows' },
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
    const [accessibilityOpen, setAccessibilityOpen] = useState(false);
    const [theme, setTheme] = useState(() => {
        if (typeof window === 'undefined') return 'default';
        return THEMES.some((item) => item.value === window.localStorage.getItem('attendance-theme'))
            ? window.localStorage.getItem('attendance-theme')
            : 'default';
    });
    const [fontScale, setFontScale] = useState(() => {
        if (typeof window === 'undefined') return 1;
        const saved = Number(window.localStorage.getItem('attendance-font-scale')); 
        return Number.isFinite(saved) && saved >= 0.9 && saved <= 1.4 ? saved : 1;
    });
    const [contrast, setContrast] = useState(() => {
        if (typeof window === 'undefined') return 'standard';
        return CONTRASTS.some((item) => item.value === window.localStorage.getItem('attendance-contrast'))
            ? window.localStorage.getItem('attendance-contrast')
            : 'standard';
    });
    const [contentSettings, setContentSettings] = useState(() => ({
        attendance: true,
        notifications: true,
        biometrics: true,
        enrollments: true,
    }));

    useEffect(() => {
        window.localStorage.setItem('attendance-theme', theme);
    }, [theme]);

    useEffect(() => {
        window.localStorage.setItem('attendance-font-scale', String(fontScale));
        document.documentElement.style.setProperty('--app-font-scale', String(fontScale));
        document.documentElement.style.fontSize = `${fontScale * 100}%`;
    }, [fontScale]);

    useEffect(() => {
        const mode = contrast === 'high' ? 'high-contrast' : contrast === 'soft' ? 'soft-contrast' : 'standard-contrast';
        document.documentElement.dataset.contrastMode = mode;
        window.localStorage.setItem('attendance-contrast', contrast);
    }, [contrast]);

    useEffect(() => {
        const saved = window.localStorage.getItem('attendance-content-settings');
        if (saved) {
            try {
                setContentSettings({ ...contentSettings, ...JSON.parse(saved) });
            } catch (error) {
                console.warn('Unable to load content settings', error);
            }
        }
    }, []);

    useEffect(() => {
        window.localStorage.setItem('attendance-content-settings', JSON.stringify(contentSettings));
    }, [contentSettings]);

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

    const visibleNav = nav.filter((item) =>
        Object.entries(CONTENT_VISIBILITY_ROUTES).every(([key, routes]) =>
            !routes.has(item.route) || contentSettings[key],
        ),
    );
    const availableContentOptions = CONTENT_MANAGEMENT_OPTIONS.filter(({ key }) =>
        nav.some((item) => CONTENT_VISIBILITY_ROUTES[key].has(item.route)),
    );

    const NavLinks = ({ onNavigate }) => (
        <nav className="min-h-0 flex-1 space-y-1 overflow-y-auto overscroll-contain px-3 py-2">
            {visibleNav.map((item) => {
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
                            <button
                                type="button"
                                onClick={() => setAccessibilityOpen((state) => !state)}
                                className="inline-flex min-h-11 w-11 shrink-0 items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-2 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100 sm:w-auto sm:px-3"
                                aria-expanded={accessibilityOpen}
                            >
                                <svg className="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                                    <circle cx="12" cy="12" r="9" />
                                    <circle cx="12" cy="8" r="1" />
                                    <path strokeLinecap="round" d="M12 11v6m-4-4h8" />
                                </svg>
                                <span className="hidden sm:inline">Accessibility</span>
                            </button>
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

                    {accessibilityOpen && (
                        <div className="mx-3 mt-3 rounded-2xl border border-blue-200 bg-white p-4 shadow-sm sm:mx-6">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="text-base font-semibold text-gray-900">Accessibility & Content Management</h2>
                                    <p className="text-xs text-gray-500">Adjust readability and manage visibility preferences.</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setAccessibilityOpen(false)}
                                    className="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                                >
                                    Close
                                </button>
                            </div>

                            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                                <div className="rounded-xl border border-gray-200 bg-gray-50 p-3">
                                    <p className="text-sm font-semibold text-gray-900">Display & Accessibility</p>
                                    <label className="mt-3 block text-xs font-medium text-gray-600">
                                        Font size: {fontScale.toFixed(1)}x
                                    </label>
                                    <input
                                        type="range"
                                        min="0.9"
                                        max="1.4"
                                        step="0.1"
                                        value={fontScale}
                                        onChange={(event) => setFontScale(Number(event.target.value))}
                                        className="mt-2 w-full accent-blue-600"
                                    />
                                    <label className="mt-3 block text-xs font-medium text-gray-600">
                                        Colour theme
                                        <select
                                            value={theme}
                                            onChange={(event) => setTheme(event.target.value)}
                                            className="mt-1 block min-h-11 w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        >
                                            {THEMES.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                        </select>
                                    </label>
                                    <div className="mt-3 space-y-2">
                                        {CONTRASTS.map((option) => (
                                            <label key={option.value} className="flex items-center gap-2 text-sm text-gray-700">
                                                <input
                                                    type="radio"
                                                    name="accessibility-contrast"
                                                    value={option.value}
                                                    checked={contrast === option.value}
                                                    onChange={() => setContrast(option.value)}
                                                />
                                                {option.label}
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                {availableContentOptions.length > 0 && (
                                    <div className="rounded-xl border border-gray-200 bg-gray-50 p-3">
                                        <p className="text-sm font-semibold text-gray-900">Content Management</p>
                                        <div className="mt-3 space-y-2 text-sm text-gray-700">
                                            {availableContentOptions.map(({ key, label }) => (
                                            <label key={key} className="flex items-center justify-between gap-3 rounded-lg bg-white px-2.5 py-2">
                                                <span>{label}</span>
                                                <input
                                                    type="checkbox"
                                                    checked={contentSettings[key]}
                                                    onChange={() => setContentSettings((current) => ({
                                                        ...current,
                                                        [key]: !current[key],
                                                    }))}
                                                    className="h-4 w-4 accent-blue-600"
                                                />
                                            </label>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

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
