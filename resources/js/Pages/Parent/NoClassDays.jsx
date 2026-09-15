import { Head } from '@inertiajs/react';
import ParentLayout from '@/Layouts/ParentLayout';

function DayList({ title, empty, days = [] }) {
    return (
        <div className="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200">
            <div className="border-b border-gray-100 px-4 py-3">
                <h2 className="text-base font-semibold text-gray-900">{title}</h2>
            </div>
            <div className="divide-y divide-gray-100">
                {days.length === 0 && (
                    <div className="px-4 py-8 text-center text-sm text-gray-400">{empty}</div>
                )}
                {days.map((day) => (
                    <div key={day.id} className="flex flex-wrap items-start justify-between gap-2 px-4 py-3">
                        <div>
                            <p className="text-sm font-semibold text-gray-900">{day.label}</p>
                            <p className="text-xs text-gray-500">{day.weekday}</p>
                            <p className="mt-1 text-sm text-gray-700">{day.name}</p>
                        </div>
                        <span
                            className={`rounded-full px-2 py-0.5 text-xs font-medium ring-1 ${
                                day.source === 'google'
                                    ? 'bg-sky-50 text-sky-800 ring-sky-200'
                                    : 'bg-amber-50 text-amber-900 ring-amber-200'
                            }`}
                        >
                            {day.source === 'google' ? 'Holiday' : 'School notice'}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function ParentNoClassDays({ days }) {
    return (
        <ParentLayout title="No-class days">
            <Head title="No-class days" />

            <p className="mb-4 text-sm text-gray-600">
                Holidays and school-declared no-class days. Attendance does not auto-open on these dates.
            </p>

            <div className="space-y-6">
                <DayList
                    title="Upcoming"
                    empty="No upcoming no-class days."
                    days={days?.upcoming ?? []}
                />
                <DayList
                    title="Recent"
                    empty="No recent no-class days."
                    days={days?.recent ?? []}
                />
            </div>
        </ParentLayout>
    );
}
