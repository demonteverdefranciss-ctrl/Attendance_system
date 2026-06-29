import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TextField from '@/Components/TextField';
import SelectField from '@/Components/SelectField';

const DAYS = [
    { v: 1, l: 'Monday' }, { v: 2, l: 'Tuesday' }, { v: 3, l: 'Wednesday' },
    { v: 4, l: 'Thursday' }, { v: 5, l: 'Friday' }, { v: 6, l: 'Saturday' }, { v: 7, l: 'Sunday' },
];

export default function ScheduleForm({ schedule, sections }) {
    const editing = !!schedule;
    const { data, setData, post, put, processing, errors } = useForm({
        section_id: schedule?.section_id ?? '',
        day_of_week: schedule?.day_of_week ?? 1,
        start_time: schedule?.start_time ? schedule.start_time.substring(0, 5) : '07:30',
        end_time: schedule?.end_time ? schedule.end_time.substring(0, 5) : '08:00',
        late_after: schedule?.late_after ? schedule.late_after.substring(0, 5) : '07:45',
        type: schedule?.type ?? 'am',
        is_active: schedule?.is_active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        editing
            ? put(route('admin.schedules.update', schedule.id))
            : post(route('admin.schedules.store'));
    };

    return (
        <AdminLayout title={editing ? 'Edit Schedule' : 'Add Schedule'}>
            <Head title={editing ? 'Edit Schedule' : 'Add Schedule'} />

            <form onSubmit={submit} className="max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <SelectField label="Section" value={data.section_id} onChange={(e) => setData('section_id', e.target.value)} error={errors.section_id}>
                        <option value="">— Select —</option>
                        {sections.map((s) => (
                            <option key={s.id} value={s.id}>{s.grade_level} - {s.name}</option>
                        ))}
                    </SelectField>
                    <SelectField label="Day of Week" value={data.day_of_week} onChange={(e) => setData('day_of_week', Number(e.target.value))} error={errors.day_of_week}>
                        {DAYS.map((d) => (
                            <option key={d.v} value={d.v}>{d.l}</option>
                        ))}
                    </SelectField>
                    <TextField label="Start Time" type="time" value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} error={errors.start_time} />
                    <TextField label="End Time" type="time" value={data.end_time} onChange={(e) => setData('end_time', e.target.value)} error={errors.end_time} />
                    <TextField label="Late After" type="time" value={data.late_after} onChange={(e) => setData('late_after', e.target.value)} error={errors.late_after} />
                    <SelectField label="Type" value={data.type} onChange={(e) => setData('type', e.target.value)} error={errors.type}>
                        <option value="am">AM</option>
                        <option value="pm">PM</option>
                        <option value="custom">Custom</option>
                    </SelectField>
                </div>

                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    Active (attendance auto-activates during this window)
                </label>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        {editing ? 'Update' : 'Save'}
                    </button>
                    <Link href={route('admin.schedules.index')} className="text-sm text-gray-500 hover:underline">Cancel</Link>
                </div>
            </form>
        </AdminLayout>
    );
}
