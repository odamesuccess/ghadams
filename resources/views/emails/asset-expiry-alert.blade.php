<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GhanaDAMS Asset Expiry Alert</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#172033;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f1f5f9;padding:24px 10px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:900px;background:#ffffff;border:1px solid #dbe3ee;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:22px 26px;background:#0f172a;color:#ffffff;">
                        <div style="font-size:11px;letter-spacing:1.7px;font-weight:700;color:#93c5fd;text-transform:uppercase;">
                            GhanaDAMS
                        </div>
                        <div style="font-size:23px;font-weight:800;margin-top:5px;">
                            Asset Expiry Alert
                        </div>
                        <div style="font-size:12px;color:#cbd5e1;margin-top:5px;">
                            Ghana Education Service — Digitalised Asset Management System
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 26px 10px;">
                        @if($assets->count() === 1)
                            @php($asset = $assets->first())

                            <p style="margin:0 0 18px;font-size:14px;line-height:1.6;color:#475569;">
                                An asset in GhanaDAMS requires attention because its recorded expiry date is approaching.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;border:1px solid #dbe3ee;">
                                <tr>
                                    <td style="width:34%;padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;">Asset Code</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700;">{{ $asset->asset_code }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;">Asset Name</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:13px;">{{ $asset->assetName?->assetname ?? 'Unnamed Asset' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;">Unit</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:13px;">{{ $asset->unit?->name ?? 'Not assigned' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;">Location</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:13px;">{{ $asset->unit?->locationname ?? 'Not assigned' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;">Expiry Date</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700;color:#b45309;">
                                        {{ $asset->warranty_end_date?->format('d M Y') }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:12px;font-weight:700;">Remaining Period</td>
                                    <td style="padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:13px;">{{ $remainingPeriod }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 12px;background:#f8fafc;font-size:12px;font-weight:700;">Status</td>
                                    <td style="padding:10px 12px;font-size:13px;font-weight:700;color:{{ $alertType === 'expiry_day' ? '#b91c1c' : '#b45309' }};">
                                        {{ $statusLabel }}
                                    </td>
                                </tr>
                            </table>

                            <div style="padding-top:20px;text-align:center;">
                                <a
                                    href="{{ route('assets.scan', $asset->scan_token ?: $asset->uuid) }}"
                                    style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-size:13px;font-weight:700;padding:11px 18px;border-radius:7px;"
                                >
                                    View Asset
                                </a>
                            </div>
                        @else
                            <p style="margin:0 0 10px;font-size:14px;line-height:1.6;color:#475569;">
                                The following <strong>{{ $assets->count() }} assets</strong> are approaching their recorded expiry date and require attention.
                            </p>

                            <div style="margin:0 0 16px;padding:10px 12px;background:{{ $alertType === 'expiry_day' ? '#fef2f2' : '#fffbeb' }};border:1px solid {{ $alertType === 'expiry_day' ? '#fecaca' : '#fde68a' }};border-radius:7px;font-size:12px;">
                                <strong>Status:</strong> {{ $statusLabel }}
                                &nbsp;&nbsp;|&nbsp;&nbsp;
                                <strong>Remaining Period:</strong> {{ $remainingPeriod }}
                                &nbsp;&nbsp;|&nbsp;&nbsp;
                                <strong>Assets Requiring Attention:</strong> {{ $assets->count() }}
                            </div>

                            <div style="overflow-x:auto;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="min-width:760px;border-collapse:collapse;border:1px solid #dbe3ee;">
                                    <thead>
                                        <tr style="background:#0f172a;color:#ffffff;">
                                            <th align="left" style="padding:9px 8px;font-size:11px;">#</th>
                                            <th align="left" style="padding:9px 8px;font-size:11px;">Asset Code</th>
                                            <th align="left" style="padding:9px 8px;font-size:11px;">Asset Name</th>
                                            <th align="left" style="padding:9px 8px;font-size:11px;">Unit</th>
                                            <th align="left" style="padding:9px 8px;font-size:11px;">Location</th>
                                            <th align="left" style="padding:9px 8px;font-size:11px;">Expiry Date</th>
                                            <th align="center" style="padding:9px 8px;font-size:11px;">View</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($assets as $index => $asset)
                                            <tr>
                                                <td style="padding:9px 8px;border-bottom:1px solid #e2e8f0;font-size:11px;">{{ $index + 1 }}</td>
                                                <td style="padding:9px 8px;border-bottom:1px solid #e2e8f0;font-size:11px;font-weight:700;">{{ $asset->asset_code }}</td>
                                                <td style="padding:9px 8px;border-bottom:1px solid #e2e8f0;font-size:11px;">{{ $asset->assetName?->assetname ?? 'Unnamed Asset' }}</td>
                                                <td style="padding:9px 8px;border-bottom:1px solid #e2e8f0;font-size:11px;">{{ $asset->unit?->name ?? 'Not assigned' }}</td>
                                                <td style="padding:9px 8px;border-bottom:1px solid #e2e8f0;font-size:11px;">{{ $asset->unit?->locationname ?? 'Not assigned' }}</td>
                                                <td style="padding:9px 8px;border-bottom:1px solid #e2e8f0;font-size:11px;font-weight:700;color:#b45309;">{{ $asset->warranty_end_date?->format('d M Y') }}</td>
                                                <td align="center" style="padding:9px 8px;border-bottom:1px solid #e2e8f0;font-size:11px;">
                                                    <a
                                                        href="{{ route('assets.scan', $asset->scan_token ?: $asset->uuid) }}"
                                                        style="color:#2563eb;text-decoration:none;font-weight:700;"
                                                    >
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="padding:14px 26px 24px;">
                        <div style="padding:13px 14px;background:#f8fafc;border-left:4px solid #2563eb;font-size:11px;line-height:1.55;color:#64748b;">
                            This is an automated GhanaDAMS asset-expiry notification. The expiry date used by this alert is the asset's recorded <strong>warranty end date</strong>.
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:14px 26px;background:#0f172a;color:#94a3b8;font-size:10px;text-align:center;">
                        Ghana Education Service — GhanaDAMS
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
