<?php

namespace App\Http\Controllers;

use App\Support\PdfWatermark;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Carbon\Carbon;


class ReportController extends Controller
{
    /* =========================================================
       STATUS-BASED REPORTS
    ========================================================= */

    public function obsolete(Request $request)
    {
        $perPage = $request->integer('per_page', 100000);

        $assets = \App\Models\Asset::query()
            ->with([
                'assetName:id,assetname as name',
                'unit:id,name,locationname',
            ])
            ->where('status', 'obsolete')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->search);

                $query->where(function ($q) use ($search) {
                    $q->where('asset_code', 'like', "%{$search}%")
                        ->orWhereHas(
                            'assetName',
                            fn($qq) => $qq->where('assetname', 'like', "%{$search}%")
                        )
                        ->orWhereHas(
                            'unit',
                            fn($qq) => $qq->where('name', 'like', "%{$search}%")
                        );
                });
            })
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        return \Inertia\Inertia::render('Reports/Obsolete', [
            'assets' => $assets,
            'filters' => $request->only('search', 'per_page'),
        ]);
    }

    public function obsoletePdf(Request $request)
    {
        $query = Asset::with(['assetName', 'unit'])
            ->where('status', 'obsolete');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                    ->orWhereHas(
                        'assetName',
                        fn($qq) => $qq->where('assetname', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'unit',
                        fn($qq) => $qq->where('name', 'like', "%{$search}%")
                    );
            });
        }

        $assets = $query->latest('updated_at')->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.obsolete', [
            'assets' => $assets,
            'currentuser' => Auth::user()->name ?? 'System',
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf);

        return $pdf->stream('obsolete-assets-report.pdf');
    }

    public function disposed(Request $request)
    {
        $perPage = $request->integer('per_page', 100000);

        $assets = \App\Models\Asset::query()
            ->with([
                'assetName:id,assetname as name',
                'unit:id,name,locationname',
            ])
            ->where('status', 'disposed')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->search);

                $query->where(function ($q) use ($search) {
                    $q->where('asset_code', 'like', "%{$search}%")
                        ->orWhereHas(
                            'assetName',
                            fn($qq) => $qq->where('assetname', 'like', "%{$search}%")
                        )
                        ->orWhereHas(
                            'unit',
                            fn($qq) => $qq->where('name', 'like', "%{$search}%")
                        );
                });
            })
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        return \Inertia\Inertia::render('Reports/Disposed', [
            'assets' => $assets,
            'filters' => $request->only('search', 'per_page'),
        ]);
    }

    public function disposedPdf(Request $request)
    {
        $query = Asset::with(['assetName', 'unit'])
            ->where('status', 'disposed');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                    ->orWhereHas(
                        'assetName',
                        fn($qq) => $qq->where('assetname', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'unit',
                        fn($qq) => $qq->where('name', 'like', "%{$search}%")
                    );
            });
        }

        $assets = $query->latest('updated_at')->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.disposed', [
            'assets' => $assets,
            'currentuser' => Auth::user()->name ?? 'System',
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf);

        return $pdf->stream('disposed-assets-report.pdf');
    }

    public function active(Request $request)
    {
        $perPage = $request->integer('per_page', 100000);

        $assets = \App\Models\Asset::query()
            ->with([
                'assetName:id,assetname as name',
                'unit:id,name,locationname',
            ])
            ->where('status', 'active')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->search);

                $query->where(function ($q) use ($search) {
                    $q->where('asset_code', 'like', "%{$search}%")
                        ->orWhereHas(
                            'assetName',
                            fn($qq) => $qq->where('assetname', 'like', "%{$search}%")
                        )
                        ->orWhereHas(
                            'unit',
                            fn($qq) => $qq->where('name', 'like', "%{$search}%")
                        );
                });
            })
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        return \Inertia\Inertia::render('Reports/Active', [
            'assets' => $assets,
            'filters' => $request->only('search', 'per_page'),
        ]);
    }

    public function activePdf(Request $request)
    {
        $query = Asset::with(['assetName', 'unit'])
            ->where('status', 'active');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                    ->orWhereHas(
                        'assetName',
                        fn($qq) => $qq->where('assetname', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'unit',
                        fn($qq) => $qq->where('name', 'like', "%{$search}%")
                    );
            });
        }

        $assets = $query->latest('updated_at')->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reports.active', [
            'assets' => $assets,
            'currentuser' => Auth::user()->name ?? 'System',
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf);

        return $pdf->stream('active-assets-report.pdf');
    }




        /**
     * Show only assets with a scheduled OPEN maintenance job.
     *
     * Completed maintenance jobs are excluded automatically.
     */
    public function underMaintenance(Request $request)
    {
        $perPage = max(10, min($request->integer('per_page', 100), 500));

        $assets = Asset::query()
            ->with([
                'assetName:id,assetname as name',
                'unit:id,name,locationname',
                'supplier:id,suppliername',
                'maintenanceProvider:id,providername',
            ])
            ->whereNotIn('status', ['obsolete', 'disposed'])
            ->whereHas('maintenanceRequests', function ($maintenance): void {
                $maintenance
                    ->whereNotNull('scheduled_date')
                    ->whereIn('status', ['pending', 'approved', 'in_progress']);
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->search);

                $query->where(function ($q) use ($search): void {
                    $q->where('asset_code', 'like', "%{$search}%")
                        ->orWhereHas(
                            'assetName',
                            fn ($qq) => $qq->where('assetname', 'like', "%{$search}%")
                        )
                        ->orWhereHas(
                            'unit',
                            fn ($qq) => $qq->where('name', 'like', "%{$search}%")
                        );
                });
            })
            ->orderByDesc(
                MaintenanceRequest::query()
                    ->select('scheduled_date')
                    ->whereColumn('maintenance_requests.asset_id', 'assets.id')
                    ->whereNotNull('scheduled_date')
                    ->whereIn('status', ['pending', 'approved', 'in_progress'])
                    ->latest('scheduled_date')
                    ->limit(1)
            )
            ->paginate($perPage)
            ->withQueryString();

        return \Inertia\Inertia::render('Reports/UnderMaintenance', [
            'assets' => $assets,
            'filters' => $request->only('search', 'per_page'),
        ]);
    }

        /**
     * PDF report using the same scheduled/open maintenance rule.
     */
    public function underMaintenancePdf(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $query = Asset::query()
            ->with([
                'assetName:id,assetname',
                'unit:id,name,locationname',
                'supplier:id,suppliername',
                'maintenanceProvider:id,providername',
            ])
            ->whereNotIn('status', ['obsolete', 'disposed'])
            ->whereHas('maintenanceRequests', function ($maintenance): void {
                $maintenance
                    ->whereNotNull('scheduled_date')
                    ->whereIn('status', ['pending', 'approved', 'in_progress']);
            });

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search): void {
                $q->where('asset_code', 'like', "%{$search}%")
                    ->orWhereHas(
                        'assetName',
                        fn ($qq) => $qq->where('assetname', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'unit',
                        fn ($qq) => $qq->where('name', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'supplier',
                        fn ($qq) => $qq->where('suppliername', 'like', "%{$search}%")
                    );
            });
        }

        $assets = $query
            ->orderByDesc(
                MaintenanceRequest::query()
                    ->select('scheduled_date')
                    ->whereColumn('maintenance_requests.asset_id', 'assets.id')
                    ->whereNotNull('scheduled_date')
                    ->whereIn('status', ['pending', 'approved', 'in_progress'])
                    ->latest('scheduled_date')
                    ->limit(1)
            )
            ->get();

        $pdf = Pdf::loadView(
            'pdf.reports.undermaintenance',
            [
                'assets' => $assets,
                'currentuser' => Auth::user()->name ?? 'System',
                'generatedAt' => now(),
            ]
        )->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf, true, 140);

        return $pdf->stream('under-maintenance-assets-report.pdf');
    }

    public function inventoryOffice(Request $request)
    {
        $perPage = $request->integer('per_page', 100000);

        $units = \App\Models\Unit::select('id', 'name', 'locationname')
            ->orderBy('locationname')
            ->get();

        $assets = \App\Models\Asset::query()
            ->with([
                'assetName:id,assetname as name',
                'unit:id,name,locationname',
            ])
            ->where('status', 'active')

            /* FILTER BY UNIT */
            ->when($request->filled('unit_id'), function ($query) use ($request) {
                $query->where('unit_id', $request->unit_id);
            })

            /* FILTER BY LOCATION NAME */
            ->when($request->filled('locationname'), function ($query) use ($request) {
                $query->whereHas('unit', function ($q) use ($request) {
                    $q->where('locationname', $request->locationname);
                });
            })

            /* SEARCH */
            ->when($request->filled('search'), function ($query) use ($request) {

                $search = trim($request->search);

                $query->where(function ($q) use ($search) {

                    $q->where('asset_code', 'like', "%{$search}%")

                        ->orWhereHas('assetName', fn($qq) =>
                        $qq->where('assetname', 'like', "%{$search}%"))

                        ->orWhereHas('unit', fn($qq) =>
                        $qq->where('name', 'like', "%{$search}%"))

                        ->orWhereHas('unit', fn($qq) =>
                        $qq->where('locationname', 'like', "%{$search}%"));
                });
            })

            ->orderBy('asset_code', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return \Inertia\Inertia::render('Reports/InventoryOffice', [
            'assets' => $assets,
            'units' => $units,
            'filters' => $request->only(
                'search',
                'unit_id',
                'locationname',
                'per_page'
            ),
        ]);
    }
    public function inventoryOfficePdf(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $query = Asset::with([
            'assetName:id,assetname',
            'unit:id,name,locationname'
        ])
            ->select(
                'id',
                'asset_code',
                'assetname_id',
                'unit_id',
                'purchase_cost',
                'warranty_end_date',
                'purchase_date'
            )
            ->where('status', 'active');

        /* FILTER BY UNIT */
        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        /* FILTER BY LOCATION */
        if ($request->filled('locationname')) {
            $query->whereHas('unit', function ($q) use ($request) {
                $q->where('locationname', $request->locationname);
            });
        }

        /* SEARCH */
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                    ->orWhereHas(
                        'assetName',
                        fn($qq) => $qq->where('assetname', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'unit',
                        fn($qq) => $qq->where('name', 'like', "%{$search}%")
                    )
                    ->orWhereHas(
                        'unit',
                        fn($qq) => $qq->where('locationname', 'like', "%{$search}%")
                    );
            });
        }

        /* LOAD DATA (No chunking needed for 2000 records) */
        $assets = $query
            ->orderBy('asset_code', 'asc')
            ->get();

        $locationname = $request->locationname ?? null;

        /* TOTALS */
        $grandTotal = [
            'total_assets' => $assets->count(),
            'total_value'  => $assets->sum(fn($a) => $a->purchase_cost ?? 0),
        ];

        /* CREATE PDF */
        $pdf = Pdf::loadView(
            'pdf.reports.inventory-office',
            [
                'assets'       => $assets,
                'locationname' => $locationname,
                'grandTotal'   => $grandTotal,
                'currentuser'  => Auth::user()->name ?? 'System',
                'generatedAt'  => now(),
            ]
        )->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf, true, 140);

        return $pdf->stream('inventory-office-report.pdf');
    }


    public function yearlyPurchased(Request $request)
    {
        $perPage = min($request->integer('per_page', 100000), 100000);

        /* =====================================
       UNITS
    ===================================== */
        $units = \App\Models\Unit::select(
            'id',
            'name',
            'locationname'
        )
            ->orderBy('name')
            ->get();


        /* =====================================
       SUPPLIERS
    ===================================== */
        $suppliers = \App\Models\Supplier::select(
            'id',
            'suppliername'
        )
            ->orderBy('suppliername')
            ->get();


        /* =====================================
       DISTINCT YEARS
    ===================================== */
        $years = \App\Models\Asset::selectRaw(
            'YEAR(purchase_date) as year'
        )
            ->whereNotNull('purchase_date')
            ->where('status', 'active')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');


        /* =====================================
       BASE FILTER QUERY
    ===================================== */
        $baseQuery = \App\Models\Asset::query()
            ->where('status', 'active')

            ->when(
                $request->filled('unit_id'),
                fn($q) => $q->where('unit_id', $request->unit_id)
            )

            ->when(
                $request->filled('supplier_id'),
                fn($q) => $q->where('supplier_id', $request->supplier_id)
            )

            ->when(
                $request->filled('year'),
                fn($q) => $q->whereYear('purchase_date', $request->year)
            )

            ->when(
                $request->filled('search'),
                function ($query) use ($request) {

                    $search = trim($request->search);

                    $query->where(function ($q) use ($search) {

                        $q->where(
                            'asset_code',
                            'like',
                            "%{$search}%"
                        )

                            ->orWhereHas(
                                'assetName',
                                fn($qq) =>
                                $qq->where(
                                    'assetname',
                                    'like',
                                    "%{$search}%"
                                )
                            )

                            ->orWhereHas(
                                'unit',
                                fn($qq) =>
                                $qq->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                            )

                            ->orWhereHas(
                                'supplier',
                                fn($qq) =>
                                $qq->where(
                                    'suppliername',
                                    'like',
                                    "%{$search}%"
                                )
                            );
                    });
                }
            );


        /* =====================================
       PAGINATED TABLE DATA
    ===================================== */
        $assets = (clone $baseQuery)
            ->with([
                'assetName:id,assetname as name',
                'unit:id,name,locationname',
                'supplier:id,suppliername',
            ])
            ->orderByDesc('purchase_date')
            ->paginate($perPage)
            ->withQueryString();


        /* =====================================
       YEAR SUMMARY
    ===================================== */
        $yearSummary = (clone $baseQuery)

            ->selectRaw('
            YEAR(purchase_date) as year,
            COUNT(*) as total_assets,
            SUM(purchase_cost) as total_value
        ')

            ->whereNotNull('purchase_date')

            ->groupByRaw('YEAR(purchase_date)')

            ->orderByDesc('year')

            ->get();


        /* =====================================
       GRAND TOTAL
    ===================================== */
        $grandTotal = [

            'total_assets' => (clone $baseQuery)->count(),

            'total_value' => (clone $baseQuery)->sum('purchase_cost'),

        ];


        /* =====================================
       RETURN
    ===================================== */
        return \Inertia\Inertia::render(
            'Reports/YearlyPurchased',
            [

                'assets' => $assets,

                'units' => $units,

                'suppliers' => $suppliers,

                'years' => $years,

                'yearSummary' => $yearSummary,

                'grandTotal' => $grandTotal,

                'filters' => $request->only(
                    'search',
                    'unit_id',
                    'supplier_id',
                    'year',
                    'per_page'
                ),

            ]
        );
    }

    public function yearlyPurchasedPdf(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $baseQuery = Asset::query()
            ->with([
                'assetName:id,assetname',
                'unit:id,name',
                'supplier:id,suppliername'
            ])
            ->where('status', 'active')

            ->when(
                $request->filled('year'),
                fn($q) => $q->whereYear('purchase_date', $request->year)
            );

        $assets = $baseQuery
            ->orderByDesc('purchase_date')
            ->get();

        $grandTotal = [
            'total_assets' => $assets->count(),
            'total_value' => $assets->sum('purchase_cost')
        ];

        $pdf = Pdf::loadView(
            'pdf.reports.yearly-purchased',
            [
                'assets' => $assets,
                'grandTotal' => $grandTotal,
                'year' => $request->year ?? 'All Years',
                'currentuser' => Auth::user()->name ?? 'System',
                'generatedAt' => now()
            ]
        )
            ->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf);

        return $pdf->stream('YearlyPurchasedAssets.pdf');
    }

    public function assetcountByYear(Request $request)
    {
        $data = Asset::selectRaw('
            YEAR(purchase_date) as year,
            COUNT(*) as total_assets,
            SUM(purchase_cost) as total_value
        ')
            ->whereIn('status', [
                'active',
                'obsolete',
                'disposed',
                'under_maintenance'
            ])
            ->whereNotNull('purchase_date')
            ->groupByRaw('YEAR(purchase_date)')
            ->orderByDesc('year')
            ->get();

        $grandTotal = [
            'total_assets' => $data->sum('total_assets'),
            'total_value'  => $data->sum('total_value'),
        ];

        return \Inertia\Inertia::render(
            'Reports/AssetCountByYear',
            [
                'data' => $data,
                'grandTotal' => $grandTotal,
            ]
        );
    }


    public function assetcountByYearPdf()
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        /* GET DATA */
        $data = Asset::selectRaw("
        YEAR(purchase_date) as year,
        COUNT(*) as total_assets,
        COALESCE(SUM(purchase_cost),0) as total_value
    ")
            ->whereIn('status', [
                'active',
                'obsolete',
                'disposed',
                'under_maintenance'
            ])
            ->whereNotNull('purchase_date')
            ->groupByRaw('YEAR(purchase_date)')
            ->orderByDesc('year')
            ->get();


        /* TOTAL */
        $grandTotal = [
            'total_assets' => $data->sum('total_assets'),
            'total_value'  => $data->sum('total_value'),
        ];


        /* CREATE PDF */
        $pdf = Pdf::loadView(
            'pdf.reports.assetcount-by-year', // match your exact filename
            [
                'data'        => $data,
                'grandTotal'  => $grandTotal,
                'generatedAt' => now(),                 // ✅ REQUIRED
                'currentuser' => Auth::user()->name ?? 'System', // ✅ REQUIRED
            ]
        )->setPaper('a4', 'landscape');


        PdfWatermark::apply($pdf, true, 120);

        return $pdf->stream('asset-count-by-year-report.pdf');
    }


    /* =========================================================
       MAINTENANCE COST SUMMARY REPORT
    ========================================================= */

    public function maintenanceCost(Request $request)
    {
        $perPage = min(max($request->integer('per_page', 50), 10), 500);

        $emptyPaginator = [
            'data' => [],
            'links' => [],
            'from' => 0,
            'to' => 0,
            'total' => 0,
        ];

        $currentYear = now()->year;
        $previousYear = $currentYear - 1;

        $emptySummary = [
            'total_maintenance_cost' => 0,
            'actual_maintenance_cost' => 0,
            'this_year_maintenance_cost' => 0,
            'last_year_maintenance_cost' => 0,
            'this_year_maintenance_count' => 0,
            'last_year_maintenance_count' => 0,
            'this_year' => $currentYear,
            'last_year' => $previousYear,
            'total_requests' => 0,
            'completed_requests' => 0,
            'pending_requests' => 0,
            'approved_requests' => 0,
            'rejected_requests' => 0,
        ];

        $filters = $request->only(
            'search',
            'priority',
            'asset_id',
            'unit_id',
            'provider_id',
            'date_from',
            'date_to',
            'per_page'
        );

        // This report is a completed-job cost history, not an open-job monitor.
        $filters['status'] = 'completed';

        if (! Schema::hasTable('maintenance_requests')) {
            return Inertia::render('Reports/MaintenanceCost', [
                'summary' => $emptySummary,
                'monthlyCosts' => [],
                'recentRequests' => $emptyPaginator,
                'assets' => [],
                'units' => [],
                'providers' => [],
                'filters' => $filters,
            ]);
        }

        $hasDeletedAt = Schema::hasColumn('maintenance_requests', 'deleted_at');

        /*
         * Annual maintenance totals are deliberately calculated across all
         * completed maintenance jobs, independent of the page's search/date
         * filters. This gives management a stable year-on-year cost indicator.
         */
        $annualCompletedQuery = DB::table('maintenance_requests')
            ->when($hasDeletedAt, function ($query): void {
                $query->whereNull('maintenance_requests.deleted_at');
            })
            ->where('maintenance_requests.status', 'completed');

        $thisYearMaintenanceCost = (float) (clone $annualCompletedQuery)
            ->whereRaw(
                'YEAR(COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at))) = ?',
                [$currentYear]
            )
            ->sum(DB::raw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0)'));

        $lastYearMaintenanceCost = (float) (clone $annualCompletedQuery)
            ->whereRaw(
                'YEAR(COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at))) = ?',
                [$previousYear]
            )
            ->sum(DB::raw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0)'));

        $thisYearMaintenanceCount = (clone $annualCompletedQuery)
            ->whereRaw(
                'YEAR(COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at))) = ?',
                [$currentYear]
            )
            ->count();

        $lastYearMaintenanceCount = (clone $annualCompletedQuery)
            ->whereRaw(
                'YEAR(COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at))) = ?',
                [$previousYear]
            )
            ->count();

        $baseQuery = DB::table('maintenance_requests')
            ->leftJoin('assets', 'maintenance_requests.asset_id', '=', 'assets.id')
            ->leftJoin('asset_names', 'assets.assetname_id', '=', 'asset_names.id')
            ->leftJoin('units', 'assets.unit_id', '=', 'units.id')
            ->leftJoin('maintenance_providers', 'maintenance_requests.maintenance_provider_id', '=', 'maintenance_providers.id')
            ->leftJoin('users as requester', 'maintenance_requests.requested_by', '=', 'requester.id')
            ->leftJoin('users as approver', 'maintenance_requests.approved_by', '=', 'approver.id')
            ->leftJoin('users as completer', 'maintenance_requests.completed_by', '=', 'completer.id')
            ->when($hasDeletedAt, function ($query): void {
                $query->whereNull('maintenance_requests.deleted_at');
            })
            ->where('maintenance_requests.status', 'completed')
            ->when($request->filled('priority'), function ($query) use ($request): void {
                $query->where('maintenance_requests.priority', $request->priority);
            })
            ->when($request->filled('asset_id'), function ($query) use ($request): void {
                $query->where('maintenance_requests.asset_id', $request->asset_id);
            })
            ->when($request->filled('unit_id'), function ($query) use ($request): void {
                $query->where('assets.unit_id', $request->unit_id);
            })
            ->when($request->filled('provider_id'), function ($query) use ($request): void {
                $query->where('maintenance_requests.maintenance_provider_id', $request->provider_id);
            })
            ->when($request->filled('date_from'), function ($query) use ($request): void {
                $query->whereDate('maintenance_requests.actual_completion_date', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($query) use ($request): void {
                $query->whereDate('maintenance_requests.actual_completion_date', '<=', $request->date_to);
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->search);

                $query->where(function ($q) use ($search): void {
                    $q->where('maintenance_requests.fault_description', 'like', "%{$search}%")
                        ->orWhere('maintenance_requests.completion_note', 'like', "%{$search}%")
                        ->orWhere('maintenance_requests.status', 'like', "%{$search}%")
                        ->orWhere('maintenance_requests.priority', 'like', "%{$search}%")
                        ->orWhere('assets.asset_code', 'like', "%{$search}%")
                        ->orWhere('asset_names.assetname', 'like', "%{$search}%")
                        ->orWhere('units.name', 'like', "%{$search}%")
                        ->orWhere('units.locationname', 'like', "%{$search}%")
                        ->orWhere('maintenance_providers.providername', 'like', "%{$search}%")
                        ->orWhere('requester.name', 'like', "%{$search}%")
                        ->orWhere('approver.name', 'like', "%{$search}%")
                        ->orWhere('completer.name', 'like', "%{$search}%");
                });
            });

        $summaryQuery = clone $baseQuery;

        $completedCount = (clone $summaryQuery)->count();

        $summary = [
            'total_maintenance_cost' => (float) (clone $summaryQuery)->sum(DB::raw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0)')),
            'actual_maintenance_cost' => (float) (clone $summaryQuery)->sum(DB::raw('COALESCE(maintenance_requests.actual_cost, 0)')),
            'this_year_maintenance_cost' => $thisYearMaintenanceCost,
            'last_year_maintenance_cost' => $lastYearMaintenanceCost,
            'this_year_maintenance_count' => $thisYearMaintenanceCount,
            'last_year_maintenance_count' => $lastYearMaintenanceCount,
            'this_year' => $currentYear,
            'last_year' => $previousYear,
            'total_requests' => $completedCount,
            'completed_requests' => $completedCount,
            'pending_requests' => 0,
            'approved_requests' => 0,
            'rejected_requests' => 0,
        ];

        $monthlyCosts = (clone $baseQuery)
            ->selectRaw("DATE_FORMAT(COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at)), '%Y-%m') as month")
            ->selectRaw('SUM(COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0)) as total')
            ->groupByRaw("DATE_FORMAT(COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at)), '%Y-%m')")
            ->orderBy('month')
            ->get();

        $recentRequests = (clone $baseQuery)
            ->select([
                'maintenance_requests.id',
                'maintenance_requests.asset_id',
                'maintenance_requests.status',
                'maintenance_requests.priority',
                'maintenance_requests.fault_description',
                'maintenance_requests.estimated_cost',
                'maintenance_requests.actual_cost',
                'maintenance_requests.scheduled_date',
                'maintenance_requests.expected_completion_date',
                'maintenance_requests.actual_completion_date',
                'maintenance_requests.completion_note',
                'maintenance_requests.created_at',
                'maintenance_requests.updated_at',
                'assets.asset_code',
                'asset_names.assetname as asset_name',
                'units.name as unit_name',
                'units.locationname',
                'maintenance_providers.providername as provider_name',
                'requester.name as requested_by_name',
                'approver.name as approved_by_name',
                'completer.name as completed_by_name',
            ])
            ->selectRaw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0) as cost')
            ->selectRaw('maintenance_requests.fault_description as description')
            ->selectRaw('maintenance_requests.priority as maintenance_type')
            ->orderByRaw('COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at)) DESC')
            ->orderByDesc('maintenance_requests.id')
            ->paginate($perPage)
            ->withQueryString();

        $assets = Schema::hasTable('assets')
            ? DB::table('assets')
                ->leftJoin('asset_names', 'assets.assetname_id', '=', 'asset_names.id')
                ->select([
                    'assets.id',
                    DB::raw('TRIM(CONCAT(COALESCE(assets.asset_code, ""), " - ", COALESCE(asset_names.assetname, "Asset"))) as name'),
                ])
                ->orderBy('name')
                ->get()
            : collect();

        $units = Schema::hasTable('units')
            ? DB::table('units')
                ->select(['id', 'name', 'locationname'])
                ->orderBy('name')
                ->get()
            : collect();

        $providers = Schema::hasTable('maintenance_providers')
            ? DB::table('maintenance_providers')
                ->select(['id', 'providername as name'])
                ->orderBy('providername')
                ->get()
            : collect();

        return Inertia::render('Reports/MaintenanceCost', [
            'summary' => $summary,
            'monthlyCosts' => $monthlyCosts,
            'recentRequests' => $recentRequests,
            'assets' => $assets,
            'units' => $units,
            'providers' => $providers,
            'filters' => $filters,
        ]);
    }

    public function maintenanceCostPdf(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        if (! Schema::hasTable('maintenance_requests')) {
            abort(404, 'Maintenance requests table was not found.');
        }

        $hasDeletedAt = Schema::hasColumn('maintenance_requests', 'deleted_at');

        /*
         * PDF output follows the same completed-job rule as the screen.
         * Open or in-progress jobs never appear in Maintenance Cost History.
         */
        $queryBuilder = DB::table('maintenance_requests')
            ->leftJoin('assets', 'maintenance_requests.asset_id', '=', 'assets.id')
            ->leftJoin('asset_names', 'assets.assetname_id', '=', 'asset_names.id')
            ->leftJoin('units', 'assets.unit_id', '=', 'units.id')
            ->leftJoin('maintenance_providers', 'maintenance_requests.maintenance_provider_id', '=', 'maintenance_providers.id')
            ->leftJoin('users as requester', 'maintenance_requests.requested_by', '=', 'requester.id')
            ->when($hasDeletedAt, function ($query): void {
                $query->whereNull('maintenance_requests.deleted_at');
            })
            ->where('maintenance_requests.status', 'completed')
            ->when($request->filled('priority'), function ($query) use ($request): void {
                $query->where('maintenance_requests.priority', $request->priority);
            })
            ->when($request->filled('asset_id'), function ($query) use ($request): void {
                $query->where('maintenance_requests.asset_id', $request->asset_id);
            })
            ->when($request->filled('unit_id'), function ($query) use ($request): void {
                $query->where('assets.unit_id', $request->unit_id);
            })
            ->when($request->filled('provider_id'), function ($query) use ($request): void {
                $query->where('maintenance_requests.maintenance_provider_id', $request->provider_id);
            })
            ->when($request->filled('date_from'), function ($query) use ($request): void {
                $query->whereDate(
                    DB::raw('COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at))'),
                    '>=',
                    $request->date_from
                );
            })
            ->when($request->filled('date_to'), function ($query) use ($request): void {
                $query->whereDate(
                    DB::raw('COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at))'),
                    '<=',
                    $request->date_to
                );
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->search);

                $query->where(function ($q) use ($search): void {
                    $q->where('maintenance_requests.fault_description', 'like', "%{$search}%")
                        ->orWhere('maintenance_requests.completion_note', 'like', "%{$search}%")
                        ->orWhere('maintenance_requests.priority', 'like', "%{$search}%")
                        ->orWhere('assets.asset_code', 'like', "%{$search}%")
                        ->orWhere('asset_names.assetname', 'like', "%{$search}%")
                        ->orWhere('units.name', 'like', "%{$search}%")
                        ->orWhere('units.locationname', 'like', "%{$search}%")
                        ->orWhere('maintenance_providers.providername', 'like', "%{$search}%")
                        ->orWhere('requester.name', 'like', "%{$search}%");
                });
            });

        $requests = (clone $queryBuilder)
            ->select([
                'maintenance_requests.id',
                'maintenance_requests.status',
                'maintenance_requests.priority',
                'maintenance_requests.fault_description',
                'maintenance_requests.actual_cost',
                'maintenance_requests.actual_completion_date',
                'maintenance_requests.created_at',
                'assets.asset_code',
                'asset_names.assetname as asset_name',
                'units.name as unit_name',
                'units.locationname',
                'maintenance_providers.providername as provider_name',
                'requester.name as requested_by_name',
            ])
            ->selectRaw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0) as cost')
            ->orderByRaw('COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at)) DESC')
            ->orderByDesc('maintenance_requests.id')
            ->get();

        $currentYear = now()->year;
        $previousYear = $currentYear - 1;

        $annualCompletedQuery = DB::table('maintenance_requests')
            ->when($hasDeletedAt, function ($query): void {
                $query->whereNull('maintenance_requests.deleted_at');
            })
            ->where('maintenance_requests.status', 'completed');

        $thisYearCost = (float) (clone $annualCompletedQuery)
            ->whereRaw(
                'YEAR(COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at))) = ?',
                [$currentYear]
            )
            ->sum(DB::raw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0)'));

        $lastYearCost = (float) (clone $annualCompletedQuery)
            ->whereRaw(
                'YEAR(COALESCE(maintenance_requests.actual_completion_date, DATE(maintenance_requests.created_at))) = ?',
                [$previousYear]
            )
            ->sum(DB::raw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0)'));

        $summary = [
            'total_requests' => $requests->count(),
            'total_maintenance_cost' => (float) $requests->sum('cost'),
            'actual_maintenance_cost' => (float) $requests->sum('actual_cost'),
            'this_year' => $currentYear,
            'last_year' => $previousYear,
            'this_year_maintenance_cost' => $thisYearCost,
            'last_year_maintenance_cost' => $lastYearCost,
        ];

        $pdf = Pdf::loadView('pdf.reports.maintenance-cost', [
            'requests' => $requests,
            'summary' => $summary,
            'filters' => $request->only(
                'search',
                'priority',
                'asset_id',
                'unit_id',
                'provider_id',
                'date_from',
                'date_to'
            ),
            'currentuser' => Auth::user()->name ?? 'System',
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf);

        return $pdf->stream('completed-maintenance-cost-history-report.pdf');
    }


