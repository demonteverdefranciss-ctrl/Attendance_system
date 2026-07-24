import { router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function ExcuseRequestsIndex({ requests = [] }) {
    const review = (item, action) => {
        const routeName = action === 'approve'
            ? 'teacher.excuse-requests.approve'
            : 'teacher.excuse-requests.reject';

        const notePrompt = action === 'approve'
            ? 'Optional note for the parent (leave blank to skip):'
            : 'Optional rejection reason for the parent (leave blank to skip):';
        const notesInput = window.prompt(notePrompt, '');
        if (notesInput === null) return;

        const notes = notesInput.trim();
        if (notes.length > 500) {
            window.alert('Note is too long. Please keep it within 500 characters.');
            return;
        }

        router.post(route(routeName, item.id), { notes }, { preserveScroll: true });
    };

    const fmt = (value) => {
        if (!value) return '—';
        const d = new Date(value);
        return Number.isNaN(d.getTime()) ? value : d.toLocaleString();
    };

    return (
        <TeacherLayout title="Explanation Letters">
            <p className="mb-4 text-sm text-gray-500">
                Parents submit explanation letters after 3 consecutive absences or late marks.
                Approving excuses those attendance records.
            </p>

            <div className="space-y-4">
                {requests.length === 0 && (
                    <div className="rounded-xl bg-white px-4 py-8 text-center text-sm text-gray-400 shadow-sm ring-1 ring-gray-200">
                        No pending explanation letters.
                    </div>
                )}

                {requests.map((item) => (
                    <div key={item.id} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 className="text-base font-semibold text-gray-900">{item.student}</h3>
                                <p className="text-xs text-gray-500">
                                    LRN: {item.lrn} · {item.section}
                                </p>
                                <p className="mt-1 text-xs text-gray-500">
                                    Parent: {item.guardian || '—'}
                                    {item.guardian_phone ? ` · ${item.guardian_phone}` : ''}
                                </p>
                                <p className="mt-1 text-xs text-gray-500">
                                    Streak: {item.streak_count} consecutive absent/late · Submitted: {fmt(item.submitted_at)}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => review(item, 'approve')}
                                    className="rounded-lg bg-green-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-green-700"
                                >
                                    Accept (excuse)
                                </button>
                                <button
                                    type="button"
                                    onClick={() => review(item, 'reject')}
                                    className="rounded-lg bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-100"
                                >
                                    Reject
                                </button>
                            </div>
                        </div>

                        <div className="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            <div className="text-xs font-semibold uppercase tracking-wide text-gray-500">Explanation letter</div>
                            <p className="mt-1 whitespace-pre-wrap">{item.letter_body}</p>
                        </div>

                        {Array.isArray(item.streak_summary) && item.streak_summary.length > 0 && (
                            <div className="mt-3 flex flex-wrap gap-2">
                                {item.streak_summary.map((row) => (
                                    <span
                                        key={`${row.record_id}-${row.date}`}
                                        className="rounded-full bg-amber-50 px-2 py-0.5 text-xs capitalize text-amber-800 ring-1 ring-amber-200"
                                    >
                                        {row.date}: {row.status}
                                    </span>
                                ))}
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </TeacherLayout>
    );
}
