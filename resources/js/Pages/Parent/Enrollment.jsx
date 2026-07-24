import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import ParentLayout from '@/Layouts/ParentLayout';
import { formatDateTime } from '@/Pages/Parent/shared';

export default function EnrollmentIndex({ enrollmentRequests = [] }) {
    const [lrn, setLrn] = useState('');
    const [firstName, setFirstName] = useState('');
    const [lastName, setLastName] = useState('');
    const [gender, setGender] = useState('');
    const [gradeLevel, setGradeLevel] = useState('');
    const [relationship, setRelationship] = useState('');

    const submitEnrollmentRequest = (e) => {
        e.preventDefault();
        router.post(
            route('parent.enrollment-requests.store'),
            {
                lrn,
                first_name: firstName,
                last_name: lastName,
                gender: gender || null,
                grade_level: gradeLevel || null,
                relationship,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLrn('');
                    setFirstName('');
                    setLastName('');
                    setGender('');
                    setGradeLevel('');
                    setRelationship('');
                },
            },
        );
    };

    return (
        <ParentLayout title="Enrollment">
            <Head title="Enrollment" />

            <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h2 className="text-base font-semibold text-gray-900">Register a Child</h2>
                <p className="mt-1 text-xs text-gray-500">
                    Enter your child&apos;s school details. A teacher will verify the information and link
                    them to your account. If your child is already in the school records, use their exact LRN.
                </p>
                <form onSubmit={submitEnrollmentRequest} className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <input
                        type="text"
                        value={lrn}
                        onChange={(e) => setLrn(e.target.value)}
                        placeholder="LRN *"
                        className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        required
                    />
                    <input
                        type="text"
                        value={firstName}
                        onChange={(e) => setFirstName(e.target.value)}
                        placeholder="First name *"
                        className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        required
                    />
                    <input
                        type="text"
                        value={lastName}
                        onChange={(e) => setLastName(e.target.value)}
                        placeholder="Last name *"
                        className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        required
                    />
                    <select
                        value={gender}
                        onChange={(e) => setGender(e.target.value)}
                        className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                        <option value="">Gender (optional)</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                    <input
                        type="text"
                        value={gradeLevel}
                        onChange={(e) => setGradeLevel(e.target.value)}
                        placeholder="Grade level (e.g. Grade 6)"
                        className="rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
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
                        className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 sm:col-span-2 lg:col-span-3 lg:w-fit"
                    >
                        Submit for teacher verification
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
                        <div className="px-4 py-8 text-center text-sm text-gray-400">No enrollment requests yet.</div>
                    )}
                    {enrollmentRequests.map((r) => (
                        <div key={r.id} className="px-4 py-4">
                            <div className="flex items-center justify-between">
                                <h3 className="text-sm font-semibold text-gray-900">
                                    {r.student || `LRN ${r.lrn}`}
                                </h3>
                                <span
                                    className={`rounded-full px-2 py-0.5 text-xs capitalize ${
                                        r.status === 'approved'
                                            ? 'bg-green-100 text-green-700'
                                            : r.status === 'rejected'
                                              ? 'bg-red-100 text-red-700'
                                              : 'bg-amber-100 text-amber-700'
                                    }`}
                                >
                                    {r.status}
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-gray-500">
                                LRN: {r.lrn}
                                {r.grade_level ? ` · ${r.grade_level}` : ''}
                                {' · Relationship: '}
                                {r.relationship || '—'}
                                {' · Requested: '}
                                {formatDateTime(r.created_at)}
                            </p>
                            {r.notes ? <p className="mt-1 text-xs text-gray-600">Teacher note: {r.notes}</p> : null}
                        </div>
                    ))}
                </div>
            </div>
        </ParentLayout>
    );
}
