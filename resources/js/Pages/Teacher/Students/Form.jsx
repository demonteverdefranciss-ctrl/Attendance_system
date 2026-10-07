import { Head, Link, useForm } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import TextField from '@/Components/TextField';
import SelectField from '@/Components/SelectField';
import { digitsOnly, personName } from '@/lib/inputFilters';

export default function TeacherStudentForm({ sections, student }) {
    const editing = !!student;
    const { data, setData, post, put, processing, errors } = useForm({
        first_name: student?.first_name ?? '',
        last_name: student?.last_name ?? '',
        lrn: student?.lrn ?? '',
        gender: student?.gender ?? '',
        birthdate: student?.birthdate?.slice(0, 10) ?? '',
        section_id: student?.section_id ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        editing ? put(route('teacher.students.update', student.id)) : post(route('teacher.students.store'));
    };

    return (
        <TeacherLayout title={editing ? 'Edit Student' : 'Add Student'}>
            <Head title={editing ? 'Edit Student' : 'Add Student'} />
            <form onSubmit={submit} className="max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <p className="text-sm text-gray-500">Students can only be managed within your assigned sections. Biometric consent must be collected separately.</p>
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <TextField label="First Name" value={data.first_name} onChange={(e) => setData('first_name', personName(e.target.value))} error={errors.first_name} />
                    <TextField label="Last Name" value={data.last_name} onChange={(e) => setData('last_name', personName(e.target.value))} error={errors.last_name} />
                    <TextField label="LRN" value={data.lrn} onChange={(e) => setData('lrn', digitsOnly(e.target.value))} error={errors.lrn} inputMode="numeric" />
                    <SelectField label="Gender" value={data.gender} onChange={(e) => setData('gender', e.target.value)} error={errors.gender}>
                        <option value="">— Select —</option><option value="male">Male</option><option value="female">Female</option>
                    </SelectField>
                    <TextField label="Birthdate" type="date" value={data.birthdate} onChange={(e) => setData('birthdate', e.target.value)} error={errors.birthdate} />
                    <SelectField label="Section" value={data.section_id} onChange={(e) => setData('section_id', e.target.value)} error={errors.section_id}>
                        <option value="">— Select —</option>
                        {sections.map((section) => <option key={section.id} value={section.id}>{section.grade_level} - {section.name}</option>)}
                    </SelectField>
                </div>
                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">{editing ? 'Save Changes' : 'Save'}</button>
                    <Link href={route('teacher.students.index')} className="text-sm text-gray-500 hover:underline">Cancel</Link>
                </div>
            </form>
        </TeacherLayout>
    );
}
