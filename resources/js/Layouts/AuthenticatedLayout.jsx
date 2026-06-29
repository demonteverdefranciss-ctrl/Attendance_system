import { router, usePage } from '@inertiajs/react';

export default function AuthenticatedLayout({ title, children }) {
    const { auth, flash } = usePage().props;

    const logout = (e) => {
        e.preventDefault();
        router.post('/logout');
    };

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="bg-white shadow-sm">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 items-center justify-between">
                        <div className="flex items-center gap-2 font-bold text-gray-800">
                            <span className="text-blue-600">🎓</span> Attendance System
                        </div>
                        <div className="flex items-center gap-4">
                            <div className="text-right">
                                <div className="text-sm font-medium text-gray-800">
                                    {auth?.user?.name}
                                </div>
                                <div className="text-xs uppercase tracking-wide text-gray-400">
                                    {auth?.user?.role}
                                </div>
                            </div>
                            <button
                                onClick={logout}
                                className="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200"
                            >
                                Logout
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {flash?.success && (
                    <div className="mb-4 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">
                        {flash.error}
                    </div>
                )}

                {title && (
                    <h1 className="mb-6 text-2xl font-bold text-gray-900">{title}</h1>
                )}

                {children}
            </main>
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
