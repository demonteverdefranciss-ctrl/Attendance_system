import { useState } from 'react';
import { router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function EnrollmentRequestsIndex({ requests, sections = [] }) {
    const [sectionByRequest, setSectionByRequest] = useState({});

    const review = (item, action) => {
        const routeName = action === 'approve'
            ? 'teacher.enrollment-requests.approve'
            : 'teacher.enrollment-requests.reject';

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

        const payload = { notes };
        if (action === 'approve' && item.is_new_student) {
            const sectionId = sectionByRequest[item.id];
            if (!sectionId) {
                window.alert('Select a section for this new student before approving.');
                return;
            }
            payload.section_id = sectionId;
        }

        router.post(route(routeName, item.id), payload, { preserveScroll: true });
    };

    const fmt = (value) => {
        if (!value) return '—';
        const d = new Date(value);
        return Number.isNaN(d.getTime()) ? value : d.toLocaleString();
    };

    return (
        <TeacherLayout title="Enrollment Requests">
            <p className="mb-4 text-sm text-gray-500">
                Parents submit their child&apos;s details here. Approve to create or link the student
                record. New students need a section assigned before approval.
            </p>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            {['Student', 'LRN', 'Details', 'Guardian', 'Relationship', 'Requested', 'Section'].map((h) => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    {h}
                                </th>
                            ))}
                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {requests.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-8 text-center text-sm text-gray-400">
                                    No pending enrollment requests.
                                </td>
                            </tr>
                        )}
                        {requests.map((item) => (
                            <tr key={item.id} className="hover:bg-gray-50">
                                <td className="px-4 py-2 text-sm text-gray-700">
                                    {item.student ?? 'Unknown student'}
                                    {item.is_new_student && (
                                        <span className="ml-2 rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">New</span>
                                    )}
                                </td>
                                <td className="px-4 py-2 text-sm text-gray-700">{item.lrn}</td>
                                <td className="px-4 py-2 text-sm text-gray-700">
                                    {item.gender ? <div className="capitalize">{item.gender}</div> : null}
                                    {item.grade_level ? <div className="text-xs text-gray-500">{item.grade_level}</div> : null}
                                </td>
                                <td className="px-4 py-2 text-sm text-gray-700">
                                    {item.guardian}
                                    {item.guardian_phone ? <div className="text-xs text-gray-500">{item.guardian_phone}</div> : null}
                                </td>
                                <td className="px-4 py-2 text-sm text-gray-700 capitalize">{item.relationship || '—'}</td>
                                <td className="px-4 py-2 text-sm text-gray-700">{fmt(item.created_at)}</td>
                                <td className="px-4 py-2 text-sm text-gray-700">
                                    {item.is_new_student ? (
                                        <select
                                            value={sectionByRequest[item.id] || ''}
                                            onChange={(e) => setSectionByRequest((prev) => ({
                                                ...prev,
                                                [item.id]: e.target.value,
                                            }))}
                                            className="rounded-lg border-gray-300 text-xs shadow-sm"
                                        >
                                            <option value="">Select section</option>
                                            {sections.map((s) => (
                                                <option key={s.id} value={s.id}>{s.label}</option>
                                            ))}
                                        </select>
                                    ) : (
                                        item.section
                                    )}
                                </td>
                                <td className="px-4 py-2">
                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            onClick={() => review(item, 'approve')}
                                            className="rounded-lg bg-green-600 px-3 py-1 text-xs font-medium text-white hover:bg-green-700"
                                        >
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => review(item, 'reject')}
                                            className="rounded-lg bg-red-600 px-3 py-1 text-xs font-medium text-white hover:bg-red-700"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </TeacherLayout>
    );
}
