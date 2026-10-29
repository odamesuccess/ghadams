<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\DemoAssetQrService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CorrectRealisticDemoAssetTypes extends Command
{
    protected $signature = 'assets:correct-demo-types
                            {--execute : Apply the correction}
                            {--confirm= : Must be FIX-DEMO-TYPES}';

    protected $description = 'Remove Notice Board, Whiteboard and Laptop Bag from the realistic demo dataset and replace them so the demo total remains unchanged';

    private const DESCRIPTION =
        'GhanaDAMS realistic demonstration data - synthetic record; not a verified physical GES asset.';

    private const EXCLUDED_TYPES = [
        'Notice Board',
        'Whiteboard',
        'Laptop Bag',
    ];

    /**
     * 188 replacement slots, matching the original 57 + 70 + 61 excluded rows.
     * If the current excluded count is different, the pool repeats proportionally.
     */
    private const REPLACEMENT_POOL = [
        'Desktop Computer' => 25,
        'Laptop Computer' => 20,
        'LaserJet Printer' => 20,
        'UPS Battery Backup' => 15,
        'Office Desk' => 25,
        'Office Chair' => 30,
        'Filing Cabinet' => 15,
        'Air Conditioner' => 10,
        'Standing Fan' => 10,
        'Telephone Set' => 8,
        'Network Switch' => 5,
        'CCTV Camera' => 5,
    ];

    private const BRANDS = [
        'Desktop Computer' => ['Dell', 'HP', 'Lenovo', 'Acer'],
        'Laptop Computer' => ['HP', 'Dell', 'Lenovo', 'ASUS'],
        'LaserJet Printer' => ['HP', 'Canon', 'Brother'],
        'UPS Battery Backup' => ['APC', 'Eaton', 'Mercury'],
        'Office Desk' => ['Local Fabrication', 'OfficePoint', 'Godrej'],
        'Office Chair' => ['Local Fabrication', 'OfficePoint', 'Godrej'],
        'Filing Cabinet' => ['Godrej', 'OfficePoint', 'Local Fabrication'],
        'Air Conditioner' => ['Hisense', 'LG', 'Samsung', 'Midea'],
        'Standing Fan' => ['Binatone', 'Nasco', 'Midea'],
        'Telephone Set' => ['Panasonic', 'Yealink', 'Grandstream'],
        'Network Switch' => ['Cisco', 'TP-Link', 'D-Link', 'Ubiquiti'],
        'CCTV Camera' => ['Hikvision', 'Dahua', 'Uniview'],
    ];

    private const COST_RANGES = [
        'Desktop Computer' => [4500, 12000],
        'Laptop Computer' => [5000, 18000],
        'LaserJet Printer' => [2500, 8000],
        'UPS Battery Backup' => [1000, 6000],
        'Office Desk' => [1200, 5000],
        'Office Chair' => [600, 3500],
        'Filing Cabinet' => [900, 4500],
        'Air Conditioner' => [3500, 10000],
        'Standing Fan' => [300, 1200],
        'Telephone Set' => [300, 1800],
        'Network Switch' => [1500, 12000],
        'CCTV Camera' => [800, 5000],
    ];

    public function handle(DemoAssetQrService $qrService): int
    {
        @set_time_limit(0);

        $excludedRows = Asset::query()
            ->where('description', self::DESCRIPTION)
            ->whereHas('assetName', fn ($q) => $q->whereIn('assetname', self::EXCLUDED_TYPES))
            ->with(['assetName:id,assetname', 'unit:id,name,unit_abbreviation,location_abbreviation,organization_id'])
            ->orderBy('id')
            ->get();

        $demoTotal = Asset::query()
            ->where('description', self::DESCRIPTION)
            ->count();

        $this->newLine();
        $this->info('============================================================');
        $this->info(' GhanaDAMS - CORRECT REALISTIC DEMO ASSET TYPES');
        $this->info('============================================================');
        $this->newLine();

        $this->line('Current active realistic demo assets: ' . $demoTotal);
        $this->line('Rows to replace: ' . $excludedRows->count());
        $this->newLine();

        $summary = $excludedRows
            ->groupBy(fn ($asset) => $asset->assetName?->assetname ?? 'Unknown')
            ->map(fn ($items) => $items->count())
            ->sortKeys();

        $this->table(
            ['Excluded Demo Type', 'Rows To Replace'],
            collect(self::EXCLUDED_TYPES)->map(
                fn ($name) => [$name, (int) ($summary[$name] ?? 0)]
            )->all()
        );

        $this->line('Replacement records will use only controlled/trackable asset categories.');
        $this->line('The total demo record count will remain unchanged.');
        $this->line('Actual/manual assets are not touched.');
        $this->newLine();

        if (! (bool) $this->option('execute')) {
            $this->warn('DRY RUN ONLY. Nothing was changed.');
            $this->line('');
            $this->line('Run:');
            $this->line('  php artisan assets:correct-demo-types --execute --confirm=FIX-DEMO-TYPES');
            return self::SUCCESS;
        }

        if ((string) $this->option('confirm') !== 'FIX-DEMO-TYPES') {
            $this->error('Execution blocked: --confirm=FIX-DEMO-TYPES is required.');
            return self::FAILURE;
        }

        if ($excludedRows->isEmpty()) {
            $this->info('No excluded demo types remain. Nothing to change.');
            return self::SUCCESS;
        }

        $assetNames = DB::table('asset_names')
            ->whereNull('deleted_at')
            ->whereIn('assetname', array_keys(self::REPLACEMENT_POOL))
            ->get()
            ->keyBy('assetname');

        $missing = array_values(array_diff(
            array_keys(self::REPLACEMENT_POOL),
            $assetNames->keys()->all()
        ));

        if ($missing !== []) {
            throw new RuntimeException('Missing replacement Asset Types: ' . implode(', ', $missing));
        }

        $photoPools = $this->buildTypePhotoPools();
        $replacementSequence = $this->replacementSequence();
        $backupPath = $this->writeBackup($excludedRows);

        $this->line('Backup created:');
        $this->line('  ' . $backupPath);
        $this->newLine();

        $newIds = [];

        DB::beginTransaction();

        try {
            foreach ($excludedRows as $index => $oldAsset) {
                $replacementType = $replacementSequence[$index % count($replacementSequence)];
                $assetName = $assetNames[$replacementType];
                $unit = $oldAsset->unit;

                if (! $unit) {
                    throw new RuntimeException("Unit not found for demo asset ID {$oldAsset->id}.");
                }

                $purchaseDate = $this->purchaseDateForStatus($oldAsset->status);
                $warrantyMonths = $this->warrantyMonths($replacementType);
                $warrantyEnd = $purchaseDate->copy()->addMonths($warrantyMonths);
                $brand = $this->randomFrom(self::BRANDS[$replacementType]);
                [$minCost, $maxCost] = self::COST_RANGES[$replacementType];

                $assetCode = sprintf(
                    'GES\\%s\\%s\\%s\\%d\\%s\\R%s',
                    $this->cleanCode($unit->unit_abbreviation ?: 'UNIT'),
                    $this->cleanCode($unit->location_abbreviation ?: 'LOC'),
                    $this->cleanCode($assetName->assetname_abbreviation),
                    $index + 1,
                    $purchaseDate->format('Y'),
                    now()->format('ymdHis') . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)
                );

                $id = DB::table('assets')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'scan_token' => (string) Str::uuid(),
                    'asset_code' => $assetCode,
                    'assetname_id' => $assetName->id,
                    'brand' => $brand,
                    'source_id' => $oldAsset->source_id,
                    'purchase_cost' => $this->money($minCost, $maxCost),
                    'purchase_date' => $purchaseDate->toDateString(),
                    'warranty_duration' => $warrantyMonths,
                    'warranty_end_date' => $warrantyEnd->toDateString(),
                    'description' => self::DESCRIPTION,
                    'serial_number' => sprintf(
                        '%s-%s-%06d',
                        $this->cleanCode(Str::substr($brand, 0, 4)),
                        $purchaseDate->format('y'),
                        random_int(1, 999999)
                    ),
                    'asset_image' => $this->randomTypePhoto($replacementType, $photoPools),
                    'qr_code' => null,
                    'supplier_id' => $oldAsset->supplier_id,
                    'maintenance_id' => $oldAsset->maintenance_id,
                    'user_id' => $oldAsset->user_id,
                    'unit_id' => $oldAsset->unit_id,
                    'organization_id' => $oldAsset->organization_id,
                    'status' => $oldAsset->status,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);

                $newIds[] = $id;

                $oldAsset->delete();
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->info('Replacement database records created. Generating QR codes...');

        foreach (array_chunk($newIds, 50) as $chunk) {
            $assets = Asset::query()->whereIn('id', $chunk)->get();

            foreach ($assets as $asset) {
                $qrService->generateForAsset($asset);
            }

            $this->line('  QR generated: ' . min(count($newIds), array_search($chunk, array_chunk($newIds, 50), true) * 50 + count($chunk)));
        }

        $remainingExcluded = Asset::query()
            ->where('description', self::DESCRIPTION)
            ->whereHas('assetName', fn ($q) => $q->whereIn('assetname', self::EXCLUDED_TYPES))
            ->count();

        $finalDemoTotal = Asset::query()
            ->where('description', self::DESCRIPTION)
            ->count();

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Excluded demo rows replaced', count($newIds)],
                ['Excluded demo rows remaining', $remainingExcluded],
                ['Final active demo records', $finalDemoTotal],
            ]
        );

        if ($remainingExcluded !== 0) {
            $this->error('Some excluded demo records remain.');
            return self::FAILURE;
        }

        $this->info('Demo asset-type correction completed successfully.');

        return self::SUCCESS;
    }

    private function replacementSequence(): array
    {
        $sequence = [];

        foreach (self::REPLACEMENT_POOL as $type => $count) {
            for ($i = 0; $i < $count; $i++) {
                $sequence[] = $type;
            }
        }

        shuffle($sequence);

        return $sequence;
    }

    private function buildTypePhotoPools(): array
    {
        $pools = [];

        foreach (array_keys(self::REPLACEMENT_POOL) as $assetType) {
            $slug = Str::slug($assetType);
            $pool = [];
            $folder = storage_path('app/public/assets/type-images/' . $slug);

            if (is_dir($folder)) {
                foreach (scandir($folder) ?: [] as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $full = $folder . DIRECTORY_SEPARATOR . $file;

                    if (! is_file($full)) {
                        continue;
                    }

                    $ext = Str::lower(pathinfo($file, PATHINFO_EXTENSION));

                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $pool[] = 'assets/type-images/' . $slug . '/' . $file;
                    }
                }
            }

            if ($pool === []) {
                foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                    $legacy = storage_path('app/public/assets/type-images/' . $slug . '.' . $ext);

                    if (is_file($legacy)) {
                        $pool[] = 'assets/type-images/' . $slug . '.' . $ext;
                    }
                }
            }

            $pools[$assetType] = array_values(array_unique($pool));
        }

        return $pools;
    }

    private function randomTypePhoto(string $assetType, array $pools): ?string
    {
        $pool = $pools[$assetType] ?? [];

        return $pool === [] ? null : $pool[array_rand($pool)];
    }

    private function purchaseDateForStatus(string $status): Carbon
    {
        [$start, $end] = match ($status) {
            'disposed' => ['2010-01-01', '2019-12-31'],
            'obsolete' => ['2011-01-01', '2020-12-31'],
            'under_maintenance' => ['2016-01-01', '2025-12-31'],
            default => ['2018-01-01', '2026-08-31'],
        };

        return Carbon::createFromTimestamp(
            random_int(Carbon::parse($start)->timestamp, Carbon::parse($end)->timestamp)
        )->startOfDay();
    }

    private function warrantyMonths(string $type): int
    {
        if (in_array($type, ['Air Conditioner', 'Network Switch'], true)) {
            return $this->randomFrom([24, 36]);
        }

        if (in_array($type, ['Office Desk', 'Office Chair', 'Filing Cabinet', 'Standing Fan'], true)) {
            return $this->randomFrom([6, 12, 24]);
        }

        return $this->randomFrom([12, 24, 36]);
    }

    private function money(int $min, int $max): string
    {
        return number_format(random_int($min * 100, $max * 100) / 100, 2, '.', '');
    }

    private function cleanCode(string $value): string
    {
        $value = Str::upper($value);
        $value = preg_replace('/[^A-Z0-9-]/', '', $value) ?? $value;

        return $value !== '' ? $value : 'X';
    }

    private function randomFrom(array $items): mixed
    {
        if ($items === []) {
            throw new RuntimeException('Cannot choose from an empty list.');
        }

        return $items[array_rand($items)];
    }

    private function writeBackup($rows): string
    {
        $dir = storage_path('app/cleanup_backups/realistic_demo_type_correction');

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'before_' . now()->format('Ymd_His') . '.json';

        File::put(
            $path,
            json_encode(
                [
                    'created_at' => now()->toIso8601String(),
                    'excluded_types' => self::EXCLUDED_TYPES,
                    'count' => $rows->count(),
                    'records' => $rows->map(fn ($asset) => $asset->getAttributes())->values()->all(),
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )
        );

        return $path;
    }
}
