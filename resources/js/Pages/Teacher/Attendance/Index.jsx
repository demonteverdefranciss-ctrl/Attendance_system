import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function AttendanceIndex({ rows, today, adhocMaxMinutes = 30, testClearEnabled = false }) {
    const [closingId, setClosingId] = useState(null);
    const [clearing, setClearing] = useState(false);
    const flash = usePage().props.flash ?? {};

    const openSession = (sectionId) => {
        router.post(route('teacher.attendance.open'), { section_id: sectionId });
    };

    const closeSession = (id) => {
        if (closingId) return;
        if (!confirm('Close this session? Unmarked students will be recorded absent.')) {
            return;
        }
        setClosingId(id);
        router.post(route('teacher.attendance.close', id), {}, {
            preserveScroll: true,
            onError: () => setClosingId(null),
            onFinish: () => setClosingId(null),
        });
    };

    const clearToday = () => {
        if (clearing) return;
        if (!confirm(
            "TEMPORARY TEST TOOL\n\nDelete ALL of today's attendance sessions and marks for your sections?\n\nThis cannot be undone. Use only while testing recognition.",
        )) {
            return;
        }
        setClearing(true);
        router.post(route('teacher.attendance.clear-today'), {}, {
            onFinish: () => setClearing(false),
        });
    };

    const fmt = (value) => {
        if (!value) return null;
        const d = new Date(value);
        return Number.isNaN(d.getTime()) ? value : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    return (
        <TeacherLayout title="Mark Attendance">
            <Head title="Mark Attendance" />

            {flash.success && (
                <div className="mb-4 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200">
                    {flash.success}
                </div>
            )}

            <p className="mb-2 text-sm text-gray-500">Today: {today}</p>
            <p className="mb-4 text-xs text-gray-500">
                Schedule windows auto-open and auto-close on the website.
                Manual &quot;Open Attendance&quot; sessions auto-close after {adhocMaxMinutes} minutes.
                Keep <code className="rounded bg-gray-100 px-1">run_recognition.ps1</code> running on the school PC so the camera follows open/close.
            </p>

            {testClearEnabled && (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-950 ring-1 ring-amber-300">
                    <div>
                        <p className="font-semibold">TEMPORARY — testing only (remove later)</p>
                        <p className="text-amber-900/90">
                            Clears today&apos;s sessions and marks so you can re-open and retest the camera.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={clearToday}
                        disabled={clearing}
                        className="rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800 disabled:opacity-50"
                    >
                        {clearing ? 'Clearing…' : "Clear today's attendance"}
                    </button>
                </div>
            )}

            {rows.length === 0 && (
                <div className="rounded-xl bg-white p-8 text-center text-sm text-gray-400 shadow-sm ring-1 ring-gray-200">
                    You are not assigned as adviser to any section yet.
                </div>
            )}

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {rows.map(({ section, session }) => (
                    <div key={section.id} className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div className="flex items-start justify-between">
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">
                                    {section.grade_level} - {section.name}
                                </h2>
                                <p className="text-sm text-gray-500">{section.students_count} students</p>
                            </div>
                            {session && (
                                <span className={`rounded-full px-2 py-0.5 text-xs ${session.status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                    {session.status}
                                    {session.is_adhoc ? ' · manual' : ' · schedule'}
                                </span>
                            )}
                        </div>

                        {session ? (
                            <>
                                <div className="mt-4 flex gap-6 text-sm">
                                    <div><span className="font-semibold text-green-600">{session.present_count}</span> present/late</div>
                                    <div><span className="font-semibold text-red-600">{session.absent_count}</span> absent</div>
                                </div>
                                {session.status === 'open' && (
                                    <p className="mt-2 text-xs text-gray-500">
                                        Opened {fmt(session.opened_at) || '—'}
                                        {session.auto_close_at
                                            ? ` · auto-closes ~${fmt(session.auto_close_at)}`
                                            : session.is_adhoc
                                                ? ''
                                                : ' · closes at schedule end'}
                                    </p>
                                )}
                                <div className="mt-4 flex flex-wrap gap-3">
                                    <Link href={route('teacher.attendance.show', session.id)} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                        Mark
                                    </Link>
                                    <Link
                                        href={route('reports.session', session.id)}
                                        className="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
                                    >
                                        View report
                                    </Link>
                                    {session.status === 'open' && (
                                        <button
                                            onClick={() => closeSession(session.id)}
                                            disabled={closingId === session.id}
                                            className="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 disabled:opacity-50"
                                        >
                                            {closingId === session.id ? 'Closing…' : 'Close session'}
                                        </button>
                                    )}
                                    {session.status === 'closed' && (
                                        <button onClick={() => openSession(section.id)} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                            Re-open Attendance
                                        </button>
                                    )}
                                </div>
                            </>
                        ) : (
                            <button onClick={() => openSession(section.id)} className="mt-4 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                Open Attendance
                            </button>
                        )}
                    </div>
                ))}
            </div>
        </TeacherLayout>
    );
}
