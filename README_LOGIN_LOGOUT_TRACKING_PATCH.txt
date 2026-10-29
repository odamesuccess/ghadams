LOGIN / LOGOUT TRACKING PATCH

Files to replace:
1. app/Services/LoginTrackerService.php
2. resources/js/pages/LoginTrackers/Index.vue

New migration to add:
3. database/migrations/2026_06_23_000001_ensure_login_tracker_session_columns.php

What this patch does:
- Login success still creates a login tracker record with login_at.
- Logout now updates the latest open login_success record with logout_at.
- Duration is calculated automatically in session_duration_seconds.
- The Login Tracker page displays login, logout, and a formatted duration.
- If no open login record exists during logout, the system safely creates a fallback logout record.

After copying the files, run:
php artisan migrate
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear

If you cannot run migration on cPanel, run the SQL in database/sql/login_tracker_session_columns.sql manually in phpMyAdmin.

Testing:
1. Log in.
2. Open Login Tracker and confirm login_at appears.
3. Click Logout.
4. Log in again.
5. Open Login Tracker and confirm the previous row has logout_at and duration.

Important:
The system can only record exact logout time when the user clicks Logout. If the user closes the browser without logging out, exact logout time cannot be known without a separate inactivity/session-expiry tracker.
