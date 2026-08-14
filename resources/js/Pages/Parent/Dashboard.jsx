import { Head, Link } from '@inertiajs/react';
import ParentLayout from '@/Layouts/ParentLayout';
import { StatCard } from '@/Components/AppSidebarLayout';
import { formatDateTime } from '@/Pages/Parent/shared';

export default function ParentDashboard({
    stats,
    unreadCount = 0,
    recentNotifications = [],
    pendingLetters = 0,
    pendingEnrollment = 0,
}) {
    return (
        <ParentLayout title="Parent Dashboard">
            <Head title="Parent Dashboard" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="My Children" value={stats.children} />
                <StatCard label="Unread Notifications" value={unreadCount} />
                <StatCard label="Letters needing reply" value={pendingLetters} />
                <StatCard label="Pending enrollments" value={pendingEnrollment} />
            </div>

            <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                    <h2 className="text-base font-semibold text-gray-900">Quick links</h2>
                    <ul className="mt-3 space-y-2 text-sm">
                        <li>
                            <Link href={route('parent.attendance.index')} className="text-blue-600 hover:underline">
                                Attendance records
                            </Link>
                        </li>
                        <li>
                            <Link href={route('parent.biometrics.index')} className="text-blue-600 hover:underline">
                                Biometric face photos
                            </Link>
                        </li>
                        <li>
                            <Link href={route('parent.enrollment.index')} className="text-blue-600 hover:underline">
                                Register / enroll a child
                            </Link>
                        </li>
                        <li>
                            <Link href={route('parent.excuse-requests.index')} className="text-blue-600 hover:underline">
                                Explanation letters
                            </Link>
                        </li>
                        <li>
                            <Link href={route('parent.notifications.index')} className="text-blue-600 hover:underline">
                                Notifications & preferences
                            </Link>
                        </li>
                    </ul>
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                        <h2 className="text-base font-semibold text-gray-900">Recent notifications</h2>
                        <Link href={route('parent.notifications.index')} className="text-xs text-blue-600 hover:underline">
                            View all
                        </Link>
                    </div>
                    <div className="divide-y divide-gray-100">
                        {recentNotifications.length === 0 && (
                            <div className="px-4 py-8 text-center text-sm text-gray-400">No notifications yet.</div>
                        )}
                        {recentNotifications.map((n) => (
                            <div key={n.id} className="px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <h3 className="text-sm font-semibold text-gray-900">{n.title || 'Attendance Update'}</h3>
                                    {!n.read_at && (
                                        <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">Unread</span>
                                    )}
                                </div>
                                <p className="mt-1 line-clamp-2 text-sm text-gray-600">{n.body}</p>
                                <p className="mt-1 text-xs text-gray-400">{formatDateTime(n.sent_at)}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </ParentLayout>
    );
}
