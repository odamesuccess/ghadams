<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\MaintenanceProvider;
use App\Models\MaintenanceRequest;
use App\Models\Supplier;
use App\Models\Unit;
use App\Support\PdfWatermark;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ManagementReportController extends Controller
{
    /**
     * Monthly / quarterly / yearly report on newly purchased assets.
     */
    public function assetPurchases(Request $request)
    {
        [$start, $end, $period, $year, $month, $quarter, $periodLabel] =
            $this->resolvePeriod($request);

        $query = $this->assetPurchaseQuery($request, $start, $end);

        $summary = [
            'total_assets' => (clone $query)->count(),
            'total_cost' => (float) (clone $query)->sum('purchase_cost'),
            'average_cost' => (float) (clone $query)->avg('purchase_cost'),
            'units' => (clone $query)->whereNotNull('unit_id')->distinct('unit_id')->count('unit_id'),
            'suppliers' => (clone $query)->whereNotNull('supplier_id')->distinct('supplier_id')->count('supplier_id'),
        ];

        $monthlyTrend = Asset::query()
            ->whereBetween('purchase_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE_FORMAT(purchase_date, '%Y-%m') as period")
            ->selectRaw('COUNT(*) as total_assets')
            ->selectRaw('COALESCE(SUM(purchase_cost), 0) as total_cost')
            ->groupByRaw("DATE_FORMAT(purchase_date, '%Y-%m')")
            ->orderBy('period')
            ->get();

        $assets = (clone $query)
            ->with([
                'assetName:id,assetname',
                'unit:id,name,locationname',
                'supplier:id,suppliername',
            ])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('ManagementReports/AssetPurchases', [
            'summary' => $summary,
            'assets' => $assets,
            'monthlyTrend' => $monthlyTrend,
            'units' => Unit::query()->select('id', 'name', 'locationname')->orderBy('name')->get(),
            'suppliers' => Supplier::query()->select('id', 'suppliername')->orderBy('suppliername')->get(),
            'years' => $this->availableYears(),
            'filters' => [
                ...$request->only('search', 'unit_id', 'supplier_id', 'per_page'),
                'period' => $period,
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
            ],
            'periodLabel' => $periodLabel,
        ]);
    }

    public function assetPurchasesPdf(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        [$start, $end, $period, $year, $month, $quarter, $periodLabel] =
            $this->resolvePeriod($request);

        $query = $this->assetPurchaseQuery($request, $start, $end);

        $assets = (clone $query)
            ->with([
                'assetName:id,assetname',
                'unit:id,name,locationname',
                'supplier:id,suppliername',
            ])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get();

        $summary = [
            'total_assets' => $assets->count(),
            'total_cost' => (float) $assets->sum('purchase_cost'),
            'average_cost' => $assets->count() > 0
                ? (float) $assets->avg('purchase_cost')
                : 0,
            'units' => $assets->pluck('unit_id')->filter()->unique()->count(),
            'suppliers' => $assets->pluck('supplier_id')->filter()->unique()->count(),
        ];

        $pdf = Pdf::loadView('pdf.management-reports.asset-purchases', [
            'assets' => $assets,
            'summary' => $summary,
            'periodLabel' => $periodLabel,
            'generatedAt' => now(),
            'currentuser' => Auth::user()?->name ?? 'System',
        ])->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf, true, 120);

        return $pdf->stream(
            'ghadams-asset-purchases-'
            . str($periodLabel)->slug('-')
            . '.pdf'
        );
    }

    /**
     * Monthly / quarterly / yearly maintenance management report.
     */
    public function maintenance(Request $request)
    {
        [$start, $end, $period, $year, $month, $quarter, $periodLabel] =
            $this->resolvePeriod($request);

        $query = $this->maintenanceQuery($request, $start, $end);

        $summaryBase = clone $query;

        $summary = [
            'total_jobs' => (clone $summaryBase)->count(),
            'completed_jobs' => (clone $summaryBase)->where('maintenance_requests.status', 'completed')->count(),
            'open_jobs' => (clone $summaryBase)->where('maintenance_requests.status', '<>', 'completed')->count(),
            'completed_cost' => (float) (clone $summaryBase)
                ->where('maintenance_requests.status', 'completed')
                ->sum(DB::raw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0)')),
            'paid_amount' => (float) (clone $summaryBase)
                ->where('maintenance_requests.status', 'completed')
                ->where('maintenance_requests.payment_status', 'paid')
                ->sum(DB::raw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0)')),
            'outstanding_amount' => (float) (clone $summaryBase)
                ->where('maintenance_requests.status', 'completed')
                ->where(function ($q): void {
                    $q->whereNull('maintenance_requests.payment_status')
                        ->orWhere('maintenance_requests.payment_status', '<>', 'paid');
                })
                ->sum(DB::raw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0)')),
        ];

        $monthlyTrend = (clone $query)
            ->selectRaw("DATE_FORMAT(COALESCE(maintenance_requests.actual_completion_date, maintenance_requests.scheduled_date, DATE(maintenance_requests.created_at)), '%Y-%m') as period")
            ->selectRaw('COUNT(*) as total_jobs')
            ->selectRaw("SUM(CASE WHEN maintenance_requests.status = 'completed' THEN 1 ELSE 0 END) as completed_jobs")
            ->selectRaw("SUM(CASE WHEN maintenance_requests.status = 'completed' THEN COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0) ELSE 0 END) as completed_cost")
            ->groupByRaw("DATE_FORMAT(COALESCE(maintenance_requests.actual_completion_date, maintenance_requests.scheduled_date, DATE(maintenance_requests.created_at)), '%Y-%m')")
            ->orderBy('period')
            ->get();

        $jobs = (clone $query)
            ->select([
                'maintenance_requests.id',
                'maintenance_requests.asset_id',
                'maintenance_requests.status',
                'maintenance_requests.priority',
                'maintenance_requests.fault_description',
                'maintenance_requests.estimated_cost',
                'maintenance_requests.actual_cost',
                'maintenance_requests.scheduled_date',
                'maintenance_requests.actual_completion_date',
                'maintenance_requests.payment_status',
                'maintenance_requests.payment_date',
                'maintenance_requests.created_at',
                'assets.asset_code',
                'asset_names.assetname as asset_name',
                'units.name as unit_name',
                'units.locationname',
                'maintenance_providers.providername as provider_name',
            ])
            ->selectRaw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0) as final_cost')
            ->orderByRaw('COALESCE(maintenance_requests.actual_completion_date, maintenance_requests.scheduled_date, DATE(maintenance_requests.created_at)) DESC')
            ->orderByDesc('maintenance_requests.id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('ManagementReports/Maintenance', [
            'summary' => $summary,
            'jobs' => $jobs,
            'monthlyTrend' => $monthlyTrend,
            'units' => Unit::query()->select('id', 'name', 'locationname')->orderBy('name')->get(),
            'providers' => MaintenanceProvider::query()->select('id', 'providername')->orderBy('providername')->get(),
            'years' => $this->availableYears(),
            'filters' => [
                ...$request->only(
                    'search',
                    'unit_id',
                    'provider_id',
                    'status',
                    'priority',
                    'per_page'
                ),
                'period' => $period,
                'year' => $year,
                'month' => $month,
                'quarter' => $quarter,
            ],
            'periodLabel' => $periodLabel,
        ]);
    }

    public function maintenancePdf(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        [$start, $end, $period, $year, $month, $quarter, $periodLabel] =
            $this->resolvePeriod($request);

        $query = $this->maintenanceQuery($request, $start, $end);

        $jobs = (clone $query)
            ->select([
                'maintenance_requests.id',
                'maintenance_requests.status',
                'maintenance_requests.priority',
                'maintenance_requests.fault_description',
                'maintenance_requests.estimated_cost',
                'maintenance_requests.actual_cost',
                'maintenance_requests.scheduled_date',
                'maintenance_requests.actual_completion_date',
                'maintenance_requests.payment_status',
                'maintenance_requests.created_at',
                'assets.asset_code',
                'asset_names.assetname as asset_name',
                'units.name as unit_name',
                'units.locationname',
                'maintenance_providers.providername as provider_name',
            ])
            ->selectRaw('COALESCE(maintenance_requests.actual_cost, maintenance_requests.estimated_cost, 0) as final_cost')
            ->orderByRaw('COALESCE(maintenance_requests.actual_completion_date, maintenance_requests.scheduled_date, DATE(maintenance_requests.created_at)) DESC')
            ->orderByDesc('maintenance_requests.id')
            ->get();

        $summary = [
            'total_jobs' => $jobs->count(),
            'completed_jobs' => $jobs->where('status', 'completed')->count(),
            'open_jobs' => $jobs->where('status', '<>', 'completed')->count(),
            'completed_cost' => (float) $jobs->where('status', 'completed')->sum('final_cost'),
            'paid_amount' => (float) $jobs->where('status', 'completed')->where('payment_status', 'paid')->sum('final_cost'),
            'outstanding_amount' => (float) $jobs->where('status', 'completed')->where('payment_status', '<>', 'paid')->sum('final_cost'),
        ];

        $pdf = Pdf::loadView('pdf.management-reports.maintenance', [
            'jobs' => $jobs,
            'summary' => $summary,
            'periodLabel' => $periodLabel,
            'generatedAt' => now(),
            'currentuser' => Auth::user()?->name ?? 'System',
        ])->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf, true, 120);

        return $pdf->stream(
            'ghadams-maintenance-'
            . str($periodLabel)->slug('-')
            . '.pdf'
        );
    }

    /**
     * Full annual asset register report.
     *
     * Status is the current stored GhanaDAMS status. Book value is calculated
     * as at the end of the selected year (or today when the current year is
     * selected) using the existing GhanaDAMS 10% residual / straight-line rule.
     */
    public function annualAssets(Request $request)
    {
        $year = $this->normaliseYear($request->integer('year', now()->year));
        $valuationDate = CarbonImmutable::create($year, 12, 31, 23, 59, 59, 'Africa/Accra');

        if ($year >= now('Africa/Accra')->year) {
            $valuationDate = CarbonImmutable::now('Africa/Accra');
        }

        $base = $this->annualAssetQuery($request, $valuationDate);

        $allAssets = (clone $base)
            ->with([
                'assetName:id,assetname,usefullife',
                'unit:id,name,locationname',
                'supplier:id,suppliername',
            ])
            ->orderBy('asset_code')
            ->get();

        $calculated = $allAssets->map(
            fn (Asset $asset) => $this->annualAssetRow($asset, $valuationDate)
        );

        $maintenanceCost = (float) MaintenanceRequest::query()
            ->where('status', 'completed')
            ->whereYear(
                DB::raw('COALESCE(actual_completion_date, DATE(created_at))'),
                $year
            )
            ->sum(DB::raw('COALESCE(actual_cost, estimated_cost, 0)'));

        $summary = [
            'total_assets' => $calculated->count(),
            'active' => $calculated->where('status', 'active')->count(),
            'under_maintenance' => $calculated->where('status', 'under_maintenance')->count(),
            'obsolete' => $calculated->where('status', 'obsolete')->count(),
            'disposed' => $calculated->where('status', 'disposed')->count(),
            'acquisition_cost' => (float) $calculated->sum('purchase_cost'),
            'book_value' => (float) $calculated->sum('book_value'),
            'maintenance_cost' => $maintenanceCost,
            'warranty_expiring' => $calculated
                ->filter(function (array $row) use ($year): bool {
                    return $row['warranty_end_date']
                        && Carbon::parse($row['warranty_end_date'])->year === $year;
                })
                ->count(),
        ];

        $page = max(1, $request->integer('page', 1));
        $perPage = $this->perPage($request);
        $total = $calculated->count();
        $slice = $calculated->forPage($page, $perPage)->values();

        $assets = [
            'data' => $slice,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'per_page' => $perPage,
            'total' => $total,
            'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'to' => min($page * $perPage, $total),
        ];

        return Inertia::render('ManagementReports/AnnualAssets', [
            'summary' => $summary,
            'assets' => $assets,
            'units' => Unit::query()->select('id', 'name', 'locationname')->orderBy('name')->get(),
            'years' => $this->availableYears(),
            'filters' => [
                ...$request->only('search', 'unit_id', 'status', 'per_page'),
                'year' => $year,
            ],
            'valuationDate' => $valuationDate->format('d M Y'),
        ]);
    }

    public function annualAssetsPdf(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '768M');

        $year = $this->normaliseYear($request->integer('year', now()->year));
        $valuationDate = CarbonImmutable::create($year, 12, 31, 23, 59, 59, 'Africa/Accra');

        if ($year >= now('Africa/Accra')->year) {
            $valuationDate = CarbonImmutable::now('Africa/Accra');
        }

        $assets = $this->annualAssetQuery($request, $valuationDate)
            ->with([
                'assetName:id,assetname,usefullife',
                'unit:id,name,locationname',
                'supplier:id,suppliername',
            ])
            ->orderBy('asset_code')
            ->get()
            ->map(fn (Asset $asset) => $this->annualAssetRow($asset, $valuationDate));

        $maintenanceCost = (float) MaintenanceRequest::query()
            ->where('status', 'completed')
            ->whereYear(
                DB::raw('COALESCE(actual_completion_date, DATE(created_at))'),
                $year
            )
            ->sum(DB::raw('COALESCE(actual_cost, estimated_cost, 0)'));

        $summary = [
            'total_assets' => $assets->count(),
            'active' => $assets->where('status', 'active')->count(),
            'under_maintenance' => $assets->where('status', 'under_maintenance')->count(),
            'obsolete' => $assets->where('status', 'obsolete')->count(),
            'disposed' => $assets->where('status', 'disposed')->count(),
            'acquisition_cost' => (float) $assets->sum('purchase_cost'),
            'book_value' => (float) $assets->sum('book_value'),
            'maintenance_cost' => $maintenanceCost,
        ];

        $pdf = Pdf::loadView('pdf.management-reports.annual-assets', [
            'assets' => $assets,
            'summary' => $summary,
            'year' => $year,
            'valuationDate' => $valuationDate,
            'generatedAt' => now(),
            'currentuser' => Auth::user()?->name ?? 'System',
        ])->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf, true, 120);

        return $pdf->stream("ghadams-full-asset-report-{$year}.pdf");
    }

    private function assetPurchaseQuery(
        Request $request,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): Builder {
        return Asset::query()
            ->whereNotNull('purchase_date')
            ->whereBetween('purchase_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->when($request->filled('unit_id'), fn (Builder $q) =>
                $q->where('unit_id', $request->integer('unit_id')))
            ->when($request->filled('supplier_id'), fn (Builder $q) =>
                $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->search);

                $query->where(function (Builder $q) use ($search): void {
                    $q->where('asset_code', 'like', "%{$search}%")
                        ->orWhereHas('assetName', fn (Builder $name) =>
                            $name->where('assetname', 'like', "%{$search}%"))
                        ->orWhereHas('unit', fn (Builder $unit) =>
                            $unit->where('name', 'like', "%{$search}%")
                                ->orWhere('locationname', 'like', "%{$search}%"))
                        ->orWhereHas('supplier', fn (Builder $supplier) =>
                            $supplier->where('suppliername', 'like', "%{$search}%"));
                });
            });
    }

    private function maintenanceQuery(
        Request $request,
        CarbonImmutable $start,
        CarbonImmutable $end
    ) {
        $dateExpression =
            'COALESCE(maintenance_requests.actual_completion_date, maintenance_requests.scheduled_date, DATE(maintenance_requests.created_at))';

        return DB::table('maintenance_requests')
            ->leftJoin('assets', 'maintenance_requests.asset_id', '=', 'assets.id')
            ->leftJoin('asset_names', 'assets.assetname_id', '=', 'asset_names.id')
            ->leftJoin('units', 'assets.unit_id', '=', 'units.id')
            ->leftJoin(
                'maintenance_providers',
                'maintenance_requests.maintenance_provider_id',
                '=',
                'maintenance_providers.id'
            )
            ->whereNull('maintenance_requests.deleted_at')
            ->whereBetween(
                DB::raw($dateExpression),
                [$start->toDateString(), $end->toDateString()]
            )
            ->when($request->filled('unit_id'), fn ($q) =>
                $q->where('assets.unit_id', $request->integer('unit_id')))
            ->when($request->filled('provider_id'), fn ($q) =>
                $q->where(
                    'maintenance_requests.maintenance_provider_id',
                    $request->integer('provider_id')
                ))
            ->when($request->filled('status'), fn ($q) =>
                $q->where('maintenance_requests.status', $request->string('status')->toString()))
            ->when($request->filled('priority'), fn ($q) =>
                $q->where('maintenance_requests.priority', $request->string('priority')->toString()))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->search);

                $query->where(function ($q) use ($search): void {
                    $q->where('assets.asset_code', 'like', "%{$search}%")
                        ->orWhere('asset_names.assetname', 'like', "%{$search}%")
                        ->orWhere('units.name', 'like', "%{$search}%")
                        ->orWhere('units.locationname', 'like', "%{$search}%")
                        ->orWhere('maintenance_providers.providername', 'like', "%{$search}%")
                        ->orWhere('maintenance_requests.fault_description', 'like', "%{$search}%");
                });
            });
    }

    private function annualAssetQuery(
        Request $request,
        CarbonImmutable $valuationDate
    ): Builder {
        return Asset::query()
            ->where(function (Builder $query) use ($valuationDate): void {
                $query->whereNull('purchase_date')
                    ->orWhereDate('purchase_date', '<=', $valuationDate->toDateString());
            })
            ->when($request->filled('unit_id'), fn (Builder $q) =>
                $q->where('unit_id', $request->integer('unit_id')))
            ->when($request->filled('status'), fn (Builder $q) =>
                $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim((string) $request->search);

                $query->where(function (Builder $q) use ($search): void {
                    $q->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('assetName', fn (Builder $name) =>
                            $name->where('assetname', 'like', "%{$search}%"))
                        ->orWhereHas('unit', fn (Builder $unit) =>
                            $unit->where('name', 'like', "%{$search}%")
                                ->orWhere('locationname', 'like', "%{$search}%"));
                });
            });
    }

    private function annualAssetRow(
        Asset $asset,
        CarbonImmutable $valuationDate
    ): array {
        $purchaseCost = (float) ($asset->purchase_cost ?? 0);
        $purchaseDate = $asset->purchase_date
            ? CarbonImmutable::parse($asset->purchase_date, 'Africa/Accra')
            : null;

        $usefulLife = max(
            1,
            (int) ($asset->assetName?->usefullife ?: 5)
        );

        $residualValue = $purchaseCost * 0.10;
        $annualDepreciation = $usefulLife > 0
            ? ($purchaseCost - $residualValue) / $usefulLife
            : 0;

        $ageInYears = 0.0;

        if ($purchaseDate && $purchaseDate->lessThanOrEqualTo($valuationDate)) {
            $ageInYears = $purchaseDate->diffInDays($valuationDate) / 365.25;
        }

        $accumulatedDepreciation = min(
            $annualDepreciation * $ageInYears,
            max(0, $purchaseCost - $residualValue)
        );

        $bookValue = max(
            $purchaseCost - $accumulatedDepreciation,
            $purchaseCost > 0 ? $residualValue : 0
        );

        return [
            'id' => $asset->id,
            'asset_code' => $asset->asset_code,
            'asset_name' => $asset->assetName?->assetname ?? 'N/A',
            'serial_number' => $asset->serial_number ?? 'N/A',
            'unit_name' => $asset->unit?->name ?? 'Not assigned',
            'location' => $asset->unit?->locationname ?? 'Not assigned',
            'supplier_name' => $asset->supplier?->suppliername ?? 'N/A',
            'status' => $asset->status ?? 'N/A',
            'purchase_date' => $asset->purchase_date?->format('Y-m-d'),
            'warranty_end_date' => $asset->warranty_end_date?->format('Y-m-d'),
            'purchase_cost' => round($purchaseCost, 2),
            'book_value' => round($bookValue, 2),
            'useful_life' => $usefulLife,
        ];
    }

    private function resolvePeriod(Request $request): array
    {
        $period = in_array(
            $request->string('period')->toString(),
            ['monthly', 'quarterly', 'yearly'],
            true
        )
            ? $request->string('period')->toString()
            : 'monthly';

        $year = $this->normaliseYear(
            $request->integer('year', now('Africa/Accra')->year)
        );

        $month = min(12, max(1, $request->integer('month', now('Africa/Accra')->month)));
        $quarter = min(
            4,
            max(
                1,
                $request->integer(
                    'quarter',
                    (int) ceil(now('Africa/Accra')->month / 3)
                )
            )
        );

        if ($period === 'yearly') {
            $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'Africa/Accra');
            $end = $start->endOfYear();
            $label = "Year {$year}";
        } elseif ($period === 'quarterly') {
            $startMonth = (($quarter - 1) * 3) + 1;
            $start = CarbonImmutable::create($year, $startMonth, 1, 0, 0, 0, 'Africa/Accra');
            $end = $start->addMonths(2)->endOfMonth();
            $label = "Q{$quarter} {$year}";
        } else {
            $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Africa/Accra');
            $end = $start->endOfMonth();
            $label = $start->format('F Y');
        }

        return [$start, $end, $period, $year, $month, $quarter, $label];
    }

    private function normaliseYear(int $year): int
    {
        $minimum = 1980;
        $maximum = now('Africa/Accra')->year + 1;

        return min($maximum, max($minimum, $year));
    }

    private function perPage(Request $request): int
    {
        return min(
            100,
            max(10, $request->integer('per_page', 25))
        );
    }

    private function availableYears(): Collection
    {
        $assetYears = Asset::query()
            ->whereNotNull('purchase_date')
            ->selectRaw('YEAR(purchase_date) as year')
            ->distinct()
            ->pluck('year')
            ->filter()
            ->map(fn ($year) => (int) $year);

        $maintenanceYears = MaintenanceRequest::query()
            ->selectRaw(
                'YEAR(COALESCE(actual_completion_date, scheduled_date, DATE(created_at))) as year'
            )
            ->distinct()
            ->pluck('year')
            ->filter()
            ->map(fn ($year) => (int) $year);

        return $assetYears
            ->merge($maintenanceYears)
            ->push((int) now('Africa/Accra')->year)
            ->unique()
            ->sortDesc()
            ->values();
    }
}
