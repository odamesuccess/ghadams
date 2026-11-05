<?php

use App\Http\Controllers\ActivityTrackerController;
use App\Http\Controllers\AdvancedReportController;
use App\Http\Controllers\AdvancedAnalyticsController;
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\ManagementReportController;
use App\Http\Controllers\ApprovalRequestController;
use App\Http\Controllers\AssetLifecycleHistoryController;
use App\Http\Controllers\SystemNotificationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetNameController;
use App\Http\Controllers\AssetQrCodeController;
use App\Http\Controllers\AssetQrBulkDownloadController;
use App\Http\Controllers\AssetRequestController;
use App\Http\Controllers\AssetScanController;
use App\Http\Controllers\AssetResearchController;
use App\Http\Controllers\AssetSuggestionController;
use App\Http\Controllers\AssetTransferController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepreciationReportController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LoginTrackerController;
use App\Http\Controllers\MaintenanceProviderController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\QrAnalyticsController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserSessionMonitorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('login'))->name('home');

/** Public asset QR scan UUID-based */
Route::get('/scan/{uuid}', [AssetScanController::class, 'show'])
    ->name('assets.scan');

/** Public short QR redirect secure token */
Route::get('/r/{link:scan_token}', [LinkController::class, 'redirect'])
    ->name('links.redirect');

/** Public expired page */
Route::view('/qr-expired', 'qr.expired')->name('qr.expired');

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

if (! function_exists('crudRoutes')) {
    function crudRoutes(string $uri, string $controller, string $routeName, string $permissionBase, string $parameter): void
    {
        Route::get("/{$uri}", [$controller, 'index'])
            ->middleware("permission:{$permissionBase}.view|view.{$permissionBase}")
            ->name("{$routeName}.index");

        Route::get("/{$uri}/create", [$controller, 'create'])
            ->middleware("permission:{$permissionBase}.create|create.{$permissionBase}")
            ->name("{$routeName}.create");

        Route::post("/{$uri}", [$controller, 'store'])
            ->middleware("permission:{$permissionBase}.create|create.{$permissionBase}")
            ->name("{$routeName}.store");

        Route::get("/{$uri}/{{$parameter}}", [$controller, 'show'])
            ->middleware("permission:{$permissionBase}.view|view.{$permissionBase}")
            ->name("{$routeName}.show");

        Route::get("/{$uri}/{{$parameter}}/edit", [$controller, 'edit'])
            ->middleware("permission:{$permissionBase}.edit|edit.{$permissionBase}")
            ->name("{$routeName}.edit");

        Route::match(['put', 'patch', 'post'], "/{$uri}/{{$parameter}}", [$controller, 'update'])
            ->middleware("permission:{$permissionBase}.edit|edit.{$permissionBase}")
            ->name("{$routeName}.update");

        Route::delete("/{$uri}/{{$parameter}}", [$controller, 'destroy'])
            ->middleware("permission:{$permissionBase}.delete|delete.{$permissionBase}")
            ->name("{$routeName}.destroy");
    }
}

