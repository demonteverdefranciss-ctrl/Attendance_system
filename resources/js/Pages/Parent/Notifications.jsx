import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import ParentLayout from '@/Layouts/ParentLayout';
import { notificationRecordTime } from '@/lib/notificationTime';

export default function NotificationsIndex({ notifications = [], notifyPref = 'push' }) {
    const [preference, setPreference] = useState(notifyPref);
    const [markingAll, setMarkingAll] = useState(false);
    const unreadCount = notifications.filter((notification) => !notification.read_at).length;

    const markRead = (id) => {
        router.post(route('parent.notifications.read', id), {}, { preserveScroll: true });
    };

    const savePreference = () => {
        router.post(
            route('parent.notifications.preferences'),
            { notify_pref: preference },
            { preserveScroll: true },
        );
    };

    const markAllRead = () => {
        setMarkingAll(true);
        router.post(route('parent.notifications.read-all'), {}, {
            preserveScroll: true,
            onFinish: () => setMarkingAll(false),
        });
    };

    return (
        <ParentLayout title="Notifications">
            <Head title="Notifications" />

            <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h2 className="text-base font-semibold text-gray-900">Notification Preference</h2>
                <p className="mt-1 text-xs text-gray-500">Choose whether to receive parent push notifications.</p>
                <div className="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <select
                        value={preference}
                        onChange={(e) => setPreference(e.target.value)}
                        className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="push">Push notifications</option>
                        <option value="none">Disable notifications</option>
                    </select>
                    <button
                        type="button"
                        onClick={savePreference}
                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Save preference
                    </button>
                </div>
            </div>

            <div className="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                    <div>
                        <h2 className="text-base font-semibold text-gray-900">Notifications</h2>
                        <p className="text-xs text-gray-500">Latest attendance updates for your children</p>
                    </div>
                    <button
                        type="button"
                        onClick={markAllRead}
                        disabled={unreadCount === 0 || markingAll}
                        className="rounded-lg bg-sky-50 px-3 py-1.5 text-xs font-medium text-sky-800 ring-1 ring-inset ring-sky-200 hover:bg-sky-100 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {markingAll ? 'Marking...' : 'Mark all as read'}
                    </button>
                </div>
                <div className="divide-y divide-gray-100">
                    {notifications.length === 0 && (
                        <div className="px-4 py-8 text-center text-sm text-gray-400">No notifications yet.</div>
                    )}
                    {notifications.map((n) => (
                        <div
                            key={n.id}
                            className={`flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:justify-between ${
                                n.read_at ? 'bg-white' : 'bg-blue-50/70 ring-1 ring-inset ring-blue-100'
                            }`}
                        >
                            <div className={`min-w-0 flex-1 ${!n.read_at ? 'font-semibold' : ''}`}>
                                <div className="flex items-center gap-2">
                                    <span className={`inline-flex h-2.5 w-2.5 rounded-full ${n.read_at ? 'bg-gray-300' : 'bg-blue-600'}`} />
                                    <h3 className={`text-sm ${n.read_at ? 'font-semibold text-gray-700' : 'font-bold text-gray-900'}`}>
                                        {n.title || 'Attendance Update'}
                                    </h3>
                                    {n.read_at ? (
                                        <span className="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-800 ring-1 ring-inset ring-amber-200">
                                            Read
                                        </span>
                                    ) : (
                                        <span className="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700 ring-1 ring-inset ring-blue-200">
                                            Unread
                                        </span>
                                    )}
                                </div>
                                <p className={`mt-1 text-sm leading-6 ${n.read_at ? 'text-gray-600' : 'text-gray-800'}`}>
                                    {n.body || 'A new attendance event was recorded.'}
                                </p>
                                <p className="mt-2 text-xs text-gray-500">
                                    {notificationRecordTime(n.created_at || n.sent_at)} · Type: {n.type}
                                </p>
                            </div>
                            {!n.read_at && (
                                <button
                                    type="button"
                                    onClick={() => markRead(n.id)}
                                    className="rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-sky-800 ring-1 ring-inset ring-sky-200 hover:bg-sky-50"
                                >
                                    Mark as read
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </ParentLayout>
    );
}
