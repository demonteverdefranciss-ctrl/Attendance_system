export default function AttendancePeriodFilter({ value, onChange, includeAll = true }) {
    const options = [...(includeAll ? [['all', 'All time']] : []), ['week', '1 week'], ['month', '1 month']];
    return (
        <div className="w-full">
            <div className="flex flex-wrap gap-2" role="group" aria-label="Attendance period">
                {options.map(([key, label]) => (
                    <button key={key} type="button" aria-pressed={value === key} onClick={() => onChange(key)}
                        className={`rounded-lg px-4 py-2 text-sm font-semibold ring-1 transition ${value === key ? 'bg-blue-600 text-white ring-blue-600' : 'bg-sky-50 text-sky-800 ring-sky-200 hover:bg-sky-100'}`}>
                        {label}
                    </button>
                ))}
            </div>
            <p className="mt-2 text-xs text-gray-500">1 week: last 7 days · 1 month: last 30 days, including today.</p>
        </div>
    );
}

export function periodDates(period, today) {
    const start = new Date(`${today}T12:00:00Z`);
    start.setUTCDate(start.getUTCDate() - (period === 'week' ? 6 : 29));
    return { from: start.toISOString().slice(0, 10), to: today };
}

export function selectedPeriod(form, today) {
    return ['week', 'month'].find((period) => {
        const range = periodDates(period, today);
        return form.from === range.from && form.to === range.to;
    });
}
