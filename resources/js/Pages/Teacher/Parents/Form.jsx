import { Head, Link, useForm } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import TextField from '@/Components/TextField';
import SelectField from '@/Components/SelectField';
import { personName, phoneChars } from '@/lib/inputFilters';

export default function TeacherParentForm({ students = [], guardian }) {
    const editing = !!guardian;
    const { data, setData, post, put, processing, errors } = useForm({
        first_name: guardian?.first_name ?? '',
        last_name: guardian?.last_name ?? '',
        phone: guardian?.phone ?? '',
        username: guardian?.user?.username ?? '',
        email: guardian?.user?.email ?? '',
        password: '',
        student_id: '',
        relationship: '',
    });
    const submit = (event) => {
        event.preventDefault();
        if (editing) {
            put(route('teacher.parents.update', guardian.id));
        } else {
            post(route('teacher.parents.store'));
        }
    };

    return (
        <TeacherLayout title={editing ? 'Edit Parent / Guardian' : 'Add Parent'}>
            <Head title={editing ? 'Edit Parent / Guardian' : 'Add Parent'} />
            <form
                onSubmit={submit}
                className="max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200"
            >
                <p className="text-sm text-gray-500">{editing ? 'Update the parent account details.' : 'The new parent account will be linked to one of your assigned students.'}</p>
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <TextField label="First Name" value={data.first_name} onChange={(event) => setData('first_name', personName(event.target.value))} error={errors.first_name} />
                    <TextField label="Last Name" value={data.last_name} onChange={(event) => setData('last_name', personName(event.target.value))} error={errors.last_name} />
                    <TextField label="Phone" value={data.phone} onChange={(event) => setData('phone', phoneChars(event.target.value))} error={errors.phone} inputMode="tel" />
                    {!editing && <>
                        <SelectField label="Student" value={data.student_id} onChange={(event) => setData('student_id', event.target.value)} error={errors.student_id}>
                            <option value="">Select an assigned student</option>
                            {students.map((student) => (
                                <option key={student.id} value={student.id}>
                                    {student.first_name} {student.last_name}{student.lrn ? ` · ${student.lrn}` : ''}
                                </option>
                            ))}
                        </SelectField>
                        <SelectField label="Relationship to student" value={data.relationship} onChange={(event) => setData('relationship', event.target.value)} error={errors.relationship}>
                            <option value="">Select relationship</option>
                            <option value="mother">Mother</option>
                            <option value="father">Father</option>
                            <option value="guardian">Guardian</option>
                        </SelectField>
                    </>}
                </div>

                <hr className="border-gray-100" />
                <p className="text-sm font-medium text-gray-500">Parent login account</p>
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <TextField label="Username" value={data.username} onChange={(event) => setData('username', event.target.value)} error={errors.username} />
                    <TextField label="Email" type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} error={errors.email} />
                    <TextField label={editing ? 'Password (leave blank to keep)' : 'Temporary Password'} type="password" value={data.password} onChange={(event) => setData('password', event.target.value)} error={errors.password} autoComplete="new-password" />
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing || (!editing && students.length === 0)} className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        {editing ? 'Save Changes' : 'Create Parent'}
                    </button>
                    <Link href={route('teacher.parents.index')} className="text-sm text-gray-500 hover:underline">Cancel</Link>
                </div>
                {!editing && students.length === 0 && <p className="text-sm text-amber-700">There are no active students in your assigned sections.</p>}
            </form>
        </TeacherLayout>
    );
}