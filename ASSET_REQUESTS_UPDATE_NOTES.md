# GhanaDAMS Asset Requests Update

This update adds a complete Asset Requests workflow for offices/units to request assets from the inventory office.

## New workflow

1. Office/unit submits an asset request.
2. Request is saved with a generated request code.
3. Inventory/admin reviews the request.
4. Request can be approved or rejected.
5. Approved request can be marked as supplied and optionally linked to a registered asset.
6. Request activity is captured through the existing ActivityTrackerService and audit log flow.

## Files added

- `app/Models/AssetRequest.php`
- `app/Http/Controllers/AssetRequestController.php`
- `database/migrations/2026_05_25_000001_create_asset_requests_table.php`
- `resources/js/pages/AssetRequests/Index.vue`
- `resources/js/pages/AssetRequests/MyRequests.vue`
- `resources/js/routes/asset-requests/index.ts`

## Files updated

- `routes/web.php`
- `app/Models/User.php`
- `app/Models/Unit.php`
- `app/Models/Asset.php`
- `app/Http/Controllers/TrashController.php`
- `database/seeders/PermissionSeeder.php`
- `resources/js/components/AppSidebar.vue`

## Commands to run locally

```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder
php artisan optimize:clear
npm install
npm run build
```

If you use Wayfinder route generation in your installed version, you may also run:

```bash
php artisan wayfinder:generate --with-form
```

## Note

The uploaded ZIP's bundled `node_modules` is missing Rollup's Linux optional package in this sandbox, so `npm run build` could not be fully verified here. PHP syntax checks passed for the updated PHP files.
