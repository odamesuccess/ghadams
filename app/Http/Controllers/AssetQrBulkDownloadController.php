<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Organization;
use App\Models\Unit;
use App\Support\StreamingZipWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetQrBulkDownloadController extends Controller
{
    /**
     * Unit / Organization selectors and QR counts.
     *
     * Organization membership follows:
     * Organization -> Unit -> Asset
     */
    public function options()
    {
        $user = Auth::user();
        $this->authorizeBulkDownload($user);

        $isSuperAdmin = $user->hasRole('Super Admin');

        $unitCounts = Asset::query()
            ->whereNotNull('qr_code')
            ->when(! $isSuperAdmin, function ($query) use ($user) {
                $query->where('unit_id', $user->unit_id);
            })
            ->selectRaw('unit_id, COUNT(*) AS total')
            ->groupBy('unit_id')
            ->pluck('total', 'unit_id');

        $units = Unit::query()
            ->when(! $isSuperAdmin, function ($query) use ($user) {
                $query->whereKey($user->unit_id);
            })
            ->whereIn('id', $unitCounts->keys())
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'unit_abbreviation',
                'locationname',
                'organization_id',
            ])
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'abbreviation' => $unit->unit_abbreviation,
                'location' => $unit->locationname,
                'organization_id' => $unit->organization_id,
                'qr_count' => (int) ($unitCounts[$unit->id] ?? 0),
            ])
            ->values();

        $organizations = collect();

        if ($isSuperAdmin) {
            $organizationCounts = Asset::query()
                ->join('units', 'units.id', '=', 'assets.unit_id')
                ->whereNull('units.deleted_at')
                ->whereNotNull('assets.qr_code')
                ->whereNotNull('units.organization_id')
                ->selectRaw('units.organization_id, COUNT(*) AS total')
                ->groupBy('units.organization_id')
                ->pluck('total', 'units.organization_id');

            $organizations = Organization::query()
                ->whereIn('id', $organizationCounts->keys())
                ->orderBy('organizations_name')
                ->get([
                    'id',
                    'organizations_name',
                    'organization_abbreviation',
                ])
                ->map(fn (Organization $organization) => [
                    'id' => $organization->id,
                    'name' => $organization->organizations_name,
                    'abbreviation' => $organization->organization_abbreviation,
                    'qr_count' => (int) ($organizationCounts[$organization->id] ?? 0),
                ])
                ->filter(fn (array $organization) => $organization['qr_count'] > 0)
                ->values();
        }

        return response()->json([
            'units' => $units,
            'organizations' => $organizations,
            'can_download_organization' => $isSuperAdmin,
        ]);
    }

    /**
     * Native browser download.
     *
     * No JavaScript POST, no CSRF interaction, no polling and no custom
     * browser progress UI.
     *
     * The ZIP is streamed progressively so the default browser download
     * starts immediately instead of waiting for all 3,003 PNG files to be
     * packaged first.
     */
    public function downloadZip(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $this->authorizeBulkDownload($user);

        $validated = $request->validate([
            'scope' => ['required', 'in:unit,organization'],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $scope = $validated['scope'];
        $scopeId = (int) $validated['id'];
        $isSuperAdmin = $user->hasRole('Super Admin');

        if ($scope === 'organization' && ! $isSuperAdmin) {
            abort(403, 'Only Super Admin can bulk-download QR codes across an organization.');
        }

        if ($scope === 'unit') {
            $target = Unit::query()->findOrFail($scopeId);

            if (! $isSuperAdmin && (int) $target->id !== (int) $user->unit_id) {
                abort(403, 'You can only download QR codes for your own Unit.');
            }

            $targetName = $target->name;
            $targetAbbreviation = $target->unit_abbreviation;

            $total = Asset::query()
                ->where('unit_id', $scopeId)
                ->whereNotNull('qr_code')
                ->count();
        } else {
            $target = Organization::query()->findOrFail($scopeId);

            $targetName = $target->organizations_name;
            $targetAbbreviation = $target->organization_abbreviation;

            $total = Asset::query()
                ->whereHas('unit', function ($query) use ($scopeId) {
                    $query->where('organization_id', $scopeId);
                })
                ->whereNotNull('qr_code')
                ->count();
        }

        abort_if(
            $total < 1,
            404,
            "No generated QR codes were found for {$targetName}."
        );

        $safeTarget = Str::of($targetAbbreviation ?: $targetName)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-_')
            ->lower();

        $filename = sprintf(
            'GhanaDAMS_QR_%s_%s_%s.zip',
            $scope,
            $safeTarget,
            now()->format('Ymd_His')
        );

        /*
         * The callback runs after HTTP headers are ready.
         * QR PNG bytes then flow directly to the browser as the ZIP is built.
         */
        return response()->streamDownload(
            function () use (
                $scope,
                $scopeId,
                $targetName,
                $total,
                $user
            ): void {
                @set_time_limit(0);
                @ini_set('max_execution_time', '0');
                @ini_set('zlib.output_compression', '0');

                ignore_user_abort(true);

                /*
                 * Avoid PHP output buffers holding the stream.
                 */
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }

                $zip = new StreamingZipWriter(flushEveryFiles: 5);

                $manifest = fopen('php://temp/maxmemory:8388608', 'w+');

                if ($manifest === false) {
                    throw new \RuntimeException('Could not create QR register manifest.');
                }

                fputcsv($manifest, [
                    'Asset Code',
                    'Asset Name',
                    'Organization',
                    'Unit',
                    'Location',
                    'Status',
                    'Scan URL',
                    'QR File',
                ]);

                $query = DB::table('assets')
                    ->join('units', 'units.id', '=', 'assets.unit_id')
                    ->leftJoin(
                        'asset_names',
                        'asset_names.id',
                        '=',
                        'assets.assetname_id'
                    )
                    ->leftJoin(
                        'organizations',
                        'organizations.id',
                        '=',
                        'units.organization_id'
                    )
                    ->whereNull('assets.deleted_at')
                    ->whereNull('units.deleted_at')
                    ->whereNotNull('assets.qr_code')
                    ->when(
                        $scope === 'unit',
                        fn ($query) => $query->where('assets.unit_id', $scopeId),
                        fn ($query) => $query->where('units.organization_id', $scopeId)
                    )
                    ->select([
                        'assets.id',
                        'assets.asset_code',
                        'assets.qr_code',
                        'assets.uuid',
                        'assets.scan_token',
                        'assets.status',
                        'asset_names.assetname as asset_name',
                        'units.name as unit_name',
                        'units.unit_abbreviation',
                        'units.locationname',
                        'organizations.organizations_name as organization_name',
                    ])
                    ->orderBy('units.name')
                    ->orderBy('assets.asset_code');

                $added = 0;
                $missing = 0;

                foreach ($query->cursor() as $asset) {
                    $relativeQrPath = $this->normalizePublicPath(
                        (string) $asset->qr_code
                    );

                    if (
                        $relativeQrPath === ''
                        || ! Storage::disk('public')->exists($relativeQrPath)
                    ) {
                        $missing++;
                        continue;
                    }

                    $absoluteQrPath = Storage::disk('public')->path(
                        $relativeQrPath
                    );

                    $safeAssetCode = Str::of($asset->asset_code)
                        ->ascii()
                        ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
                        ->trim('-_');

                    if ((string) $safeAssetCode === '') {
                        $safeAssetCode = 'asset-' . $asset->id;
                    }

                    $unitFolder = Str::of(
                        $asset->unit_abbreviation
                            ?: $asset->unit_name
                            ?: 'Unknown-Unit'
                    )
                        ->ascii()
                        ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
                        ->trim('-_');

                    $zipEntry = $scope === 'organization'
                        ? "QR-Codes/{$unitFolder}/{$safeAssetCode}-{$asset->id}.png"
                        : "QR-Codes/{$safeAssetCode}-{$asset->id}.png";

                    /*
                     * PNGs are stored directly; they are already compressed.
                     */
                    $zip->addFileFromPath(
                        $zipEntry,
                        $absoluteQrPath
                    );

                    $scanToken = $asset->scan_token ?: $asset->uuid;
                    $scanUrl = $scanToken
                        ? url('/scan/' . $scanToken)
                        : '';

                    fputcsv($manifest, [
                        $asset->asset_code,
                        $asset->asset_name ?? '',
                        $asset->organization_name ?? $targetName,
                        $asset->unit_name ?? '',
                        $asset->locationname ?? '',
                        Str::headline((string) $asset->status),
                        $scanUrl,
                        $zipEntry,
                    ]);

                    $added++;
                }

                rewind($manifest);
                $manifestCsv = stream_get_contents($manifest);
                fclose($manifest);

                $zip->addString(
                    'QR_REGISTER.csv',
                    $manifestCsv !== false ? $manifestCsv : ''
                );

                $zip->addString(
                    'README.txt',
                    implode(PHP_EOL, [
                        'GhanaDAMS Bulk QR Code Download',
                        '================================',
                        '',
                        'Scope: ' . Str::headline($scope),
                        'Target: ' . $targetName,
                        'Requested QR records: ' . $total,
                        'QR PNG files included: ' . $added,
                        'Missing physical QR files: ' . $missing,
                        'Generated: ' . now()->format('Y-m-d H:i:s'),
                        'Generated by: ' . $user->name,
                        '',
                        'This ZIP was streamed directly to the default browser',
                        'download handler to avoid long pre-generation timeouts.',
                        '',
                        'QR_REGISTER.csv contains asset code, asset name,',
                        'Organization, Unit, Location, status, scan URL and',
                        'the matching QR PNG filename.',
                    ])
                );

                $zip->finish();
            },
            $filename,
            [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',

                /*
                 * Tell nginx not to accumulate the generated ZIP before
                 * forwarding it to the browser.
                 */
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    /**
     * Async endpoints from the previous 504 patch are intentionally unused.
     * Keeping harmless methods/routes temporarily avoids breaking stale tabs;
     * the frontend now calls downloadZip() directly via GET.
     */
    public function startBuild(Request $request)
    {
        return response()->json([
            'message' => 'Native browser streaming is now enabled. Refresh the QR Register and download again.',
        ], 409);
    }

    public function buildStatus(string $token)
    {
        return response()->json([
            'status' => 'disabled',
            'message' => 'Background polling has been replaced by native browser streaming.',
        ], 410);
    }

    public function downloadBuiltFile(string $token)
    {
        abort(410, 'Background ZIP files have been replaced by native browser streaming.');
    }

    private function authorizeBulkDownload($user): void
    {
        abort_unless(
            $user
                && (
                    $user->can('assetqrcodes.save')
                    || $user->can('save.assetqrcodes')
                    || $user->hasRole('Super Admin')
                ),
            403
        );
    }

    private function normalizePublicPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if (Str::startsWith($path, '/storage/')) {
            return ltrim(Str::after($path, '/storage/'), '/');
        }

        if (Str::startsWith($path, 'storage/')) {
            return ltrim(Str::after($path, 'storage/'), '/');
        }

        return ltrim($path, '/');
    }
}
