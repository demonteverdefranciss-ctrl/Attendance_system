import { router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import TeacherReviewActions from '@/Components/TeacherReviewActions';
import Pagination, { usePageRows } from '@/Components/Pagination';

export default function BiometricPhotosIndex({ submissions }) {
    const { rows, paginator } = usePageRows(submissions);

    const review = (id, action, notes) => {
        const routeName = action === 'approve'
            ? 'teacher.biometric-photos.approve'
            : 'teacher.biometric-photos.reject';

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
                The system already checked that each photo has one usable face. Confirm this is the
                correct student, then accept. Accepted photos can be imported on the school PC with{' '}
                <code className="rounded bg-gray-100 px-1">python sync_enrollment.py</code>.
            </p>

            <div className="space-y-4">
                {rows.length === 0 && (
                    <div className="rounded-xl bg-white p-8 text-center text-sm text-gray-400 shadow-sm ring-1 ring-gray-200">
                        No pending biometric photo submissions.
                    </div>
                )}

                {rows.map((item) => (
                    <div key={item.id} className="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
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
                            {item.system_validated ? (
                                <p className="mt-1 text-xs font-medium text-blue-700">
                                    System validated
                                    {Array.isArray(item.validation_summary) && item.validation_summary.some((row) => row.descriptor)
                                        ? ' · facial descriptor generated'
                                        : ''}
                                </p>
                            ) : null}
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

                        <TeacherReviewActions
                            onAccept={(notes) => review(item.id, 'approve', notes)}
                            onReject={(notes) => review(item.id, 'reject', notes)}
                        />
                    </div>
                ))}
            </div>
            <Pagination paginator={paginator} />
        </TeacherLayout>
    );
}
