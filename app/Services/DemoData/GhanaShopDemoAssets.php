<?php

namespace App\Services\DemoData;

use App\Models\Asset;
use App\Services\AssetCodeGenerator;
use App\Services\DemoAssetQrService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A small SYNTHETIC demo set of assets modelled on products that large Ghanaian
 * shops list in Ghana cedis (config/demo_ghana_shop.php).
 *
 * Every created asset is recorded in demo_dataset_records under its own dataset
 * name, so remove() deletes exactly these rows and nothing else.
 */
class GhanaShopDemoAssets
{
    public function __construct(private array $config) {}

    public static function make(): self
    {
        return new self((array) config('demo_ghana_shop'));
    }

    public function dataset(): string
    {
        return (string) $this->config['dataset'];
    }

    /** @return int[] */
    public function ownedAssetIds(): array
    {
        if (! Schema::hasTable(SyntheticDatasetWriter::REGISTRY)) {
            return [];
        }

        return DB::table(SyntheticDatasetWriter::REGISTRY)
            ->where('dataset', $this->dataset())
            ->where('record_type', 'asset')
            ->orderBy('record_id')
            ->pluck('record_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * One row per asset to create, in purchase-date order. Deterministic: the same
     * database gives the same plan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function plan(): array
    {
        $types = DB::table('asset_names')->whereNull('deleted_at')->get()
            ->keyBy(fn ($row) => strtoupper(trim((string) $row->assetname_abbreviation)));

        $gesId = DB::table('organizations')->whereNull('deleted_at')
            ->where('organization_abbreviation', 'GES')->value('id');

        $units = DB::table('units')->whereNull('deleted_at')
            ->when($gesId, fn ($q) => $q->where('organization_id', $gesId))
            ->whereNotNull('unit_abbreviation')->where('unit_abbreviation', '<>', '')
            ->whereNotNull('location_abbreviation')->where('location_abbreviation', '<>', '')
            ->orderBy('id')->get()->values();

        if ($units->isEmpty()) {
            throw new RuntimeException('No units with a unit and location abbreviation were found.');
        }

        $from = Carbon::parse($this->config['purchase_from']);
        $days = max(1, $from->diffInDays(Carbon::parse($this->config['purchase_to'])));
        $total = array_sum(array_column($this->config['items'], 'quantity'));
        $rows = [];
        $n = 0;

        foreach ($this->config['items'] as $itemIndex => $item) {
            $type = $types[strtoupper($item['type'])] ?? throw new RuntimeException("Asset type {$item['type']} does not exist.");

            for ($copy = 0; $copy < (int) $item['quantity']; $copy++, $n++) {
                // Spread items over units and over the purchase window.
                $unit = $units[($itemIndex * 7 + $copy * 3) % $units->count()];
                $date = $from->copy()->addDays((int) floor(($n * 37 % $total) * $days / $total))->toDateString();

                $rows[] = [
                    'item' => $itemIndex,
                    'type' => strtoupper($item['type']),
                    'type_id' => (int) $type->id,
                    'type_name' => $type->assetname,
                    'brand' => $item['brand'],
                    'model' => $item['model'],
                    'part' => $item['part'] ?? null,
                    'price_ghs' => (float) $item['price_ghs'],
                    'seller' => $item['seller'],
                    'url' => $item['url'],
                    'unit_id' => (int) $unit->id,
                    'unit' => $unit->unit_abbreviation,
                    'organization_id' => (int) $unit->organization_id,
                    'purchase_date' => $date,
                ];
            }
        }

        usort($rows, fn ($a, $b) => [$a['purchase_date'], $a['item']] <=> [$b['purchase_date'], $b['item']]);

        foreach ($rows as $i => &$row) {
            $row['serial_number'] = sprintf('DEMO-GH-%s-%s-%04d', $row['type'], substr($row['purchase_date'], 2, 2), $i + 1);
        }

        return $rows;
    }

    /** @return array<int, array{0: string, 1: string}> asset id => [code, serial] */
    public function write(array $plan): array
    {
        if ($this->ownedAssetIds() !== []) {
            throw new RuntimeException('The Ghana shop demo assets already exist. Remove them first.');
        }

        $serials = array_column($plan, 'serial_number');
        if (DB::table('assets')->whereIn('serial_number', $serials)->exists()) {
            throw new RuntimeException('A planned serial number already exists on another asset.');
        }

        $created = [];

        DB::transaction(function () use ($plan, &$created) {
            $now = now()->format('Y-m-d H:i:s');
            $pick = fn (array $ids, int $i) => $ids[$i % count($ids)];
            $suppliers = $this->ids('suppliers');
            $providers = $this->ids('maintenance_providers');
            $sources = $this->ids('sources');
            $legacy = $this->legacyColumns();
            $images = $this->typeImages();
            $warranty = (int) $this->config['warranty_months'];

            foreach ($plan as $i => $row) {
                $unit = DB::table('units')->find($row['unit_id']);

                $code = AssetCodeGenerator::generate(
                    $row['type'], (string) $unit->location_abbreviation, $row['purchase_date'],
                    (string) $unit->unit_abbreviation, $row['unit_id'],
                );

                $id = DB::table('assets')->insertGetId($this->only('assets', $legacy + [
                    'uuid' => (string) Str::uuid(),
                    'scan_token' => (string) Str::uuid(),
                    'asset_code' => $code,
                    'assetname_id' => $row['type_id'],
                    'brand' => $row['brand'],
                    'model_number' => $row['model'],
                    'part_number' => $row['part'],
                    'serial_number' => $row['serial_number'],
                    'source_id' => $pick($sources, $i),
                    'supplier_id' => $pick($suppliers, $i),
                    'maintenance_id' => $pick($providers, $i),
                    'purchase_cost' => $row['price_ghs'],
                    'purchase_date' => $row['purchase_date'],
                    'warranty_duration' => $warranty,
                    'warranty_end_date' => Carbon::parse($row['purchase_date'])->addMonths($warranty)->toDateString(),
                    'description' => sprintf(
                        'SYNTHETIC DEMONSTRATION DATA: modelled on a %s listing at GHS %s (retrieved %s). Not a real GES purchase.',
                        $row['seller'], number_format($row['price_ghs'], 2), $this->config['retrieved_at'],
                    ),
                    'asset_image' => $images[$row['type']] ?? null,
                    'qr_code' => null,
                    'user_id' => $this->custodianFor($row['unit_id']),
                    'unit_id' => $row['unit_id'],
                    'organization_id' => $row['organization_id'],
                    'status' => 'active',
                    'created_at' => $row['purchase_date'].' 10:00:00',
                    'updated_at' => $now,
                ]));

                DB::table(SyntheticDatasetWriter::REGISTRY)->insert([
                    'dataset' => $this->dataset(),
                    'record_type' => 'asset',
                    'record_id' => $id,
                    'batch_ref' => null,
                    'meta' => json_encode([
                        'initial_unit_id' => $row['unit_id'],
                        'seller' => $row['seller'],
                        'listing_url' => $row['url'],
                        'listed_price_ghs' => $row['price_ghs'],
                        'retrieved_at' => $this->config['retrieved_at'],
                    ], JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $created[$id] = [$code, $row['serial_number']];
            }
        });

        return $created;
    }

    public function generateQrCodes(): int
    {
        $service = app(DemoAssetQrService::class);
        $done = 0;

        foreach (Asset::query()->whereIn('id', $this->ownedAssetIds())->get() as $asset) {
            $service->generateForAsset($asset);
            $done++;
        }

        return $done;
    }

    /** Rows outside this set that point at its assets (they would block removal). */
    public function blockingReferences(): array
    {
        $ids = $this->ownedAssetIds();
        $problems = [];

        foreach (['maintenance_requests', 'asset_transfers', 'asset_lifecycle_histories', 'asset_scan_logs'] as $table) {
            if ($ids && Schema::hasTable($table) && Schema::hasColumn($table, 'asset_id')
                && ($count = DB::table($table)->whereIn('asset_id', $ids)->count())) {
                $problems[] = "{$count} row(s) in {$table} refer to these demo assets.";
            }
        }

        return $problems;
    }

    /** Delete exactly the assets this set created (their AI suggestions go with them). */
    public function remove(): int
    {
        if ($problems = $this->blockingReferences()) {
            throw new RuntimeException(implode(' ', $problems));
        }

        $ids = $this->ownedAssetIds();

        DB::transaction(function () use ($ids) {
            foreach (DB::table('assets')->whereIn('id', $ids)->pluck('qr_code') as $path) {
                if ($path && str_starts_with($path, 'assets/qrcodes/')) {
                    Storage::disk('public')->delete($path);
                }
            }

            if (Schema::hasTable('asset_suggestions')) {
                DB::table('asset_suggestions')->whereIn('asset_id', $ids)->delete();
            }

            DB::table('assets')->whereIn('id', $ids)->delete();
            DB::table(SyntheticDatasetWriter::REGISTRY)->where('dataset', $this->dataset())->delete();
        });

        return count($ids);
    }

    /* =========================================================
       HELPERS
    ========================================================= */

    /** A demo custodian of the unit from the main synthetic dataset, else any user of the unit. */
    private function custodianFor(int $unitId): int
    {
        $synthetic = DB::table(SyntheticDatasetWriter::REGISTRY)
            ->where('dataset', config('demo_dataset.dataset'))->where('record_type', 'user')
            ->pluck('record_id');

        return (int) (DB::table('users')->where('unit_id', $unitId)->whereIn('id', $synthetic)->orderBy('id')->value('id')
            ?? DB::table('users')->where('unit_id', $unitId)->orderBy('id')->value('id')
            ?? DB::table('users')->orderBy('id')->value('id')
            ?? throw new RuntimeException('No user found to register the demo assets.'));
    }

    /** @return int[] */
    private function ids(string $table): array
    {
        $ids = DB::table($table)
            ->when(Schema::hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $ids ?: throw new RuntimeException("Table {$table} has no rows to reference.");
    }

    private function legacyColumns(): array
    {
        $values = [];

        foreach (['asset_condition_id' => 'asset_conditions', 'asset_location_id' => 'asset_locations'] as $column => $table) {
            if (Schema::hasColumn('assets', $column) && ($id = DB::table($table)->orderBy('id')->value('id'))) {
                $values[$column] = $id;
            }
        }

        return $values;
    }

    /** The same representative type photos the main demo dataset uses. */
    private function typeImages(): array
    {
        $types = DB::table('asset_names')->whereNull('deleted_at')->get()
            ->mapWithKeys(fn ($row) => [strtoupper((string) $row->assetname_abbreviation) => ['name' => $row->assetname]])
            ->all();

        return SyntheticDatasetWriter::make()->typeImages($types);
    }

    private function only(string $table, array $row): array
    {
        return array_intersect_key($row, array_flip(Schema::getColumnListing($table)));
    }
}
