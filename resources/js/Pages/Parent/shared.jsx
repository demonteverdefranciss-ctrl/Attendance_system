import { useState } from 'react';
import { router } from '@inertiajs/react';

export function submissionBadge(status) {
    if (status === 'approved') return 'bg-green-100 text-green-700';
    if (status === 'rejected') return 'bg-red-100 text-red-700';
    return 'bg-amber-100 text-amber-700';
}

export function formatDateTime(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;

    return d.toLocaleString([], {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function ChildBiometricUpload({ child }) {
    const [files, setFiles] = useState([]);
    const [consent, setConsent] = useState(false);
    const [uploading, setUploading] = useState(false);

    const submission = child.biometric_submission;
    const canUpload = !submission || submission.status === 'rejected';

    const submit = (e) => {
        e.preventDefault();
        if (!files.length || !consent) return;

        const formData = new FormData();
        formData.append('student_id', child.id);
        formData.append('consent_acknowledged', '1');
        Array.from(files).forEach((file, index) => {
            formData.append(`photos[${index}]`, file);
        });

        setUploading(true);
        router.post(route('parent.biometric-photos.store'), formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
                setFiles([]);
                setConsent(false);
            },
        });
    };

    return (
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 className="text-sm font-semibold text-gray-900">{child.name}</h3>
                    <p className="text-xs text-gray-500">LRN {child.lrn} · {child.section}</p>
                </div>
                {child.consent_biometric ? (
                    <span className="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">Consent on file</span>
                ) : (
                    <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">No consent yet</span>
                )}
            </div>

            {submission && (
                <div className="mt-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                    <span className={`mr-2 rounded-full px-2 py-0.5 capitalize ${submissionBadge(submission.status)}`}>
                        {submission.status}
                    </span>
                    Submitted {submission.created_at || '—'}
                    {submission.notes ? <p className="mt-1">Teacher note: {submission.notes}</p> : null}
                </div>
            )}

            {!canUpload ? (
                <p className="mt-3 text-xs text-gray-500">
                    {submission?.status === 'approved'
                        ? 'Photos approved. The school will import them for face enrollment.'
                        : 'Your submission is pending teacher review.'}
                </p>
            ) : (
                <form onSubmit={submit} className="mt-3 space-y-3">
                    <p className="text-xs text-gray-500">
                        Upload 1–3 clear front-facing photos (JPEG/PNG, max 2 MB each). A teacher must
                        approve them before they are used for face recognition.
                    </p>
                    <input
                        type="file"
                        accept="image/jpeg,image/png"
                        multiple
                        onChange={(e) => setFiles(e.target.files ? Array.from(e.target.files).slice(0, 3) : [])}
                        className="block w-full text-xs text-gray-600"
                        required
                    />
                    <label className="flex items-start gap-2 text-xs text-gray-700">
                        <input
                            type="checkbox"
                            checked={consent}
                            onChange={(e) => setConsent(e.target.checked)}
                            className="mt-0.5 rounded border-gray-300"
                            required
                        />
                        <span>
                            I consent to the collection and use of my child&apos;s biometric data (face
                            photos) for school attendance purposes, in accordance with RA 10173.
                        </span>
                    </label>
                    <button
                        type="submit"
                        disabled={uploading || !files.length || !consent}
                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {uploading ? 'Uploading…' : 'Submit photos for review'}
                    </button>
                </form>
            )}
        </div>
    );
}
