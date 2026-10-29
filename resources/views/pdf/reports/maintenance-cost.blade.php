<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Completed Maintenance Cost History</title>
    <style>
        @page {
            margin: 12mm 10mm;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #222;
        }

        .top {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 5px 0;
        }

        .top td {
            border: none;
            padding: 0;
        }

        .date {
            text-align: right;
            font-size: 9px;
        }

        .org {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: .3px;
            margin: 2px 0 2px 0;
        }

        .title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 8px 0;
        }

        .summary {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 5px 0;
            margin: 0 0 8px 0;
        }

        .summary td {
            border: 1px solid #bbb;
            padding: 6px 7px;
            vertical-align: top;
        }

        .summary-label {
            display: block;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 3px;
        }

        .summary-value {
            display: block;
            font-size: 11px;
            font-weight: bold;
        }

        .filter-note {
            margin: 0 0 6px 0;
            font-size: 8px;
            color: #555;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table thead {
            display: table-header-group;
        }

        .report-table tr {
            page-break-inside: avoid;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #999;
            padding: 4px 5px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .report-table th {
            background: #efefef;
            font-size: 8px;
            font-weight: bold;
            text-align: left;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .muted {
            color: #666;
            font-size: 7.5px;
        }

        .footer {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 8px;
        }

        .footer td {
            border: none;
            padding: 0;
        }

        .footer-right {
            text-align: right;
        }
    </style>
</head>
<body>

<table class="top">
    <tr>
        <td></td>
        <td class="date">
            <strong>Date:</strong> {{ $generatedAt->format('d M Y') }}
        </td>
    </tr>
</table>

<div class="org">GHANA EDUCATION SERVICE - HEADQUARTERS</div>
<div class="title">COMPLETED MAINTENANCE COST HISTORY</div>

<table class="summary">
    <tr>
        <td>
            <span class="summary-label">Total Cost</span>
            <span class="summary-value">GH₵{{ number_format((float) ($summary['total_maintenance_cost'] ?? 0), 2) }}</span>
        </td>
        <td>
            <span class="summary-label">Actual Cost</span>
            <span class="summary-value">GH₵{{ number_format((float) ($summary['actual_maintenance_cost'] ?? 0), 2) }}</span>
        </td>
        <td>
            <span class="summary-label">Maintenance This Year ({{ $summary['this_year'] ?? now()->year }})</span>
            <span class="summary-value">GH₵{{ number_format((float) ($summary['this_year_maintenance_cost'] ?? 0), 2) }}</span>
        </td>
        <td>
            <span class="summary-label">Maintenance Last Year ({{ $summary['last_year'] ?? (now()->year - 1) }})</span>
            <span class="summary-value">GH₵{{ number_format((float) ($summary['last_year_maintenance_cost'] ?? 0), 2) }}</span>
        </td>
        <td>
            <span class="summary-label">Completed Jobs</span>
            <span class="summary-value">{{ number_format((int) ($summary['total_requests'] ?? 0)) }}</span>
        </td>
    </tr>
</table>

@if(
    !empty($filters['search']) ||
    !empty($filters['priority']) ||
    !empty($filters['asset_id']) ||
    !empty($filters['unit_id']) ||
    !empty($filters['provider_id']) ||
    !empty($filters['date_from']) ||
    !empty($filters['date_to'])
)
    <div class="filter-note">
        <strong>Report filters:</strong>
        @if(!empty($filters['search'])) Search="{{ $filters['search'] }}" @endif
        @if(!empty($filters['priority'])) | Priority={{ ucfirst($filters['priority']) }} @endif
        @if(!empty($filters['date_from'])) | From={{ $filters['date_from'] }} @endif
        @if(!empty($filters['date_to'])) | To={{ $filters['date_to'] }} @endif
    </div>
@endif

<table class="report-table">
    <thead>
        <tr>
            <th style="width:3%;">#</th>
            <th style="width:13%;">Asset Code</th>
            <th style="width:12%;">Asset</th>
            <th style="width:15%;">Unit / Location</th>
            <th style="width:14%;">Provider</th>
            <th style="width:8%;">Priority</th>
            <th style="width:10%;">Completion Date</th>
            <th style="width:15%;">Fault / Work</th>
            <th style="width:10%;" class="right">Cost</th>
        </tr>
    </thead>
    <tbody>
        @forelse($requests as $index => $request)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>{{ $request->asset_code ?? '-' }}</td>
                <td>{{ $request->asset_name ?? '-' }}</td>
                <td>
                    {{ $request->unit_name ?? '-' }}
                    @if(!empty($request->locationname))
                        <div class="muted">{{ $request->locationname }}</div>
                    @endif
                </td>
                <td>{{ $request->provider_name ?? '-' }}</td>
                <td>{{ ucfirst((string) ($request->priority ?? '-')) }}</td>
                <td>
                    {{
                        $request->actual_completion_date
                            ? \Carbon\Carbon::parse($request->actual_completion_date)->format('d M Y')
                            : (
                                $request->created_at
                                    ? \Carbon\Carbon::parse($request->created_at)->format('d M Y')
                                    : '-'
                            )
                    }}
                </td>
                <td>{{ $request->fault_description ?? '-' }}</td>
                <td class="right">
                    GH₵{{ number_format((float) ($request->cost ?? 0), 2) }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="center">No completed maintenance cost records found.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="footer">
    <tr>
        <td>Generated on: {{ $generatedAt->format('d M Y H:i') }}</td>
        <td class="footer-right">Generated by {{ $currentuser }}</td>
    </tr>
</table>

</body>
</html>
