import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TextField from '@/Components/TextField';
import { employeeNo, personName, phoneChars } from '@/lib/inputFilters';

export default function TeacherForm({ teacher }) {
    const editing = !!teacher;
    const { data, setData, post, put, processing, errors } = useForm({
        first_name: teacher?.first_name ?? '',
        last_name: teacher?.last_name ?? '',
        employee_no: teacher?.employee_no ?? '',
        phone: teacher?.phone ?? '',
        username: teacher?.user?.username ?? '',
        email: teacher?.user?.email ?? '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        editing
            ? put(route('admin.teachers.update', teacher.id))
            : post(route('admin.teachers.store'));
    };

    return (
        <AdminLayout title={editing ? 'Edit Teacher' : 'Add Teacher'}>
            <Head title={editing ? 'Edit Teacher' : 'Add Teacher'} />

            <form onSubmit={submit} className="max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <TextField
                        label="First Name"
                        value={data.first_name}
                        onChange={(e) => setData('first_name', personName(e.target.value))}
                        error={errors.first_name}
                        hint="Letters only (no numbers)."
                    />
                    <TextField
                        label="Last Name"
                        value={data.last_name}
                        onChange={(e) => setData('last_name', personName(e.target.value))}
                        error={errors.last_name}
                        hint="Letters only (no numbers)."
                    />
                    <TextField
                        label="Employee No."
                        value={data.employee_no}
                        onChange={(e) => setData('employee_no', employeeNo(e.target.value))}
                        error={errors.employee_no}
                        hint="Letters, numbers, and hyphens only."
                    />
                    <TextField
                        label="Phone"
                        value={data.phone}
                        onChange={(e) => setData('phone', phoneChars(e.target.value))}
                        error={errors.phone}
                        hint="Numbers only (and optional +)."
                        inputMode="tel"
                    />
                </div>

                <hr className="border-gray-100" />
                <p className="text-sm font-medium text-gray-500">Login account</p>

                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <TextField label="Username" value={data.username} onChange={(e) => setData('username', e.target.value)} error={errors.username} />
                    <TextField label="Email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} error={errors.email} />
                    <TextField
                        label={editing ? 'Password (leave blank to keep)' : 'Password'}
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={errors.password}
                        hint="At least 10 characters, with uppercase, lowercase, a number, and a symbol (e.g. Teacher@2026)."
                        autoComplete="new-password"
                    />
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        {editing ? 'Update' : 'Save'}
                    </button>
                    <Link href={route('admin.teachers.index')} className="text-sm text-gray-500 hover:underline">
                        Cancel
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
