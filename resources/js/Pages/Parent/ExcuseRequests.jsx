import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import FilePickButton from '@/Components/FilePickButton';
import ParentLayout from '@/Layouts/ParentLayout';

function fileUrl(requestId, type) {
    return route('parent.excuse-requests.file', { excuseRequest: requestId, type });
}

export default function ExcuseRequestsIndex({ excuseRequests = [], eligibleAbsences = [] }) {
    const [modes, setModes] = useState({});
    const [letters, setLetters] = useState({});
    const [pdfs, setPdfs] = useState({});
    const [photos, setPhotos] = useState({});
    const [submittingId, setSubmittingId] = useState(null);
    const [selectedAbsence, setSelectedAbsence] = useState(eligibleAbsences[0]?.id ?? '');

    const modeFor = (id) => modes[id] || 'text';
    const requiredOpen = excuseRequests.filter((r) => r.is_required && r.status === 'awaiting_letter');

    const startLetter = (e) => {
        e.preventDefault();
        if (!selectedAbsence) {
            window.alert('Choose an absence to explain.');
            return;
        }
        router.post(
            route('parent.excuse-requests.store'),
            { attendance_record_id: selectedAbsence },
            { preserveScroll: true },
        );
    };

    const submitExcuseLetter = (e, requestId) => {
        e.preventDefault();
        const mode = modeFor(requestId);
        const letter_body = (letters[requestId] || '').trim();
        const pdf = pdfs[requestId] || null;
        const photo = photos[requestId] || null;

        if (mode === 'text' && letter_body.length < 10) {
            window.alert('Please write at least 10 characters explaining the absences/lates.');
            return;
        }
        if (mode === 'pdf' && !pdf) {
            window.alert('Please choose a PDF file for the explanation letter.');
            return;
        }

        const formData = new FormData();
        if (mode === 'text') {
            formData.append('letter_body', letter_body);
        } else {
            formData.append('letter_pdf', pdf);
        }
        if (photo) {
            formData.append('photo', photo);
        }

        setSubmittingId(requestId);
        router.post(route('parent.excuse-requests.submit', requestId), formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setLetters((prev) => ({ ...prev, [requestId]: '' }));
                setPdfs((prev) => ({ ...prev, [requestId]: null }));
                setPhotos((prev) => ({ ...prev, [requestId]: null }));
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                if (first) window.alert(typeof first === 'string' ? first : 'Please check the letter and files.');
            },
            onFinish: () => setSubmittingId(null),
        });
    };

    return (
        <ParentLayout title="Explanation Letters">
            <Head title="Explanation Letters" />

            <div className="space-y-4">
                {requiredOpen.length > 0 && (
                    <div className="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200">
                        <p className="font-semibold">Warning: 3 consecutive absences</p>
                        <p className="mt-1 text-xs">
                            {requiredOpen.map((r) => r.student).join(', ')} must submit an explanation letter.
                            This is separate from optional letters for a single absence.
                        </p>
                    </div>
                )}

                {eligibleAbsences.length > 0 && (
                    <form
                        onSubmit={startLetter}
                        className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200"
                    >
                        <h2 className="text-sm font-semibold text-gray-900">Explain an absence</h2>
                        <p className="mt-1 text-xs text-gray-500">
                            You can send a letter for any absence or late mark. After 3 consecutive absences, a required warning appears below.
                        </p>
                        <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                            <select
                                value={selectedAbsence}
                                onChange={(e) => setSelectedAbsence(e.target.value)}
                                className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                {eligibleAbsences.map((row) => (
                                    <option key={row.id} value={row.id}>
                                        {row.student} · {row.date} · {row.status}
                                    </option>
                                ))}
                            </select>
                            <button
                                type="submit"
                                className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                            >
                                Start letter
                            </button>
                        </div>
                    </form>
                )}

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div className="border-b border-gray-100 px-4 py-3">
                    <h2 className="text-base font-semibold text-gray-900">Explanation Letters</h2>
                    <p className="text-xs text-gray-500">
                        Submit a typed letter or PDF, and optionally attach a photo. A teacher can accept it to mark those days excused.
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
                            {r.is_required ? (
                                <div className="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-800 ring-1 ring-red-200">
                                    Warning: 3 consecutive absences — explanation required
                                </div>
                            ) : (
                                <p className="mt-1 text-xs text-gray-500">Optional explanation for this absence/late</p>
                            )}
                            <p className="mt-1 text-xs text-gray-500">
                                {r.streak_count} day{r.streak_count === 1 ? '' : 's'}
                                {Array.isArray(r.streak_summary) && r.streak_summary.length > 0
                                    ? ` · ${r.streak_summary.map((s) => `${s.date} (${s.status})`).join(', ')}`
                                    : ''}
                            </p>
                            {r.status === 'awaiting_letter' && (
                                <form className="mt-3 space-y-3" onSubmit={(e) => submitExcuseLetter(e, r.id)}>
                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setModes((prev) => ({ ...prev, [r.id]: 'text' }));
                                                setPdfs((prev) => ({ ...prev, [r.id]: null }));
                                            }}
                                            className={`flex flex-col items-center gap-2 rounded-xl px-3 py-4 text-center ring-1 transition ${
                                                modeFor(r.id) === 'text'
                                                    ? 'bg-blue-50 ring-2 ring-blue-600'
                                                    : 'bg-white ring-gray-200 hover:bg-gray-50'
                                            }`}
                                        >
                                            <svg viewBox="0 0 48 48" className="h-10 w-10" fill="none" aria-hidden="true">
                                                <rect x="8" y="8" width="32" height="32" rx="4" fill="#EFF6FF" stroke="#2563EB" strokeWidth="2" />
                                                <path d="M16 18h16M16 24h16M16 30h10" stroke="#2563EB" strokeWidth="2" strokeLinecap="round" />
                                            </svg>
                                            <span className="text-sm font-semibold text-gray-900">Type a letter</span>
                                        </button>
                                        <FilePickButton
                                            kind="pdf"
                                            accept="application/pdf,.pdf"
                                            label="Upload PDF"
                                            hint="PDF letter, max 5 MB"
                                            value={pdfs[r.id] || null}
                                            onChange={(file) => {
                                                setPdfs((prev) => ({ ...prev, [r.id]: file }));
                                                if (file) {
                                                    setModes((prev) => ({ ...prev, [r.id]: 'pdf' }));
                                                }
                                            }}
                                        />
                                        <FilePickButton
                                            kind="photo"
                                            accept="image/jpeg,image/png,.jpg,.jpeg,.png"
                                            label="Add photo"
                                            hint="Optional JPEG/PNG"
                                            value={photos[r.id] || null}
                                            onChange={(file) => setPhotos((prev) => ({ ...prev, [r.id]: file }))}
                                        />
                                    </div>

                                    {modeFor(r.id) === 'text' ? (
                                        <textarea
                                            value={letters[r.id] || ''}
                                            onChange={(e) => setLetters((prev) => ({ ...prev, [r.id]: e.target.value }))}
                                            rows={4}
                                            placeholder="Explain why your child was absent or late..."
                                            className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            required
                                        />
                                    ) : (
                                        <p className="text-xs text-gray-500">
                                            PDF selected. You can still add an optional photo, then submit.
                                        </p>
                                    )}

                                    <button
                                        type="submit"
                                        disabled={submittingId === r.id}
                                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                                    >
                                        {submittingId === r.id ? 'Submitting…' : 'Submit explanation letter'}
                                    </button>
                                </form>
                            )}
                            {r.status !== 'awaiting_letter' && (
                                <div className="mt-2 space-y-2">
                                    {r.letter_body ? (
                                        <p className="whitespace-pre-wrap rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700">
                                            {r.letter_body}
                                        </p>
                                    ) : null}
                                    {r.has_pdf ? (
                                        <a
                                            href={fileUrl(r.id, 'pdf')}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-block text-xs font-medium text-blue-700 hover:underline"
                                        >
                                            View PDF{r.letter_pdf_name ? ` (${r.letter_pdf_name})` : ''}
                                        </a>
                                    ) : null}
                                    {r.has_photo ? (
                                        <div>
                                            <p className="text-xs font-medium text-gray-600">
                                                Photo{r.photo_name ? `: ${r.photo_name}` : ''}
                                            </p>
                                            <img
                                                src={fileUrl(r.id, 'photo')}
                                                alt="Supporting photo"
                                                className="mt-1 max-h-48 rounded-lg object-contain"
                                            />
                                        </div>
                                    ) : null}
                                </div>
                            )}
                            {r.notes ? <p className="mt-1 text-xs text-gray-600">Teacher note: {r.notes}</p> : null}
                        </div>
                    ))}
                </div>
            </div>
            </div>
        </ParentLayout>
    );
}
