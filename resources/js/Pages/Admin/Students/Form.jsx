import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TextField from '@/Components/TextField';
import SelectField from '@/Components/SelectField';

export default function StudentForm({ student, guardianIds = [], sections, guardians }) {
    const editing = !!student;
    const { data, setData, post, put, processing, errors } = useForm({
        first_name: student?.first_name ?? '',
        last_name: student?.last_name ?? '',
        lrn: student?.lrn ?? '',
        gender: student?.gender ?? '',
        birthdate: student?.birthdate ? student.birthdate.substring(0, 10) : '',
        section_id: student?.section_id ?? '',
        consent_biometric: student?.consent_biometric ?? false,
        guardian_ids: guardianIds ?? [],
    });

    const toggleGuardian = (id) => {
        setData(
            'guardian_ids',
            data.guardian_ids.includes(id)
                ? data.guardian_ids.filter((g) => g !== id)
                : [...data.guardian_ids, id],
        );
    };

    const submit = (e) => {
        e.preventDefault();
        editing
            ? put(route('admin.students.update', student.id))
            : post(route('admin.students.store'));
    };

    return (
        <AdminLayout title={editing ? 'Edit Student' : 'Add Student'}>
            <Head title={editing ? 'Edit Student' : 'Add Student'} />

            <form onSubmit={submit} className="max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <TextField label="First Name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} error={errors.first_name} />
                    <TextField label="Last Name" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} error={errors.last_name} />
                    <TextField label="LRN" value={data.lrn} onChange={(e) => setData('lrn', e.target.value)} error={errors.lrn} />
                    <SelectField label="Gender" value={data.gender} onChange={(e) => setData('gender', e.target.value)} error={errors.gender}>
                        <option value="">— Select —</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </SelectField>
                    <TextField label="Birthdate" type="date" value={data.birthdate} onChange={(e) => setData('birthdate', e.target.value)} error={errors.birthdate} />
                    <SelectField label="Section" value={data.section_id} onChange={(e) => setData('section_id', e.target.value)} error={errors.section_id}>
                        <option value="">— None —</option>
                        {sections.map((s) => (
                            <option key={s.id} value={s.id}>{s.grade_level} - {s.name}</option>
                        ))}
                    </SelectField>
                </div>

                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={data.consent_biometric}
                        onChange={(e) => setData('consent_biometric', e.target.checked)}
                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    Parental consent for biometric (face) enrollment
                </label>

                <div>
                    <p className="mb-2 text-sm font-medium text-gray-700">Parents / Guardians</p>
                    {guardians.length === 0 ? (
                        <p className="text-sm text-gray-400">No guardians available. Add one first.</p>
                    ) : (
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            {guardians.map((g) => (
                                <label key={g.id} className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.guardian_ids.includes(g.id)}
                                        onChange={() => toggleGuardian(g.id)}
                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    {g.first_name} {g.last_name}
                                </label>
                            ))}
                        </div>
                    )}
                    <p className="mt-1 text-xs text-gray-400">The first selected guardian is set as primary contact.</p>
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        {editing ? 'Update' : 'Save'}
                    </button>
                    <Link href={route('admin.students.index')} className="text-sm text-gray-500 hover:underline">Cancel</Link>
                </div>
            </form>
        </AdminLayout>
    );
}
