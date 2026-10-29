<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetScanLog;
use App\Services\ActivityTrackerService;

class AssetScanController extends Controller
{
    public function show(string $value)
    {
        $value = trim($value);

        $asset = Asset::where('uuid', $value)
            ->orWhere('scan_token', $value)
            ->with([
                'assetName',
                'unit',
                'supplier',
                'maintenanceProvider',
                'source',
            ])
            ->firstOrFail();

        $agent = request()->userAgent();
        $expectedLocation = $asset->unit?->locationname;
        $scanLocation = request()->input('location');

        $log = AssetScanLog::create([
            'asset_id' => $asset->id,
            'scanned_by' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => $agent,
            'browser' => $this->browser($agent),
            'platform' => $this->platform($agent),
            'device' => $this->device($agent),
            'scan_location' => $scanLocation,
            'is_unknown_location' => blank($scanLocation),
            'is_tamper_suspected' => filled($scanLocation) && filled($expectedLocation) && ! str_contains(strtolower($scanLocation), strtolower($expectedLocation)),
        ]);

        ActivityTrackerService::record(
            request(),
            'QR Scan',
            'scanned',
            'Asset QR code scanned',
            'QR code scanned for asset '.$asset->asset_code,
            $asset,
            null,
            $log->toArray()
        );

        return view('scan.asset', compact('asset'));
    }

    private function browser(?string $agent): ?string
    {
        if (! $agent) return null;
        return match (true) {
            str_contains($agent, 'Edg') => 'Microsoft Edge',
            str_contains($agent, 'OPR') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Chrome') => 'Google Chrome',
            str_contains($agent, 'Firefox') => 'Mozilla Firefox',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Unknown Browser',
        };
    }

    private function platform(?string $agent): ?string
    {
        if (! $agent) return null;
        return match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Mac') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Unknown Platform',
        };
    }

    private function device(?string $agent): ?string
    {
        if (! $agent) return null;
        return match (true) {
            str_contains($agent, 'iPad') || str_contains($agent, 'Tablet') => 'Tablet',
            str_contains($agent, 'Mobile') || str_contains($agent, 'Android') || str_contains($agent, 'iPhone') => 'Mobile',
            default => 'Desktop',
        };
    }
}
