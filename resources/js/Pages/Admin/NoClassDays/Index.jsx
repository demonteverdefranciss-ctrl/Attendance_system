import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function pad(n) {
    return String(n).padStart(2, '0');
}

function shiftMonth(year, month, delta) {
    const d = new Date(year, month - 1 + delta, 1);
    return { year: d.getFullYear(), month: d.getMonth() + 1 };
}

function formatLong(dateStr) {
    const d = new Date(`${dateStr}T00:00:00`);
    return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
}

export default function NoClassDaysIndex({
    year,
    month,
    monthLabel,
    startWeekday,
    daysInMonth,
    today,
    marked = {},
    upcoming = [],
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        date: today,
        name: '',
    });

    const cells = useMemo(() => {
        const list = [];
        for (let i = 0; i < startWeekday - 1; i += 1) {
            list.push(null);
        }
        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = `${year}-${pad(month)}-${pad(day)}`;
            const isoWeekday = ((startWeekday - 1 + day - 1) % 7) + 1;
            list.push({
                day,
                date,
                weekend: isoWeekday >= 6,
                today: date === today,
                mark: marked[date] || null,
            });
        }
        return list;
    }, [year, month, startWeekday, daysInMonth, today, marked]);

    const monthHref = (y, m) => route('admin.no-class-days.index', { year: y, month: m });
    const prev = shiftMonth(year, month, -1);
    const next = shiftMonth(year, month, 1);

    const removeDay = (id) => {
        if (!window.confirm('Remove this no-class day? Sessions can auto-open again on that date.')) {
            return;
        }
        router.delete(route('admin.no-class-days.destroy', id), { preserveScroll: true });
    };

    const onDayClick = (cell) => {
        if (!cell || cell.weekend) {
            return;
        }
        if (cell.mark) {
            removeDay(cell.mark.id);
            return;
        }
        router.post(
            route('admin.no-class-days.store'),
            { date: cell.date, name: '' },
            { preserveScroll: true },
        );
    };

    const submitForm = (e) => {
        e.preventDefault();
        post(route('admin.no-class-days.store'), {
            preserveScroll: true,
            onSuccess: () => reset('name'),
        });
    };

    return (
        <AdminLayout title="No-class days">
            <Head title="No-class days" />

            <p className="mb-6 max-w-3xl text-sm text-gray-600">
                Sessions never auto-open on Saturday or Sunday. Click a weekday to mark a holiday or no-class date so
                the camera session will not start by itself. Teachers can still open attendance by hand.
            </p>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200 sm:p-6">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <Link
                            href={monthHref(prev.year, prev.month)}
                            className="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                        >
                            ← Prev
                        </Link>
                        <h2 className="text-lg font-semibold text-gray-900">{monthLabel}</h2>
                        <Link
                            href={monthHref(next.year, next.month)}
                            className="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100"
                        >
                            Next →
                        </Link>
                    </div>

                    <div className="grid grid-cols-7 gap-1 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {WEEKDAYS.map((label) => (
                            <div key={label} className="py-2">
                                {label}
                            </div>
                        ))}
                    </div>

                    <div className="grid grid-cols-7 gap-1">
                        {cells.map((cell, index) => {
                            if (!cell) {
                                return <div key={`empty-${index}`} className="min-h-[4.5rem] rounded-lg" />;
                            }

                            let classes = 'min-h-[4.5rem] rounded-lg border p-2 text-left text-sm transition ';
                            if (cell.weekend) {
                                classes += 'cursor-default border-gray-100 bg-gray-50 text-gray-400';
                            } else if (cell.mark) {
                                classes += 'cursor-pointer border-amber-300 bg-amber-50 text-amber-950 hover:bg-amber-100';
                            } else {
                                classes += 'cursor-pointer border-gray-200 bg-white hover:border-blue-300 hover:bg-blue-50';
                            }
                            if (cell.today) {
                                classes += ' ring-2 ring-blue-500';
                            }

                            return (
                                <button
                                    key={cell.date}
                                    type="button"
                                    onClick={() => onDayClick(cell)}
                                    className={classes}
                                    title={
                                        cell.weekend
                                            ? 'Weekends already skip auto-open'
                                            : cell.mark
                                              ? 'Click to remove no-class day'
                                              : 'Click to mark no class'
                                    }
                                >
                                    <div className="font-semibold">{cell.day}</div>
                                    {cell.weekend && <div className="mt-1 text-[11px] leading-tight">Weekend</div>}
                                    {cell.mark && (
                                        <div className="mt-1 line-clamp-2 text-[11px] leading-tight text-amber-800">
                                            {cell.mark.name || 'No class'}
                                        </div>
                                    )}
                                </button>
                            );
                        })}
                    </div>

                    <div className="mt-4 flex flex-wrap gap-3 text-xs text-gray-500">
                        <span className="inline-flex items-center gap-1.5">
                            <span className="h-3 w-3 rounded border border-amber-300 bg-amber-50" /> No class
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <span className="h-3 w-3 rounded border border-gray-100 bg-gray-50" /> Weekend (always off)
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <span className="h-3 w-3 rounded ring-2 ring-blue-500" /> Today
                        </span>
                    </div>
                </div>

                <div className="space-y-6">
                    <form onSubmit={submitForm} className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <h3 className="font-semibold text-gray-900">Add a date</h3>
                        <label className="mt-3 block text-sm text-gray-700">
                            Date
                            <input
                                type="date"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                                className="mt-1 w-full rounded-lg border-gray-300 text-sm"
                            />
                            {errors.date && <p className="mt-1 text-xs text-red-600">{errors.date}</p>}
                        </label>
                        <label className="mt-3 block text-sm text-gray-700">
                            Name (optional)
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Holiday or event"
                                className="mt-1 w-full rounded-lg border-gray-300 text-sm"
                                maxLength={150}
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                        </label>
                        <button
                            type="submit"
                            disabled={processing}
                            className="mt-3 w-full rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            Mark no class
                        </button>
                    </form>

                    <div className="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                        <h3 className="font-semibold text-gray-900">Upcoming</h3>
                        {upcoming.length === 0 ? (
                            <p className="mt-2 text-sm text-gray-500">No upcoming no-class days.</p>
                        ) : (
                            <ul className="mt-3 space-y-2">
                                {upcoming.map((day) => (
                                    <li key={day.id} className="flex items-start justify-between gap-2 text-sm">
                                        <div>
                                            <div className="font-medium text-gray-800">{formatLong(day.date)}</div>
                                            <div className="text-gray-500">{day.name || 'No class'}</div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeDay(day.id)}
                                            className="text-xs text-red-600 hover:underline"
                                        >
                                            Remove
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