/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware([
        'auth',
        'verified',
        'force.password.change',
        'account.active',
        'permission:dashboard.view|view.dashboard',
    ])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| AUTHENTICATED & PROTECTED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'force.password.change', 'account.active'])->group(function () {


    /*
    |--------------------------------------------------------------------------
    | RECYCLE BIN / SOFT DELETE RESTORE
    |--------------------------------------------------------------------------
    */

    Route::get('/trash', [TrashController::class, 'index'])
        ->middleware('permission:trash.view|view.trash')
        ->name('trash.index');

    Route::post('/trash/{module}/{id}/restore', [TrashController::class, 'restore'])
        ->middleware('permission:trash.restore|restore.trash')
        ->name('trash.restore');

    Route::delete('/trash/{module}/{id}/force-delete', [TrashController::class, 'forceDelete'])
        ->middleware('permission:trash.force-delete|force-delete.trash')
        ->name('trash.force-delete');

    /*
    |--------------------------------------------------------------------------
    | USERS / ROLES / PERMISSIONS
    |--------------------------------------------------------------------------
    */

    Route::post('/users/bulk-activate', [UserController::class, 'bulkActivate'])
        ->middleware('permission:users.activate|activate.users')
        ->name('users.bulk-activate');

    Route::post('/users/bulk-deactivate', [UserController::class, 'bulkDeactivate'])
        ->middleware('permission:users.deactivate|deactivate.users')
        ->name('users.bulk-deactivate');

    Route::post('/users/bulk-delete', [UserController::class, 'bulkDelete'])
        ->middleware('permission:users.delete|delete.users')
        ->name('users.bulk-delete');

    crudRoutes('users', UserController::class, 'users', 'users', 'user');

    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
        ->middleware('permission:users.reset-password|reset-password.users')
        ->name('users.reset-password');

    Route::post('/users/{user}/activate', [UserController::class, 'activate'])
        ->middleware('permission:users.activate|activate.users')
        ->name('users.activate');

    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])
        ->middleware('permission:users.deactivate|deactivate.users')
        ->name('users.deactivate');

    Route::post('/users/{user}/expire-now', [UserController::class, 'expireNow'])
        ->middleware('permission:users.expire|expire.users')
        ->name('users.expire-now');

    Route::post('/users/{user}/extend-expiry', [UserController::class, 'extendExpiry'])
        ->middleware('permission:users.extend-expiry|extend-user-expiry.users')
        ->name('users.extend-expiry');

    Route::get('/users/{user}/pdf', [UserController::class, 'pdf'])
        ->middleware('permission:users.view|view.users')
        ->name('users.pdf');


    crudRoutes('roles', RoleController::class, 'roles', 'roles', 'role');
    crudRoutes('permissions', PermissionController::class, 'permissions', 'permissions', 'permission');

    /*
    |--------------------------------------------------------------------------
    | ORGANIZATIONS / SETUP
    |--------------------------------------------------------------------------
    */

    crudRoutes('organizations', OrganizationController::class, 'organizations', 'organizations', 'organization');
    crudRoutes('assetnames', AssetNameController::class, 'assetnames', 'assetnames', 'assetname');
    crudRoutes('sources', SourceController::class, 'sources', 'sources', 'source');
    crudRoutes('locations', LocationController::class, 'locations', 'locations', 'location');
    crudRoutes('units', UnitController::class, 'units', 'units', 'unit');
    crudRoutes('suppliers', SupplierController::class, 'suppliers', 'suppliers', 'supplier');
    crudRoutes('maintenanceproviders', MaintenanceProviderController::class, 'maintenanceproviders', 'maintenanceproviders', 'maintenanceprovider');
    crudRoutes('assets', AssetController::class, 'assets', 'assets', 'asset');

    /*
    |--------------------------------------------------------------------------
    | AI ASSET RESEARCH (JSON)
    |--------------------------------------------------------------------------
    | The AI only creates suggestions. Assets change only on approve.
    | Unit scoping is enforced by AssetSuggestionPolicy.
    */

    Route::get('/asset-research', [AssetResearchController::class, 'index'])
        ->middleware('permission:assets.research|assets.review-suggestions')
        ->name('asset-research.index');

    Route::patch('/asset-research/{asset}/identifiers', [AssetResearchController::class, 'updateIdentifiers'])
        ->middleware('permission:assets.research')
        ->name('asset-research.identifiers');

    Route::post('/assets/{asset}/research', [AssetSuggestionController::class, 'research'])
        ->middleware(['permission:assets.research', 'throttle:asset-research'])
        ->name('assets.research');

    Route::get('/assets/{asset}/suggestions', [AssetSuggestionController::class, 'index'])
        ->middleware('permission:assets.research|assets.review-suggestions')
        ->name('assets.suggestions.index');

    Route::post('/suggestions/{suggestion}/approve', [AssetSuggestionController::class, 'approve'])
        ->middleware('permission:assets.review-suggestions')
        ->name('suggestions.approve');

    Route::post('/suggestions/{suggestion}/reject', [AssetSuggestionController::class, 'reject'])
        ->middleware('permission:assets.review-suggestions')
        ->name('suggestions.reject');

    /*
    |--------------------------------------------------------------------------
    | ASSET QR CODES
    |--------------------------------------------------------------------------
    | IMPORTANT:
    | - Do NOT use crudRoutes() for Asset QR Codes.
    | - AssetQrCodeController does not need create/store/show/edit/update here.
    | - These routes manage already-generated QR images only.
    | - The delete route removes the QR image and clears the qr_code column.
    | - It must NOT delete the main asset record.
    |--------------------------------------------------------------------------
    */

    /*
     * Optional test route for QR generation/debugging.
     * Keep this only if you still use QrCodeController@qr for testing.
     */
    Route::get('/qr-test', [QrCodeController::class, 'qr'])
        ->middleware('permission:assetqrcodes.view|view.assetqrcodes')
        ->name('qr-test');

    /*
     * List all generated asset QR codes.
     * Non-super-admin unit restrictions should remain inside the controller.
     */
    Route::get('/assetqrcodes', [AssetQrCodeController::class, 'index'])
        ->middleware('permission:assetqrcodes.view|view.assetqrcodes')
        ->name('assetqrcodes.index');
    /*
     * Bulk QR download selectors.
     */
    Route::get('/assetqrcodes/download-options', [AssetQrBulkDownloadController::class, 'options'])
        ->middleware('permission:assetqrcodes.save|save.assetqrcodes')
        ->name('assetqrcodes.download-options');

    /*
     * Download all QR PNGs for one Unit or Organization as one ZIP.
     */
    Route::get('/assetqrcodes/download-zip', [AssetQrBulkDownloadController::class, 'downloadZip'])
        ->middleware('permission:assetqrcodes.save|save.assetqrcodes')
        ->name('assetqrcodes.download-zip');
    /*
     * Async large QR ZIP preparation.
     * Prevents nginx 504 when an Organization contains thousands of QR files.
     */
    Route::post('/assetqrcodes/download-zip/start', [AssetQrBulkDownloadController::class, 'startBuild'])
        ->middleware('permission:assetqrcodes.save|save.assetqrcodes')
        ->name('assetqrcodes.download-start');

    Route::get('/assetqrcodes/download-zip/status/{token}', [AssetQrBulkDownloadController::class, 'buildStatus'])
        ->middleware('permission:assetqrcodes.save|save.assetqrcodes')
        ->name('assetqrcodes.download-status');

    Route::get('/assetqrcodes/download-zip/file/{token}', [AssetQrBulkDownloadController::class, 'downloadBuiltFile'])
        ->middleware('permission:assetqrcodes.save|save.assetqrcodes')
        ->name('assetqrcodes.download-file');
    Route::post('/assetqrcodes/backfill-existing', [AssetQrCodeController::class, 'backfillExistingMedia'])
        ->middleware('permission:assetqrcodes.view|view.assetqrcodes')
        ->name('assetqrcodes.backfill-existing');

    /*
     * Print or reprint a single asset QR code as PDF.
     */
    Route::get('/assetqrcodes/{assetqrcode}/print', [AssetQrCodeController::class, 'print'])
        ->middleware('permission:assetqrcodes.print|print.assetqrcodes')
        ->name('assetqrcodes.print');

    /*
     * Delete only the QR code image for an asset.
     * This should call AssetQrCodeController@destroy.
     */
    Route::delete('/assetqrcodes/{assetqrcode}', [AssetQrCodeController::class, 'destroy'])
        ->middleware('permission:assetqrcodes.delete|delete.assetqrcodes')
        ->name('assetqrcodes.destroy');

    /*
    |--------------------------------------------------------------------------
    | LINK QR SYSTEM
    |--------------------------------------------------------------------------
    | Do not manually add links.show here.
    | crudRoutes('links', ...) already creates:
    | GET /links/{link} => links.show
    |--------------------------------------------------------------------------
    */

    Route::get('/links/analytics', [QrAnalyticsController::class, 'index'])
        ->middleware('permission:links.view|view.links')
        ->name('links.analytics');

    Route::get('/links/{link}/qranalytic', [LinkController::class, 'analytic'])
        ->middleware('permission:links.qranalytic|view.links')
        ->name('links.analytic');

    crudRoutes('links', LinkController::class, 'links', 'links', 'link');


    /*
    |--------------------------------------------------------------------------
    | USER SESSION MONITOR
    |--------------------------------------------------------------------------
    */
    Route::get('/user-session-monitor', [UserSessionMonitorController::class, 'index'])
        ->name('user-session-monitor.index');

    /*
    |--------------------------------------------------------------------------
    | LOGIN TRACKERS
    |--------------------------------------------------------------------------
    */

    Route::get('/login-trackers', [LoginTrackerController::class, 'index'])
        ->middleware('permission:login-trackers.view|view.login-trackers')
        ->name('login-trackers.index');

    Route::get('/login-trackers/export-csv', [LoginTrackerController::class, 'exportCsv'])
        ->middleware('permission:login-trackers.export|export.login-trackers')
        ->name('login-trackers.export-csv');

    Route::get('/login-trackers/export-pdf', [LoginTrackerController::class, 'exportPdf'])
        ->middleware('permission:login-trackers.export|export.login-trackers')
        ->name('login-trackers.export-pdf');

    Route::delete('/login-trackers/{loginTracker}', [LoginTrackerController::class, 'destroy'])
        ->middleware('permission:login-trackers.delete|delete.login-trackers')
        ->name('login-trackers.destroy');

    /*
    |--------------------------------------------------------------------------
    | ACTIVITY TRACKERS
    |--------------------------------------------------------------------------
    */

    Route::get('/activity-trackers', [ActivityTrackerController::class, 'index'])
        ->middleware('permission:activity-trackers.view|view.activity-trackers')
        ->name('activity-trackers.index');

    Route::get('/activity-trackers/export-csv', [ActivityTrackerController::class, 'exportCsv'])
        ->middleware('permission:activity-trackers.export|export.activity-trackers')
        ->name('activity-trackers.export-csv');

    Route::get('/activity-trackers/export-pdf', [ActivityTrackerController::class, 'exportPdf'])
        ->middleware('permission:activity-trackers.export|export.activity-trackers')
        ->name('activity-trackers.export-pdf');

    Route::delete('/activity-trackers/{activityTracker}', [ActivityTrackerController::class, 'destroy'])
        ->middleware('permission:activity-trackers.delete|delete.activity-trackers')
        ->name('activity-trackers.destroy');

    /*
    |--------------------------------------------------------------------------
    | AUDIT LOGS
    |--------------------------------------------------------------------------
    */

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:audit-logs.view|view.audit-logs')
        ->name('audit-logs.index');

    Route::get('/audit-logs/export-csv', [AuditLogController::class, 'exportCsv'])
        ->middleware('permission:audit-logs.export|export.audit-logs')
        ->name('audit-logs.export-csv');

    Route::get('/audit-logs/export-pdf', [AuditLogController::class, 'exportPdf'])
        ->middleware('permission:audit-logs.export|export.audit-logs')
        ->name('audit-logs.export-pdf');

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS
    |--------------------------------------------------------------------------
    */

    Route::get('/notifications', [SystemNotificationController::class, 'index'])
        ->middleware('permission:notifications.view|view.notifications')
        ->name('notifications.index');

    Route::get('/notifications/latest', [SystemNotificationController::class, 'latest'])
        ->middleware('permission:notifications.view|view.notifications')
        ->name('notifications.latest');

    Route::post('/notifications/{notification}/read', [SystemNotificationController::class, 'markAsRead'])
        ->middleware('permission:notifications.edit|mark-read.notifications')
        ->name('notifications.read');

    Route::post('/notifications/mark-all-read', [SystemNotificationController::class, 'markAllAsRead'])
        ->middleware('permission:notifications.edit|mark-read.notifications')
        ->name('notifications.mark-all-read');

    Route::delete('/notifications/clear-all', [SystemNotificationController::class, 'clearAll'])
        ->middleware('permission:notifications.delete|clear.notifications')
        ->name('notifications.clear-all');

    Route::delete('/notifications/{notification}', [SystemNotificationController::class, 'destroy'])
        ->middleware('permission:notifications.delete|delete.notifications')
        ->name('notifications.destroy');

    /*
    |--------------------------------------------------------------------------
    | ASSET LIFECYCLE
    |--------------------------------------------------------------------------
    */

    Route::get('/asset-lifecycle-histories', [AssetLifecycleHistoryController::class, 'index'])
        ->middleware('permission:asset-lifecycle.view|view.asset-lifecycle')
        ->name('asset-lifecycle-histories.index');

    Route::post('/assets/{asset}/lifecycle', [AssetLifecycleHistoryController::class, 'store'])
        ->middleware('permission:asset-lifecycle.create|create.asset-lifecycle')
        ->name('assets.lifecycle.store');



    /*
    |--------------------------------------------------------------------------
    | ASSET REQUESTS FROM OFFICES / UNITS
    |--------------------------------------------------------------------------
    */

    Route::get('/asset-requests', [AssetRequestController::class, 'index'])
        ->middleware('permission:asset-requests.view|view.asset-requests')
        ->name('asset-requests.index');

    Route::get('/my-asset-requests', [AssetRequestController::class, 'myRequests'])
        ->middleware('permission:asset-requests.view|view.asset-requests')
        ->name('asset-requests.my-requests');

    Route::post('/asset-requests', [AssetRequestController::class, 'store'])
        ->middleware('permission:asset-requests.create|create.asset-requests')
        ->name('asset-requests.store');

    Route::patch('/asset-requests/{assetRequest}', [AssetRequestController::class, 'update'])
        ->middleware('permission:asset-requests.create|create.asset-requests')
        ->name('asset-requests.update');

    Route::post('/asset-requests/{assetRequest}/approve', [AssetRequestController::class, 'approve'])
        ->middleware('permission:asset-requests.approve|approve.asset-requests')
        ->name('asset-requests.approve');

    Route::post('/asset-requests/{assetRequest}/reject', [AssetRequestController::class, 'reject'])
        ->middleware('permission:asset-requests.reject|reject.asset-requests')
        ->name('asset-requests.reject');

    Route::post('/asset-requests/{assetRequest}/supply', [AssetRequestController::class, 'supply'])
        ->middleware('permission:asset-requests.supply|supply.asset-requests')
        ->name('asset-requests.supply');

    Route::post('/asset-requests/{assetRequest}/cancel', [AssetRequestController::class, 'cancel'])
        ->middleware('permission:asset-requests.cancel|cancel.asset-requests')
        ->name('asset-requests.cancel');

    Route::delete('/asset-requests/{assetRequest}', [AssetRequestController::class, 'destroy'])
        ->middleware('permission:asset-requests.delete|delete.asset-requests')
        ->name('asset-requests.destroy');



    /*
    |--------------------------------------------------------------------------
    | ASSET TRANSFERS / ASSET MOVEMENT TRACKING
    |--------------------------------------------------------------------------
    */

    Route::get('/asset-transfers', [AssetTransferController::class, 'index'])
        ->middleware('permission:asset-transfers.view|view.asset-transfers')
        ->name('asset-transfers.index');

    Route::get('/my-asset-transfers', [AssetTransferController::class, 'myTransfers'])
        ->middleware('permission:asset-transfers.view|view.asset-transfers')
        ->name('asset-transfers.my-transfers');

    Route::post('/asset-transfers', [AssetTransferController::class, 'store'])
        ->middleware('permission:asset-transfers.create|create.asset-transfers')
        ->name('asset-transfers.store');

    Route::post('/asset-transfers/{assetTransfer}/approve', [AssetTransferController::class, 'approve'])
        ->middleware('permission:asset-transfers.approve|approve.asset-transfers')
        ->name('asset-transfers.approve');

    Route::post('/asset-transfers/{assetTransfer}/reject', [AssetTransferController::class, 'reject'])
        ->middleware('permission:asset-transfers.reject|reject.asset-transfers')
        ->name('asset-transfers.reject');

    Route::post('/asset-transfers/{assetTransfer}/complete', [AssetTransferController::class, 'complete'])
        ->middleware('permission:asset-transfers.complete|complete.asset-transfers')
        ->name('asset-transfers.complete');

    Route::post('/asset-transfers/{assetTransfer}/cancel', [AssetTransferController::class, 'cancel'])
        ->middleware('permission:asset-transfers.cancel|cancel.asset-transfers')
        ->name('asset-transfers.cancel');

    Route::delete('/asset-transfers/{assetTransfer}', [AssetTransferController::class, 'destroy'])
        ->middleware('permission:asset-transfers.delete|delete.asset-transfers')
        ->name('asset-transfers.destroy');


    /*
    |--------------------------------------------------------------------------
    | APPROVAL REQUESTS
    |--------------------------------------------------------------------------
    */

    Route::get('/approval-requests', [ApprovalRequestController::class, 'index'])
        ->middleware('permission:approval-requests.view|view.approval-requests')
        ->name('approval-requests.index');

    Route::post('/approval-requests', [ApprovalRequestController::class, 'store'])
        ->middleware('permission:approval-requests.create|create.approval-requests')
        ->name('approval-requests.store');

    Route::patch('/approval-requests/{approvalRequest}', [ApprovalRequestController::class, 'update'])
        ->middleware('permission:approval-requests.create|create.approval-requests')
        ->name('approval-requests.update');

    Route::post('/approval-requests/{approvalRequest}/approve', [ApprovalRequestController::class, 'approve'])
        ->middleware('permission:approval-requests.approve|approve.approval-requests')
        ->name('approval-requests.approve');

    Route::post('/approval-requests/{approvalRequest}/reject', [ApprovalRequestController::class, 'reject'])
        ->middleware('permission:approval-requests.reject|reject.approval-requests')
        ->name('approval-requests.reject');

    Route::delete('/approval-requests/{approvalRequest}', [ApprovalRequestController::class, 'destroy'])
        ->middleware('permission:approval-requests.delete|delete.approval-requests')
        ->name('approval-requests.destroy');

 /*
|--------------------------------------------------------------------------
| MAINTENANCE REQUESTS
|--------------------------------------------------------------------------
*/

