import { Link, router, usePage } from '@inertiajs/react';

const nav = [
    { label: 'Dashboard', route: 'teacher.dashboard' },
    { label: 'Mark Attendance', route: 'teacher.attendance.index' },
    { label: 'Enrollment Requests', route: 'teacher.enrollment-requests.index' },
    { label: 'Biometric Photos', route: 'teacher.biometric-photos.index' },
    { label: 'Reports', route: 'reports.index' },
];

export default function TeacherLayout({ title, actions, children }) {
    const { auth, flash } = usePage().props;

    const logout = (e) => {
        e.preventDefault();
        router.post(route('logout'));
    };

    return (
        <div className="min-h-screen bg-gray-100">
            <div className="flex">
                <aside className="hidden md:flex md:w-64 md:flex-col md:fixed md:inset-y-0 bg-white border-r border-gray-200">
                    <div className="flex h-16 items-center gap-2 px-6 font-bold text-gray-800">
                        <span className="text-blue-600">🎓</span> Attendance
                    </div>
                    <nav className="flex-1 space-y-1 px-3 py-2">
                        {nav.map((item) => {
                            const active = route().current(item.route);
                            return (
                                <Link
                                    key={item.route}
                                    href={route(item.route)}
                                    className={`block rounded-lg px-3 py-2 text-sm font-medium ${
                                        active ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <div className="flex-1 md:pl-64">
                    <header className="flex h-16 items-center justify-between bg-white px-6 shadow-sm">
                        <div className="md:hidden font-bold text-gray-800">🎓 Attendance</div>
                        <div className="ml-auto flex items-center gap-4">
                            <div className="text-right">
                                <div className="text-sm font-medium text-gray-800">{auth?.user?.name}</div>
                                <div className="text-xs uppercase tracking-wide text-gray-400">{auth?.user?.role}</div>
                            </div>
                            <button onClick={logout} className="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200">
                                Logout
                            </button>
                        </div>
                    </header>

                    <main className="p-6">
                        {flash?.success && (
                            <div className="mb-4 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">{flash.success}</div>
                        )}
                        {flash?.error && (
                            <div className="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{flash.error}</div>
                        )}
                        {flash?.warning && (
                            <div className="mb-4 rounded-lg bg-amber-50 px-4 py-2 text-sm text-amber-800">{flash.warning}</div>
                        )}

                        <div className="mb-6 flex items-center justify-between">
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
