<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetScanLog;
use App\Models\MaintenanceRequest;
use Inertia\Inertia;

class AdvancedReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports/Advanced', [
            'reports' => [
                ['title' => 'Asset Register Report', 'description' => 'Complete institutional asset register.', 'csv' => route('advanced-reports.asset-register.csv')],
                ['title' => 'Warranty Expiry Report', 'description' => 'Assets with expired or soon-expiring warranties.', 'csv' => route('advanced-reports.warranty-expiry.csv')],
                ['title' => 'End-of-Life Report', 'description' => 'Assets whose useful life has ended or is close to ending.', 'csv' => route('advanced-reports.end-of-life.csv')],
                ['title' => 'Replacement Recommendation Report', 'description' => 'Assets recommended for replacement due to lifecycle, age or maintenance pressure.', 'csv' => route('advanced-reports.replacement-recommendation.csv')],
                ['title' => 'Maintenance History Report', 'description' => 'Maintenance requests, costs, approvals and completion information.', 'csv' => route('advanced-reports.maintenance-history.csv')],
                ['title' => 'QR Scan Report', 'description' => 'QR scan history with device, browser and location/tamper signals.', 'csv' => route('advanced-reports.qr-scans.csv')],
            ],
        ]);
    }

    public function assetRegisterCsv()
    {
        $assets = Asset::with(['assetName','unit','supplier','maintenanceProvider','source','organization'])->latest()->get();
        return $this->csv('asset-register-report.csv', ['Asset Code','Name','Brand','Serial Number','Status','Unit','Location','Supplier','Source','Purchase Cost','Purchase Date','Warranty End'], $assets->map(fn ($asset) => [
            $asset->asset_code,
            $asset->assetName?->assetname,
            $asset->brand,
            $asset->serial_number,
            $asset->status,
            $asset->unit?->name,
            $asset->unit?->locationname,
            $asset->supplier?->suppliername,
            $asset->source?->name,
            $asset->purchase_cost,
            optional($asset->purchase_date)->format('Y-m-d'),
            optional($asset->warranty_end_date)->format('Y-m-d'),
        ])->all());
    }

    public function warrantyExpiryCsv()
    {
        $assets = Asset::with(['assetName', 'unit'])
            ->where('status', 'active')
            ->whereNotNull('warranty_end_date')
            ->orderBy('warranty_end_date')
            ->get();
        return $this->csv('warranty-expiry-report.csv', ['Asset Code','Name','Status','Unit','Warranty End','Days Remaining'], $assets->map(fn ($asset) => [
            $asset->asset_code,
            $asset->assetName?->assetname,
            $asset->status,
            $asset->unit?->name,
            optional($asset->warranty_end_date)->format('Y-m-d'),
            $asset->warranty_end_date ? now()->diffInDays($asset->warranty_end_date, false) : null,
        ])->all());
    }

    public function endOfLifeCsv()
    {
        $assets = Asset::with(['assetName','unit'])->whereNotNull('purchase_date')->get();
        return $this->csv('end-of-life-report.csv', ['Asset Code','Name','Status','Purchase Date','Useful Life','End Of Life','Days Remaining'], $assets->map(function ($asset) {
            $usefulLife = (int) ($asset->assetName?->usefullife ?? 0);
            $end = $usefulLife > 0 && $asset->purchase_date ? $asset->purchase_date->copy()->addYears($usefulLife) : null;
            return [$asset->asset_code, $asset->assetName?->assetname, $asset->status, optional($asset->purchase_date)->format('Y-m-d'), $usefulLife ?: null, optional($end)->format('Y-m-d'), $end ? now()->diffInDays($end, false) : null];
        })->all());
    }

    public function replacementRecommendationCsv()
    {
        $assets = Asset::withCount('maintenanceRequests')->with(['assetName','unit'])->get();
        return $this->csv('replacement-recommendation-report.csv', ['Asset Code','Name','Status','Maintenance Count','Recommendation'], $assets->map(function ($asset) {
            $recommendation = 'Keep monitoring';
            if (in_array($asset->status, ['obsolete','disposed'], true)) {
                $recommendation = 'Replace / remove from active service';
            } elseif ($asset->maintenance_requests_count >= 3) {
                $recommendation = 'Consider replacement due to frequent maintenance';
            } elseif ($asset->warranty_end_date && now()->greaterThan($asset->warranty_end_date)) {
                $recommendation = 'Warranty expired; assess replacement risk';
            }
            return [$asset->asset_code, $asset->assetName?->assetname, $asset->status, $asset->maintenance_requests_count, $recommendation];
        })->all());
    }

    public function maintenanceHistoryCsv()
    {
        $items = MaintenanceRequest::with(['asset.assetName','requester','approver','maintenanceProvider'])->latest()->get();
        return $this->csv('maintenance-history-report.csv', ['Date','Asset','Fault','Status','Priority','Provider','Estimated Cost','Actual Cost','Requested By','Approved By'], $items->map(fn ($item) => [
            optional($item->created_at)->format('Y-m-d H:i'),
            $item->asset?->asset_code,
            $item->fault_description,
            $item->status,
            $item->priority,
            $item->maintenanceProvider?->providername,
            $item->estimated_cost,
            $item->actual_cost,
            $item->requester?->name,
            $item->approver?->name,
        ])->all());
    }

    public function qrScansCsv()
    {
        $items = AssetScanLog::with(['asset.assetName','scannedBy'])->latest()->get();
        return $this->csv('qr-scan-report.csv', ['Date','Asset','Scanned By','IP','Browser','Platform','Device','Location','Unknown Location','Tamper Suspected'], $items->map(fn ($item) => [
            optional($item->created_at)->format('Y-m-d H:i'),
            $item->asset?->asset_code,
            $item->scannedBy?->name ?? 'Public',
            $item->ip_address,
            $item->browser,
            $item->platform,
            $item->device,
            $item->scan_location,
            $item->is_unknown_location ? 'Yes' : 'No',
            $item->is_tamper_suspected ? 'Yes' : 'No',
        ])->all());
    }

    private function csv(string $filename, array $headers, array $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
