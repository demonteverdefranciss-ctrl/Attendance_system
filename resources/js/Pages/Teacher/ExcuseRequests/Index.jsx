import { router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import TeacherReviewActions from '@/Components/TeacherReviewActions';

export default function ExcuseRequestsIndex({ requests = [] }) {
    const review = (item, action, notes) => {
        const routeName = action === 'approve'
            ? 'teacher.excuse-requests.approve'
            : 'teacher.excuse-requests.reject';

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
                Parents may explain any absence. Letters after 3 consecutive absences are flagged as a warning.
                Accepting excuses those attendance records.
            </p>

            <div className="space-y-4">
                {requests.length === 0 && (
                    <div className="rounded-xl bg-white px-4 py-8 text-center text-sm text-gray-400 shadow-sm ring-1 ring-gray-200">
                        No pending explanation letters.
                    </div>
                )}

                {requests.map((item) => (
                    <div key={item.id} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
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
                                {item.is_required ? 'Warning: 3 consecutive absences' : 'Optional explanation'}
                                {' · '}
                                {item.streak_count} day{item.streak_count === 1 ? '' : 's'} · Submitted: {fmt(item.submitted_at)}
                            </p>
                            {item.is_required ? (
                                <p className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-800 ring-1 ring-red-200">
                                    This letter is required because of 3 consecutive absences.
                                </p>
                            ) : null}
                        </div>

                        <div className="mt-3 space-y-3 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            <div className="text-xs font-semibold uppercase tracking-wide text-gray-500">Explanation letter</div>
                            {item.letter_body ? (
                                <p className="whitespace-pre-wrap">{item.letter_body}</p>
                            ) : (
                                <p className="text-xs text-gray-500">No typed letter — a PDF was uploaded.</p>
                            )}
                            {item.has_pdf ? (
                                <a
                                    href={route('teacher.excuse-requests.file', { excuseRequest: item.id, type: 'pdf' })}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-block text-sm font-medium text-blue-700 hover:underline"
                                >
                                    Open PDF{item.letter_pdf_name ? ` (${item.letter_pdf_name})` : ''}
                                </a>
                            ) : null}
                            {item.has_photo ? (
                                <div>
                                    <p className="text-xs font-medium text-gray-600">
                                        Supporting photo{item.photo_name ? `: ${item.photo_name}` : ''}
                                    </p>
                                    <img
                                        src={route('teacher.excuse-requests.file', { excuseRequest: item.id, type: 'photo' })}
                                        alt="Supporting photo"
                                        className="mt-1 max-h-64 rounded-lg object-contain"
                                    />
                                </div>
                            ) : null}
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

                        <TeacherReviewActions
                            onAccept={(notes) => review(item, 'approve', notes)}
                            onReject={(notes) => review(item, 'reject', notes)}
                        />
                    </div>
                ))}
            </div>
        </TeacherLayout>
    );
}
