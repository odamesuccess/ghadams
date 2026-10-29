<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>User Profile Report</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 35px;
        }

        /* ===== HEADER ===== */
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 25px;
            display: table;
            width: 100%;
        }

        .header-left,
        .header-right {
            display: table-cell;
            vertical-align: middle;
        }

        .header-right {
            text-align: right;
        }

        .title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .subtitle {
            font-size: 12px;
            color: #555;
        }

        .profile-image {
            width: 130px;
            height: 130px;
            border-radius: 6px;
            border: 1px solid #ccc;
            object-fit: cover;
        }

        /* ===== SECTION ===== */
        .section {
            margin-top: 25px;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #ccc;
            padding-bottom: 6px;
            margin-bottom: 15px;
        }

        /* ===== DATA TABLE ===== */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table tr {
            border-bottom: 1px solid #eee;
        }

        .table td {
            padding: 8px 4px;
            vertical-align: top;
        }

        .label {
            width: 35%;
            font-weight: bold;
            color: #333;
        }

        .value {
            width: 65%;
        }

        /* ===== ROLE TAGS ===== */
        .role {
            display: inline-block;
            padding: 3px 8px;
            margin: 2px 4px 2px 0;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            font-size: 10px;
            border-radius: 4px;
        }

        /* ===== FOOTER ===== */
        .footer {
            position: fixed;
            bottom: 20px;
            left: 35px;
            right: 35px;
            font-size: 10px;
            color: #666;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }
    </style>
</head>
<body>

@php
    use Carbon\Carbon;

    $imagePath = $user->profile_image
        ? storage_path('app/public/' . $user->profile_image)
        : null;

    $age = $user->date_of_birth
        ? Carbon::parse($user->date_of_birth)->age
        : null;
@endphp

<!-- HEADER -->
<div class="header">
    <div class="header-left">
        <div class="title">User Profile Report</div>
        <div class="subtitle">Official Personnel Record</div>
    </div>

    <div class="header-right">
        @if($imagePath && file_exists($imagePath))
            <img src="{{ $imagePath }}" class="profile-image">
        @endif
    </div>
</div>

<!-- PERSONAL INFORMATION -->
<div class="section">
    <div class="section-title">Personal Information</div>

    <table class="table">
        <tr>
            <td class="label">Full Name</td>
            <td class="value">{{ $user->name }}</td>
        </tr>

        <tr>
            <td class="label">Sex</td>
            <td class="value">{{ $user->sex ?? '—' }}</td>
        </tr>

        <tr>
            <td class="label">Date of Birth</td>
            <td class="value">
                {{ $user->date_of_birth
                    ? Carbon::parse($user->date_of_birth)->format('F j, Y')
                    : '—'
                }}
            </td>
        </tr>

        <tr>
            <td class="label">Age</td>
            <td class="value">{{ $age ? $age . ' years' : '—' }}</td>
        </tr>
    </table>
</div>

<!-- CONTACT & ASSIGNMENT -->
<div class="section">
    <div class="section-title">Contact & Assignment</div>

    <table class="table">
        <tr>
            <td class="label">Email Address</td>
            <td class="value">{{ $user->email }}</td>
        </tr>

        <tr>
            <td class="label">Contact Number</td>
            <td class="value">{{ $user->contact_number ?? '—' }}</td>
        </tr>

        <tr>
            <td class="label">Unit / Department</td>
            <td class="value">
                {{ $user->unit?->name ?? '—' }}
            </td>
        </tr>

        <tr>
            <td class="label">User Status</td>
            <td class="value">{{ $user->user_status ?? '—' }}</td>
        </tr>
    </table>
</div>

<!-- ROLES -->
<div class="section">
    <div class="section-title">System Roles</div>

    <div>
        @forelse($user->roles as $role)
            <span class="role">
                {{ str_replace('.', ' ', $role->name) }}
            </span>
        @empty
            <span class="value">No role assigned</span>
        @endforelse
    </div>
</div>

<!-- SYSTEM INFORMATION -->
<div class="section">
    <div class="section-title">System Information</div>

    <table class="table">
        <tr>
            <td class="label">Record Created</td>
            <td class="value">
                {{ Carbon::parse($user->created_at)->format('F j, Y') }}
            </td>
        </tr>

        <tr>
            <td class="label">Last Updated</td>
            <td class="value">
                {{ Carbon::parse($user->updated_at)->format('F j, Y') }}
            </td>
        </tr>
    </table>
</div>

<!-- FOOTER -->
<div class="footer">
    Generated by {{ $currentuser }}
    on {{ now()->format('F j, Y') }}
    &nbsp;•&nbsp;
    © {{ date('Y') }} User Management System
</div>

</body>
</html>
