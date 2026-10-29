<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class BuildAssetQrBulkZip extends Command
{
    protected $signature = 'assetqrcodes:build-bulk-zip {token}';
    protected $description = 'Build a large Unit/Organization QR ZIP outside the web request to prevent nginx 504 timeouts';

    private const JOB_DIR = 'app/temp/asset-qr-bulk/jobs';
    private const FILE_DIR = 'app/temp/asset-qr-bulk/files';

    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');

        $token = Str::lower((string) $this->argument('token'));

        if (! preg_match('/^[a-z0-9]{40}$/', $token)) {
            $this->error('Invalid bulk QR job token.');
            return self::FAILURE;
        }

        $job = $this->readJob($token);

        if (! $job) {
            $this->error('Bulk QR job not found.');
            return self::FAILURE;
        }

        if (! class_exists(ZipArchive::class)) {
            $this->failJob(
                $token,
                $job,
                'PHP ZipArchive is not available. Enable the PHP ZIP extension.'
            );

            return self::FAILURE;
        }

        try {
            $this->build($token, $job);
            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);

            $this->failJob($token, $job, $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function build(string $token, array $job): void
    {
        $scope = (string) ($job['scope'] ?? '');
        $scopeId = (int) ($job['scope_id'] ?? 0);

        if (! in_array($scope, ['unit', 'organization'], true) || $scopeId < 1) {
            throw new RuntimeException('Bulk QR job contains an invalid target.');
        }

        if ($scope === 'unit') {
            $target = Unit::query()->findOrFail($scopeId);
            $targetName = $target->name;
            $targetAbbreviation = $target->unit_abbreviation;

            $query = $this->baseAssetQuery()
                ->where('assets.unit_id', $scopeId)
                ->orderBy('assets.asset_code');
        } else {
            $target = Organization::query()->findOrFail($scopeId);
            $targetName = $target->organizations_name;
            $targetAbbreviation = $target->organization_abbreviation;

            $query = $this->baseAssetQuery()
                ->where('units.organization_id', $scopeId)
                ->orderBy('units.name')
                ->orderBy('assets.asset_code');
        }

        $total = (clone $query)->count();

        if ($total < 1) {
            throw new RuntimeException("No generated QR codes were found for {$targetName}.");
        }

        $safeTarget = Str::of($targetAbbreviation ?: $targetName)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-_')
            ->lower();

        $timestamp = now()->format('Ymd_His');
        $downloadFilename = "GhanaDAMS_QR_{$scope}_{$safeTarget}_{$timestamp}.zip";

        $fileDir = storage_path(self::FILE_DIR);

        if (! File::isDirectory($fileDir)) {
            File::makeDirectory($fileDir, 0755, true);
        }

        $zipPath = $fileDir . DIRECTORY_SEPARATOR . $token . '.zip';

        if (is_file($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive();

        $opened = $zip->open(
            $zipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE
        );

        if ($opened !== true) {
            throw new RuntimeException('Could not create the bulk QR ZIP archive.');
        }

        $manifestHandle = fopen('php://temp', 'w+');

        if ($manifestHandle === false) {
            $zip->close();
            throw new RuntimeException('Could not create QR register manifest.');
        }

        fputcsv($manifestHandle, [
            'Asset Code',
            'Asset Name',
            'Organization',
            'Unit',
            'Location',
            'Status',
            'Scan URL',
            'QR File',
        ]);

        $job['status'] = 'building';
        $job['progress'] = 0;
        $job['processed'] = 0;
        $job['total'] = $total;
        $job['message'] = "Preparing {$total} QR codes...";
        $job['updated_at'] = now()->toIso8601String();
        $this->writeJob($token, $job);

        $processed = 0;
        $added = 0;
        $missing = 0;

        /*
         * lazyById avoids loading all 3,003 rows into memory at once.
         */
        $query->lazyById(200, 'assets.id', 'id')
            ->each(function ($asset) use (
                &$processed,
                &$added,
                &$missing,
                $scope,
                $targetName,
                $zip,
                $manifestHandle,
                $token,
                &$job,
                $total
            ) {
                $processed++;

                $relativeQrPath = $this->normalizePublicPath((string) $asset->qr_code);

                if (
                    $relativeQrPath === ''
                    || ! Storage::disk('public')->exists($relativeQrPath)
                ) {
                    $missing++;
                } else {
                    $absoluteQrPath = Storage::disk('public')->path($relativeQrPath);

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

                    if ($zip->addFile($absoluteQrPath, $zipEntry)) {
                        /*
                         * PNG is already compressed. Storing it without attempting
                         * DEFLATE again makes large 3,000-file ZIP builds much faster.
                         */
                        if (method_exists($zip, 'setCompressionName')) {
                            @$zip->setCompressionName($zipEntry, ZipArchive::CM_STORE);
                        }

                        $scanToken = $asset->scan_token ?: $asset->uuid;
                        $scanUrl = $scanToken
                            ? url('/scan/' . $scanToken)
                            : '';

                        fputcsv($manifestHandle, [
                            $asset->asset_code,
                            $asset->asset_name ?? '',
                            $targetName,
                            $asset->unit_name ?? '',
                            $asset->locationname ?? '',
                            Str::headline((string) $asset->status),
                            $scanUrl,
                            $zipEntry,
                        ]);

                        $added++;
                    } else {
                        $missing++;
                    }
                }

                if (
                    $processed === 1
                    || $processed % 50 === 0
                    || $processed === $total
                ) {
                    $progress = min(
                        99,
                        (int) floor(($processed / max(1, $total)) * 100)
                    );

                    $job['status'] = 'building';
                    $job['progress'] = $progress;
                    $job['processed'] = $processed;
                    $job['message'] = "Preparing QR ZIP: {$processed} / {$total}";
                    $job['updated_at'] = now()->toIso8601String();
                    $this->writeJob($token, $job);
                }
            });

        rewind($manifestHandle);
        $manifestCsv = stream_get_contents($manifestHandle);
        fclose($manifestHandle);

        $zip->addFromString(
            'QR_REGISTER.csv',
            $manifestCsv !== false ? $manifestCsv : ''
        );

        $zip->addFromString(
            'README.txt',
            implode(PHP_EOL, [
                'GhanaDAMS Bulk QR Code Download',
                '================================',
                '',
                'Scope: ' . Str::headline($scope),
                'Target: ' . $targetName,
                'Generated: ' . now()->format('Y-m-d H:i:s'),
                'QR files included: ' . $added,
                'Missing physical QR files: ' . $missing,
                '',
                'Large ZIP generation was completed in a background Artisan process',
                'to avoid HTTP/nginx gateway timeouts.',
                '',
                'QR_REGISTER.csv contains the asset code, asset name, Organization,',
                'Unit, Location, status, public scan URL and QR filename.',
            ])
        );

        $zip->close();

        clearstatcache(true, $zipPath);

        if ($added < 1 || ! is_file($zipPath)) {
            @unlink($zipPath);
            throw new RuntimeException('No physical QR PNG files could be added to the ZIP.');
        }

        $job['status'] = 'ready';
        $job['progress'] = 100;
        $job['processed'] = $processed;
        $job['total'] = $total;
        $job['message'] = "Ready: {$added} QR codes packaged.";
        $job['download_filename'] = $downloadFilename;
        $job['zip_path'] = $zipPath;
        $job['qr_added'] = $added;
        $job['missing_files'] = $missing;
        $job['file_size'] = filesize($zipPath) ?: null;
        $job['updated_at'] = now()->toIso8601String();

        $this->writeJob($token, $job);

        $this->info("Ready: {$downloadFilename}");
        $this->info("QR files: {$added}; missing: {$missing}");
    }

    private function baseAssetQuery()
    {
        return DB::table('assets')
            ->join('units', 'units.id', '=', 'assets.unit_id')
            ->leftJoin('asset_names', 'asset_names.id', '=', 'assets.assetname_id')
            ->whereNull('assets.deleted_at')
            ->whereNull('units.deleted_at')
            ->whereNotNull('assets.qr_code')
            ->select([
                'assets.id',
                'assets.asset_code',
                'assets.qr_code',
                'assets.uuid',
                'assets.scan_token',
                'assets.status',
                'assets.unit_id',
                'asset_names.assetname as asset_name',
                'units.name as unit_name',
                'units.unit_abbreviation',
                'units.locationname',
                'units.organization_id',
            ]);
    }

    private function jobDirectory(): string
    {
        return storage_path(self::JOB_DIR);
    }

    private function jobPath(string $token): string
    {
        return $this->jobDirectory() . DIRECTORY_SEPARATOR . $token . '.json';
    }

    private function readJob(string $token): ?array
    {
        $path = $this->jobPath($token);

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function writeJob(string $token, array $job): void
    {
        if (! File::isDirectory($this->jobDirectory())) {
            File::makeDirectory($this->jobDirectory(), 0755, true);
        }

        $path = $this->jobPath($token);
        $temp = $path . '.tmp';

        $json = json_encode(
            $job,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        if ($json === false || File::put($temp, $json) === false) {
            throw new RuntimeException('Could not update the bulk QR job status.');
        }

        @rename($temp, $path);
    }

    private function failJob(string $token, array $job, string $message): void
    {
        $job['status'] = 'failed';
        $job['message'] = 'QR ZIP preparation failed.';
        $job['error'] = $message;
        $job['updated_at'] = now()->toIso8601String();

        try {
            $this->writeJob($token, $job);
        } catch (Throwable $ignored) {
            // Do not hide the original build failure.
        }
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
