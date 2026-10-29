<?php

namespace App\Http\Controllers;

use App\Models\ActivityTracker;
use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\AssetScanLog;
use App\Models\AssetTransfer;
use App\Models\AuditLog;
use App\Models\LoginTracker;
use App\Models\MaintenanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdvancedAnalyticsController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('AdvancedAnalytics/Dashboard', [
            'summary' => $this->summaryCards(),
            'assetStatus' => $this->assetStatus(),
            'assetYears' => $this->assetYears(),
            'topLocations' => $this->topLocations(8),
            'topCategories' => $this->topCategories(8),
            'workflow' => $this->workflowSummary(),
            'quality' => $this->qualitySummary(),
            'risks' => $this->riskSummary(),
        ]);
    }

    public function distributionCount(): Response
    {
        return Inertia::render('AdvancedAnalytics/DistributionCount', [
            'summary' => $this->summaryCards(),
            'status' => $this->assetStatus(),
            'locations' => $this->topLocations(20),
            'categories' => $this->topCategories(20),
            'sources' => $this->assetSources(),
            'costBands' => $this->costBands(),
            'ageBands' => $this->ageBands(),
        ]);
    }

    public function dataQuality(): Response
    {
        return Inertia::render('AdvancedAnalytics/DataQuality', [
            'summary' => $this->summaryCards(),
            'quality' => $this->qualitySummary(),
            'checks' => $this->qualityChecks(),
            'duplicateAssetCodes' => $this->duplicateAssetCodes(),
            'missingCriticalFields' => $this->missingCriticalFields(),
        ]);
    }

    public function maintenance(): Response
    {
        return Inertia::render('AdvancedAnalytics/Maintenance', [
            'summary' => $this->summaryCards(),
            'maintenanceSummary' => $this->maintenanceSummary(),
            'status' => $this->maintenanceByStatus(),
            'priority' => $this->maintenanceByPriority(),
            'topAssets' => $this->topMaintenanceAssets(),
            'monthlyCost' => $this->maintenanceMonthlyCost(),
        ]);
    }

    /**
     * Live asset-code lookup for the Maintenance Analytics page.
     *
     * The response is deliberately small so the type-ahead remains fast even
     * when the asset register grows. Asset code is prioritised, while asset
     * name and serial number are also searchable for convenience.
     */
    public function maintenanceAssetSearch(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if ($search === '') {
            return response()->json([]);
        }

        $assets = Asset::query()
            ->with([
                'assetName:id,assetname',
                'unit:id,name,locationname',
            ])
            ->where(function ($query) use ($search) {
                $query->where('asset_code', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhereHas('assetName', function ($assetNameQuery) use ($search) {
                        $assetNameQuery->where('assetname', 'like', "%{$search}%");
                    });
            })
            ->orderByRaw(
                'CASE WHEN asset_code = ? THEN 0 WHEN asset_code LIKE ? THEN 1 ELSE 2 END',
                [$search, $search.'%']
            )
            ->orderBy('asset_code')
            ->limit(15)
            ->get()
            ->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->assetName?->assetname ?? 'Uncategorised',
                'serial_number' => $asset->serial_number,
                'unit' => $asset->unit?->name ?? 'Unassigned',
                'location' => $asset->unit?->locationname ?? 'Unassigned',
                'status' => $this->label($asset->status),
            ])
            ->values();

        return response()->json($assets);
    }

    /**
     * Return maintenance and financial analytics for one selected asset.
     */
    public function maintenanceAssetDetail(Asset $asset): JsonResponse
    {
        $asset->load([
            'assetName:id,assetname,usefullife',
            'unit:id,name,locationname',
            'maintenanceRequests' => function ($query) {
                $query->with('maintenanceProvider:id,providername')
                    ->latest('created_at');
            },
        ]);

        $maintenance = $asset->maintenanceRequests;
        $actualCost = (float) $maintenance->sum(fn ($row) => (float) ($row->actual_cost ?? 0));
        $estimatedCost = (float) $maintenance->sum(fn ($row) => (float) ($row->estimated_cost ?? 0));
        $completedCount = $maintenance->where('status', 'completed')->count();
        $depreciation = $this->selectedAssetDepreciation($asset);

        return response()->json([
            'asset' => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->assetName?->assetname ?? 'Uncategorised',
                'brand' => $asset->brand ?? 'Not recorded',
                'serial_number' => $asset->serial_number ?? 'Not recorded',
                'unit' => $asset->unit?->name ?? 'Unassigned',
                'location' => $asset->unit?->locationname ?? 'Unassigned',
                'status' => $this->label($asset->status),
                'purchase_date' => optional($asset->purchase_date)->format('Y-m-d'),
                'warranty_end_date' => optional($asset->warranty_end_date)->format('Y-m-d'),
            ],
            'metrics' => [
                'maintenance_count' => $maintenance->count(),
                'completed_maintenance_count' => $completedCount,
                'total_actual_maintenance_cost' => round($actualCost, 2),
                'total_actual_maintenance_cost_label' => $this->money($actualCost),
                'total_estimated_maintenance_cost' => round($estimatedCost, 2),
                'total_estimated_maintenance_cost_label' => $this->money($estimatedCost),
                'purchase_cost' => $depreciation['purchase_cost'],
                'purchase_cost_label' => $depreciation['purchase_cost_label'],
                'book_value' => $depreciation['book_value'],
                'book_value_label' => $depreciation['book_value_label'],
                'accumulated_depreciation' => $depreciation['accumulated_depreciation'],
                'accumulated_depreciation_label' => $depreciation['accumulated_depreciation_label'],
                'residual_value' => $depreciation['residual_value'],
                'residual_value_label' => $depreciation['residual_value_label'],
                'useful_life' => $depreciation['useful_life'],
                'asset_age' => $depreciation['asset_age'],
                'end_of_use_date' => $depreciation['end_of_use_date'],
            ],
            'maintenance' => $maintenance
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'request_date' => optional($row->created_at)->format('Y-m-d'),
                    'scheduled_date' => optional($row->scheduled_date)->format('Y-m-d'),
                    'completion_date' => optional($row->actual_completion_date)->format('Y-m-d'),
                    'display_date' => optional(
                        $row->actual_completion_date
                            ?? $row->scheduled_date
                            ?? $row->created_at
                    )->format('Y-m-d'),
                    'status' => $this->label($row->status),
                    'priority' => $this->label($row->priority),
                    'fault_description' => $row->fault_description,
                    'provider' => $row->maintenanceProvider?->providername ?? 'Not assigned',
                    'estimated_cost' => $row->estimated_cost !== null ? (float) $row->estimated_cost : null,
                    'estimated_cost_label' => $row->estimated_cost !== null
                        ? $this->money((float) $row->estimated_cost)
                        : 'Not recorded',
                    'actual_cost' => $row->actual_cost !== null ? (float) $row->actual_cost : null,
                    'actual_cost_label' => $row->actual_cost !== null
                        ? $this->money((float) $row->actual_cost)
                        : 'Not recorded',
                ])
                ->values(),
        ]);
    }
    public function lifecycleRisk(): Response
    {
        return Inertia::render('AdvancedAnalytics/LifecycleRisk', [
            'summary' => $this->summaryCards(),
            'risks' => $this->riskSummary(),
            'endOfLife' => $this->endOfLifeAssets(),
            'lifecycleDataQuality' => $this->lifecycleDataQuality(),
            'highMaintenance' => $this->highMaintenanceAssets(),
            'obsoleteAssets' => $this->obsoleteAssets(),
            'scanAlerts' => $this->scanAlerts(),
        ]);
    }

    public function warrantyEndOfLife(): Response
    {
        return Inertia::render('AdvancedAnalytics/WarrantyEndOfLife', [
            'summary' => $this->summaryCards(),
            'warranty' => $this->warrantySummary(),
            'warrantyRows' => $this->warrantyRows(),
            'endOfLife' => $this->endOfLifeAssets(),
        ]);
    }

    public function implementationEvidence(): Response
    {
        return Inertia::render('AdvancedAnalytics/ImplementationEvidence', [
            'summary' => $this->summaryCards(),
            'evidence' => $this->implementationEvidenceItems(),
            'chapterOutputs' => $this->chapterOutputMap(),
        ]);
    }

    private function summaryCards(): array
    {
        $totalAssets = Asset::count();
        $totalValue = (float) Asset::sum('purchase_cost');
        $qrCount = Asset::whereNotNull('qr_code')->where('qr_code', '<>', '')->count();
        $active = Asset::where('status', 'active')->count();
        $underMaintenance = Asset::where('status', 'under_maintenance')->count();
        $obsolete = Asset::where('status', 'obsolete')->count();
        $disposed = Asset::where('status', 'disposed')->count();

        return [
            ['label' => 'Total Assets', 'value' => $totalAssets, 'subtext' => 'All registered asset records', 'tone' => 'blue'],
            ['label' => 'Active Assets', 'value' => $active, 'subtext' => 'Assets currently supporting operations', 'tone' => 'emerald'],
            ['label' => 'Lifecycle Risk', 'value' => $obsolete + $disposed + $underMaintenance, 'subtext' => 'Obsolete, disposed or under maintenance', 'tone' => 'amber'],
            ['label' => 'Total Purchase Value', 'value' => $this->money($totalValue), 'subtext' => 'Historical acquisition value', 'tone' => 'purple'],
            ['label' => 'QR Coverage', 'value' => $this->percent($qrCount, $totalAssets), 'subtext' => $qrCount.' assets with QR code', 'tone' => 'cyan'],
            ['label' => 'Pending Workflows', 'value' => $this->pendingWorkflowCount(), 'subtext' => 'Requests, transfers and maintenance pending', 'tone' => 'rose'],
        ];
    }

    private function assetStatus(): array
    {
        return Asset::query()
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(purchase_cost),0) as value'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $this->label($row->status),
                'status' => $row->status,
                'total' => (int) $row->total,
                'value' => (float) $row->value,
                'valueLabel' => $this->money((float) $row->value),
            ])->values()->all();
    }

    private function assetYears(): array
    {
        return Asset::query()
            ->whereNotNull('purchase_date')
            ->selectRaw('YEAR(purchase_date) as year, COUNT(*) as total, COALESCE(SUM(purchase_cost),0) as value')
            ->groupByRaw('YEAR(purchase_date)')
            ->orderBy('year')
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->year,
                'year' => (int) $row->year,
                'total' => (int) $row->total,
                'value' => (float) $row->value,
                'valueLabel' => $this->money((float) $row->value),
            ])->values()->all();
    }

    private function topLocations(int $limit = 10): array
    {
        return Asset::query()
            ->leftJoin('units', 'units.id', '=', 'assets.unit_id')
            ->selectRaw("COALESCE(units.locationname, units.name, 'Unassigned') as label, COUNT(*) as total, COALESCE(SUM(assets.purchase_cost),0) as value")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
                'value' => (float) $row->value,
                'valueLabel' => $this->money((float) $row->value),
            ])->values()->all();
    }

    private function topCategories(int $limit = 10): array
    {
        return Asset::query()
            ->leftJoin('asset_names', 'asset_names.id', '=', 'assets.assetname_id')
            ->selectRaw("COALESCE(asset_names.assetname, 'Uncategorised') as label, COUNT(*) as total, COALESCE(SUM(assets.purchase_cost),0) as value")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
                'value' => (float) $row->value,
                'valueLabel' => $this->money((float) $row->value),
            ])->values()->all();
    }

    private function assetSources(): array
    {
        return Asset::query()
            ->leftJoin('sources', 'sources.id', '=', 'assets.source_id')
            ->selectRaw("COALESCE(sources.name, 'Unspecified') as label, COUNT(*) as total, COALESCE(SUM(assets.purchase_cost),0) as value")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
                'value' => (float) $row->value,
                'valueLabel' => $this->money((float) $row->value),
            ])->values()->all();
    }

    private function costBands(): array
    {
        $bands = [
            ['label' => 'No Cost', 'min' => null, 'max' => null],
            ['label' => 'Below GH¢1,000', 'min' => 0, 'max' => 999.99],
            ['label' => 'GH¢1,000 - GH¢4,999', 'min' => 1000, 'max' => 4999.99],
            ['label' => 'GH¢5,000 - GH¢19,999', 'min' => 5000, 'max' => 19999.99],
            ['label' => 'GH¢20,000+', 'min' => 20000, 'max' => null],
        ];

        return collect($bands)->map(function ($band) {
            $query = Asset::query();
            if ($band['label'] === 'No Cost') {
                $query->whereNull('purchase_cost');
            } elseif ($band['max'] === null) {
                $query->where('purchase_cost', '>=', $band['min']);
            } else {
                $query->whereBetween('purchase_cost', [$band['min'], $band['max']]);
            }
            $count = $query->count();
            return ['label' => $band['label'], 'total' => $count];
        })->values()->all();
    }

    private function ageBands(): array
    {
        $assets = Asset::query()->whereNotNull('purchase_date')->get(['purchase_date']);
        $bands = [
            '0-2 years' => 0,
            '3-5 years' => 0,
            '6-10 years' => 0,
            'Above 10 years' => 0,
            'Unknown age' => Asset::whereNull('purchase_date')->count(),
        ];

        foreach ($assets as $asset) {
            $age = $asset->purchase_date?->diffInYears(now()) ?? null;
            if ($age === null) {
                $bands['Unknown age']++;
            } elseif ($age <= 2) {
                $bands['0-2 years']++;
            } elseif ($age <= 5) {
                $bands['3-5 years']++;
            } elseif ($age <= 10) {
                $bands['6-10 years']++;
            } else {
                $bands['Above 10 years']++;
            }
        }

        return collect($bands)->map(fn ($total, $label) => ['label' => $label, 'total' => $total])->values()->all();
    }

    private function workflowSummary(): array
    {
        return [
            ['label' => 'Pending Asset Requests', 'value' => AssetRequest::where('status', 'pending')->count(), 'subtext' => 'Demand awaiting review'],
            ['label' => 'Pending Transfers', 'value' => AssetTransfer::where('status', 'pending')->count(), 'subtext' => 'Movement awaiting approval'],
            ['label' => 'Pending Maintenance', 'value' => MaintenanceRequest::whereIn('status', ['pending', 'approved', 'in_progress'])->count(), 'subtext' => 'Repair workflow pressure'],
            ['label' => 'Recent Audit Events', 'value' => AuditLog::where('created_at', '>=', now()->subDays(30))->count(), 'subtext' => 'Last 30 days'],
            ['label' => 'Recent Activities', 'value' => ActivityTracker::where('created_at', '>=', now()->subDays(30))->count(), 'subtext' => 'Last 30 days'],
            ['label' => 'Failed Logins', 'value' => LoginTracker::where('event', 'like', '%fail%')->where('created_at', '>=', now()->subDays(30))->count(), 'subtext' => 'Last 30 days'],
        ];
    }

    private function qualitySummary(): array
    {
        $total = Asset::count();
        $checks = $this->qualityChecks();
        $average = count($checks) ? round(collect($checks)->avg('score'), 1) : 0;

        return [
            'total' => $total,
            'overallScore' => $average,
            'label' => $average >= 80 ? 'Strong' : ($average >= 60 ? 'Moderate' : 'Needs attention'),
            'qrCoverage' => $this->percent(Asset::whereNotNull('qr_code')->where('qr_code', '<>', '')->count(), $total),
            'criticalCompleteness' => $average.'%',
        ];
    }

    private function qualityChecks(): array
    {
        $total = max(Asset::count(), 1);
        $checks = [
            ['field' => 'Purchase date', 'complete' => Asset::whereNotNull('purchase_date')->count(), 'why' => 'Required for asset aging, end-of-life and depreciation analytics.'],
            ['field' => 'Purchase cost', 'complete' => Asset::whereNotNull('purchase_cost')->count(), 'why' => 'Required for valuation, cost visibility and depreciation reporting.'],
            ['field' => 'Unit assignment', 'complete' => Asset::whereNotNull('unit_id')->count(), 'why' => 'Required for inventory-by-office accountability.'],
            ['field' => 'Asset name/category', 'complete' => Asset::whereNotNull('assetname_id')->count(), 'why' => 'Required for category distribution and useful-life modelling.'],
            ['field' => 'QR code', 'complete' => Asset::whereNotNull('qr_code')->where('qr_code', '<>', '')->count(), 'why' => 'Required for physical verification and scan-based evidence.'],
            ['field' => 'Warranty end date', 'complete' => Asset::whereNotNull('warranty_end_date')->count(), 'why' => 'Required for warranty-risk and maintenance planning.'],
            ['field' => 'Serial number', 'complete' => Asset::whereNotNull('serial_number')->where('serial_number', '<>', '')->count(), 'why' => 'Required for physical identity verification.'],
        ];

        return collect($checks)->map(function ($check) use ($total) {
            $score = round(($check['complete'] / $total) * 100, 1);
            return [
                'field' => $check['field'],
                'complete' => $check['complete'],
                'missing' => max($total - $check['complete'], 0),
                'score' => $score,
                'scoreLabel' => $score.'%',
                'why' => $check['why'],
            ];
        })->values()->all();
    }

    private function duplicateAssetCodes(): array
    {
        return Asset::query()
            ->select('asset_code', DB::raw('COUNT(*) as total'))
            ->whereNotNull('asset_code')
            ->groupBy('asset_code')
            ->having('total', '>', 1)
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row) => ['asset_code' => $row->asset_code, 'total' => (int) $row->total])
            ->values()->all();
    }

    private function missingCriticalFields(): array
    {
        return Asset::with(['assetName', 'unit'])
            ->whereNull('purchase_date')
            ->orWhereNull('purchase_cost')
            ->orWhereNull('unit_id')
            ->orWhereNull('qr_code')
            ->orWhereNull('serial_number')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($asset) => [
                'asset_code' => $asset->asset_code,
                'name' => $asset->assetName?->assetname ?? 'Uncategorised',
                'unit' => $asset->unit?->name ?? 'Unassigned',
                'missing' => collect([
                    'purchase_date' => blank($asset->purchase_date),
                    'purchase_cost' => blank($asset->purchase_cost),
                    'unit' => blank($asset->unit_id),
                    'qr_code' => blank($asset->qr_code),
                    'serial_number' => blank($asset->serial_number),
                ])->filter()->keys()->implode(', '),
            ])->values()->all();
    }

    /**
     * Calculate the selected asset's current book value using the same
     * straight-line depreciation rule already used by GhanaDAMS reports:
     * 10% residual value and useful life from the asset category.
     */
    private function selectedAssetDepreciation(Asset $asset): array
    {
        $purchaseCost = $asset->purchase_cost !== null
            ? (float) $asset->purchase_cost
            : null;

        $purchaseDate = $asset->purchase_date?->copy()->startOfDay();
        $usefulLife = (int) ($asset->assetName?->usefullife ?: 5);

        if ($usefulLife <= 0) {
            $usefulLife = 5;
        }

        if ($purchaseCost === null || $purchaseDate === null) {
            return [
                'purchase_cost' => $purchaseCost,
                'purchase_cost_label' => $purchaseCost !== null ? $this->money($purchaseCost) : 'Not recorded',
                'book_value' => null,
                'book_value_label' => 'Not available',
                'accumulated_depreciation' => null,
                'accumulated_depreciation_label' => 'Not available',
                'residual_value' => $purchaseCost !== null ? round($purchaseCost * 0.10, 2) : null,
                'residual_value_label' => $purchaseCost !== null ? $this->money($purchaseCost * 0.10) : 'Not available',
                'useful_life' => $usefulLife,
                'asset_age' => null,
                'end_of_use_date' => $purchaseDate?->copy()->addYears($usefulLife)->toDateString(),
            ];
        }

        $residualValue = $purchaseCost * 0.10;
        $annualDepreciation = ($purchaseCost - $residualValue) / $usefulLife;
        $today = now()->startOfDay();

        $assetAge = $purchaseDate->greaterThan($today)
            ? 0
            : $purchaseDate->diffInDays($today) / 365.25;

        $yearsUsed = max(0, min($assetAge, $usefulLife));
        $accumulatedDepreciation = min(
            $annualDepreciation * $yearsUsed,
            $purchaseCost - $residualValue
        );
        $bookValue = max(
            $purchaseCost - $accumulatedDepreciation,
            $residualValue
        );

        return [
            'purchase_cost' => round($purchaseCost, 2),
            'purchase_cost_label' => $this->money($purchaseCost),
            'book_value' => round($bookValue, 2),
            'book_value_label' => $this->money($bookValue),
            'accumulated_depreciation' => round($accumulatedDepreciation, 2),
            'accumulated_depreciation_label' => $this->money($accumulatedDepreciation),
            'residual_value' => round($residualValue, 2),
            'residual_value_label' => $this->money($residualValue),
            'useful_life' => $usefulLife,
            'asset_age' => round($assetAge, 2),
            'end_of_use_date' => $purchaseDate->copy()->addYears($usefulLife)->toDateString(),
        ];
    }

    private function maintenanceSummary(): array
    {
        $total = MaintenanceRequest::count();
        $actual = (float) MaintenanceRequest::sum('actual_cost');
        $estimated = (float) MaintenanceRequest::sum('estimated_cost');
        $completed = MaintenanceRequest::where('status', 'completed')->count();
        $pending = MaintenanceRequest::whereIn('status', ['pending', 'approved', 'in_progress'])->count();

        return [
            ['label' => 'Maintenance Requests', 'value' => $total, 'subtext' => 'Total repair records'],
            ['label' => 'Completed Requests', 'value' => $completed, 'subtext' => $this->percent($completed, $total).' completion ratio'],
            ['label' => 'Open Maintenance', 'value' => $pending, 'subtext' => 'Pending or in progress'],
            ['label' => 'Actual Cost', 'value' => $this->money($actual), 'subtext' => 'Recorded actual maintenance cost'],
        ];
    }

    private function maintenanceByStatus(): array
    {
        return MaintenanceRequest::query()
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(actual_cost),0) as cost'))
            ->groupBy('status')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['label' => $this->label($row->status), 'total' => (int) $row->total, 'cost' => (float) $row->cost, 'costLabel' => $this->money((float) $row->cost)])
            ->values()->all();
    }

    private function maintenanceByPriority(): array
    {
        return MaintenanceRequest::query()
            ->select('priority', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(actual_cost),0) as cost'))
            ->groupBy('priority')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['label' => $this->label($row->priority), 'total' => (int) $row->total, 'cost' => (float) $row->cost, 'costLabel' => $this->money((float) $row->cost)])
            ->values()->all();
    }

    private function topMaintenanceAssets(): array
    {
        return MaintenanceRequest::query()
            ->leftJoin('assets', 'assets.id', '=', 'maintenance_requests.asset_id')
            ->leftJoin('asset_names', 'asset_names.id', '=', 'assets.assetname_id')
            ->selectRaw("assets.asset_code as asset_code, COALESCE(asset_names.assetname, 'Uncategorised') as name, COUNT(*) as total, COALESCE(SUM(maintenance_requests.actual_cost),0) as cost")
            ->groupBy('assets.asset_code', 'name')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($row) => ['asset_code' => $row->asset_code, 'name' => $row->name, 'total' => (int) $row->total, 'costLabel' => $this->money((float) $row->cost)])
            ->values()->all();
    }

    private function maintenanceMonthlyCost(): array
    {
        return MaintenanceRequest::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total, COALESCE(SUM(actual_cost),0) as cost")
            ->groupBy('month')
            ->orderBy('month')
            ->limit(18)
            ->get()
            ->map(fn ($row) => ['label' => $row->month, 'total' => (int) $row->total, 'cost' => (float) $row->cost, 'costLabel' => $this->money((float) $row->cost)])
            ->values()->all();
    }
    private function riskSummary(): array
    {
        // WARRANTY SCOPE RULE:
        // Warranty metrics use ACTIVE assets only.
        //
        // This keeps Lifecycle Risk consistent with:
        // - Dashboard Warranty Monitoring;
        // - Inventory by Office (active assets);
        // - Warranty & End-of-Life analytics.
        //
        // Under-maintenance, obsolete and disposed assets remain available
        // in their own lifecycle/maintenance measures but are not added to
        // the active warranty totals.
        $expiredWarranty = Asset::query()
            ->where('status', 'active')
            ->whereNotNull('warranty_end_date')
            ->whereDate('warranty_end_date', '<', today()->toDateString())
            ->count();

        $warranty90 = Asset::query()
            ->where('status', 'active')
            ->whereNotNull('warranty_end_date')
            ->whereBetween('warranty_end_date', [
                today()->toDateString(),
                today()->copy()->addDays(90)->toDateString(),
            ])
            ->count();

        $endOfLifeAttention = collect($this->endOfLifeAssets())
            ->whereIn('risk', ['Overdue', 'Due Soon'])
            ->count();

        $obsolete = Asset::query()
            ->where('status', 'obsolete')
            ->count();

        $highMaintenance = Asset::query()
            ->whereNotIn('status', ['disposed'])
            ->withCount([
                'maintenanceRequests as completed_maintenance_count' => fn ($query) =>
                    $query->where('status', 'completed'),
            ])
            ->get()
            ->where('completed_maintenance_count', '>=', 3)
            ->count();

        $scanAlerts = AssetScanLog::query()
            ->where('created_at', '>=', now()->subDays(90))
            ->where(function ($query) {
                $query->where('is_unknown_location', true)
                    ->orWhere('is_tamper_suspected', true);
            })
            ->count();

        return [
            [
                'label' => 'Expired Warranty',
                'value' => $expiredWarranty,
                'subtext' => 'Active assets outside warranty',
                'severity' => 'high',
                'tone' => 'rose',
            ],
            [
                'label' => 'Warranty Due Soon',
                'value' => $warranty90,
                'subtext' => 'Active assets due within 90 days',
                'severity' => 'medium',
                'tone' => 'amber',
            ],
            [
                'label' => 'End-of-Life Attention',
                'value' => $endOfLifeAttention,
                'subtext' => 'Recently overdue or due within 12 months',
                'severity' => 'medium',
                'tone' => 'amber',
            ],
            [
                'label' => 'Obsolete Assets',
                'value' => $obsolete,
                'subtext' => 'Awaiting replacement/disposal action',
                'severity' => 'high',
                'tone' => 'rose',
            ],
            [
                'label' => 'Repeated Maintenance',
                'value' => $highMaintenance,
                'subtext' => 'At least 3 completed maintenance jobs',
                'severity' => 'medium',
                'tone' => 'violet',
            ],
            [
                'label' => 'Recent Scan Alerts',
                'value' => $scanAlerts,
                'subtext' => 'Unknown location/tamper signals in 90 days',
                'severity' => 'high',
                'tone' => 'sky',
            ],
        ];
    }
    private function endOfLifeAssets(): array
    {
        $today = today();

        return Asset::query()
            ->leftJoin('asset_names', 'asset_names.id', '=', 'assets.assetname_id')
            ->leftJoin('units', 'units.id', '=', 'assets.unit_id')
            ->whereNotNull('assets.purchase_date')
            ->whereNotIn('assets.status', ['disposed'])
            ->select(
                'assets.*',
                'asset_names.assetname as asset_name',
                'asset_names.usefullife as useful_life',
                'units.name as unit_name'
            )
            ->get()
            ->map(function ($asset) use ($today) {
                $usefulLife = (int) ($asset->useful_life ?? 0);

                if ($usefulLife <= 0 || ! $asset->purchase_date) {
                    return null;
                }

                $endDate = $asset->purchase_date
                    ->copy()
                    ->addYears($usefulLife);

                $daysRemaining = $today->diffInDays($endDate, false);

                // Keep the management watch list actionable. Very old calculated
                // end-of-life dates are classified as legacy/data-quality records
                // instead of dominating a current priority list.
                if ($daysRemaining < -365 || $daysRemaining > 365) {
                    return null;
                }

                $risk = match (true) {
                    $daysRemaining < 0 => 'Overdue',
                    $daysRemaining <= 180 => 'Due Soon',
                    default => 'Watch',
                };

                return [
                    'asset_code' => $asset->asset_code,
                    'name' => $asset->asset_name ?? 'Uncategorised',
                    'unit' => $asset->unit_name ?? 'Unassigned',
                    'status' => $this->label($asset->status),
                    'purchase_date' => optional($asset->purchase_date)->format('Y-m-d'),
                    'useful_life' => $usefulLife,
                    'end_date' => $endDate->format('Y-m-d'),
                    'days_remaining' => $daysRemaining,
                    'risk' => $risk,
                ];
            })
            ->filter()
            ->sortBy(fn (array $row) => abs((int) $row['days_remaining']))
            ->take(30)
            ->values()
            ->all();
    }
    private function highMaintenanceAssets(): array
    {
        return Asset::query()
            ->whereNotIn('status', ['disposed'])
            ->withCount([
                'maintenanceRequests as completed_maintenance_count' => fn ($query) =>
                    $query->where('status', 'completed'),
            ])
            ->with(['assetName', 'unit'])
            ->having('completed_maintenance_count', '>=', 3)
            ->orderByDesc('completed_maintenance_count')
            ->limit(30)
            ->get()
            ->map(fn ($asset) => [
                'asset_code' => $asset->asset_code,
                'name' => $asset->assetName?->assetname ?? 'Uncategorised',
                'unit' => $asset->unit?->name ?? 'Unassigned',
                'status' => $this->label($asset->status),
                'maintenance_count' => (int) $asset->completed_maintenance_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Identify records that belong in data-quality / legacy review rather than
     * the current lifecycle priority watch list.
     */
    private function lifecycleDataQuality(): array
    {
        $today = today();
        $legacyEndOfLife = 0;
        $futurePurchaseDates = 0;
        $missingUsefulLife = 0;

        Asset::query()
            ->leftJoin('asset_names', 'asset_names.id', '=', 'assets.assetname_id')
            ->whereNotNull('assets.purchase_date')
            ->whereNotIn('assets.status', ['disposed'])
            ->select(
                'assets.purchase_date',
                'asset_names.usefullife as useful_life'
            )
            ->get()
            ->each(function ($asset) use (
                $today,
                &$legacyEndOfLife,
                &$futurePurchaseDates,
                &$missingUsefulLife
            ) {
                if ($asset->purchase_date?->isAfter($today)) {
                    $futurePurchaseDates++;
                }

                $usefulLife = (int) ($asset->useful_life ?? 0);

                if ($usefulLife <= 0) {
                    $missingUsefulLife++;
                    return;
                }

                $endDate = $asset->purchase_date
                    ->copy()
                    ->addYears($usefulLife);

                if ($endDate->lt($today->copy()->subYear())) {
                    $legacyEndOfLife++;
                }
            });

        return [
            'legacy_end_of_life' => $legacyEndOfLife,
            'future_purchase_dates' => $futurePurchaseDates,
            'missing_useful_life' => $missingUsefulLife,
        ];
    }

    private function obsoleteAssets(): array
    {
        return Asset::with(['assetName', 'unit'])
            ->where('status', 'obsolete')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($asset) => [
                'asset_code' => $asset->asset_code,
                'name' => $asset->assetName?->assetname ?? 'Uncategorised',
                'unit' => $asset->unit?->name ?? 'Unassigned',
                'status' => $this->label($asset->status),
                'purchase_cost' => $this->money((float) $asset->purchase_cost),
            ])
            ->values()
            ->all();
    }

    private function obsoleteDisposedAssets(): array
    {
        return Asset::with(['assetName', 'unit'])
            ->whereIn('status', ['obsolete', 'disposed'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($asset) => [
                'asset_code' => $asset->asset_code,
                'name' => $asset->assetName?->assetname ?? 'Uncategorised',
                'unit' => $asset->unit?->name ?? 'Unassigned',
                'status' => $this->label($asset->status),
                'purchase_cost' => $this->money((float) $asset->purchase_cost),
            ])->values()->all();
    }
    private function scanAlerts(): array
    {
        return AssetScanLog::with(['asset.assetName', 'asset.unit', 'scannedBy'])
            ->where('created_at', '>=', now()->subDays(90))
            ->where(function ($query) {
                $query->where('is_unknown_location', true)
                    ->orWhere('is_tamper_suspected', true);
            })
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn ($log) => [
                'date' => optional($log->created_at)->format('Y-m-d H:i'),
                'asset_code' => $log->asset?->asset_code,
                'name' => $log->asset?->assetName?->assetname ?? 'Uncategorised',
                'unit' => $log->asset?->unit?->name ?? 'Unassigned',
                'scanned_by' => $log->scannedBy?->name ?? 'Public',
                'location' => $log->scan_location ?? 'Not captured',
                'alert' => $log->is_tamper_suspected ? 'Tamper suspected' : 'Unknown location',
            ])
            ->values()
            ->all();
    }

    private function warrantySummary(): array
    {
        // Warranty analytics are deliberately scoped to ACTIVE assets only.
        $total = Asset::query()
            ->where('status', 'active')
            ->count();

        $expired = Asset::query()
            ->where('status', 'active')
            ->whereNotNull('warranty_end_date')
            ->whereDate('warranty_end_date', '<', today()->toDateString())
            ->count();

        $soon = Asset::query()
            ->where('status', 'active')
            ->whereNotNull('warranty_end_date')
            ->whereBetween('warranty_end_date', [
                today()->toDateString(),
                today()->copy()->addDays(90)->toDateString(),
            ])
            ->count();

        $missing = Asset::query()
            ->where('status', 'active')
            ->whereNull('warranty_end_date')
            ->count();

        return [
            [
                'label' => 'Expired Warranty',
                'value' => $expired,
                'subtext' => $this->percent($expired, $total).' of active assets',
            ],
            [
                'label' => 'Warranty Due Soon',
                'value' => $soon,
                'subtext' => 'Active assets within next 90 days',
            ],
            [
                'label' => 'Missing Warranty Date',
                'value' => $missing,
                'subtext' => 'Active records needing data completion',
            ],
            [
                'label' => 'Warranty Coverage',
                'value' => $this->percent($total - $missing, $total),
                'subtext' => 'Active assets with warranty end date',
            ],
        ];
    }

    private function warrantyRows(): array
    {
        return Asset::with(['assetName', 'unit'])
            ->where('status', 'active')
            ->whereNotNull('warranty_end_date')
            ->orderBy('warranty_end_date')
            ->limit(40)
            ->get()
            ->map(fn ($asset) => [
                'asset_code' => $asset->asset_code,
                'name' => $asset->assetName?->assetname ?? 'Uncategorised',
                'unit' => $asset->unit?->name ?? 'Unassigned',
                'status' => $this->label($asset->status),
                'warranty_end' => optional($asset->warranty_end_date)->format('Y-m-d'),
                'days_remaining' => $asset->warranty_end_date ? now()->diffInDays($asset->warranty_end_date, false) : null,
            ])->values()->all();
    }

    private function implementationEvidenceItems(): array
    {
        return [
            ['figure' => 'Figure 4.20', 'title' => 'Advanced Analytics Dashboard', 'page' => '/advanced-analytics', 'evidence' => 'Overall data science summary, KPIs, risks and workflow indicators.'],
            ['figure' => 'Figure 4.21', 'title' => 'Asset Distribution Count Analysis', 'page' => '/advanced-analytics/distribution-count', 'evidence' => 'Counts by status, location, category, source, cost and age.'],
            ['figure' => 'Figure 4.22', 'title' => 'Asset Data Quality Index', 'page' => '/advanced-analytics/data-quality', 'evidence' => 'Completeness, duplicates and missing critical fields.'],
            ['figure' => 'Figure 4.23', 'title' => 'Maintenance Analytics Output', 'page' => '/advanced-analytics/maintenance', 'evidence' => 'Maintenance status, priority, cost and repeated maintenance.'],
            ['figure' => 'Figure 4.24', 'title' => 'Lifecycle Risk Analytics Output', 'page' => '/advanced-analytics/lifecycle-risk', 'evidence' => 'Obsolete, disposed, end-of-life, high-maintenance and scan-risk outputs.'],
            ['figure' => 'Figure 4.25', 'title' => 'Warranty and End-of-Life Analytics', 'page' => '/advanced-analytics/warranty-end-of-life', 'evidence' => 'Warranty expiry, missing warranty data and useful-life risk.'],
        ];
    }

    private function chapterOutputMap(): array
    {
        return [
            ['chapter_area' => 'Implementation dashboard', 'system_page' => 'Advanced Analytics Dashboard', 'chapter_value' => 'Shows direct evidence of data-driven decision support.'],
            ['chapter_area' => 'Descriptive analysis', 'system_page' => 'Distribution Count Analysis', 'chapter_value' => 'Supports asset status, location and category findings.'],
            ['chapter_area' => 'Data quality analysis', 'system_page' => 'Data Quality Index', 'chapter_value' => 'Supports completeness and reliability discussion.'],
            ['chapter_area' => 'Diagnostic analytics', 'system_page' => 'Maintenance Analytics', 'chapter_value' => 'Supports maintenance cost and recurring-failure discussion.'],
            ['chapter_area' => 'Prescriptive analytics', 'system_page' => 'Lifecycle Risk Analytics', 'chapter_value' => 'Supports replacement and disposal priority discussion.'],
            ['chapter_area' => 'Lifecycle analytics', 'system_page' => 'Warranty and End-of-Life Analytics', 'chapter_value' => 'Supports warranty, useful-life and replacement planning.'],
        ];
    }

    private function pendingWorkflowCount(): int
    {
        return AssetRequest::where('status', 'pending')->count()
            + AssetTransfer::where('status', 'pending')->count()
            + MaintenanceRequest::whereIn('status', ['pending', 'approved', 'in_progress'])->count();
    }

    private function money(float $value): string
    {
        return 'GH¢'.number_format($value, 2);
    }

    private function percent(int|float $part, int|float $whole): string
    {
        if ((float) $whole <= 0) {
            return '0%';
        }
        return round(((float) $part / (float) $whole) * 100, 1).'%';
    }

    private function label(?string $value): string
    {
        return str($value ?? 'Unknown')->replace('_', ' ')->title()->toString();
    }
}
