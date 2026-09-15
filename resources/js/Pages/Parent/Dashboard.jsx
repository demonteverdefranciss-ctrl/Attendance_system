import { Head, Link } from '@inertiajs/react';
import ParentLayout from '@/Layouts/ParentLayout';
import { formatDateTime } from '@/Pages/Parent/shared';

const icons = {
    attendance: 'M9 5H5v16h14V5h-4M9 3h6v4H9zM8 12l2 2 5-5M8 18h8',
    calendar: 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2M8 14h2m4 0h2m-8 3h2',
    photo: 'M8 5l2-2h4l2 2h4v15H4V5h4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
    children: 'M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0M5 21v-3a7 7 0 0 1 14 0v3M19 5v6m-3-3h6',
    letter: 'M4 5h16v14H4zM4 6l8 6 8-6',
    bell: 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
    arrow: 'M5 12h14m-5-5 5 5-5 5',
};
function Icon({ name, className = 'h-5 w-5' }) {
    return <svg aria-hidden="true" className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d={icons[name]} /></svg>;
}
const tones = { blue: 'bg-blue-50 text-blue-700', violet: 'bg-violet-50 text-violet-700', amber: 'bg-amber-50 text-amber-800', teal: 'bg-teal-50 text-teal-700' };
const focus = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-4';
const tasks = [
    { title: 'Attendance records', description: 'Check attendance, arrival, and departure times.', destination: 'parent.attendance.index', icon: 'attendance', tone: 'blue' },
    { title: 'No-class days', description: 'Plan ahead for school holidays and suspensions.', destination: 'parent.no-class-days.index', icon: 'calendar', tone: 'teal' },
    { title: 'Explanation letters', description: 'Reply to attendance concerns from the teacher.', destination: 'parent.excuse-requests.index', icon: 'letter', tone: 'amber' },
    { title: 'Notifications', description: 'Read school alerts and manage your preferences.', destination: 'parent.notifications.index', icon: 'bell', tone: 'violet' },
    { title: 'Enroll a child', description: 'Submit a child’s details or track your request.', destination: 'parent.enrollment.index', icon: 'children', tone: 'teal' },
    { title: 'Biometric face photos', description: 'Upload your child’s face photos for attendance.', destination: 'parent.biometrics.index', icon: 'photo', tone: 'violet' },
];
function QuickLink({ item, pendingLetters }) {
    return (
        <Link href={route(item.destination)} className={'group flex min-w-0 items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition-colors hover:border-blue-300 hover:bg-blue-50/50 ' + focus}>
            <span className={'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ' + tones[item.tone]}><Icon name={item.icon} className="h-6 w-6" /></span>
            <div className="min-w-0 flex-1">
                <h4 className="text-sm font-semibold text-gray-900 group-hover:text-blue-800">{item.title}</h4>
                <p className="mt-1 text-sm leading-relaxed text-gray-500">{item.description}</p>
                {item.icon === 'letter' && pendingLetters > 0 && <span className="mt-2 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">{pendingLetters} to reply</span>}
            </div>
            <Icon name="arrow" className="mt-1 h-4 w-4 shrink-0 text-gray-400 group-hover:text-blue-600" />
        </Link>
    );
}
export default function ParentDashboard({ stats, unreadCount = 0, recentNotifications = [], pendingLetters = 0, pendingEnrollment = 0 }) {
    const summaries = [
        { label: 'My children', value: stats?.children ?? 0, item: tasks[0], hint: 'View attendance', icon: 'children' },
        { label: 'Unread notifications', value: unreadCount, item: tasks[3], hint: 'Read school updates' },
        { label: 'Letters needing reply', value: pendingLetters, item: tasks[2], hint: 'View explanation letters' },
        { label: 'Pending enrollments', value: pendingEnrollment, item: tasks[4], hint: 'Check enrollment status' },
    ];
    return (
        <ParentLayout title="Parent Dashboard">
            <Head title="Parent Dashboard" />
            <p className="-mt-3 mb-6 text-sm leading-relaxed text-gray-500">Keep up with your children’s attendance, school updates, and requests in one place.</p>
            <section aria-label="Attendance overview" className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {summaries.map((s) => (
                    <Link key={s.label} href={route(s.item.destination)} className={'rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-colors hover:border-blue-300 ' + focus}>
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-sm font-medium text-gray-600">{s.label}</span>
                            <span className={'rounded-xl p-2.5 ' + tones[s.item.tone]}><Icon name={s.icon || s.item.icon} /></span>
                        </div>
                        <p className="mt-2 break-words text-3xl font-bold tracking-tight text-gray-900">{s.value}</p>
                        <p className="mt-3 flex items-center justify-between gap-2 text-xs font-medium text-blue-700">{s.hint}<Icon name="arrow" className="h-4 w-4 shrink-0" /></p>
                    </Link>
                ))}
            </section>
            {pendingLetters > 0 && (
                <aside className="mt-5 flex flex-wrap items-center gap-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4" aria-label="Letters needing your attention">
                    <span className="rounded-xl bg-white p-2.5 text-amber-700"><Icon name="letter" /></span>
                    <div className="min-w-0 flex-1 basis-48">
                        <p className="text-sm font-semibold text-amber-950">{pendingLetters} explanation {pendingLetters === 1 ? 'letter needs' : 'letters need'} your reply</p>
                        <p className="mt-1 text-sm text-amber-800">Send a reply to help the teacher understand your child’s attendance.</p>
                    </div>
                    <Link href={route('parent.excuse-requests.index')} className={'inline-flex min-h-11 items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-amber-900 ring-1 ring-inset ring-amber-200 hover:bg-amber-100 ' + focus}>Review letters<Icon name="arrow" className="h-4 w-4" /></Link>
                </aside>
            )}
            <div className="mt-6 grid grid-cols-1 items-start gap-6 xl:grid-cols-5">
                <section aria-labelledby="quick-links-title" className="min-w-0 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-3">
                    <h2 id="quick-links-title" className="text-lg font-semibold text-gray-900">Quick links</h2>
                    <p className="mt-1 text-sm text-gray-500">What would you like to do?</p>
                    {[{ title: 'Attendance & school updates', items: tasks.slice(0, 4) }, { title: 'Child setup', items: tasks.slice(4) }].map((group) => (
                        <div key={group.title}>
                            <h3 className="mb-3 mt-6 text-xs font-semibold uppercase tracking-wider text-gray-500">{group.title}</h3>
                            <div className="grid gap-3 sm:grid-cols-2">{group.items.map((item) => <QuickLink key={item.destination} item={item} pendingLetters={pendingLetters} />)}</div>
                        </div>
                    ))}
                </section>
                <section aria-labelledby="notifications-title" className="min-w-0 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm xl:col-span-2">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-5">
                        <div><h2 id="notifications-title" className="text-lg font-semibold text-gray-900">Recent notifications</h2><p className="mt-1 text-sm text-gray-500">The latest updates from school</p></div>
                        <Link href={route('parent.notifications.index')} className={'inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-semibold text-blue-700 hover:bg-blue-50 ' + focus}>View all</Link>
                    </div>
                    <div className="divide-y divide-gray-100">
                        {recentNotifications.length === 0 && (
                            <div className="px-5 py-12 text-center">
                                <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600"><Icon name="bell" /></span>
                                <p className="mt-4 text-sm font-semibold text-gray-800">No notifications yet</p>
                                <p className="mt-1 text-sm text-gray-500">School attendance updates will appear here.</p>
                            </div>
                        )}
                        {recentNotifications.slice(0, 4).map((n) => (
                            <div key={n.id} className="flex items-start gap-3 px-5 py-4">
                                <span className={'mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ' + (n.read_at ? 'bg-gray-100 text-gray-400' : 'bg-blue-50 text-blue-600')}><Icon name="bell" className="h-4 w-4" /></span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 className="text-sm font-semibold text-gray-900">{n.title || 'Attendance update'}</h3>
                                        {!n.read_at && <span className="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Unread</span>}
                                    </div>
                                    <p className="mt-1 break-words text-sm leading-relaxed text-gray-600">{n.body}</p>
                                    {n.sent_at && <p className="mt-2 text-xs text-gray-500">{formatDateTime(n.sent_at)}</p>}
                                </div>
                            </div>
                        ))}
                    </div>
                    {recentNotifications.length > 0 && <div className="border-t border-gray-100 bg-gray-50/70 px-5 py-3"><Link href={route('parent.notifications.index')} className={'inline-flex min-h-11 items-center gap-2 rounded-lg text-sm font-semibold text-blue-700 hover:underline ' + focus}>Open notification center<Icon name="arrow" className="h-4 w-4" /></Link></div>}
                </section>
            </div>
        </ParentLayout>
    );
}