Route::get('/maintenance-requests', [MaintenanceRequestController::class, 'index'])
    ->middleware('permission:maintenance-requests.view|view.maintenance-requests')
    ->name('maintenance-requests.index');

Route::get('/my-maintenance-requests', [MaintenanceRequestController::class, 'userMaintenanceRequest'])
    ->middleware('permission:maintenance-requests.view|view.maintenance-requests')
    ->name('maintenance-requests.usermaintenancerequest');
Route::get('/maintenance-requests/asset-search', [MaintenanceRequestController::class, 'assetSearch'])
    ->middleware('permission:maintenance-requests.view|view.maintenance-requests')
    ->name('maintenance-requests.asset-search');

Route::get('/accounts/maintenance-payments', [MaintenanceRequestController::class, 'maintenancePayment'])
    ->middleware('permission:maintenance-requests.edit|edit.maintenance-requests')
    ->name('accounts.maintenance-payments.index');

Route::get('/accounts/maintenance-payments/{maintenanceRequest}/pdf', [MaintenanceRequestController::class, 'jobPaymentPdf'])
    ->middleware('permission:maintenance-requests.edit|edit.maintenance-requests')
    ->name('accounts.maintenance-payments.job-pdf');

// Legacy URL retained for existing bookmarks/links.
Route::get('/my-maintenance-payments', [MaintenanceRequestController::class, 'maintenancePayment'])
    ->middleware('permission:maintenance-requests.edit|edit.maintenance-requests')
    ->name('maintenance-requests.maintenancepayment');

