import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout, { StatCard } from '@/Layouts/AuthenticatedLayout';
import { useState } from 'react';

function submissionBadge(status) {
    if (status === 'approved') return 'bg-green-100 text-green-700';
    if (status === 'rejected') return 'bg-red-100 text-red-700';
    return 'bg-amber-100 text-amber-700';
}

function ChildBiometricUpload({ child }) {
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

export default function ParentDashboard({
    stats,
    children = [],
    notifications = [],
    unreadCount = 0,
    notifyPref = 'push',
    enrollmentRequests = [],
}) {
    const [preference, setPreference] = useState(notifyPref);
    const [lrn, setLrn] = useState('');
    const [relationship, setRelationship] = useState('');

    const markRead = (id) => {
        router.post(route('parent.notifications.read', id), {}, { preserveScroll: true });
    };

    const savePreference = () => {
        router.post(
            route('parent.notifications.preferences'),
            { notify_pref: preference },
            { preserveScroll: true }
        );
    };

    const submitEnrollmentRequest = (e) => {
        e.preventDefault();
        router.post(
            route('parent.enrollment-requests.store'),
            { lrn, relationship },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLrn('');
                    setRelationship('');
                },
            }
        );
    };

    const formatDateTime = (value) => {
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
    };

    return (
        <AuthenticatedLayout title="Parent Dashboard">
            <Head title="Parent Dashboard" />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard label="My Children" value={stats.children} />
                <StatCard label="Unread Notifications" value={unreadCount} />
            </div>

            <div className="mt-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h2 className="text-base font-semibold text-gray-900">Biometric Face Photos</h2>
                <p className="mt-1 text-xs text-gray-500">
                    Upload your child&apos;s photos for teacher-approved face enrollment (RA 10173 consent required).
                </p>
                {children.length === 0 ? (
                    <p className="mt-4 text-sm text-gray-400">
                        Link a child first using the enrollment form below.
                    </p>
                ) : (
                    <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        {children.map((child) => (
                            <ChildBiometricUpload key={child.id} child={child} />
                        ))}
                    </div>
                )}
            </div>

            <div className="mt-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h2 className="text-base font-semibold text-gray-900">Enroll Child</h2>
                <p className="mt-1 text-xs text-gray-500">
                    Submit the child LRN for teacher verification before linking to your account.
                </p>
                <form onSubmit={submitEnrollmentRequest} className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <input
                        type="text"
                        value={lrn}
                        onChange={(e) => setLrn(e.target.value)}
                        placeholder="Student LRN"
                        className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        required
                    />
                    <input
                        type="text"
                        value={relationship}
                        onChange={(e) => setRelationship(e.target.value)}
                        placeholder="Relationship (e.g. mother)"
                        className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                    <button
                        type="submit"
                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Submit for verification
                    </button>
                </form>
            </div>

            <div className="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div className="border-b border-gray-100 px-4 py-3">
                    <h2 className="text-base font-semibold text-gray-900">Enrollment Requests</h2>
                    <p className="text-xs text-gray-500">Track approval status of your child-link requests</p>
                </div>
                <div className="divide-y divide-gray-100">
                    {enrollmentRequests.length === 0 && (
                        <div className="px-4 py-8 text-center text-sm text-gray-400">
                            No enrollment requests yet.
                        </div>
                    )}
                    {enrollmentRequests.map((r) => (
                        <div key={r.id} className="px-4 py-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-semibold text-gray-900">
                                    {r.student || `LRN ${r.lrn}`}
                                </h3>
                                <span className={`rounded-full px-2 py-0.5 text-xs capitalize ${
                                    r.status === 'approved'
                                        ? 'bg-green-100 text-green-700'
                                        : r.status === 'rejected'
                                            ? 'bg-red-100 text-red-700'
                                            : 'bg-amber-100 text-amber-700'
                                }`}>
                                    {r.status}
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-gray-500">
                                LRN: {r.lrn} · Relationship: {r.relationship || '—'} · Requested: {formatDateTime(r.created_at)}
                            </p>
                            {r.notes ? (
                                <p className="mt-1 text-xs text-gray-600">Teacher note: {r.notes}</p>
                            ) : null}
                        </div>
                    ))}
                </div>
            </div>

            <div className="mt-6 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h2 className="text-base font-semibold text-gray-900">Notification Preference</h2>
                <p className="mt-1 text-xs text-gray-500">Choose whether to receive parent push notifications.</p>
                <div className="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <select
                        value={preference}
                        onChange={(e) => setPreference(e.target.value)}
                        className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="push">Push notifications</option>
                        <option value="none">Disable notifications</option>
                    </select>
                    <button
                        type="button"
                        onClick={savePreference}
                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                    >
                        Save preference
                    </button>
                </div>
            </div>

            <div className="mt-8 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <div className="border-b border-gray-100 px-4 py-3">
                    <h2 className="text-base font-semibold text-gray-900">Notifications</h2>
                    <p className="text-xs text-gray-500">Latest attendance updates for your children</p>
                </div>
                <div className="divide-y divide-gray-100">
                    {notifications.length === 0 && (
                        <div className="px-4 py-8 text-center text-sm text-gray-400">
                            No notifications yet.
                        </div>
                    )}
                    {notifications.map((n) => (
                        <div key={n.id} className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="text-sm font-semibold text-gray-900">{n.title || 'Attendance Update'}</h3>
                                    {n.read_at ? (
                                        <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Read</span>
                                    ) : (
                                        <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">Unread</span>
                                    )}
                                </div>
                                <p className="mt-1 text-sm text-gray-700">{n.body || 'A new attendance event was recorded.'}</p>
                                <p className="mt-1 text-xs text-gray-500">
                                    Sent: {formatDateTime(n.sent_at)} · Type: {n.type}
                                </p>
                            </div>
                            {!n.read_at && (
                                <button
                                    type="button"
                                    onClick={() => markRead(n.id)}
                                    className="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
                                >
                                    Mark as read
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
