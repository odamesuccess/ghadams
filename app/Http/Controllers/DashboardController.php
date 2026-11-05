<?php

namespace App\Http\Controllers;

use App\Models\ActivityTracker;
use App\Models\ApprovalRequest;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\LoginTracker;
use App\Models\MaintenanceRequest;
use App\Models\SystemNotification;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        /*
        =====================================================
        SUMMARY (Single Optimized Aggregate Query)
        =====================================================
        */

        $summary = Asset::selectRaw("
                COUNT(*) as totalAssets,
                COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as activeAssets,
                COALESCE(SUM(CASE WHEN status = 'disposed' THEN 1 ELSE 0 END), 0) as disposedAssets,
                COALESCE(SUM(CASE WHEN status = 'obsolete' THEN 1 ELSE 0 END), 0) as obsoleteAssets,
                COALESCE(SUM(CASE WHEN status = 'under_maintenance' THEN 1 ELSE 0 END), 0) as underMaintenanceAssets,
                COALESCE(SUM(purchase_cost), 0) as totalValue,

                COALESCE(SUM(
                    CASE
                        WHEN purchase_cost IS NULL THEN 0
                        WHEN status NOT IN ('active', 'under_maintenance') THEN 0
                        WHEN purchase_date IS NULL THEN 1
                        WHEN TIMESTAMPDIFF(YEAR, purchase_date, CURDATE()) < 10 THEN 1
                        ELSE 0
                    END
                ), 0) as depreciableAssetCount,

                COALESCE(SUM(
                    CASE
                        WHEN purchase_cost IS NULL THEN 0
                        WHEN status NOT IN ('active', 'under_maintenance') THEN 0
                        WHEN purchase_date IS NULL THEN purchase_cost
                        WHEN TIMESTAMPDIFF(YEAR, purchase_date, CURDATE()) >= 10 THEN 0
                        ELSE GREATEST(
                            purchase_cost * 0.10,
                            purchase_cost - (
                                purchase_cost * 0.10 * TIMESTAMPDIFF(YEAR, purchase_date, CURDATE())
                            )
                        )
                    END
                ), 0) as depreciatedAssetValue
            ")
            ->first();

        /*
        =====================================================
        ASSETS BY STATUS
        =====================================================
        */

        $assetsByStatus = Asset::selectRaw("
                status,
                COUNT(*) as total
            ")
            ->whereNotNull('status')
            ->groupBy('status')
            ->orderByDesc('total')
            ->pluck('total', 'status');

        /*
        =====================================================
        ASSETS BY YEAR - LAST 15 YEARS INCLUDING CURRENT YEAR
        Used for the acquisition trend line chart.
        =====================================================
        */

        $currentYear = now()->year;
        $startYear = $currentYear - 14;
        $yearRows = Asset::selectRaw('YEAR(purchase_date) as year, COUNT(*) as total, COALESCE(SUM(purchase_cost), 0) as value')
            ->whereBetween('purchase_date', ["{$startYear}-01-01", "{$currentYear}-12-31"])
            ->groupBy(DB::raw('YEAR(purchase_date)'))
            ->get()->keyBy('year');
        $assetsByYear = collect(range($startYear, $currentYear))->map(function ($year) use ($yearRows) {
            $row = $yearRows->get($year);
            return [
                'year' => (int) $year,
                'total' => (int) ($row->total ?? 0),
                'value' => (float) ($row->value ?? 0),
            ];
        });

        /*
        =====================================================
        ASSET YEAR SUMMARY CARDS
        =====================================================
        This sends:
        1. Fixed recent years: 2026, 2025, 2024, 2023.
        2. All older actual purchase years up to 2022.

        This allows the Vue dashboard to show:
        - 2026 Purchase Year
        - 2025 Purchase Year
        - 2024 Purchase Year
        - 2023 Purchase Year
        - First Purchase Year - 2022 Purchase Years
        =====================================================
        */

        $summaryYears = [2026, 2025, 2024, 2023];

        $assetYearRows = Asset::selectRaw("
                YEAR(purchase_date) as year,
                COUNT(*) as quantity,
                COALESCE(SUM(purchase_cost), 0) as cost
            ")
            ->whereNotNull('purchase_date')
            ->groupBy(DB::raw('YEAR(purchase_date)'))
            ->orderByDesc('year')
            ->get()
            ->keyBy(fn ($row) => (int) $row->year);

        $recentYearSummary = collect($summaryYears)
            ->map(function ($year) use ($assetYearRows) {
                $row = $assetYearRows->get($year);

                return [
                    'year' => (int) $year,
                    'quantity' => (int) ($row->quantity ?? 0),
                    'cost' => (float) ($row->cost ?? 0),
                ];
            });

        $olderYearSummary = $assetYearRows
            ->filter(fn ($row, $year) => (int) $year <= 2022)
            ->sortKeysDesc()
            ->map(fn ($row) => [
                'year' => (int) $row->year,
                'quantity' => (int) ($row->quantity ?? 0),
                'cost' => (float) ($row->cost ?? 0),
            ])
            ->values();

        $assetYearSummary = $recentYearSummary
            ->merge($olderYearSummary)
            ->values();

        /*
        =====================================================
        ASSETS BY LOCATION (Top 10)
        Uses LEFT JOIN so assets without assigned unit are not lost.
        =====================================================
        */

        $assetsByLocation = Asset::leftJoin('units', 'units.id', '=', 'assets.unit_id')
            ->selectRaw("
                COALESCE(units.locationname, 'Unassigned') as location,
                COUNT(*) as total
            ")
            ->groupBy(DB::raw("COALESCE(units.locationname, 'Unassigned')"))
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'location' => $row->location,
                'total' => (int) $row->total,
            ]);

        /*
        =====================================================
        ASSETS BY ASSET NAME (Top 12)
        Uses LEFT JOIN so assets without asset name are not lost.
        =====================================================
        */

        $assetsByName = Asset::leftJoin('asset_names', 'asset_names.id', '=', 'assets.assetname_id')
            ->selectRaw("
                COALESCE(asset_names.assetname, 'Unassigned') as name,
                asset_names.assetname_abbreviation as short,
                COUNT(*) as total
            ")
            ->groupBy(
                DB::raw("COALESCE(asset_names.assetname, 'Unassigned')"),
                'asset_names.assetname_abbreviation'
            )
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'short' => $row->short,
                'total' => (int) $row->total,
            ]);

        /*
        =====================================================
        WARRANTY EXPIRING WITHIN 90 DAYS
        =====================================================
        */

        // Warranty dashboard metrics use ACTIVE assets only so the
        // dashboard, Inventory by Office and lifecycle analytics all share
        // the same warranty-monitoring scope.
        $expiringSoon = Asset::query()
            ->where('status', 'active')
            ->whereNotNull('warranty_end_date')
            ->whereBetween('warranty_end_date', [
                today()->toDateString(),
                today()->copy()->addDays(90)->toDateString(),
            ])
            ->count();

        /*
        =====================================================
        SECURITY / WORKFLOW STATS
        =====================================================
        */

        $securityStats = [
            'todaysLogins' => (int) LoginTracker::where('event', 'login_success')
                ->whereDate('created_at', today())
                ->count(),

            'failedLogins' => (int) LoginTracker::where('event', 'login_failed')
                ->whereDate('created_at', today())
                ->count(),

            'inactiveBlocked' => (int) LoginTracker::where('event', 'inactive_blocked')
                ->whereDate('created_at', today())
                ->count(),

            'expiredBlocked' => (int) LoginTracker::where('event', 'expired_blocked')
                ->whereDate('created_at', today())
                ->count(),

            'activitiesToday' => (int) ActivityTracker::whereDate('created_at', today())
                ->count(),

            'auditLogsToday' => (int) AuditLog::whereDate('created_at', today())
                ->count(),

            'unreadNotifications' => (int) SystemNotification::whereNull('read_at')
                ->count(),

            'pendingApprovals' => (int) ApprovalRequest::where('status', 'pending')
                ->count(),

            'pendingMaintenance' => (int) MaintenanceRequest::whereIn('status', [
                    'pending',
                    'approved',
                    'in_progress',
                ])
                ->count(),
        ];

        /*
        =====================================================
        RETURN DASHBOARD DATA
        =====================================================
        */

        return Inertia::render('Dashboard/Index', [
            /* SUMMARY */
            'totalAssets' => (int) ($summary->totalAssets ?? 0),
            'activeAssets' => (int) ($summary->activeAssets ?? 0),
            'disposedAssets' => (int) ($summary->disposedAssets ?? 0),
            'obsoleteAssets' => (int) ($summary->obsoleteAssets ?? 0),
            'underMaintenanceAssets' => (int) ($summary->underMaintenanceAssets ?? 0),
            'totalValue' => (float) ($summary->totalValue ?? 0),
            'depreciatedAssetValue' => (float) ($summary->depreciatedAssetValue ?? 0),
            'depreciableAssetCount' => (int) ($summary->depreciableAssetCount ?? 0),

            /* CHART DATA */
            'assetsByStatus' => $assetsByStatus,
            'assetsByYear' => $assetsByYear,
            'assetsByLocation' => $assetsByLocation,
            'assetsByName' => $assetsByName,

            /* YEAR SUMMARY CARDS */
            'assetYearSummary' => $assetYearSummary,

            /* ALERTS / SECURITY / WORKFLOW */
            'expiringSoon' => (int) $expiringSoon,
            'securityStats' => $securityStats,

            /* AI RESEARCH (null when the user has no AI permission) */
            'aiResearchPending' => $this->aiResearchPending(),
        ]);
    }

    private function aiResearchPending(): ?int
    {
        $user = auth()->user();

        if (! $user || (! $user->can('assets.research') && ! $user->can('assets.review-suggestions'))) {
            return null;
        }

        return AssetResearchController::pendingReviewCount($user);
    }
}
