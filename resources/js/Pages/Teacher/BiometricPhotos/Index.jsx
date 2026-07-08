import { router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function BiometricPhotosIndex({ submissions }) {
    const review = (id, action) => {
        const routeName = action === 'approve'
            ? 'teacher.biometric-photos.approve'
            : 'teacher.biometric-photos.reject';

        const notePrompt = action === 'approve'
            ? 'Optional note for the parent (leave blank to skip):'
            : 'Optional rejection reason (leave blank to skip):';
        const notesInput = window.prompt(notePrompt, '');
        if (notesInput === null) return;

        const notes = notesInput.trim();
        if (notes.length > 500) {
            window.alert('Note is too long. Please keep it within 500 characters.');
            return;
        }

        router.post(route(routeName, id), { notes }, { preserveScroll: true });
    };

    const fmt = (value) => {
        if (!value) return '—';
        const d = new Date(value);
        return Number.isNaN(d.getTime()) ? value : d.toLocaleString();
    };

    return (
        <TeacherLayout title="Biometric Photo Reviews">
            <p className="mb-4 text-sm text-gray-500">
                Parents upload face photos with consent. Approve only if the photos clearly show the
                correct student. Approved photos can be imported on the school PC with{' '}
                <code className="rounded bg-gray-100 px-1">python sync_enrollment.py</code>.
            </p>

            <div className="space-y-4">
                {submissions.length === 0 && (
                    <div className="rounded-xl bg-white p-8 text-center text-sm text-gray-400 shadow-sm ring-1 ring-gray-200">
                        No pending biometric photo submissions.
                    </div>
                )}

                {submissions.map((item) => (
                    <div key={item.id} className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">{item.student}</h2>
                                <p className="mt-1 text-sm text-gray-500">
                                    LRN {item.lrn} · {item.section}
                                </p>
                                <p className="text-sm text-gray-500">
                                    Parent: {item.guardian}
                                    {item.guardian_phone ? ` (${item.guardian_phone})` : ''}
                                </p>
                                <p className="text-xs text-gray-400">Submitted {fmt(item.created_at)}</p>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => review(item.id, 'approve')}
                                    className="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700"
                                >
                                    Approve
                                </button>
                                <button
                                    type="button"
                                    onClick={() => review(item.id, 'reject')}
                                    className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                                >
                                    Reject
                                </button>
                            </div>
                        </div>

                        <div className="mt-4 flex flex-wrap gap-3">
                            {item.photos.map((photo) => (
                                <a
                                    key={photo.id}
                                    href={photo.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="block overflow-hidden rounded-lg ring-1 ring-gray-200"
                                >
                                    <img
                                        src={photo.url}
                                        alt={photo.name || 'Submitted photo'}
                                        className="h-32 w-32 object-cover"
                                    />
                                </a>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </TeacherLayout>
    );
}
