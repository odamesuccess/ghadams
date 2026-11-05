<?php

use App\Models\Asset;
use App\Models\User;
use App\Services\AssetCodeGenerator;
use App\Services\DemoData\SyntheticDatasetWriter;
use App\Services\DemoData\SyntheticInvoicePdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/* =========================================================
   Helpers
========================================================= */

function codeUnit(string $unit = 'ICTD', string $location = 'ICT'): int
{
    $organizationId = DB::table('organizations')->value('id') ?? DB::table('organizations')->insertGetId([
        'organizations_name' => 'Ghana Education Service',
        'organization_abbreviation' => 'GES',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (int) DB::table('units')->insertGetId([
        'name' => $unit.' unit',
        'unit_abbreviation' => $unit,
        'email' => Str::lower($unit).'@example.com',
        'phonenumber' => '0300000000',
        'locationname' => $location.' block',
        'location_abbreviation' => $location,
        'officenumber' => '1',
        'organization_id' => $organizationId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Register an asset like AssetController@store does: generate the code, then insert. */
function codeAsset(int $unitId, string $type, string $purchaseDate, array $attributes = []): Asset
{
    $owner = User::query()->first() ?? User::factory()->create(['unit_id' => $unitId]);
    $unit = DB::table('units')->find($unitId);
    $now = now();

    $typeId = DB::table('asset_names')->where('assetname_abbreviation', $type)->value('id')
        ?? DB::table('asset_names')->insertGetId([
            'assetname' => 'Type '.$type,
            'assetname_abbreviation' => $type,
            'brand' => 'Brand '.$type,
            'usefullife' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    $parent = fn (string $table, array $row) => DB::table($table)->insertGetId($row + ['created_at' => $now, 'updated_at' => $now]);

    return Asset::forceCreate([
        'asset_code' => array_key_exists('asset_code', $attributes)
            ? $attributes['asset_code']
            : AssetCodeGenerator::generate($type, $unit->location_abbreviation, $purchaseDate, $unit->unit_abbreviation, $unitId),
        'assetname_id' => $typeId,
        'brand' => 'HP',
        'serial_number' => 'SN-'.Str::random(10),
        'purchase_date' => $purchaseDate,
        'source_id' => $parent('sources', ['name' => 'SRC'.Str::random(8)]),
        'user_id' => $owner->id,
        'unit_id' => $unitId,
        'supplier_id' => $parent('suppliers', ['suppliername' => 'Supplier '.Str::random(6), 'phonenumber' => (string) random_int(100000000, 999999999), 'email' => Str::random(8).'@example.com']),
        'maintenance_id' => $parent('maintenance_providers', ['providername' => 'Provider '.Str::random(6), 'phonenumber' => (string) random_int(100000000, 999999999), 'email' => Str::random(8).'@example.com']),
        'asset_condition_id' => $parent('asset_conditions', ['conditionname' => 'Good '.Str::random(6)]),
        'asset_location_id' => $parent('asset_locations', ['locationname' => 'Room '.Str::random(6), 'Officenumber' => '1']),
        'organization_id' => $unit->organization_id,
        'status' => 'active',
        ...array_diff_key($attributes, ['asset_code' => true]),
    ]);
}

function nextCode(int $unitId, string $type, string $date, array $exclude = []): string
{
    $unit = DB::table('units')->find($unitId);

    return AssetCodeGenerator::generate($type, $unit->location_abbreviation, $date, $unit->unit_abbreviation, $unitId, $exclude);
}

/* =========================================================
   Format and sequences
========================================================= */

test('the first asset gets the official format with a two-digit type sequence', function () {
    $unit = codeUnit();

    expect(codeAsset($unit, 'LJP', '2026-03-01')->asset_code)->toBe('GES\\ICTD\\ICT\\LJP\\1\\2026\\01');
});

test('the location sequence counts all types and the type sequence counts one type', function () {
    $unit = codeUnit();

    $codes = collect([['LJP', '2026-01-10'], ['DPC', '2026-02-10'], ['LJP', '2026-03-10'], ['DPC', '2026-04-10']])
        ->map(fn ($row) => codeAsset($unit, ...$row)->asset_code)->all();

    expect($codes)->toBe([
        'GES\\ICTD\\ICT\\LJP\\1\\2026\\01',
        'GES\\ICTD\\ICT\\DPC\\2\\2026\\01',
        'GES\\ICTD\\ICT\\LJP\\3\\2026\\02',
        'GES\\ICTD\\ICT\\DPC\\4\\2026\\02',
    ]);
});

test('both sequences reset when the purchase year changes', function () {
    $unit = codeUnit();
    codeAsset($unit, 'LJP', '2025-05-01');
    codeAsset($unit, 'LJP', '2025-06-01');

    expect(codeAsset($unit, 'LJP', '2026-01-02')->asset_code)->toBe('GES\\ICTD\\ICT\\LJP\\1\\2026\\01')
        ->and(codeAsset($unit, 'LJP', '2025-12-31')->asset_code)->toBe('GES\\ICTD\\ICT\\LJP\\3\\2025\\03');
});

test('numbering is separate for each unit', function () {
    $ictd = codeUnit('ICTD', 'ICT');
    $hr = codeUnit('HRMD', 'HQ');
    codeAsset($ictd, 'LJP', '2026-01-01');

    expect(codeAsset($hr, 'LJP', '2026-01-01')->asset_code)->toBe('GES\\HRMD\\HQ\\LJP\\1\\2026\\01');
});

test('leading zeros are kept on the type sequence only', function () {
    $unit = codeUnit();

    foreach (range(1, 9) as $i) {
        codeAsset($unit, 'LJP', '2026-01-01');
    }

    expect(codeAsset($unit, 'LJP', '2026-01-01')->asset_code)->toBe('GES\\ICTD\\ICT\\LJP\\10\\2026\\10');
});

test('stored abbreviations are used as they are, trimmed and upper-cased', function () {
    $unit = codeUnit('DDG-QA', 'HQ');

    expect(codeAsset($unit, 'ups', '2026-01-01')->asset_code)->toBe('GES\\DDG-QA\\HQ\\UPS\\1\\2026\\01');
});

/* =========================================================
   Collision safety
========================================================= */

test('a code held by a soft-deleted asset is never reused', function () {
    $unit = codeUnit();
    codeAsset($unit, 'LJP', '2026-01-01')->delete();

    $code = codeAsset($unit, 'LJP', '2026-02-01')->asset_code;

    expect($code)->toBe('GES\\ICTD\\ICT\\LJP\\2\\2026\\02')
        ->and(Asset::withTrashed()->where('asset_code', $code)->count())->toBe(1);
});

test('numbering continues after the highest code in use, not the row count', function () {
    $unit = codeUnit();
    // A gap left by deleted or moved assets: only one live asset, but it holds sequence 5 / 03.
    codeAsset($unit, 'LJP', '2026-01-01', ['asset_code' => 'GES\\ICTD\\ICT\\LJP\\5\\2026\\03']);

    expect(nextCode($unit, 'LJP', '2026-02-01'))->toBe('GES\\ICTD\\ICT\\LJP\\6\\2026\\04')
        ->and(nextCode($unit, 'DPC', '2026-02-01'))->toBe('GES\\ICTD\\ICT\\DPC\\6\\2026\\01');
});

test('codes of assets that moved to another unit still block reuse', function () {
    $ictd = codeUnit('ICTD', 'ICT');
    $hr = codeUnit('HRMD', 'HQ');
    $moved = codeAsset($ictd, 'LJP', '2026-01-01');
    $moved->forceFill(['unit_id' => $hr])->save();

    expect(nextCode($ictd, 'LJP', '2026-03-01'))->toBe('GES\\ICTD\\ICT\\LJP\\2\\2026\\02');
});

test('repeated registration never produces a duplicate code', function () {
    $unit = codeUnit();
    $types = ['LJP', 'DPC', 'LTP', 'UPS'];
    $codes = [];

    foreach (range(0, 59) as $i) {
        $codes[] = codeAsset($unit, $types[$i % 4], (2024 + $i % 3).'-0'.(1 + $i % 9).'-15')->asset_code;
    }

    expect(array_unique($codes))->toHaveCount(60)
        ->and(collect($codes)->every(fn ($c) => AssetCodeGenerator::parse($c) !== null))->toBeTrue();
});

test('excluded assets are left out of the numbering', function () {
    $unit = codeUnit();
    $pending = codeAsset($unit, 'LJP', '2026-01-01', ['asset_code' => 'OLD-CODE-1']);

    expect(nextCode($unit, 'LJP', '2026-01-01'))->toBe('GES\\ICTD\\ICT\\LJP\\2\\2026\\02')
        ->and(nextCode($unit, 'LJP', '2026-01-01', [$pending->id]))->toBe('GES\\ICTD\\ICT\\LJP\\1\\2026\\01');
});

test('parse reads back what format writes', function () {
    $code = AssetCodeGenerator::format('ictd', 'ict', 'ljp', 2, 2026, 1);

    expect($code)->toBe('GES\\ICTD\\ICT\\LJP\\2\\2026\\01')
        ->and(AssetCodeGenerator::parse($code))->toBe([
            'unit' => 'ICTD', 'location' => 'ICT', 'type' => 'LJP',
            'location_sequence' => 2, 'year' => 2026, 'type_sequence' => 1,
        ])
        ->and(AssetCodeGenerator::parse('SYN\\ICTD\\ICT\\LJP\\2\\2026\\01'))->toBeNull();
});

/* =========================================================
   Synthetic demo dataset recode
========================================================= */

function registerSynthetic(Asset $asset, int $initialUnitId): void
{
    DB::table(SyntheticDatasetWriter::REGISTRY)->insert([
        'dataset' => config('demo_dataset.dataset'),
        'record_type' => 'asset',
        'record_id' => $asset->id,
        'meta' => json_encode(['initial_unit_id' => $initialUnitId]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('recoding the demo dataset gives only its own assets official codes', function () {
    $unit = codeUnit();
    $real = [
        codeAsset($unit, 'LJP', '2026-01-01'),
        codeAsset($unit, 'DPC', '2026-01-02'),
    ];
    $deleted = codeAsset($unit, 'LJP', '2026-01-03');
    $deleted->delete();

    $synthetic = collect([['LJP', '2026-02-01'], ['LJP', '2025-07-01'], ['DPC', '2026-03-01']])
        ->map(function ($row, $i) use ($unit) {
            $asset = codeAsset($unit, $row[0], $row[1], ['asset_code' => "SYN\\ICTD\\ICT\\{$row[0]}\\".($i + 1).'\\2026\\01']);
            registerSynthetic($asset, $unit);

            return $asset;
        });

    $uuids = $synthetic->mapWithKeys(fn ($a) => [$a->id => [$a->uuid, $a->scan_token]])->all();

    $changes = SyntheticDatasetWriter::make()->recodeAssets(true);

    expect($changes)->toHaveCount(3)
        // Real assets, soft-deleted included, keep their codes.
        ->and($real[0]->fresh()->asset_code)->toBe('GES\\ICTD\\ICT\\LJP\\1\\2026\\01')
        ->and($real[1]->fresh()->asset_code)->toBe('GES\\ICTD\\ICT\\DPC\\2\\2026\\01')
        ->and(Asset::withTrashed()->find($deleted->id)->asset_code)->toBe('GES\\ICTD\\ICT\\LJP\\3\\2026\\02')
        // Synthetic assets continue after them, in purchase-date order, per year.
        ->and($synthetic[1]->fresh()->asset_code)->toBe('GES\\ICTD\\ICT\\LJP\\1\\2025\\01')
        ->and($synthetic[0]->fresh()->asset_code)->toBe('GES\\ICTD\\ICT\\LJP\\4\\2026\\03')
        ->and($synthetic[2]->fresh()->asset_code)->toBe('GES\\ICTD\\ICT\\DPC\\5\\2026\\02');

    foreach ($synthetic as $asset) {
        expect([$asset->fresh()->uuid, $asset->fresh()->scan_token])->toBe($uuids[$asset->id]);
    }
});

test('recoding twice gives the same codes', function () {
    $unit = codeUnit();
    codeAsset($unit, 'LJP', '2026-01-01');

    foreach (['2026-02-01', '2026-03-01'] as $date) {
        registerSynthetic(codeAsset($unit, 'LJP', $date, ['asset_code' => 'SYN-'.$date]), $unit);
    }

    $first = collect(SyntheticDatasetWriter::make()->recodeAssets(true))->map(fn ($c) => $c[1])->all();
    $second = collect(SyntheticDatasetWriter::make()->recodeAssets(true))->map(fn ($c) => $c[1])->all();

    expect($second)->toBe($first)
        ->and(array_values($first))->toBe(['GES\\ICTD\\ICT\\LJP\\2\\2026\\02', 'GES\\ICTD\\ICT\\LJP\\3\\2026\\03']);
});

test('a recode preview changes nothing', function () {
    $unit = codeUnit();
    $asset = codeAsset($unit, 'LJP', '2026-02-01', ['asset_code' => 'SYN-PREVIEW']);
    registerSynthetic($asset, $unit);

    $changes = SyntheticDatasetWriter::make()->recodeAssets(false);

    expect($changes[$asset->id])->toBe(['SYN-PREVIEW', 'GES\\ICTD\\ICT\\LJP\\1\\2026\\01'])
        ->and($asset->fresh()->asset_code)->toBe('SYN-PREVIEW');
});

test('the asset code on a synthetic invoice PDF can be replaced', function () {
    $job = [
        'actual_cost' => 120, 'payment_status' => 'paid', 'payment_date' => '2026-01-05', 'payment_ref' => 'PAY-1',
        'invoice' => ['ref' => 'INV-1', 'date' => '2026-01-04'], 'fault_description' => 'Paper jam',
        'completion_note' => 'Fixed', 'actual_completion_date' => '2026-01-04',
    ];
    $asset = ['asset_code' => 'SYN\\ICTD\\ICT\\LJP\\1\\2026\\01', 'brand' => 'HP', 'model_number' => 'M404', 'type_name' => 'Printer', 'serial_number' => 'S1'];

    $pdf = SyntheticInvoicePdf::render($job, $asset, 7);
    $updated = SyntheticInvoicePdf::replaceAssetCode($pdf, $asset['asset_code'], 'GES\\ICTD\\ICT\\LJP\\4\\2026\\02');

    expect($updated)->toBe(SyntheticInvoicePdf::render($job, ['asset_code' => 'GES\\ICTD\\ICT\\LJP\\4\\2026\\02'] + $asset, 7))
        ->and(SyntheticInvoicePdf::replaceAssetCode($pdf, 'NOT-THERE', 'X'))->toBeNull();
});