Route::post('/maintenance-requests', [MaintenanceRequestController::class, 'store'])
    ->middleware('permission:maintenance-requests.create|create.maintenance-requests')
    ->name('maintenance-requests.store');

Route::put('/maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'update'])
    ->middleware('permission:maintenance-requests.edit|edit.maintenance-requests')
    ->name('maintenance-requests.update');

Route::post('/maintenance-requests/{maintenanceRequest}/approve', [MaintenanceRequestController::class, 'approve'])
    ->middleware('permission:maintenance-requests.approve|approve.maintenance-requests')
    ->name('maintenance-requests.approve');

Route::post('/maintenance-requests/{maintenanceRequest}/complete', [MaintenanceRequestController::class, 'complete'])
    ->middleware('permission:maintenance-requests.complete|complete.maintenance-requests')
    ->name('maintenance-requests.complete');
Route::post('/maintenance-requests/{maintenanceRequest}/invoice', [MaintenanceRequestController::class, 'uploadInvoice'])
    ->middleware('auth')
    ->name('maintenance-requests.invoice.upload');

Route::get('/maintenance-requests/{maintenanceRequest}/invoice', [MaintenanceRequestController::class, 'invoicePdf'])
    ->middleware('auth')
    ->name('maintenance-requests.invoice.view');

Route::post('/maintenance-requests/{maintenanceRequest}/payment-status', [MaintenanceRequestController::class, 'updatePaymentStatus'])
    ->middleware('permission:maintenance-requests.edit|edit.maintenance-requests')
    ->name('maintenance-requests.payment-status');

Route::delete('/maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'destroy'])
    ->middleware('permission:maintenance-requests.delete|delete.maintenance-requests')
    ->name('maintenance-requests.destroy');


    /*
    |--------------------------------------------------------------------------
    | ADVANCED ANALYTICS / DATA SCIENCE OUTPUTS
    |--------------------------------------------------------------------------
    */

    Route::prefix('advanced-analytics')
        ->name('advanced-analytics.')
        ->middleware('permission:reports.view|view.reports')
        ->group(function () {
            Route::get('/', [AdvancedAnalyticsController::class, 'dashboard'])->name('dashboard');
            Route::get('/distribution-count', [AdvancedAnalyticsController::class, 'distributionCount'])->name('distribution-count');
            Route::get('/data-quality', [AdvancedAnalyticsController::class, 'dataQuality'])->name('data-quality');
            Route::get('/maintenance', [AdvancedAnalyticsController::class, 'maintenance'])->name('maintenance');
            Route::get('/lifecycle-risk', [AdvancedAnalyticsController::class, 'lifecycleRisk'])->name('lifecycle-risk');
            Route::get('/warranty-end-of-life', [AdvancedAnalyticsController::class, 'warrantyEndOfLife'])->name('warranty-end-of-life');
            Route::get('/implementation-evidence', [AdvancedAnalyticsController::class, 'implementationEvidence'])->name('implementation-evidence');
        });

    /*
    |--------------------------------------------------------------------------
    | ADVANCED REPORTS / EXCEL CSV EXPORTS
    |--------------------------------------------------------------------------
    */

    Route::prefix('advanced-reports')
        ->name('advanced-reports.')
        ->middleware('permission:reports.view|view.reports')
        ->group(function () {
            Route::get('/', [AdvancedReportController::class, 'index'])
                ->name('index');

            Route::get('/asset-register.csv', [AdvancedReportController::class, 'assetRegisterCsv'])
                ->middleware('permission:reports.export|export.reports')
                ->name('asset-register.csv');

            Route::get('/warranty-expiry.csv', [AdvancedReportController::class, 'warrantyExpiryCsv'])
                ->middleware('permission:reports.export|export.reports')
                ->name('warranty-expiry.csv');

            Route::get('/end-of-life.csv', [AdvancedReportController::class, 'endOfLifeCsv'])
                ->middleware('permission:reports.export|export.reports')
                ->name('end-of-life.csv');

            Route::get('/replacement-recommendation.csv', [AdvancedReportController::class, 'replacementRecommendationCsv'])
                ->middleware('permission:reports.export|export.reports')
                ->name('replacement-recommendation.csv');

            Route::get('/maintenance-history.csv', [AdvancedReportController::class, 'maintenanceHistoryCsv'])
                ->middleware('permission:reports.export|export.reports')
                ->name('maintenance-history.csv');

            Route::get('/qr-scans.csv', [AdvancedReportController::class, 'qrScansCsv'])
                ->middleware('permission:reports.export|export.reports')
                ->name('qr-scans.csv');
        });

    /*
    |--------------------------------------------------------------------------
    | MANAGEMENT REPORTS
    |--------------------------------------------------------------------------
    */

    Route::prefix('management-reports')
        ->name('management-reports.')
        ->middleware('permission:management-reports.view')
        ->group(function () {
            Route::get('/asset-purchases', [ManagementReportController::class, 'assetPurchases'])
                ->middleware('permission:management-reports.asset-purchases.view')
                ->name('asset-purchases');

            Route::get('/asset-purchases/pdf', [ManagementReportController::class, 'assetPurchasesPdf'])
                ->middleware('permission:management-reports.asset-purchases.view|management-reports.pdf')
                ->name('asset-purchases.pdf');

            Route::get('/maintenance', [ManagementReportController::class, 'maintenance'])
                ->middleware('permission:management-reports.maintenance.view')
                ->name('maintenance');

            Route::get('/maintenance/pdf', [ManagementReportController::class, 'maintenancePdf'])
                ->middleware('permission:management-reports.maintenance.view|management-reports.pdf')
                ->name('maintenance.pdf');

            Route::get('/annual-assets', [ManagementReportController::class, 'annualAssets'])
                ->middleware('permission:management-reports.annual-assets.view')
                ->name('annual-assets');

            Route::get('/annual-assets/pdf', [ManagementReportController::class, 'annualAssetsPdf'])
                ->middleware('permission:management-reports.annual-assets.view|management-reports.pdf')
                ->name('annual-assets.pdf');
        });

    /*
    |--------------------------------------------------------------------------
    | REPORTS
    |--------------------------------------------------------------------------
    */

    Route::prefix('reports')
        ->name('reports.')
        ->middleware('permission:reports.view|view.reports')
        ->group(function () {
            Route::get('/obsolete-assets', [ReportController::class, 'obsolete'])->name('obsolete');
            Route::get('/obsolete-assets/pdf', [ReportController::class, 'obsoletePdf'])->name('obsolete.pdf');

            Route::get('/disposed-assets', [ReportController::class, 'disposed'])->name('disposed');
            Route::get('/disposed-assets/pdf', [ReportController::class, 'disposedPdf'])->name('disposed.pdf');

            Route::get('/active-assets', [ReportController::class, 'active'])->name('active');
            Route::get('/active-assets/pdf', [ReportController::class, 'activePdf'])->name('active.pdf');

            Route::get('/under-maintenance-assets', [ReportController::class, 'underMaintenance'])->name('underMaintenance');
            Route::get('/under-maintenance/pdf', [ReportController::class, 'underMaintenancePdf'])->name('underMaintenance.pdf');

            Route::get('/asset-depreciation', [ReportController::class, 'depreciation'])->name('depreciation');
            Route::get('/asset-depreciation/pdf', [ReportController::class, 'depreciationPdf'])->name('depreciation.pdf');

            Route::get('/yearlypurchased-assets', [ReportController::class, 'yearlyPurchased'])->name('yearlyPurchased');
            Route::get('/yearlypurchased-assets/pdf', [ReportController::class, 'yearlyPurchasedPdf'])->name('yearlyPurchased.pdf');

            Route::get('/inventory-office', [ReportController::class, 'inventoryOffice'])->name('inventoryOffice');
            Route::get('/inventory-office/pdf', [ReportController::class, 'inventoryOfficePdf'])->name('inventoryOffice.pdf');

            Route::get('/asset-count-by-year', [ReportController::class, 'assetcountByYear'])->name('assetcountByYear');
            Route::get('/asset-count-by-year/pdf', [ReportController::class, 'assetcountByYearPdf'])->name('assetcountByYear.pdf');

            Route::get('/quantity-category', [ReportController::class, 'quantityCategory'])->name('quantityCategory');
            Route::get('/value-category', [ReportController::class, 'valueCategory'])->name('valueCategory');
            Route::get('/maintenance-cost', [ReportController::class, 'maintenanceCost'])->name('maintenanceCost');
            Route::get('/maintenance-cost/pdf', [ReportController::class, 'maintenanceCostPdf'])->name('maintenanceCost.pdf');
            Route::get('/aging-assets', [ReportController::class, 'agingAssets'])->name('agingAssets');
        });

    /*
    |--------------------------------------------------------------------------
    | DEPRECIATION REPORT
    |--------------------------------------------------------------------------
    */

    Route::get('/depreciation-report', [DepreciationReportController::class, 'index'])
        ->middleware('permission:depreciationreports.view|view.depreciation-reports')
        ->name('depreciationreports.index');

    Route::get('/depreciation-report/pdf', [DepreciationReportController::class, 'pdf'])
        ->middleware('permission:depreciationreports.export|export.depreciation-reports')
        ->name('depreciationreports.pdf');
});

