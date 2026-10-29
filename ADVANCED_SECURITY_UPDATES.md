# Advanced GhanaDAMS Security, Audit and User Control Update

This package has been updated with:

1. User account active/inactive control.
2. User account expiry dates.
3. Deactivation reason, deactivated date and deactivated-by tracking.
4. Last login, last logout and last seen tracking.
5. Login tracker model, migration, service and admin page.
6. Activity tracker model, migration, observer, service and admin page.
7. Forgotten-password link visible on login page.
8. Admin password reset now sends a reset link instead of setting `Password123!`.
9. Password reset completion is tracked.
10. Password reset link sending is tracked through Laravel notification events.
11. Inactive/expired users are blocked from login.
12. Middleware added to remove inactive/expired users from protected sessions.
13. CRUD permissions added for all major modules.
14. Permission middleware added to protected routes.
15. Report and depreciation report routes moved under authenticated/protected middleware.
16. Login Tracker and Activity Tracker sidebar items added.
17. `.env` removed from the ZIP for safer sharing.

## After upload/run

Run these commands on the server/local project:

```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder
php artisan permission:cache-reset
php artisan optimize:clear
npm install
npm run build
```

If your server uses the provided SQL import instead of migrations, use:

```text
database/advanced_security_update.sql
```

## Mail requirement

Forgotten password and admin password reset link require correct mail settings in `.env`.
Do not share `.env` in downloadable project packages.
