import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TextField from '@/Components/TextField';
import SelectField from '@/Components/SelectField';

export default function SectionForm({ section, teachers }) {
    const editing = !!section;
    const { data, setData, post, put, processing, errors } = useForm({
        name: section?.name ?? '',
        grade_level: section?.grade_level ?? 'Grade 6',
        school_year: section?.school_year ?? '',
        adviser_id: section?.adviser_id ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        editing
            ? put(route('admin.sections.update', section.id))
            : post(route('admin.sections.store'));
    };

    return (
        <AdminLayout title={editing ? 'Edit Section' : 'Add Section'}>
            <Head title={editing ? 'Edit Section' : 'Add Section'} />

            <form onSubmit={submit} className="max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <TextField label="Section Name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} placeholder="e.g. Mabini" />
                    <TextField label="Grade Level" value={data.grade_level} onChange={(e) => setData('grade_level', e.target.value)} error={errors.grade_level} />
                    <TextField label="School Year" value={data.school_year} onChange={(e) => setData('school_year', e.target.value)} error={errors.school_year} placeholder="e.g. 2026-2027" />
                    <SelectField label="Adviser" value={data.adviser_id} onChange={(e) => setData('adviser_id', e.target.value)} error={errors.adviser_id}>
                        <option value="">— None —</option>
                        {teachers.map((t) => (
                            <option key={t.id} value={t.id}>{t.first_name} {t.last_name}</option>
                        ))}
                    </SelectField>
                </div>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        {editing ? 'Update' : 'Save'}
                    </button>
                    <Link href={route('admin.sections.index')} className="text-sm text-gray-500 hover:underline">Cancel</Link>
                </div>
            </form>
        </AdminLayout>
    );
}
