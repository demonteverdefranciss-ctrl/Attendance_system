/** Show a compact age for new notices and the record date after 24 hours. */
export function notificationRecordTime(value) {
    if (!value) return 'Recorded date unavailable';

    const recordedAt = new Date(value);
    if (Number.isNaN(recordedAt.getTime())) return `Recorded ${value}`;

    const ageMs = Date.now() - recordedAt.getTime();
    if (ageMs < 24 * 60 * 60 * 1000) {
        const minutes = Math.max(0, Math.floor(ageMs / 60000));
        if (minutes < 1) return 'Recorded just now';
        if (minutes < 60) return `Recorded ${minutes}m ago`;
        return `Recorded ${Math.floor(minutes / 60)}h ago`;
    }

    return `Recorded ${recordedAt.toLocaleString([], {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    })}`;
}
