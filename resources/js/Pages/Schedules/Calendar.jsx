import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TeacherLayout from '@/Layouts/TeacherLayout';
import ParentLayout from '@/Layouts/ParentLayout';

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const pad = (value) => String(value).padStart(2, '0');
const shiftMonth = (year, month, delta) => {
    const date = new Date(year, month - 1 + delta, 1);
    return { year: date.getFullYear(), month: date.getMonth() + 1 };
};

export default function ScheduleCalendar({ schedules, year, month, monthLabel, startWeekday, daysInMonth, noClassDays, today }) {
    const role = usePage().props.auth?.user?.role;
    const Layout = role === 'admin' ? AdminLayout : role === 'teacher' ? TeacherLayout : ParentLayout;
    const previous = shiftMonth(year, month, -1);
    const next = shiftMonth(year, month, 1);
    const cells = Array.from({ length: startWeekday - 1 }, () => null);

    for (let day = 1; day <= daysInMonth; day += 1) {
        const date = `${year}-${pad(month)}-${pad(day)}`;
        const weekday = ((startWeekday - 1 + day - 1) % 7) + 1;
        cells.push({ day, date, weekday, weekend: weekday >= 6, noClass: noClassDays?.[date], today: date === today });
    }
    while (cells.length % 7) cells.push(null);

    return <Layout title="Schedule Calendar">
        <Head title="Schedule Calendar" />
        <p className="mb-4 text-sm text-gray-600">All active attendance schedules are shown below. This calendar is view-only for teachers and parents.</p>
        <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-6">
            <div className="mb-4 flex items-center justify-between gap-3">
                <Link href={route('schedules.calendar', previous)} className="rounded-lg bg-sky-50 px-3 py-1.5 text-sm font-medium text-sky-800 ring-1 ring-inset ring-sky-200 hover:bg-sky-100">← Prev</Link>
                <h2 className="text-lg font-semibold text-gray-900">{monthLabel}</h2>
                <Link href={route('schedules.calendar', next)} className="rounded-lg bg-sky-50 px-3 py-1.5 text-sm font-medium text-sky-800 ring-1 ring-inset ring-sky-200 hover:bg-sky-100">Next →</Link>
            </div>
            <div className="grid grid-cols-7 gap-1 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">{WEEKDAYS.map((label) => <div key={label} className="py-2">{label}</div>)}</div>
            <div className="grid grid-cols-7 gap-1">
                {cells.map((cell, index) => {
                    if (!cell) return <div key={`empty-${index}`} className="min-h-28 rounded-lg" />;
                    const entries = schedules.filter((schedule) => schedule.day_of_week === cell.weekday);
                    let classes = `min-h-28 rounded-lg border p-2 text-left ${cell.weekend ? 'border-gray-100 bg-gray-50 text-gray-400' : 'border-gray-200 bg-white'}`;
                    if (cell.noClass) classes = 'min-h-28 rounded-lg border border-amber-300 bg-amber-50 p-2 text-left text-amber-950';
                    if (cell.today) classes += ' ring-2 ring-blue-500';
                    return <div key={cell.date} className={classes}>
                        <div className="font-semibold">{cell.day}</div>
                        {cell.weekend && <div className="mt-1 text-[11px]">Weekend</div>}
                        {cell.noClass ? <div className="mt-1 text-[11px] leading-tight text-amber-800">{cell.noClass}</div> : <div className="mt-1 space-y-1">{entries.map((schedule) => <div key={schedule.id} className="rounded bg-blue-50 px-1 py-0.5 text-[10px] leading-tight text-blue-900"><span className="font-semibold">{schedule.start_time}</span> {schedule.section ? `${schedule.section.grade_level}-${schedule.section.name}` : 'Section'}</div>)}</div>}
                    </div>;
                })}
            </div>
            <div className="mt-4 flex flex-wrap gap-3 text-xs text-gray-500"><span className="inline-flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-blue-50 ring-1 ring-blue-200" /> Attendance schedule</span><span className="inline-flex items-center gap-1.5"><span className="h-3 w-3 rounded border border-amber-300 bg-amber-50" /> No class</span><span className="inline-flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-gray-50 ring-1 ring-gray-100" /> Weekend</span></div>
        </div>
    </Layout>;
}