/*
|--------------------------------------------------------------------------
| FORCE PASSWORD CHANGE
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/force-change-password', [ChangePasswordController::class, 'edit'])
        ->name('force-password.change');

    Route::put('/force-change-password', [ChangePasswordController::class, 'update'])
        ->name('force-password.update');
});

require __DIR__.'/settings.php';

Route::patch(
    '/maintenance-requests/{maintenanceRequest}/job-status',
    [MaintenanceRequestController::class, 'updateJobStatus']
)
    ->middleware(
        ['auth', 'force.password.change', 'account.active', 'permission:maintenance-requests.complete|complete.maintenance-requests']
    )
    ->name('maintenance-requests.job-status');

/*
|--------------------------------------------------------------------------
| GHANADAMS_SPECIAL_ROLE_ARCHITECTURE
|--------------------------------------------------------------------------
| LMS-style Special Role administration.
*/
Route::middleware(['auth'])->group(function (): void {
    Route::get(
        '/roles/access-overview',
        [\App\Http\Controllers\RoleAccessOverviewController::class, 'index']
    )->name('roles.access-overview');

    Route::patch(
        '/roles/{role}/special-settings',
        [\App\Http\Controllers\RoleAccessOverviewController::class, 'update']
    )->name('roles.special-settings');
});
