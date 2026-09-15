import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TextField from '@/Components/TextField';
import SelectField from '@/Components/SelectField';
import { personName, phoneChars } from '@/lib/inputFilters';

export default function GuardianForm({ guardian }) {
    const editing = !!guardian;
    const { data, setData, post, put, processing, errors } = useForm({
        first_name: guardian?.first_name ?? '',
        last_name: guardian?.last_name ?? '',
        phone: guardian?.phone ?? '',
        notify_pref: guardian?.notify_pref ?? 'push',
        username: guardian?.user?.username ?? '',
        email: guardian?.user?.email ?? '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        editing
            ? put(route('admin.guardians.update', guardian.id))
            : post(route('admin.guardians.store'));
    };

    return (
        <AdminLayout title={editing ? 'Edit Guardian' : 'Add Guardian'}>
            <Head title={editing ? 'Edit Guardian' : 'Add Guardian'} />

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
                        label="Phone"
                        value={data.phone}
                        onChange={(e) => setData('phone', phoneChars(e.target.value))}
                        error={errors.phone}
                        hint="Numbers only (and optional +)."
                        inputMode="tel"
                    />
                    <SelectField label="Notification Preference" value={data.notify_pref} onChange={(e) => setData('notify_pref', e.target.value)} error={errors.notify_pref}>
                        <option value="push">Push (app)</option>
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                        <option value="none">None</option>
                    </SelectField>
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
                        hint="At least 10 characters, with uppercase, lowercase, a number, and a symbol (e.g. Parent@2026)."
                        autoComplete="new-password"
                    />
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        {editing ? 'Update' : 'Save'}
                    </button>
                    <Link href={route('admin.guardians.index')} className="text-sm text-gray-500 hover:underline">
                        Cancel
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
