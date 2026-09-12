import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import FilePickButton from '@/Components/FilePickButton';
import { inspectFacePhoto, prepareFacePhoto } from '@/lib/facePhotoCheck';

export function submissionBadge(status) {
    if (status === 'active' || status === 'approved') return 'bg-green-100 text-green-700';
    if (status === 'rejected') return 'bg-red-100 text-red-700';
    return 'bg-amber-100 text-amber-700';
}

export function enrollmentLabel(submission) {
    const status = submission?.enrollment_status || submission?.status;
    if (status === 'active') return 'Active';
    if (status === 'approved') return 'Approved';
    if (status === 'rejected') return 'Rejected — recapture';
    if (status === 'pending') return 'Pending teacher review';
    return status || '';
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
    const { assetBase } = usePage().props;
    const [files, setFiles] = useState([]);
    const [consent, setConsent] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [checking, setChecking] = useState(false);
    const [photoError, setPhotoError] = useState(null);

    const submission = child.biometric_submission;
    const canUpload = !submission || submission.status === 'rejected';

    const chooseFiles = async (next) => {
        setPhotoError(null);
        const selected = Array.isArray(next) ? next.slice(0, 3) : [];
        if (selected.length === 0) {
            setFiles([]);
            return;
        }

        setChecking(true);
        const accepted = [];
        try {
            for (let i = 0; i < selected.length; i += 1) {
                const prepared = await prepareFacePhoto(selected[i]);
                const result = await inspectFacePhoto(prepared, assetBase);
                if (!result.ok) {
                    const which = selected.length > 1 ? `Photo ${i + 1}: ` : '';
                    setPhotoError(which + result.message);
                    setFiles([]);
                    return;
                }
                accepted.push(prepared);
            }
            setFiles(accepted);
        } finally {
            setChecking(false);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        if (!files.length || !consent || checking) return;

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
            onError: (errors) => {
                const photo =
                    errors.photos ||
                    errors['photos.0'] ||
                    errors['photos.1'] ||
                    errors['photos.2'] ||
                    Object.values(errors)[0];
                setPhotoError(photo || 'The photos could not be saved. Try a clearer JPEG or PNG of the face.');
            },
            onSuccess: () => {
                setFiles([]);
                setConsent(false);
                setPhotoError(null);
            },
            onFinish: () => {
                setUploading(false);
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
                    <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-800 ring-1 ring-inset ring-amber-200">No consent yet</span>
                )}
            </div>

            {submission && (
                <div className="mt-3 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                    <span className={`mr-2 rounded-full px-2 py-0.5 capitalize ${submissionBadge(submission.enrollment_status || submission.status)}`}>
                        {enrollmentLabel(submission)}
                    </span>
                    {submission.system_validated ? (
                        <span className="mr-2 rounded-full bg-blue-50 px-2 py-0.5 text-blue-700">System validated</span>
                    ) : null}
                    Submitted {submission.created_at || '—'}
                    {submission.notes ? <p className="mt-1">Teacher note: {submission.notes}</p> : null}
                </div>
            )}

            {!canUpload ? (
                <p className="mt-3 text-xs text-gray-500">
                    {submission?.status === 'approved'
                        ? (submission?.enrollment_status === 'active'
                            ? 'Photos are active for face enrollment.'
                            : 'Photos approved. The school will import them for face enrollment.')
                        : 'The system already accepted these photos. Waiting for a teacher to confirm this is the correct student.'}
                </p>
            ) : (
                <form onSubmit={submit} className="mt-3 space-y-3">
                    <p className="text-xs text-gray-500">
                        Upload 1–3 photos of your child&apos;s face (JPEG/PNG). Large phone photos are resized
                        automatically. Full-body pictures, objects, and photos with no face are rejected. A teacher
                        confirms identity after the system accepts the face.
                    </p>
                    <FilePickButton
                        kind="photo"
                        accept="image/jpeg,image/png"
                        multiple
                        label="Add face photos"
                        hint="Face photo — not a full-body shot"
                        value={files}
                        error={photoError}
                        onChange={chooseFiles}
                    />
                    {checking ? (
                        <p className="text-xs text-blue-700">Checking that each photo shows a face…</p>
                    ) : null}
                    {photoError ? (
                        <p className="text-xs font-medium text-red-600">{photoError}</p>
                    ) : null}
                    <label className="flex items-start gap-2 text-xs text-gray-700">
                        <input
                            type="checkbox"
                            checked={consent}
                            onChange={(e) => setConsent(e.target.checked)}
                            className="mt-0.5 rounded border-gray-300"
                        />
                        <span>
                            I consent to the collection and use of my child&apos;s biometric data (face
                            photos) for school attendance purposes, in accordance with RA 10173.
                        </span>
                    </label>
                    <button
                        type="submit"
                        disabled={uploading || checking || !files.length || !consent}
                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {uploading ? 'Validating…' : 'Submit photos'}
                    </button>
                </form>
            )}
        </div>
    );
}
