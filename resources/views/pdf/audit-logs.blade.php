<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 5px; vertical-align: top; }
        th { background: #0f172a; color: white; }
        .muted { color: #666; font-size: 10px; }
    </style>
</head>
<body>
    <h2>Audit Logs Report</h2>
    <p class="muted">Generated: {{ $generatedAt }}</p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>User</th>
                <th>Module</th>
                <th>Action</th>
                <th>Description</th>
                <th>IP / Location</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
                <tr>
                    <td>{{ optional($log->created_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ $log->user->name ?? 'System' }}</td>
                    <td>{{ $log->module }}</td>
                    <td>{{ $log->action }}</td>
                    <td>{{ $log->description ?? $log->title }}</td>
                    <td>
                        {{ $log->ip_address ?? '—' }}<br>
                        <span class="muted">{{ $log->ip_city ?? '—' }}{{ $log->ip_country ? ', '.$log->ip_country : '' }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