public function depreciation()
{
    $assets = Asset::with(['assetName','unit'])
        ->whereNotNull('purchase_date')
        ->whereNotNull('purchase_cost')
        ->get();

    $depreciations = $assets->map(function ($asset) {

        $purchaseCost = $asset->purchase_cost ?? 0;

        // Residual value (10%)
        $residualValue = $purchaseCost * 0.10;

        // Useful life from asset or asset_names
        $usefulLife =
            $asset->useful_life
            ?? $asset->assetName->usefullife
            ?? 5;

        // Annual depreciation
        $annualDepreciation =
            ($purchaseCost - $residualValue) / $usefulLife;

        // Asset age limited to useful life
        $assetAge = now()->diffInYears($asset->purchase_date);

        $yearsUsed = min($assetAge, $usefulLife);

        // Accumulated depreciation
        $accumulatedDepreciation =
            $annualDepreciation * $yearsUsed;

        // Book value
        $bookValue =
            $purchaseCost - $accumulatedDepreciation;

        if ($bookValue < $residualValue) {
            $bookValue = $residualValue;
        }

        // End of useful life date
        $endOfUseDate = null;

        if ($asset->purchase_date) {
            $endOfUseDate = Carbon::parse($asset->purchase_date)
                ->addYears($usefulLife)
                ->toDateString();
        }

        return [

            'id' => $asset->id,

            'asset_code' => $asset->asset_code,

            'asset' => $asset->assetName->assetname ?? '',

            'location' => $asset->unit->locationname ?? '',

            'purchase_date' => $asset->purchase_date,

            'warranty_end_date' => $asset->warranty_end_date ?? null,

            'warranty_duration' => $asset->warranty_duration ?? null,

            'useful_life' => $usefulLife,

            'end_of_use_date' => $endOfUseDate,

            'total_maintenance_cost' => $asset->total_maintenance_cost ?? 0,

            'purchase_cost' => round($purchaseCost,2),

            'residual_value' => round($residualValue,2),

            'annual_depreciation' => round($annualDepreciation,2),

            'accumulated_depreciation' => round($accumulatedDepreciation,2),

            'book_value' => round($bookValue,2)

        ];
    });

    return Inertia::render('Reports/Depreciation',[
        'assets'=>$depreciations
    ]);
}






}
