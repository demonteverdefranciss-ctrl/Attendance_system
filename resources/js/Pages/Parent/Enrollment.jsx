import { Head, useForm } from '@inertiajs/react';
import ParentLayout from '@/Layouts/ParentLayout';
import { formatDateTime } from '@/Pages/Parent/shared';
import TextField from '@/Components/TextField';
import SelectField from '@/Components/SelectField';
import { digitsOnly, personName } from '@/lib/inputFilters';

export default function EnrollmentIndex({ enrollmentRequests = [] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        lrn: '',
        first_name: '',
        last_name: '',
        gender: '',
        grade_level: '',
        relationship: '',
    });

    const submitEnrollmentRequest = (event) => {
        event.preventDefault();
        post(route('parent.enrollment-requests.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <ParentLayout title="Enrollment">
            <Head title="Enrollment" />

            <section aria-labelledby="register-child-heading" className="max-w-4xl rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 sm:p-6">
                <div className="border-b border-gray-100 pb-4">
                    <h2 id="register-child-heading" className="text-base font-semibold text-gray-900">Register a Child</h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Provide the school details below. A teacher will verify the request and link your child to your account. Use the exact LRN if your child is already in the school records.
                    </p>
                </div>
                <form onSubmit={submitEnrollmentRequest} className="mt-5 space-y-5">
                    <fieldset>
                        <legend className="mb-3 text-sm font-semibold text-gray-800">Child details</legend>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <TextField
                                id="enrollment-lrn"
                                label="Learner Reference Number (LRN)"
                                value={data.lrn}
                                onChange={(event) => setData('lrn', digitsOnly(event.target.value))}
                                error={errors.lrn}
                                hint="Numbers only. Enter the LRN shown on school records."
                                inputMode="numeric"
                                required
                            />
                            <TextField
                                id="enrollment-first-name"
                                label="First name"
                                value={data.first_name}
                                onChange={(event) => setData('first_name', personName(event.target.value))}
                                error={errors.first_name}
                                required
                            />
                            <TextField
                                id="enrollment-last-name"
                                label="Last name"
                                value={data.last_name}
                                onChange={(event) => setData('last_name', personName(event.target.value))}
                                error={errors.last_name}
                                required
                            />
                            <SelectField
                                id="enrollment-gender"
                                label="Gender"
                                value={data.gender}
                                onChange={(event) => setData('gender', event.target.value)}
                                error={errors.gender}
                            >
                                <option value="">Select gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </SelectField>
                            <SelectField
                                id="enrollment-grade"
                                label="Grade level"
                                value={data.grade_level}
                                onChange={(event) => setData('grade_level', event.target.value)}
                                error={errors.grade_level}
                            >
                                <option value="">Select grade level</option>
                                {[1, 2, 3, 4, 5, 6].map((grade) => (
                                    <option key={grade} value={`Grade ${grade}`}>Grade {grade}</option>
                                ))}
                            </SelectField>
                            <TextField
                                id="enrollment-relationship"
                                label="Your relationship to the child"
                                value={data.relationship}
                                onChange={(event) => setData('relationship', personName(event.target.value))}
                                error={errors.relationship}
                                hint="For example, mother, father, or guardian."
                            />
                        </div>
                    </fieldset>
                    <button
                        type="submit"
                        disabled={processing}
                        className="min-h-11 rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60"
                    >
                        {processing ? 'Submitting request…' : 'Submit for teacher verification'}
                    </button>
                </form>
            </section>

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
