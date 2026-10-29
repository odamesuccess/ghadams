@php
    use Illuminate\Support\Carbon;

    $assetName = $asset->assetName?->assetname ?? 'Unnamed Asset';
    $unitName = $asset->unit?->name ?? 'Not assigned';
    $locationName = $asset->unit?->locationname ?? 'Not assigned';

    $statusRaw = strtolower(trim((string) ($asset->status ?? 'unknown')));
    $statusKey = str_replace([' ', '-'], '_', $statusRaw);
    $statusLabel = ucwords(str_replace('_', ' ', $statusRaw));

    $statusTone = match ($statusKey) {
        'active' => 'success',
        'under_maintenance', 'maintenance', 'in_progress' => 'warning',
        'obsolete' => 'danger',
        'disposed' => 'muted',
        default => 'info',
    };

    $purchaseDate = filled($asset->purchase_date)
        ? Carbon::parse($asset->purchase_date)
        : null;

    $warrantyEndDate = filled($asset->warranty_end_date)
        ? Carbon::parse($asset->warranty_end_date)
        : null;

    $warrantyExpired = $warrantyEndDate
        ? $warrantyEndDate->copy()->endOfDay()->isPast()
        : null;

    $warrantyDuration = filled($asset->warranty_duration)
        ? (
            is_numeric($asset->warranty_duration)
                ? number_format((float) $asset->warranty_duration, 0) . ' months'
                : $asset->warranty_duration
        )
        : 'Not recorded';

    $dateAcquired = $asset->created_at
        ? $asset->created_at->format('d M Y')
        : 'Not recorded';

    $scannedAt = now();
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $assetName }} | GhanaDAMS Asset Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">

    <style>
        :root {
            --page: #f1f5f9;
            --surface: #ffffff;
            --surface-2: #f8fafc;
            --surface-3: #eef2f7;
            --text: #0f172a;
            --muted: #64748b;
            --subtle: #94a3b8;
            --border: #dbe3ee;
            --accent: #2563eb;
            --accent-soft: #eff6ff;
            --success: #047857;
            --success-soft: #ecfdf5;
            --success-border: #a7f3d0;
            --warning: #b45309;
            --warning-soft: #fffbeb;
            --warning-border: #fde68a;
            --danger: #b91c1c;
            --danger-soft: #fef2f2;
            --danger-border: #fecaca;
            --info: #0369a1;
            --info-soft: #f0f9ff;
            --info-border: #bae6fd;
            --shadow: 0 24px 60px rgba(15, 23, 42, .10);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --page: #070b14;
                --surface: #0b1120;
                --surface-2: #0f172a;
                --surface-3: #111c31;
                --text: #f8fafc;
                --muted: #94a3b8;
                --subtle: #64748b;
                --border: #22304a;
                --accent: #60a5fa;
                --accent-soft: rgba(37, 99, 235, .12);
                --success: #6ee7b7;
                --success-soft: rgba(16, 185, 129, .10);
                --success-border: rgba(52, 211, 153, .26);
                --warning: #fbbf24;
                --warning-soft: rgba(245, 158, 11, .10);
                --warning-border: rgba(251, 191, 36, .25);
                --danger: #fda4af;
                --danger-soft: rgba(244, 63, 94, .10);
                --danger-border: rgba(251, 113, 133, .25);
                --info: #7dd3fc;
                --info-soft: rgba(14, 165, 233, .10);
                --info-border: rgba(56, 189, 248, .24);
                --shadow: 0 26px 80px rgba(0, 0, 0, .28);
            }
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: var(--page);
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 14px;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            background:
                radial-gradient(circle at 8% 0%, rgba(37, 99, 235, .10), transparent 27rem),
                radial-gradient(circle at 100% 100%, rgba(16, 185, 129, .06), transparent 24rem),
                var(--page);
            color: var(--text);
        }

        .page {
            width: 100%;
            max-width: none;
            margin: 0;
        }

        .shell {
            width: 100%;
            overflow: hidden;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            border-radius: 22px;
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background:
                linear-gradient(135deg, var(--surface-2), var(--surface));
        }

        .brand {
            min-width: 0;
        }

        .eyebrow {
            margin: 0 0 3px;
            color: var(--accent);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .15em;
            text-transform: uppercase;
        }

        .brand h1 {
            margin: 0;
            font-size: clamp(18px, 2vw, 24px);
            line-height: 1.15;
            letter-spacing: -.025em;
        }

        .brand p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .verified {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            flex: 0 0 auto;
            padding: 9px 12px;
            border: 1px solid var(--success-border);
            border-radius: 999px;
            background: var(--success-soft);
            color: var(--success);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .verified-icon {
            display: grid;
            place-items: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--success);
            color: #ffffff;
            font-size: 12px;
            line-height: 1;
        }

        .content {
            display: grid;
            grid-template-columns: minmax(340px, 0.72fr) minmax(0, 1.28fr);
            gap: 16px;
            width: 100%;
            padding: 16px;
            flex: 1;
            align-items: stretch;
        }

        .media-card,
        .details-card {
            min-width: 0;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--surface-2);
        }

        .media-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 16px;
        }

        .image-stage {
            display: grid;
            place-items: center;
            min-height: clamp(300px, 39vh, 520px);
            flex: 1;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 14px;
            background:
                radial-gradient(circle at center, rgba(96, 165, 250, .045), transparent 62%),
                linear-gradient(145deg, var(--surface), var(--surface-3));
        }

        .image-stage img {
            display: block;
            width: 100%;
            max-width: 100%;
            height: 100%;
            max-height: 100%;
            object-fit: contain;
            object-position: center;
            padding: 12px;
        }

        .no-image {
            display: grid;
            place-items: center;
            gap: 9px;
            padding: 40px;
            color: var(--muted);
            text-align: center;
        }

        .no-image-icon {
            display: grid;
            place-items: center;
            width: 54px;
            height: 54px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
            font-size: 24px;
        }

        .asset-summary {
            width: 100%;
            padding: 14px 2px 0;
        }

        .asset-summary-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .asset-summary h2 {
            margin: 0;
            font-size: 21px;
            line-height: 1.25;
            letter-spacing: -.02em;
        }

        .asset-code {
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            word-break: break-all;
        }

        .status-pill {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 9px;
            border: 1px solid;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .tone-success {
            color: var(--success);
            background: var(--success-soft);
            border-color: var(--success-border);
        }

        .tone-warning {
            color: var(--warning);
            background: var(--warning-soft);
            border-color: var(--warning-border);
        }

        .tone-danger {
            color: var(--danger);
            background: var(--danger-soft);
            border-color: var(--danger-border);
        }

        .tone-info {
            color: var(--info);
            background: var(--info-soft);
            border-color: var(--info-border);
        }

        .tone-muted {
            color: var(--muted);
            background: var(--surface-3);
            border-color: var(--border);
        }

        .details-card {
            display: flex;
            flex-direction: column;
            justify-content: stretch;
            height: 100%;
            padding: 16px;
        }

        .details-card > .section {
            display: flex;
            flex: 1 1 0;
            flex-direction: column;
            justify-content: center;
        }

        .section + .section {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .section-heading {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 10px;
        }

        .section-icon {
            display: grid;
            place-items: center;
            width: 28px;
            height: 28px;
            border-radius: 9px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 13px;
            font-weight: 900;
        }

        .section-heading h3 {
            margin: 0;
            font-size: 12px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 9px;
        }

        .info-item {
            min-width: 0;
            padding: 11px 12px;
            border: 1px solid var(--border);
            border-radius: 11px;
            background: var(--surface);
        }

        .info-item.full {
            grid-column: 1 / -1;
        }

        .info-label {
            display: block;
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        .info-value {
            display: block;
            min-width: 0;
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
            word-break: break-word;
        }

        .warranty {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .warranty-note {
            margin-top: 4px;
            font-size: 10px;
            font-weight: 700;
        }

        .warranty-expired {
            color: var(--danger);
        }

        .warranty-valid {
            color: var(--success);
        }

        .verification-strip {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 18px;
            padding: 12px 18px;
            border-top: 1px solid var(--border);
            background: var(--surface-2);
        }

        .verification-message {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .checkmark {
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--success-soft);
            color: var(--success);
            font-weight: 900;
        }

        .verification-message strong {
            display: block;
            font-size: 12px;
        }

        .verification-message span {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 10px;
        }

        .scan-meta {
            color: var(--muted);
            font-size: 10px;
            text-align: right;
            white-space: nowrap;
        }

        @media (min-width: 821px) {
            .shell {
                min-height: calc(100vh - 28px);
            }

            .content {
                min-height: 0;
            }

            .media-card,
            .details-card {
                min-height: 0;
            }

            .image-stage {
                min-height: 0;
                height: 100%;
            }

            .image-stage img {
                width: 100%;
                height: 100%;
                max-height: 100%;
                object-fit: contain;
                object-position: center;
                padding: 14px;
            }
        }

        @media (min-width: 1500px) {
            .content {
                grid-template-columns: minmax(420px, 0.78fr) minmax(0, 1.22fr);
            }

            .details-card {
                padding: 18px;
            }

            .info-item {
                padding: 12px 14px;
            }

            .info-value {
                font-size: 14px;
            }
        }

        @media (max-width: 1100px) {
            body {
                padding: 10px;
            }

            .content {
                grid-template-columns: minmax(300px, 0.85fr) minmax(0, 1.15fr);
                gap: 12px;
                padding: 12px;
            }

            .image-stage {
                min-height: 280px;
            }

            .image-stage img {
                max-height: 320px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .info-item.full {
                grid-column: auto;
            }
        }

        @media (max-width: 820px) {
            .shell {
                min-height: 0;
            }

            .content {
                flex: 0 0 auto;
            }

            .media-card,
            .details-card {
                height: auto;
            }

            .details-card {
                justify-content: flex-start;
            }

            .details-card > .section {
                flex: 0 0 auto;
                justify-content: flex-start;
            }

            body {
                padding: 8px;
            }

            .shell {
                border-radius: 16px;
            }

            .topbar {
                align-items: flex-start;
                padding: 14px;
            }

            .content {
                grid-template-columns: 1fr;
                gap: 10px;
                padding: 10px;
            }

            .media-card,
            .details-card {
                border-radius: 14px;
            }

            .media-card {
                padding: 12px;
            }

            .details-card {
                padding: 12px;
            }

            .image-stage {
                min-height: 240px;
                max-height: none;
            }

            .image-stage img {
                width: 100%;
                height: auto;
                max-height: none;
                padding: 0;
                object-fit: contain;
            }

            .asset-summary {
                padding: 12px 2px 0;
            }

            .verification-strip {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 12px 14px;
            }

            .scan-meta {
                padding-left: 38px;
                text-align: left;
                white-space: normal;
            }
        }

        @media (max-width: 560px) {
            body {
                padding: 0;
                background: var(--page);
            }

            .page {
                width: 100%;
            }

            .shell {
                border-left: 0;
                border-right: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .topbar {
                flex-direction: column;
                gap: 10px;
                padding: 13px 12px;
            }

            .brand h1 {
                font-size: 19px;
            }

            .brand p {
                max-width: 100%;
                font-size: 11px;
                line-height: 1.4;
            }

            .verified {
                align-self: flex-start;
                padding: 7px 9px;
                font-size: 9px;
            }

            .verified-icon {
                width: 18px;
                height: 18px;
            }

            .content {
                padding: 8px;
            }

            .media-card,
            .details-card {
                padding: 10px;
            }

            .image-stage {
                min-height: 210px;
                border-radius: 11px;
            }

            .image-stage img {
                width: 100%;
                height: auto;
                max-height: none;
                padding: 0;
                object-fit: contain;
            }

            .asset-summary-top {
                flex-direction: column;
                gap: 8px;
            }

            .asset-summary h2 {
                font-size: 17px;
            }

            .asset-code {
                font-size: 11px;
            }

            .status-pill {
                padding: 6px 8px;
                font-size: 9px;
            }

            .section + .section {
                margin-top: 12px;
                padding-top: 12px;
            }

            .section-heading {
                margin-bottom: 8px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 7px;
            }

            .info-item {
                padding: 10px;
            }

            .info-label {
                font-size: 9px;
            }

            .info-value {
                font-size: 12px;
            }

            .verification-strip {
                padding: 11px 12px;
            }

            .verification-message strong {
                font-size: 11px;
                line-height: 1.35;
            }

            .verification-message span,
            .scan-meta {
                font-size: 9px;
            }
        }

        @media (max-width: 380px) {
            .content {
                padding: 6px;
            }

            .media-card,
            .details-card {
                padding: 8px;
            }

            .image-stage {
                min-height: 190px;
            }

            .image-stage img {
                width: 100%;
                height: auto;
                max-height: none;
                object-fit: contain;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition: none !important;
            }
        }
    
        /* GHANADAMS_AUTO_FIT_START */
        /*
         * Desktop/tablet-landscape viewport fitting.
         * The header, media/details area and verification footer are all
         * constrained to the visible browser viewport. Mobile remains a
         * normal vertically scrolling layout.
         */
        @media (min-width: 821px) {
            html,
            body {
                width: 100%;
                height: 100%;
                min-height: 0;
            }

            body {
                height: 100vh;
                height: 100dvh;
                min-height: 0;
                padding: 8px;
                overflow: hidden;
            }

            .page {
                width: 100%;
                height: 100%;
                min-height: 0;
                overflow: hidden;
            }

            .shell {
                width: 100%;
                height: 100%;
                min-height: 0 !important;
                max-height: 100%;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                border-radius: clamp(12px, 1.1vw, 20px);
            }

            .topbar {
                flex: 0 0 auto;
                min-height: 0;
                padding: clamp(8px, 1.15vh, 14px) clamp(12px, 1.25vw, 20px);
                gap: 12px;
            }

            .eyebrow {
                margin-bottom: 2px;
                font-size: clamp(8px, .62vw, 11px);
            }

            .brand h1 {
                font-size: clamp(16px, 1.35vw, 22px);
                line-height: 1.08;
            }

            .brand p {
                margin-top: 3px;
                font-size: clamp(9px, .68vw, 12px);
                line-height: 1.25;
            }

            .verified {
                gap: 6px;
                padding: clamp(6px, .75vh, 9px) clamp(8px, .75vw, 12px);
                font-size: clamp(8px, .58vw, 10px);
            }

            .verified-icon {
                width: clamp(16px, 1.25vw, 20px);
                height: clamp(16px, 1.25vw, 20px);
                font-size: clamp(9px, .7vw, 12px);
            }

            .content {
                flex: 1 1 auto;
                width: 100%;
                min-height: 0 !important;
                height: auto;
                overflow: hidden;
                padding: clamp(8px, 1.05vh, 14px);
                gap: clamp(8px, .85vw, 14px);
                grid-template-columns: minmax(330px, .72fr) minmax(0, 1.28fr);
                grid-template-rows: minmax(0, 1fr);
                align-items: stretch;
            }

            .media-card,
            .details-card {
                height: 100% !important;
                min-height: 0 !important;
                max-height: 100%;
                overflow: hidden;
                border-radius: clamp(12px, 1vw, 18px);
            }

            .media-card {
                padding: clamp(8px, 1vh, 14px);
            }

            .image-stage {
                flex: 1 1 auto;
                min-height: 0 !important;
                height: auto !important;
                max-height: none !important;
                overflow: hidden;
                border-radius: clamp(10px, .8vw, 14px);
            }

            .image-stage img {
                width: 100% !important;
                height: 100% !important;
                min-height: 0;
                max-width: 100%;
                max-height: 100% !important;
                padding: clamp(4px, .7vw, 10px);
                object-fit: contain !important;
                object-position: center !important;
            }

            .asset-summary {
                flex: 0 0 auto;
                padding: clamp(6px, .85vh, 11px) 2px 0;
            }

            .asset-summary h2 {
                font-size: clamp(15px, 1.15vw, 20px);
                line-height: 1.12;
            }

            .asset-code {
                margin-top: 3px;
                font-size: clamp(8px, .62vw, 11px);
                line-height: 1.2;
            }

            .status-pill {
                gap: 5px;
                padding: clamp(4px, .55vh, 7px) clamp(6px, .6vw, 9px);
                font-size: clamp(7px, .52vw, 9px);
            }

            .status-dot {
                width: 6px;
                height: 6px;
            }

            .details-card {
                display: grid !important;
                grid-template-rows: repeat(3, minmax(0, 1fr));
                padding: clamp(8px, 1vh, 14px) !important;
            }

            .details-card > .section {
                display: flex !important;
                min-height: 0 !important;
                flex: none !important;
                flex-direction: column;
                justify-content: center !important;
                overflow: hidden;
            }

            .details-card > .section + .section {
                margin-top: 0 !important;
                padding-top: clamp(5px, .75vh, 10px) !important;
                border-top: 1px solid var(--border);
            }

            .details-card > .section:not(:last-child) {
                padding-bottom: clamp(5px, .75vh, 10px);
            }

            .section-heading {
                flex: 0 0 auto;
                gap: clamp(5px, .5vw, 8px);
                margin-bottom: clamp(4px, .6vh, 8px);
            }

            .section-icon {
                width: clamp(20px, 1.55vw, 26px);
                height: clamp(20px, 1.55vw, 26px);
                border-radius: 7px;
                font-size: clamp(9px, .65vw, 12px);
            }

            .section-heading h3 {
                font-size: clamp(9px, .66vw, 11px);
                line-height: 1.1;
            }

            .info-grid {
                min-height: 0;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: clamp(4px, .55vw, 8px);
            }

            .info-item {
                min-height: 0;
                padding: clamp(6px, .8vh, 10px) clamp(7px, .7vw, 11px);
                border-radius: 9px;
            }

            .info-label {
                margin-bottom: 2px;
                font-size: clamp(7px, .5vw, 9px);
                line-height: 1.1;
            }

            .info-value {
                font-size: clamp(9px, .72vw, 12px);
                line-height: 1.18;
            }

            .warranty-note {
                margin-top: 2px;
                font-size: clamp(7px, .52vw, 9px);
                line-height: 1.1;
            }

            .verification-strip {
                flex: 0 0 auto;
                min-height: 0;
                gap: 10px;
                padding: clamp(6px, .8vh, 10px) clamp(10px, 1vw, 16px);
            }

            .verification-message {
                gap: 7px;
            }

            .checkmark {
                width: clamp(20px, 1.55vw, 26px);
                height: clamp(20px, 1.55vw, 26px);
                font-size: clamp(10px, .7vw, 12px);
            }

            .verification-message strong {
                font-size: clamp(8px, .62vw, 11px);
                line-height: 1.18;
            }

            .verification-message span,
            .scan-meta {
                font-size: clamp(7px, .5vw, 9px);
                line-height: 1.15;
            }
        }

        /* Extra compression for shorter laptop/browser viewports. */
        @media (min-width: 821px) and (max-height: 760px) {
            body {
                padding: 5px;
            }

            .topbar {
                padding-top: 6px;
                padding-bottom: 6px;
            }

            .content {
                padding: 6px;
                gap: 7px;
            }

            .media-card,
            .details-card {
                padding: 7px !important;
            }

            .asset-summary {
                padding-top: 5px;
            }

            .section-heading {
                margin-bottom: 3px;
            }

            .info-item {
                padding-top: 5px;
                padding-bottom: 5px;
            }

            .verification-strip {
                padding-top: 5px;
                padding-bottom: 5px;
            }
        }

        @media (min-width: 821px) and (max-height: 640px) {
            .brand p,
            .verification-message span {
                display: none;
            }

            .details-card > .section + .section {
                padding-top: 3px !important;
            }

            .details-card > .section:not(:last-child) {
                padding-bottom: 3px;
            }

            .section-icon {
                width: 18px;
                height: 18px;
            }

            .info-item {
                padding: 4px 6px;
            }

            .asset-code {
                margin-top: 2px;
            }
        }

        /* Mobile/tablet portrait: natural page flow and scrolling. */
        @media (max-width: 820px) {
            html,
            body {
                height: auto;
                min-height: 100%;
            }

            body {
                min-height: 100vh;
                min-height: 100dvh;
                overflow-x: hidden;
                overflow-y: auto;
            }

            .page,
            .shell {
                height: auto;
                min-height: 0;
                max-height: none;
                overflow: visible;
            }

            .content {
                height: auto;
                min-height: 0;
                overflow: visible;
            }

            .media-card,
            .details-card {
                height: auto !important;
                max-height: none;
                overflow: visible;
            }
        }
        /* GHANADAMS_AUTO_FIT_END */
</style>
</head>

<body>
<div class="page">
    <main class="shell">
        <header class="topbar">
            <div class="brand">
                <p class="eyebrow">GhanaDAMS</p>
                <h1>Asset Verification Details</h1>
                <p>Ghana Education Service — Digitalised Asset Management System</p>
            </div>

            <div class="verified" aria-label="Verified GhanaDAMS asset record">
                <span class="verified-icon">✓</span>
                Verified Asset
            </div>
        </header>

        <section class="content">
            <article class="media-card">
                <div class="image-stage">
                    @if($asset->asset_image)
                        <img
                            src="/storage/{{ $asset->asset_image }}"
                            alt="{{ $assetName }}"
                        >
                    @else
                        <div class="no-image">
                            <span class="no-image-icon">▧</span>
                            <div>
                                <strong>No asset image available</strong>
                                <div style="margin-top:4px;font-size:11px;">
                                    The GhanaDAMS record is still available for verification.
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="asset-summary">
                    <div class="asset-summary-top">
                        <div>
                            <h2>{{ $assetName }}</h2>
                            <div class="asset-code">{{ $asset->asset_code }}</div>
                        </div>

                        <span class="status-pill tone-{{ $statusTone }}">
                            <span class="status-dot"></span>
                            {{ $statusLabel }}
                        </span>
                    </div>
                </div>
            </article>

            <article class="details-card">
                <section class="section">
                    <div class="section-heading">
                        <span class="section-icon">01</span>
                        <h3>Asset Identification</h3>
                    </div>

                    <div class="info-grid">
                        <div class="info-item full">
                            <span class="info-label">Asset Code</span>
                            <span class="info-value">{{ $asset->asset_code }}</span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Asset Name</span>
                            <span class="info-value">{{ $assetName }}</span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Current Status</span>
                            <span class="info-value">{{ $statusLabel }}</span>
                        </div>
                    </div>
                </section>

                <section class="section">
                    <div class="section-heading">
                        <span class="section-icon">02</span>
                        <h3>Organisational Details</h3>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Unit / Division</span>
                            <span class="info-value">{{ $unitName }}</span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Location</span>
                            <span class="info-value">{{ $locationName }}</span>
                        </div>
                    </div>
                </section>

                <section class="section">
                    <div class="section-heading">
                        <span class="section-icon">03</span>
                        <h3>Acquisition &amp; Warranty</h3>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Purchase Date</span>
                            <span class="info-value">
                                {{ $purchaseDate?->format('d M Y') ?? 'Not recorded' }}
                            </span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Date Acquired</span>
                            <span class="info-value">{{ $dateAcquired }}</span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Warranty Duration</span>
                            <span class="info-value">{{ $warrantyDuration }}</span>
                        </div>

                        <div class="info-item">
                            <span class="info-label">Warranty End Date</span>
                            <span class="info-value">
                                {{ $warrantyEndDate?->format('d M Y') ?? 'Not recorded' }}
                            </span>

                            @if(!is_null($warrantyExpired))
                                <span
                                    class="warranty-note {{ $warrantyExpired ? 'warranty-expired' : 'warranty-valid' }}"
                                >
                                    {{ $warrantyExpired ? 'Warranty expired' : 'Warranty valid' }}
                                </span>
                            @endif
                        </div>
                    </div>
                </section>
            </article>
        </section>

        <footer class="verification-strip">
            <div class="verification-message">
                <span class="checkmark">✓</span>
                <div>
                    <strong>Asset record successfully verified through GhanaDAMS</strong>
                    <span>
                        The QR code resolved to a registered GhanaDAMS asset record.
                    </span>
                </div>
            </div>

            <div class="scan-meta">
                Last scanned: {{ $scannedAt->format('d M Y, H:i') }}
            </div>
        </footer>
    </main>
</div>
</body>
</html>
