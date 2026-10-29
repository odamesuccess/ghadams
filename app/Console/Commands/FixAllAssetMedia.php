<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetName;
use App\Services\BulkAssetMediaService;
use Illuminate\Console\Command;

class FixAllAssetMedia extends Command
{
    protected $signature = 'assets:fix-all-media
                            {--force-qr : Regenerate every QR code instead of only missing/broken QR codes}
                            {--chunk=100 : Number of assets processed in each database batch}';

    protected $description = 'One-command GhanaDAMS repair for all existing asset QR codes and real asset-type photographs';

    public function handle(BulkAssetMediaService $service): int
    {
        @set_time_limit(0);

        $catalog = config('asset_type_real_photos', []);
        $dbTypes = AssetName::query()
            ->whereNull('deleted_at')
            ->orderBy('assetname')
            ->pluck('assetname')
            ->all();

        $this->info('============================================================');
        $this->info(' GhanaDAMS - FIX ALL ASSET QR + REAL TYPE PHOTOS');
        $this->info('============================================================');
        $this->newLine();

        $this->line('Project root: ' . base_path());
        $this->line('Correct photo folder: ' . storage_path('app/public/assets/type-images'));
        $this->line('QR folder: ' . storage_path('app/public/assets/qrcodes'));
        $this->line('Configured asset types: ' . count($catalog));
        $this->line('Asset types in database: ' . count($dbTypes));
        $this->line('Assets to inspect: ' . Asset::query()->count());
        $this->newLine();

        if ($catalog === []) {
            $this->error('STOPPED: config/asset_type_real_photos.php is empty.');
            return self::FAILURE;
        }

        $missingConfig = array_values(array_diff($dbTypes, array_keys($catalog)));

        if ($missingConfig !== []) {
            $this->warn('Database asset types not present in the photo catalogue:');
            foreach ($missingConfig as $name) {
                $this->line('  - ' . $name);
            }
            $this->newLine();
        }

        $this->line('Stage 1: clearing old GhanaDAMS-managed SVG/wrong fallback images.');
        $this->line('Stage 2: obtaining one filtered REAL photograph for each configured asset type.');
        $this->line('Stage 3: ensuring every existing asset has a working QR code.');
        $this->line('Stage 4: assigning the correct shared type photo only where an actual uploaded photo is absent.');
        $this->newLine();

        $lastAssetProgress = 0;

        $stats = $service->fixAll(
            forceQr: (bool) $this->option('force-qr'),
            chunkSize: max(1, (int) $this->option('chunk')),
            onProgress: function (string $kind, mixed $subject, array $state) use (&$lastAssetProgress): void {
                if ($kind === 'type') {
                    $resolved = count($state['types'] ?? []);
                    $unresolved = count($state['unresolved'] ?? []);
                    $this->line("  Photo catalogue: {$resolved} resolved, {$unresolved} unresolved");
                    return;
                }

                if ($kind === 'asset') {
                    $processed = (int) ($state['processed'] ?? 0);

                    if ($processed === 1 || $processed - $lastAssetProgress >= 100) {
                        $lastAssetProgress = $processed;
                        $this->line("  Assets processed: {$processed}");
                    }
                }
            },
        );

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Configured asset types', $stats['configured_types']],
                ['Database asset types', $stats['database_types']],
                ['Unresolved photo types', $stats['unresolved_types']],
                ['Assets processed', $stats['processed']],
                ['QR codes generated/repaired', $stats['qr_generated']],
                ['Type photos assigned/upgraded', $stats['images_assigned']],
            ]
        );

        if ($stats['unresolved_types'] > 0) {
            $this->warn(
                'Some photo types were deliberately left unresolved because GhanaDAMS could not find a sufficiently unambiguous real photograph. '
                . 'No unrelated image was attached. Check storage/app/public/assets/type-images/SOURCES.json and storage/logs/laravel.log.'
            );
        } else {
            $this->info('All configured representative asset-type photographs were resolved.');
        }

        $this->newLine();
        $this->info('FIX ALL operation completed.');

        return self::SUCCESS;
    }
}
