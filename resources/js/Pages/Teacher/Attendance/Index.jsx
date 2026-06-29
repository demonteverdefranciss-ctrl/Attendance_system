import { Head, Link, router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function AttendanceIndex({ rows, today }) {
    const openSession = (sectionId) => {
        router.post(route('teacher.attendance.open'), { section_id: sectionId });
    };

    const closeSession = (id) => {
        if (confirm('Close this session? Unmarked students will be recorded absent.')) {
            router.post(route('teacher.attendance.close', id), {}, { preserveScroll: true });
        }
    };

    return (
        <TeacherLayout title="Mark Attendance">
            <Head title="Mark Attendance" />

            <p className="mb-4 text-sm text-gray-500">Today: {today}</p>

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
                                </span>
                            )}
                        </div>

                        {session ? (
                            <>
                                <div className="mt-4 flex gap-6 text-sm">
                                    <div><span className="font-semibold text-green-600">{session.present_count}</span> present/late</div>
                                    <div><span className="font-semibold text-red-600">{session.absent_count}</span> absent</div>
                                </div>
                                <div className="mt-4 flex gap-3">
                                    <Link href={route('teacher.attendance.show', session.id)} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                        Mark
                                    </Link>
                                    {session.status === 'open' && (
                                        <button onClick={() => closeSession(session.id)} className="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                                            Close session
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
