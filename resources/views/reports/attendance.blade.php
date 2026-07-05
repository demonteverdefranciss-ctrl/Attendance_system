<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .meta { color: #666; margin-bottom: 10px; }
        .summary { margin-bottom: 12px; }
        .summary span { display: inline-block; margin-right: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #eef2f9; }
        .status { text-transform: capitalize; }
    </style>
</head>
<body>
    <h1>Attendance Report</h1>
    <div class="meta">Bigaa Elementary School &middot; {{ $from }} to {{ $to }}</div>

    <div class="summary">
        <span><strong>Rate:</strong> {{ $summary['rate'] }}%</span>
        <span><strong>Present:</strong> {{ $summary['present'] }}</span>
        <span><strong>Late:</strong> {{ $summary['late'] }}</span>
        <span><strong>Absent:</strong> {{ $summary['absent'] }}</span>
        <span><strong>Excused:</strong> {{ $summary['excused'] }}</span>
        <span><strong>Total:</strong> {{ $summary['total'] }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th><th>Section</th><th>Student</th><th>Status</th><th>Time In</th><th>Time Out</th><th>Method</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $r)
                <tr>
                    <td>{{ $r['date'] }}</td>
                    <td>{{ $r['section'] }}</td>
                    <td>{{ $r['student'] }}</td>
                    <td class="status">{{ $r['status'] }}</td>
                    <td>{{ $r['time_in'] ?? '—' }}</td>
                    <td>{{ $r['time_out'] ?? '—' }}</td>
                    <td class="status">{{ $r['method'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:#888;">No records for this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
