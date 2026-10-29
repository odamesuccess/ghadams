<?php

namespace App\Http\Controllers;

use App\Support\PdfWatermark;

use App\Models\Asset;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DepreciationReportController extends Controller
{
    /**
     * Statuses that should appear in the depreciation report.
     *
     * Active = asset is currently in use.
     * Under Maintenance = asset is still useful but temporarily under repair.
     *
     * Excluded:
     * Obsolete = no longer useful / not repairable.
     * Disposed = removed from active asset use.
     */
    private array $depreciableStatuses = [
        'active',
        'under maintenance',
    ];

    /**
     * Display the depreciation report page.
     */
    public function index(Request $request): Response
    {
        /*
        |--------------------------------------------------------------------------
        | Per Page
        |--------------------------------------------------------------------------
        | Default is 10 so the table behaves like your Roles table.
        */
        $perPage = $request->integer('per_page', 10);

        if (! in_array($perPage, [5, 10, 20, 50, 100, 250, 500], true)) {
            $perPage = 10;
        }

        $query = Asset::query()
            ->with([
                'assetName:id,assetname,usefullife',
                'unit:id,name,locationname',
            ])
            ->whereNotNull('purchase_date')
            ->whereNotNull('purchase_cost');

        /*
        |--------------------------------------------------------------------------
        | Only Active and Under Maintenance Assets
        |--------------------------------------------------------------------------
        */
        $this->applyDepreciableStatusFilter($query);

        $this->applyFilters($query, $request);

        $assets = $query
            ->latest('purchase_date')
            ->paginate($perPage)
            ->through(fn (Asset $asset) => $this->calculateDepreciation($asset))
            ->withQueryString();

        return Inertia::render('DepreciationReports/Index', [
            'assets' => $assets,

            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'from_date' => $request->input('from_date', ''),
                'to_date' => $request->input('to_date', ''),
                'per_page' => $perPage,
            ],

            'summary' => $this->summary($request),
        ]);
    }

    /**
     * Export the depreciation report as PDF.
     */
    public function pdf(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $query = Asset::query()
            ->with([
                'assetName:id,assetname,usefullife',
                'unit:id,name,locationname',
            ])
            ->whereNotNull('purchase_date')
            ->whereNotNull('purchase_cost');

        /*
        |--------------------------------------------------------------------------
        | Only Active and Under Maintenance Assets
        |--------------------------------------------------------------------------
        */
        $this->applyDepreciableStatusFilter($query);

        $this->applyFilters($query, $request);

        $assets = $query
            ->orderBy('purchase_date', 'desc')
            ->get()
            ->map(fn (Asset $asset) => $this->calculateDepreciation($asset));

        $summary = [
            'total_assets' => $assets->count(),
            'total_purchase_cost' => round($assets->sum('purchase_cost'), 2),
            'total_residual_value' => round($assets->sum('residual_value'), 2),
            'total_annual_depreciation' => round($assets->sum('annual_depreciation'), 2),
            'total_accumulated_depreciation' => round($assets->sum('accumulated_depreciation'), 2),
            'total_book_value' => round($assets->sum('book_value'), 2),
        ];

        $pdf = Pdf::loadView('pdf.reports.depreciation', [
            'assets' => $assets,
            'summary' => $summary,

            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'from_date' => $request->input('from_date', ''),
                'to_date' => $request->input('to_date', ''),
            ],

            'currentuser' => Auth::user()->name ?? 'System',
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf, true, 140);

        return $pdf->stream('asset-depreciation-report.pdf');
    }

    /**
     * Apply the base depreciable status filter.
     *
     * This supports both database formats:
     * - active
     * - Active
     * - under maintenance
     * - Under Maintenance
     * - under_maintenance
     */
    private function applyDepreciableStatusFilter(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereRaw('LOWER(REPLACE(status, "_", " ")) = ?', ['active'])
                ->orWhereRaw('LOWER(REPLACE(status, "_", " ")) = ?', ['under maintenance']);
        });
    }

    /**
     * Apply search and filter conditions.
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('status'), function (Builder $query) use ($request) {
                $status = $this->normalizeStatus((string) $request->status);

                /*
                |--------------------------------------------------------------------------
                | Status Filter
                |--------------------------------------------------------------------------
                | Only allow filtering by statuses that are valid for depreciation.
                | This prevents obsolete or disposed assets from appearing through URL edits.
                */
                if (in_array($status, $this->depreciableStatuses, true)) {
                    $query->whereRaw('LOWER(REPLACE(status, "_", " ")) = ?', [$status]);
                }
            })

            ->when($request->filled('from_date'), function (Builder $query) use ($request) {
                $query->whereDate('purchase_date', '>=', $request->from_date);
            })

            ->when($request->filled('to_date'), function (Builder $query) use ($request) {
                $query->whereDate('purchase_date', '<=', $request->to_date);
            })

            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function (Builder $q) use ($search) {
                    $q->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('assetName', function (Builder $qq) use ($search) {
                            $qq->where('assetname', 'like', "%{$search}%");
                        })
                        ->orWhereHas('unit', function (Builder $qq) use ($search) {
                            $qq->where('name', 'like', "%{$search}%")
                                ->orWhere('locationname', 'like', "%{$search}%");
                        });
                });
            });
    }

    /**
     * Normalize status values for safer comparison.
     */
    private function normalizeStatus(string $status): string
    {
        return strtolower(str_replace('_', ' ', trim($status)));
    }

    /**
     * Calculate depreciation using the straight-line method.
     */
    private function calculateDepreciation(Asset $asset): array
    {
        $purchaseCost = (float) ($asset->purchase_cost ?? 0);

        $usefulLife = (int) ($asset->assetName?->usefullife ?: 5);

        if ($usefulLife <= 0) {
            $usefulLife = 5;
        }

        /*
        |--------------------------------------------------------------------------
        | Straight-Line Depreciation Formula
        |--------------------------------------------------------------------------
        | Residual Value = 10% of Purchase Cost
        | Annual Depreciation = (Purchase Cost - Residual Value) / Useful Life
        | Accumulated Depreciation = Annual Depreciation x Years Used
        | Book Value = Purchase Cost - Accumulated Depreciation
        |
        | Future purchase dates should not create negative depreciation.
        */

        $residualValue = $purchaseCost * 0.10;

        $annualDepreciation = $usefulLife > 0
            ? ($purchaseCost - $residualValue) / $usefulLife
            : 0;

        $purchaseDate = $asset->purchase_date
            ? Carbon::parse($asset->purchase_date)->startOfDay()
            : null;

        $today = now()->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | Asset Age
        |--------------------------------------------------------------------------
        | Future purchase dates must return 0 years.
        */
        if (! $purchaseDate) {
            $assetAge = 0;
        } elseif ($purchaseDate->greaterThan($today)) {
            $assetAge = 0;
        } else {
            $assetAge = $purchaseDate->diffInDays($today) / 365.25;
        }

        /*
        |--------------------------------------------------------------------------
        | Years Used
        |--------------------------------------------------------------------------
        | Years used cannot be negative and cannot exceed useful life.
        */
        $yearsUsed = min($assetAge, $usefulLife);

        if ($yearsUsed < 0) {
            $yearsUsed = 0;
        }

        $accumulatedDepreciation = $annualDepreciation * $yearsUsed;

        /*
        |--------------------------------------------------------------------------
        | Book Value
        |--------------------------------------------------------------------------
        | Book value should not fall below residual value.
        */
        $bookValue = $purchaseCost - $accumulatedDepreciation;

        if ($bookValue < $residualValue) {
            $bookValue = $residualValue;
        }

        $endOfUseDate = $purchaseDate
            ? $purchaseDate->copy()->addYears($usefulLife)->toDateString()
            : null;

        return [
            'id' => $asset->id,

            'asset_code' => $asset->asset_code,
            'asset_name' => $asset->assetName?->assetname ?? 'N/A',
            'brand' => $asset->brand ?? 'N/A',
            'serial_number' => $asset->serial_number ?? 'N/A',

            'location' => $asset->unit?->locationname ?? 'N/A',
            'unit' => $asset->unit?->name ?? 'N/A',
            'status' => $asset->status ?? 'N/A',

            'purchase_date' => $asset->purchase_date
                ? Carbon::parse($asset->purchase_date)->format('Y-m-d')
                : null,

            'warranty_end_date' => $asset->warranty_end_date
                ? Carbon::parse($asset->warranty_end_date)->format('Y-m-d')
                : null,

            'useful_life' => $usefulLife,
            'asset_age' => round($assetAge, 3),
            'years_used' => round($yearsUsed, 3),
            'end_of_use_date' => $endOfUseDate,

            'purchase_cost' => round($purchaseCost, 2),
            'residual_value' => round($residualValue, 2),
            'annual_depreciation' => round($annualDepreciation, 2),
            'accumulated_depreciation' => round($accumulatedDepreciation, 2),
            'book_value' => round($bookValue, 2),
        ];
    }

    /**
     * Generate summary totals for the depreciation report.
     */
    private function summary(Request $request): array
    {
        $query = Asset::query()
            ->with([
                'assetName:id,assetname,usefullife',
                'unit:id,name,locationname',
            ])
            ->whereNotNull('purchase_date')
            ->whereNotNull('purchase_cost');

        /*
        |--------------------------------------------------------------------------
        | Only Active and Under Maintenance Assets
        |--------------------------------------------------------------------------
        */
        $this->applyDepreciableStatusFilter($query);

        $this->applyFilters($query, $request);

        $assets = $query
            ->get()
            ->map(fn (Asset $asset) => $this->calculateDepreciation($asset));

        return [
            'total_assets' => $assets->count(),
            'total_purchase_cost' => round($assets->sum('purchase_cost'), 2),
            'total_residual_value' => round($assets->sum('residual_value'), 2),
            'total_annual_depreciation' => round($assets->sum('annual_depreciation'), 2),
            'total_accumulated_depreciation' => round($assets->sum('accumulated_depreciation'), 2),
            'total_book_value' => round($assets->sum('book_value'), 2),
        ];
    }



}
