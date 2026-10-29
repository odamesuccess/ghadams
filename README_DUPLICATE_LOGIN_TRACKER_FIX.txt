DUPLICATE / TRIPLE LOGIN TRACKER FIX
====================================

Files included:
1. app/Services/LoginTrackerService.php
2. app/Providers/FortifyServiceProvider.php
3. database/sql/find_duplicate_login_success_rows.sql

What this patch fixes:
- Stops one login from creating double or triple login_success records.
- Adds duplicate protection inside LoginTrackerService.
- Uses the session login_tracker_id to close the correct login row during logout.
- Removes the unnecessary session timestamp write from FortifyServiceProvider because LoginTrackerService now manages it.
- Logout updates the existing login_success record instead of creating another confusing logout row.

Why double entries happened:
- The login tracker was being triggered more than once in the same login flow.
- The new LoginTrackerService now checks for a recent open login_success record for the same user/email/IP/browser before creating a new row.

After replacing the files, run:

php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

Then test:
1. Log out.
2. Log in once.
3. Open Login Tracker.
4. You should now see only one new login_success row for that login.
5. Log out.
6. The same row should receive logout_at and session_duration_seconds.

Important:
- Existing duplicate rows already in the database will remain unless you delete them.
- Use database/sql/find_duplicate_login_success_rows.sql only to inspect old duplicates.
