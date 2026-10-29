# Advanced GhanaDAMS Security, Audit, User Control and Asset Governance Update

This package adds the next enterprise update layer:

- User account expiry and active/inactive controls
- Forgotten password and secure password reset tracking
- Login tracker with IP, browser, platform, device and session duration
- Activity tracker with old/new values
- Full audit logs page with PDF and Excel-compatible CSV exports
- System notifications page with unread/read workflow
- Asset lifecycle history and asset detail timeline
- Approval workflow for transfer, disposal, maintenance and assignment requests
- Maintenance request module with approval and completion actions
- Advanced dashboard security cards
- QR scan tracking upgrades: browser, platform, device, location, unknown-location and tamper flags
- QR reprint history tracking
- Advanced reports page with Excel-compatible CSV exports
- CRUD permissions and protected routes for new modules
- Sidebar links for Security & Audit, lifecycle, approval, maintenance and advanced reports

## After uploading

```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder
php artisan permission:cache-reset
php artisan optimize:clear
npm install
npm run build
```

If your hosting uses phpMyAdmin instead of migrations, use `database/advanced_update_schema.sql` or the updated SQL file provided separately.

## Important

The exports named Excel CSV are CSV files that open directly in Microsoft Excel. This avoids adding another composer dependency such as Maatwebsite Excel.
