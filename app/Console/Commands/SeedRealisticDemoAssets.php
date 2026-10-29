<?php

namespace App\Console\Commands;

use App\Services\DemoAssetQrService;
use App\Models\Asset;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SeedRealisticDemoAssets extends Command
{
    protected $signature = 'assets:seed-realistic-demo
                            {--execute : Actually insert the demonstration records}
                            {--confirm= : Safety token; must be CREATE-3000-DEMO when --execute is used}
                            {--remove : Soft-delete only records created by this realistic demo seeder}
                            {--remove-confirm= : Safety token; must be REMOVE-DEMO when --remove is used}
                            {--skip-media : Do not run QR/image backfill after inserting}';

    protected $description = 'Create a realistic but clearly synthetic 3,000-record GhanaDAMS asset demonstration dataset (Option B multi-photo mode)';

    private const COUNT = 3000;

    private const DESCRIPTION =
        'GhanaDAMS realistic demonstration data - synthetic record; not a verified physical GES asset.';

    private const DISTRIBUTION = [
        'Desktop Computer' => 303,
        'Laptop Computer' => 263,
        'LaserJet Printer' => 211,
        'Photocopier Machine' => 70,
        'Scanner' => 78,
        'Projector' => 96,
        'UPS Battery Backup' => 172,
        'Network Switch' => 75,
        'Wireless Router' => 52,
        'External Hard Drive' => 87,
        'Office Desk' => 286,
        'Office Chair' => 343,
        'Filing Cabinet' => 172,
        'Bookshelf' => 87,
        'Conference Table' => 35,
        'Air Conditioner' => 97,
        'Standing Fan' => 132,
        'Water Dispenser' => 52,
        'Telephone Set' => 112,
        'Television Set' => 30,
        'Server Machine' => 17,
        'Network Rack Cabinet' => 13,
        'Biometric Attendance Device' => 13,
        'CCTV Camera' => 48,
        'Public Address System' => 17,
        'Generator Set' => 9,
        'Extension Board' => 130,
    ];

    private const BRANDS = [
        'Desktop Computer' => ['Dell', 'HP', 'Lenovo', 'Acer'],
        'Laptop Computer' => ['HP', 'Dell', 'Lenovo', 'ASUS'],
        'LaserJet Printer' => ['HP', 'Canon', 'Brother'],
        'Photocopier Machine' => ['Canon', 'Ricoh', 'Xerox', 'Kyocera'],
        'Scanner' => ['Epson', 'Canon', 'HP'],
        'Projector' => ['Epson', 'BenQ', 'ViewSonic', 'Panasonic'],
        'UPS Battery Backup' => ['APC', 'Eaton', 'Mercury'],
        'Network Switch' => ['Cisco', 'TP-Link', 'D-Link', 'Ubiquiti'],
        'Wireless Router' => ['TP-Link', 'MikroTik', 'D-Link', 'Ubiquiti'],
        'External Hard Drive' => ['Seagate', 'Western Digital', 'Toshiba'],
        'Office Desk' => ['Local Fabrication', 'OfficePoint', 'Godrej'],
        'Office Chair' => ['Local Fabrication', 'OfficePoint', 'Godrej'],
        'Filing Cabinet' => ['Godrej', 'OfficePoint', 'Local Fabrication'],
        'Bookshelf' => ['Local Fabrication', 'OfficePoint', 'Godrej'],
        'Conference Table' => ['Local Fabrication', 'OfficePoint', 'Godrej'],
        'Air Conditioner' => ['Hisense', 'LG', 'Samsung', 'Midea'],
        'Standing Fan' => ['Binatone', 'Nasco', 'Midea'],
        'Water Dispenser' => ['Nasco', 'Hisense', 'Midea'],
        'Telephone Set' => ['Panasonic', 'Yealink', 'Grandstream'],
        'Television Set' => ['Samsung', 'LG', 'Hisense', 'TCL'],
        'Server Machine' => ['Dell', 'HPE', 'Lenovo'],
        'Network Rack Cabinet' => ['Sysracks', 'APC', 'Tripp Lite'],
        'Biometric Attendance Device' => ['ZKTeco', 'Hikvision', 'Suprema'],
        'CCTV Camera' => ['Hikvision', 'Dahua', 'Uniview'],
        'Public Address System' => ['Ahuja', 'TOA', 'Yamaha', 'Bosch'],
        'Generator Set' => ['Perkins', 'Cummins', 'FG Wilson'],
        'Extension Board' => ['Master', 'Belkin', 'APC'],
    ];

    private const COST_RANGES = [
        'Desktop Computer' => [4500, 12000],
        'Laptop Computer' => [5000, 18000],
        'LaserJet Printer' => [2500, 8000],
        'Photocopier Machine' => [12000, 50000],
        'Scanner' => [1500, 5000],
        'Projector' => [4000, 15000],
        'UPS Battery Backup' => [1000, 6000],
        'Network Switch' => [1500, 12000],
        'Wireless Router' => [500, 5000],
        'External Hard Drive' => [500, 2500],
        'Office Desk' => [1200, 5000],
        'Office Chair' => [600, 3500],
        'Filing Cabinet' => [900, 4500],
        'Bookshelf' => [900, 4000],
        'Conference Table' => [2500, 12000],
        'Air Conditioner' => [3500, 10000],
        'Standing Fan' => [300, 1200],
        'Water Dispenser' => [500, 2500],
        'Telephone Set' => [300, 1800],
        'Television Set' => [3000, 12000],
        'Server Machine' => [25000, 120000],
        'Network Rack Cabinet' => [5000, 25000],
        'Biometric Attendance Device' => [1500, 7000],
        'CCTV Camera' => [800, 5000],
        'Public Address System' => [5000, 30000],
        'Generator Set' => [25000, 150000],
        'Extension Board' => [100, 800],
    ];

    public function handle(DemoAssetQrService $qrService): int
    {
        @set_time_limit(0);

        if ((bool) $this->option('remove')) {
            return $this->removeDemo();
        }

        if (array_sum(self::DISTRIBUTION) !== self::COUNT) {
            $this->error('Internal distribution error: the Asset-Type allocation does not total 3,000.');
            return self::FAILURE;
        }

        $context = $this->loadContext();
        $photoPools = $this->buildTypePhotoPools();

        $this->newLine();
        $this->info('============================================================');
        $this->info(' GhanaDAMS - 3,000 REALISTIC DEMONSTRATION ASSETS (OPTION B)');
        $this->info('============================================================');
        $this->newLine();

        $this->line('Existing active assets: ' . DB::table('assets')->whereNull('deleted_at')->count());
        $this->line('New realistic DEMO assets to create: ' . self::COUNT);
        $this->line('Asset Types represented: ' . count(self::DISTRIBUTION));
        $this->line('Active Units available: ' . count($context['units']));
        $this->line('Image mode: multiple cached type photos per Asset Type where available');
        $this->newLine();

        $this->warn('These records are realistic SYNTHETIC/DEMO DATA.');
        $this->warn('They must not be represented as verified physical GES inventory or empirical research findings.');
        $this->newLine();

        $this->showPlan($photoPools);

        $existingDemo = DB::table('assets')
            ->where('description', self::DESCRIPTION)
            ->count();

        if ($existingDemo > 0) {
            $this->error(
                "This seeder has already created {$existingDemo} demo record(s), including soft-deleted records."
            );
            $this->line('Remove the previous set first with:');
            $this->line('  php artisan assets:seed-realistic-demo --remove --remove-confirm=REMOVE-DEMO');
            return self::FAILURE;
        }

        if (! (bool) $this->option('execute')) {
            $this->warn('DRY RUN ONLY. Nothing was inserted.');
            $this->line('');
            $this->line('To create the full 3,000-record dataset:');
            $this->line('  php artisan assets:seed-realistic-demo --execute --confirm=CREATE-3000-DEMO');
            return self::SUCCESS;
        }

        if ((string) $this->option('confirm') !== 'CREATE-3000-DEMO') {
            $this->error('Execution blocked: --confirm=CREATE-3000-DEMO is required.');
            return self::FAILURE;
        }

        $statusPool = array_merge(
            array_fill(0, 2880, 'active'),
            array_fill(0, 60, 'under_maintenance'),
            array_fill(0, 30, 'obsolete'),
            array_fill(0, 30, 'disposed'),
        );

        shuffle($statusPool);

        $now = now();
        $runTag = $now->format('ymdHis');
        $globalSequence = 0;
        $rows = [];
        $created = 0;

        $this->newLine();
        $this->info('Creating realistic demonstration records...');

        DB::beginTransaction();

        try {
            foreach (self::DISTRIBUTION as $assetType => $quantity) {
                $assetName = $context['assetNames'][$assetType];

                for ($typeSequence = 1; $typeSequence <= $quantity; $typeSequence++) {
                    $globalSequence++;
                    $status = $statusPool[$globalSequence - 1];
                    $unit = $this->pickUnit($context['units'], $assetType);
                    $purchaseDate = $this->purchaseDateForStatus($status);
                    $warrantyMonths = $this->warrantyMonths($assetType);
                    $warrantyEnd = $purchaseDate->copy()->addMonths($warrantyMonths);
                    $brand = $this->randomFrom(self::BRANDS[$assetType]);
                    [$minCost, $maxCost] = self::COST_RANGES[$assetType];

                    $unitCode = $this->cleanCode($unit->unit_abbreviation ?: 'UNIT');
                    $locationCode = $this->cleanCode($unit->location_abbreviation ?: 'LOC');
                    $assetAbbr = $this->cleanCode($assetName->assetname_abbreviation ?: Str::upper(Str::substr(Str::slug($assetType, ''), 0, 4)));

                    $assetCode = sprintf(
                        'GES\\%s\\%s\\%s\\%d\\%s\\D%s',
                        $unitCode,
                        $locationCode,
                        $assetAbbr,
                        $typeSequence,
                        $purchaseDate->format('Y'),
                        $runTag . str_pad((string) $globalSequence, 4, '0', STR_PAD_LEFT),
                    );

                    $serial = sprintf(
                        '%s-%s-%06d',
                        $this->cleanCode(Str::substr($brand, 0, 4)),
                        $purchaseDate->format('y'),
                        random_int(1, 999999),
                    );

                    $createdAt = Carbon::now()
                        ->subDays(random_int(0, 30))
                        ->subMinutes(random_int(0, 1440));

                    $rows[] = [
                        'uuid' => (string) Str::uuid(),
                        'scan_token' => (string) Str::uuid(),
                        'asset_code' => $assetCode,
                        'assetname_id' => $assetName->id,
                        'brand' => $brand,
                        'source_id' => $this->weightedSourceId($context['sources']),
                        'purchase_cost' => $this->money($minCost, $maxCost),
                        'purchase_date' => $purchaseDate->toDateString(),
                        'warranty_duration' => $warrantyMonths,
                        'warranty_end_date' => $warrantyEnd->toDateString(),
                        'description' => self::DESCRIPTION,
                        'serial_number' => $serial,
                        'asset_image' => $this->randomTypePhoto($assetType, $photoPools),
                        'qr_code' => null,
                        'supplier_id' => $this->randomFrom($context['supplierIds']),
                        'maintenance_id' => $this->randomFrom($context['maintenanceIds']),
                        'user_id' => $context['userId'],
                        'unit_id' => $unit->id,
                        'organization_id' => $unit->organization_id ?: $context['organizationId'],
                        'status' => $status,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                        'deleted_at' => null,
                    ];

                    if (count($rows) >= 250) {
                        DB::table('assets')->insert($rows);
                        $created += count($rows);
                        $rows = [];
                        $this->line("  Inserted: {$created} / " . self::COUNT);
                    }
                }
            }

            if ($rows !== []) {
                DB::table('assets')->insert($rows);
                $created += count($rows);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->line("  Inserted: {$created} / " . self::COUNT);
        $this->newLine();

        if ($created !== self::COUNT) {
            $this->error("Expected 3,000 inserts but created {$created}.");
            return self::FAILURE;
        }

        if (! (bool) $this->option('skip-media')) {
            $this->info('Generating QR codes for the new demo records while preserving Option B image variation...');

            $demoAssets = Asset::query()
                ->where('description', self::DESCRIPTION)
                ->whereNull('qr_code')
                ->orderBy('id')
                ->get();

            $generatedQr = 0;

            foreach ($demoAssets as $asset) {
                $qrService->generateForAsset($asset);
                $generatedQr++;

                if ($generatedQr === 1 || $generatedQr % 250 === 0) {
                    $this->line("  QR generated: {$generatedQr}");
                }
            }

            $this->table(
                ['Media Result', 'Count'],
                [
                    ['QR codes generated', $generatedQr],
                    ['Option B assigned images preserved', $demoAssets->count()],
                ]
            );
        }

        $this->newLine();
        $this->info('3,000-record realistic demonstration dataset created successfully.');
        $this->table(
            ['Status', 'Target Count'],
            [
                ['active', 2880],
                ['under_maintenance', 60],
                ['obsolete', 30],
                ['disposed', 30],
                ['TOTAL', 3000],
            ]
        );

        $this->line('Demo marker used for safe removal:');
        $this->line('  ' . self::DESCRIPTION);
        $this->line('');
        $this->line('To remove ONLY this demo dataset later:');
        $this->line('  php artisan assets:seed-realistic-demo --remove --remove-confirm=REMOVE-DEMO');

        return self::SUCCESS;
    }

    private function loadContext(): array
    {
        $assetNames = DB::table('asset_names')
            ->whereNull('deleted_at')
            ->whereIn('assetname', array_keys(self::DISTRIBUTION))
            ->get()
            ->keyBy('assetname');

        $missingTypes = array_values(array_diff(
            array_keys(self::DISTRIBUTION),
            $assetNames->keys()->all()
        ));

        if ($missingTypes !== []) {
            throw new RuntimeException(
                'Missing required Asset Types: ' . implode(', ', $missingTypes)
            );
        }

        $units = DB::table('units')
            ->whereNull('deleted_at')
            ->select(
                'id',
                'name',
                'unit_abbreviation',
                'locationname',
                'location_abbreviation',
                'organization_id'
            )
            ->get()
            ->all();

        if ($units === []) {
            throw new RuntimeException('No active Units were found.');
        }

        $supplierIds = DB::table('suppliers')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $maintenanceIds = DB::table('maintenance_providers')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sources = DB::table('sources')
            ->whereNull('deleted_at')
            ->select('id', 'name')
            ->get()
            ->all();

        if ($supplierIds === [] || $maintenanceIds === [] || $sources === []) {
            throw new RuntimeException(
                'Suppliers, Maintenance Providers and Sources must contain active records before seeding.'
            );
        }

        $userId = (int) DB::table('users')->orderBy('id')->value('id');

        if ($userId <= 0) {
            throw new RuntimeException('No user exists for assets.user_id.');
        }

        $organizationId = (int) (
            collect($units)->pluck('organization_id')->filter()->first()
            ?: DB::table('organizations')->orderBy('id')->value('id')
        );

        if ($organizationId <= 0) {
            throw new RuntimeException('No Organization exists for assets.organization_id.');
        }

        return [
            'assetNames' => $assetNames,
            'units' => $units,
            'supplierIds' => $supplierIds,
            'maintenanceIds' => $maintenanceIds,
            'sources' => $sources,
            'userId' => $userId,
            'organizationId' => $organizationId,
        ];
    }

    private function showPlan(array $photoPools): void
    {
        $rows = [];

        foreach (self::DISTRIBUTION as $type => $count) {
            $rows[] = [$type, $count, count($photoPools[$type] ?? [])];
        }

        $rows[] = ['TOTAL', array_sum(self::DISTRIBUTION), ''];

        $this->table(['Asset Type', 'Demo Records', 'Photo Pool'], $rows);

        $this->table(
            ['Status', 'Planned Count'],
            [
                ['active', 2880],
                ['under_maintenance', 60],
                ['obsolete', 30],
                ['disposed', 30],
                ['TOTAL', 3000],
            ]
        );
    }

    private function buildTypePhotoPools(): array
    {
        $pools = [];

        foreach (array_keys(self::DISTRIBUTION) as $assetType) {
            $slug = Str::slug($assetType);
            $pool = [];
            $folder = storage_path('app/public/assets/type-images/' . $slug);

            if (is_dir($folder)) {
                $files = scandir($folder) ?: [];

                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $full = $folder . DIRECTORY_SEPARATOR . $file;

                    if (! is_file($full)) {
                        continue;
                    }

                    $ext = Str::lower(pathinfo($file, PATHINFO_EXTENSION));

                    if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        continue;
                    }

                    $pool[] = 'assets/type-images/' . $slug . '/' . $file;
                }
            }

            if ($pool === []) {
                foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
                    $legacy = storage_path('app/public/assets/type-images/' . $slug . '.' . $extension);

                    if (is_file($legacy)) {
                        $pool[] = 'assets/type-images/' . $slug . '.' . $extension;
                    }
                }
            }

            $pools[$assetType] = array_values(array_unique($pool));
        }

        return $pools;
    }

    private function randomTypePhoto(string $assetType, array $photoPools): ?string
    {
        $pool = $photoPools[$assetType] ?? [];

        if ($pool === []) {
            return null;
        }

        return $pool[array_rand($pool)];
    }

    private function pickUnit(array $units, string $assetType): object
    {
        $specialised = [
            'Server Machine',
            'Network Rack Cabinet',
            'Network Switch',
            'Wireless Router',
            'Biometric Attendance Device',
            'CCTV Camera',
            'External Hard Drive',
        ];

        if (in_array($assetType, $specialised, true) && random_int(1, 100) <= 65) {
            $preferred = array_values(array_filter(
                $units,
                fn ($unit) =>
                    str_contains(Str::upper((string) $unit->unit_abbreviation), 'ICT')
                    || str_contains(Str::lower((string) $unit->name), 'ict')
                    || str_contains(Str::lower((string) $unit->name), 'digital')
            ));

            if ($preferred !== []) {
                return $this->randomFrom($preferred);
            }
        }

        return $this->randomFrom($units);
    }

    private function purchaseDateForStatus(string $status): Carbon
    {
        [$start, $end] = match ($status) {
            'disposed' => ['2010-01-01', '2019-12-31'],
            'obsolete' => ['2011-01-01', '2020-12-31'],
            'under_maintenance' => ['2016-01-01', '2025-12-31'],
            default => ['2018-01-01', '2026-08-31'],
        };

        $startTs = Carbon::parse($start)->timestamp;
        $endTs = Carbon::parse($end)->timestamp;

        return Carbon::createFromTimestamp(random_int($startTs, $endTs))->startOfDay();
    }

    private function warrantyMonths(string $assetType): int
    {
        $longer = [
            'Server Machine',
            'Generator Set',
            'Air Conditioner',
            'Photocopier Machine',
            'Network Switch',
            'Network Rack Cabinet',
        ];

        if (in_array($assetType, $longer, true)) {
            return $this->randomFrom([24, 36]);
        }

        $short = [
            'Extension Board',
            'Standing Fan',
            'Office Chair',
            'Office Desk',
            'Bookshelf',
            'Filing Cabinet',
            'Conference Table',
        ];

        if (in_array($assetType, $short, true)) {
            return $this->randomFrom([6, 12, 24]);
        }

        return $this->randomFrom([12, 24, 36]);
    }

    private function weightedSourceId(array $sources): int
    {
        $roll = random_int(1, 100);

        $find = function (array $needles) use ($sources): ?int {
            foreach ($sources as $source) {
                $name = Str::lower((string) $source->name);

                foreach ($needles as $needle) {
                    if (str_contains($name, Str::lower($needle))) {
                        return (int) $source->id;
                    }
                }
            }

            return null;
        };

        if ($roll <= 65) {
            return $find(['government procurement', 'procurement'])
                ?: (int) $this->randomFrom($sources)->id;
        }

        if ($roll <= 85) {
            return $find(['local vendor', 'purchase'])
                ?: (int) $this->randomFrom($sources)->id;
        }

        if ($roll <= 92) {
            return $find(['donation'])
                ?: (int) $this->randomFrom($sources)->id;
        }

        if ($roll <= 97) {
            return $find(['ngo'])
                ?: (int) $this->randomFrom($sources)->id;
        }

        return $find(['international aid', 'international'])
            ?: (int) $this->randomFrom($sources)->id;
    }

    private function money(int $min, int $max): string
    {
        $pesewas = random_int($min * 100, $max * 100);

        return number_format($pesewas / 100, 2, '.', '');
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
            throw new RuntimeException('Cannot select from an empty list.');
        }

        return $items[array_rand($items)];
    }

    private function removeDemo(): int
    {
        if ((string) $this->option('remove-confirm') !== 'REMOVE-DEMO') {
            $this->error('Removal blocked: --remove-confirm=REMOVE-DEMO is required.');
            return self::FAILURE;
        }

        $count = DB::table('assets')
            ->whereNull('deleted_at')
            ->where('description', self::DESCRIPTION)
            ->count();

        if ($count === 0) {
            $this->info('No active realistic demo records remain.');
            return self::SUCCESS;
        }

        $now = now();

        $deleted = DB::table('assets')
            ->whereNull('deleted_at')
            ->where('description', self::DESCRIPTION)
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);

        $this->info("Soft-deleted {$deleted} realistic demonstration asset record(s).");
        $this->line('Existing genuine/manual asset records were not touched.');

        return self::SUCCESS;
    }
}
