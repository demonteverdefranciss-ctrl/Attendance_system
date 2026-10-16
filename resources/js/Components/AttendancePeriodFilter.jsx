export default function AttendancePeriodFilter({ value, onChange, includeAll = true, from, to, onRangeChange }) {
    const options = [
        ...(includeAll ? [['all', 'All time']] : []),
        ['week', 'Last 7 days'],
        ['last_week', 'Last week'],
        ['this_week', 'This week'],
        ['month', '1 month'],
        ['custom', 'Custom date'],
    ];
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

            {value === 'custom' && (
                <div className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                    <label className="flex flex-1 flex-col gap-1 text-xs font-medium text-gray-600">
                        From
                        <input
                            type="date"
                            value={from || ''}
                            onChange={(event) => onRangeChange?.('from', event.target.value)}
                            className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700"
                        />
                    </label>
                    <label className="flex flex-1 flex-col gap-1 text-xs font-medium text-gray-600">
                        To
                        <input
                            type="date"
                            value={to || ''}
                            onChange={(event) => onRangeChange?.('to', event.target.value)}
                            className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700"
                        />
                    </label>
                </div>
            )}
            <p className="mt-2 text-xs text-gray-500">
                Last 7 days: rolling range · Last week: previous Monday–Sunday · This week: Monday to today.
            </p>
        </div>
    );
}

export function periodDates(period, today) {
    const start = new Date(`${today}T12:00:00Z`);
    if (period === 'last_week') {
        const daysSinceMonday = (start.getUTCDay() + 6) % 7;
        start.setUTCDate(start.getUTCDate() - daysSinceMonday - 7);
        const end = new Date(start);
        end.setUTCDate(end.getUTCDate() + 6);
        return { from: start.toISOString().slice(0, 10), to: end.toISOString().slice(0, 10) };
    }
    if (period === 'this_week') {
        const daysSinceMonday = (start.getUTCDay() + 6) % 7;
        start.setUTCDate(start.getUTCDate() - daysSinceMonday);
    } else {
        start.setUTCDate(start.getUTCDate() - (period === 'week' ? 6 : 29));
    }
    return { from: start.toISOString().slice(0, 10), to: today };
}

export function selectedPeriod(form, today) {
    return ['week', 'last_week', 'this_week', 'month'].find((period) => {
        const range = periodDates(period, today);
        return form.from === range.from && form.to === range.to;
    });
}
