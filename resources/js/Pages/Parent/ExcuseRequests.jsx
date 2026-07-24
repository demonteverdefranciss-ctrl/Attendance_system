import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import ParentLayout from '@/Layouts/ParentLayout';

export default function ExcuseRequestsIndex({ excuseRequests = [] }) {
    const [letters, setLetters] = useState({});

    const submitExcuseLetter = (e, requestId) => {
        e.preventDefault();
        const letter_body = (letters[requestId] || '').trim();
        if (letter_body.length < 10) {
            window.alert('Please write at least 10 characters explaining the absences/lates.');
            return;
        }
        router.post(
            route('parent.excuse-requests.submit', requestId),
            { letter_body },
            {
                preserveScroll: true,
                onSuccess: () => setLetters((prev) => ({ ...prev, [requestId]: '' })),
            },
        );
    };

    return (
        <ParentLayout title="Explanation Letters">
            <Head title="Explanation Letters" />

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div className="border-b border-gray-100 px-4 py-3">
                    <h2 className="text-base font-semibold text-gray-900">Explanation Letters</h2>
                    <p className="text-xs text-gray-500">
                        After 3 consecutive absences or late marks, submit a letter for the teacher to review.
                    </p>
                </div>
                <div className="divide-y divide-gray-100">
                    {excuseRequests.length === 0 && (
                        <div className="px-4 py-8 text-center text-sm text-gray-400">
                            No explanation letter requests yet.
                        </div>
                    )}
                    {excuseRequests.map((r) => (
                        <div key={r.id} className="px-4 py-4">
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="text-sm font-semibold text-gray-900">{r.student}</h3>
                                <span
                                    className={`rounded-full px-2 py-0.5 text-xs capitalize ${
                                        r.status === 'approved'
                                            ? 'bg-green-100 text-green-700'
                                            : r.status === 'rejected'
                                              ? 'bg-red-100 text-red-700'
                                              : r.status === 'pending'
                                                ? 'bg-blue-100 text-blue-700'
                                                : 'bg-amber-100 text-amber-700'
                                    }`}
                                >
                                    {r.status === 'awaiting_letter' ? 'needs letter' : r.status}
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-gray-500">
                                {r.streak_count} consecutive absent/late
                                {Array.isArray(r.streak_summary) && r.streak_summary.length > 0
                                    ? ` · ${r.streak_summary.map((s) => `${s.date} (${s.status})`).join(', ')}`
                                    : ''}
                            </p>
                            {r.status === 'awaiting_letter' && (
                                <form className="mt-3 space-y-2" onSubmit={(e) => submitExcuseLetter(e, r.id)}>
                                    <textarea
                                        value={letters[r.id] || ''}
                                        onChange={(e) => setLetters((prev) => ({ ...prev, [r.id]: e.target.value }))}
                                        rows={4}
                                        placeholder="Explain why your child was absent or late..."
                                        className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        required
                                    />
                                    <button
                                        type="submit"
                                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                                    >
                                        Submit explanation letter
                                    </button>
                                </form>
                            )}
                            {r.letter_body && r.status !== 'awaiting_letter' && (
                                <p className="mt-2 whitespace-pre-wrap rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700">
                                    {r.letter_body}
                                </p>
                            )}
                            {r.notes ? <p className="mt-1 text-xs text-gray-600">Teacher note: {r.notes}</p> : null}
                        </div>
                    ))}
                </div>
            </div>
        </ParentLayout>
    );
}
