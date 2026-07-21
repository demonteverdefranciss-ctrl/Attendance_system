import { useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import CameraPreview from '@/Components/CameraPreview';
import RecognitionStatus, { fetchRecognitionStatus, startRecognition } from '@/Components/RecognitionStatus';
import TeacherLayout from '@/Layouts/TeacherLayout';

const LIVE_REFRESH_MS = 2000;

const STATUSES = ['present', 'late', 'absent', 'excused'];
const COLORS = {
    present: 'bg-green-600',
    late: 'bg-amber-500',
    absent: 'bg-red-600',
    excused: 'bg-blue-500',
};

export default function Mark({ session, students, records, cameraStreamUrl, recognition }) {
    const [recognitionStatus, setRecognitionStatus] = useState(recognition?.status ?? 'unavailable');
    const [startingRecognition, setStartingRecognition] = useState(false);
    const [closing, setClosing] = useState(false);
    const [liveNotice, setLiveNotice] = useState('');
    const prevTimeIns = useRef({});
    const initial = {};
    students.forEach((s) => {
        initial[s.id] = records[s.id]?.status ?? '';
    });

    const { data, setData, post, processing } = useForm({ records: initial });

    // Live updates: while the session is open, re-fetch records so face
    // recognitions from the camera appear without a manual refresh.
    // Pause while closing so the reload does not cancel the Close POST.
    useEffect(() => {
        if (session.status === 'closed' || closing) return;
        const timer = setInterval(() => {
            router.reload({ only: ['records', 'session'] });
        }, LIVE_REFRESH_MS);
        return () => clearInterval(timer);
    }, [session.status, closing]);

    // Announce only newly arrived camera time-ins.
    useEffect(() => {
        const prev = prevTimeIns.current;
        const fresh = [];
        students.forEach((s) => {
            const timeIn = records[s.id]?.time_in;
            if (timeIn && timeIn !== prev[s.id]) {
                if (prev[s.id] !== undefined || Object.keys(prev).length > 0) {
                    fresh.push(s);
                }
            }
        });
        const next = {};
        students.forEach((s) => {
            next[s.id] = records[s.id]?.time_in ?? null;
        });
        const wasEmpty = Object.keys(prev).length === 0;
        prevTimeIns.current = next;
        if (wasEmpty || fresh.length === 0) return;

        const latest = fresh[fresh.length - 1];
        setLiveNotice(`Camera marked: ${latest.last_name}, ${latest.first_name}`);
        const clear = setTimeout(() => setLiveNotice(''), 4000);
        return () => clearTimeout(clear);
    }, [records, students]);    // Fill in statuses arriving from the camera, but never overwrite a
    // teacher's unsaved manual selection.
    useEffect(() => {
        setData((prev) => {
            const merged = { ...prev.records };
            let changed = false;
            students.forEach((s) => {
                const incoming = records[s.id]?.status;
                if (incoming && !merged[s.id]) {
                    merged[s.id] = incoming;
                    changed = true;
                }
            });
            return changed ? { ...prev, records: merged } : prev;
        });
    }, [records]);

    useEffect(() => {
        if (!recognition?.enabled || session.status === 'closed') return;

        const poll = async () => {
            try {
                const data = await fetchRecognitionStatus();
                setRecognitionStatus(data.status);
            } catch {
                // ignore transient network errors
            }
        };

        poll();
        const timer = setInterval(poll, 10000);
        return () => clearInterval(timer);
    }, [recognition?.enabled, session.status]);

    const handleStartRecognition = async () => {
        setStartingRecognition(true);
        try {
            const data = await startRecognition();
            setRecognitionStatus(data.status);
        } finally {
            setStartingRecognition(false);
        }
    };

    const setStatus = (id, status) => {
        setData('records', { ...data.records, [id]: status });
    };

    const markAll = (status) => {
        const all = {};
        students.forEach((s) => (all[s.id] = status));
        setData('records', all);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('teacher.attendance.store', session.id), { preserveScroll: true });
    };

    const reopenSession = () => {
        router.post(route('teacher.attendance.open'), { section_id: session.section.id });
    };

    const closeSession = () => {
        if (closing) return;
        if (!confirm('Close this session? Unmarked students will be recorded absent.')) {
            return;
        }
        setClosing(true);
        router.post(route('teacher.attendance.close', session.id), {}, {
            onError: () => setClosing(false),
            onFinish: () => setClosing(false),
        });
    };

    const recordTimeOutNow = (studentId) => {
        router.post(route('teacher.attendance.time-out', [session.id, studentId]), {}, { preserveScroll: true });
    };

    const displayTime = (value) => {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const sessionDate = (() => {
        const d = new Date(session.session_date);
        return Number.isNaN(d.getTime()) ? session.session_date : d.toLocaleDateString();
    })();

    const closed = session.status === 'closed';

    return (
        <TeacherLayout
            title={`${session.section.grade_level} - ${session.section.name}`}
            actions={
                <Link href={route('teacher.attendance.index')} className="text-sm text-gray-500 hover:underline">
                    ← Back
                </Link>
            }
        >
            <Head title="Mark Attendance" />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-gray-500">
                    Session {sessionDate} ·{' '}
                    <span className={closed ? 'text-gray-500' : 'text-green-600'}>{session.status}</span>
                    {!closed && (
                        <span className="ml-2 inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700">
                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-green-500" />
                            live updates · every 2s
                        </span>
                    )}
                </p>
                {!closed && (
                    <button onClick={() => markAll('present')} className="text-sm text-blue-600 hover:underline">
                        Mark all present
                    </button>
                )}
            </div>

            {liveNotice && (
                <div className="mb-4 rounded-xl bg-green-50 px-4 py-3 text-sm font-medium text-green-800 ring-1 ring-green-200">
                    {liveNotice}
                </div>
            )}

            {closed && (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                    <span>This session is closed. The camera will not run until you open a new session.</span>
                    <button
                        type="button"
                        onClick={reopenSession}
                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Re-open Attendance
                    </button>
                </div>
            )}

            <div className="mb-6 space-y-3">
                <RecognitionStatus
                    enabled={recognition?.enabled}
                    status={recognitionStatus}
                    onStart={handleStartRecognition}
                    starting={startingRecognition}
                />
                <CameraPreview
                    streamUrl={cameraStreamUrl}
                    recognitionEnabled={recognition?.enabled}
                    sessionOpen={!closed}
                />
            </div>

            <form onSubmit={submit}>
                <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Student</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Time In</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Time Out</th>
                                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {students.map((s) => (
                                <tr key={s.id}>
                                    <td className="px-4 py-3 text-sm text-gray-700">{s.last_name}, {s.first_name}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-2">
                                            {STATUSES.map((status) => {
                                                const active = data.records[s.id] === status;
                                                return (
                                                    <button
                                                        type="button"
                                                        key={status}
                                                        disabled={closed}
                                                        onClick={() => setStatus(s.id, status)}
                                                        className={`rounded-lg px-3 py-1 text-xs font-medium capitalize ${
                                                            active ? `${COLORS[status]} text-white` : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                                        } disabled:opacity-50`}
                                                    >
                                                        {status}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">{displayTime(records[s.id]?.time_in)}</td>
                                    <td className="px-4 py-3 text-sm text-gray-700">{displayTime(records[s.id]?.time_out)}</td>
                                    <td className="px-4 py-3">
                                        {!closed && ['present', 'late'].includes(data.records[s.id]) && !records[s.id]?.time_out ? (
                                            <button
                                                type="button"
                                                onClick={() => recordTimeOutNow(s.id)}
                                                className="rounded-lg bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200"
                                            >
                                                Record time-out now
                                            </button>
                                        ) : (
                                            <span className="text-xs text-gray-400">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {!closed && (
                    <div className="mt-4 flex items-center gap-3">
                        <button type="submit" disabled={processing || closing} className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                            Save Attendance
                        </button>
                        <button type="button" onClick={closeSession} disabled={closing} className="rounded-lg bg-gray-100 px-4 py-2 font-medium text-gray-700 hover:bg-gray-200 disabled:opacity-50">
                            {closing ? 'Closing…' : 'Close session'}
                        </button>
                    </div>
                )}
                {closed && (
                    <div className="mt-4">
                        <button
                            type="button"
                            onClick={reopenSession}
                            className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700"
                        >
                            Re-open Attendance
                        </button>
                    </div>
                )}
            </form>
        </TeacherLayout>
    );
}
