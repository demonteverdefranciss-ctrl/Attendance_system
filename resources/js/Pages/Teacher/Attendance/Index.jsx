import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function AttendanceIndex({ rows, today, adhocMaxMinutes = 30 }) {
    const [closingId, setClosingId] = useState(null);

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

    const fmt = (value) => {
        if (!value) return null;
        const d = new Date(value);
        return Number.isNaN(d.getTime()) ? value : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    return (
        <TeacherLayout title="Mark Attendance">
            <Head title="Mark Attendance" />

            <p className="mb-2 text-sm text-gray-500">Today: {today}</p>
            <p className="mb-4 text-xs text-gray-500">
                Schedule windows auto-open and auto-close on the website.
                Manual &quot;Open Attendance&quot; sessions auto-close after {adhocMaxMinutes} minutes.
                Keep <code className="rounded bg-gray-100 px-1">run_recognition.ps1</code> running on the school PC so the camera follows open/close.
            </p>

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
                                <div className="mt-4 flex gap-3">
                                    <Link href={route('teacher.attendance.show', session.id)} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                        Mark
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
