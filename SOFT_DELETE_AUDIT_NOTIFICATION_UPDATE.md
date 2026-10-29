# Soft Delete, Activity Notification, and Audit Log Update

This update adds a full recycle-bin style soft delete layer, global activity tracking, audit logging, and notifications for major system activities.

## Added

- `deleted_at` soft-delete column migration for custom application tables and role/permission tables.
- `SoftDeletes` trait added to application models.
- Custom soft-deletable `App\Models\Role` and `App\Models\Permission` models for Spatie Permission.
- Expanded `ActivityTrackerObserver` to record:
  - created
  - updated
  - soft deleted
  - restored
  - permanently deleted
- Activity records now continue to feed:
  - `activity_trackers`
  - `audit_logs`
  - `system_notifications`
- New `TrashController` for restoring and permanently deleting soft-deleted records.
- New Inertia page: `resources/js/pages/Trash/Index.vue`.
- New routes:
  - `GET /trash`
  - `POST /trash/{module}/{id}/restore`
  - `DELETE /trash/{module}/{id}/force-delete`
- New permissions:
  - `trash.view`
  - `trash.restore`
  - `trash.force-delete`
  - `view.trash`
  - `restore.trash`
  - `force-delete.trash`
- Sidebar footer now shows `Recycle Bin` when the user has trash permission.

## After Uploading

Run these commands:

```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder
php artisan optimize:clear
php artisan permission:cache-reset
npm install
npm run build
```

If you are on cPanel/shared hosting, build locally first, then upload the updated `public/build` folder together with the PHP files.

## Notes

- Normal deletes now become soft deletes for models using `SoftDeletes`.
- Use the Recycle Bin page to restore records or permanently delete them.
- Audit and notification records are intentionally not observed by the global observer to prevent recursive log creation.
