<?php

use App\Models\Asset;
use App\Models\User;
use App\Services\AssetCodeGenerator;
use App\Services\DemoData\GhanaShopDemoAssets;
use App\Services\DemoData\SyntheticDatasetWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');

    $now = now();
    $org = DB::table('organizations')->insertGetId(['organizations_name' => 'Ghana Education Service', 'organization_abbreviation' => 'GES', 'created_at' => $now, 'updated_at' => $now]);

    foreach ([['ICTD', 'ICT'], ['HRMD', 'HQ']] as [$unit, $location]) {
        DB::table('units')->insert(['name' => $unit, 'unit_abbreviation' => $unit, 'email' => Str::lower($unit).'@example.com', 'phonenumber' => '0300000000', 'locationname' => $location, 'location_abbreviation' => $location, 'officenumber' => '1', 'organization_id' => $org, 'created_at' => $now, 'updated_at' => $now]);
    }

    foreach (['LTP' => 'Laptop Computer', 'UPS' => 'UPS Battery Backup'] as $abbr => $name) {
        DB::table('asset_names')->insert(['assetname' => $name, 'assetname_abbreviation' => $abbr, 'brand' => 'Brand '.$abbr, 'usefullife' => 5, 'created_at' => $now, 'updated_at' => $now]);
    }

    DB::table('sources')->insert(['name' => 'Government of Ghana', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('suppliers')->insert(['suppliername' => 'Demo Supplier', 'phonenumber' => '0244000000', 'email' => 'supplier@example.com', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('maintenance_providers')->insert(['providername' => 'Demo Provider', 'phonenumber' => '0244000001', 'email' => 'provider@example.com', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('asset_conditions')->insert(['conditionname' => 'Good', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('asset_locations')->insert(['locationname' => 'Room 1', 'Officenumber' => '1', 'created_at' => $now, 'updated_at' => $now]);
    User::factory()->create();

    config(['demo_ghana_shop.items' => [
        ['type' => 'LTP', 'brand' => 'HP', 'model' => '250 G10 (Core 3, 8GB RAM, 512GB SSD)', 'part' => null, 'price_ghs' => 6999, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/laptops', 'quantity' => 3],
        ['type' => 'UPS', 'brand' => 'APC', 'model' => 'Back-UPS 800VA', 'part' => 'BV800I-MSX', 'price_ghs' => 1499, 'seller' => 'CompuGhana', 'url' => 'https://compughana.com/collections/ups', 'quantity' => 2],
    ]]);
});

test('the dry run writes nothing', function () {
    $this->artisan('demo:ghana-shop-assets')->assertSuccessful();

    expect(Asset::count())->toBe(0);
});

test('writing needs the confirmation token', function () {
    $this->artisan('demo:ghana-shop-assets --execute')->assertFailed();

    expect(Asset::count())->toBe(0);
});

test('assets are created with shop prices in cedis, official codes and QR labels', function () {
    $this->artisan('demo:ghana-shop-assets --execute --confirm=GHANA-SHOP-2026')->assertSuccessful();

    $assets = Asset::orderBy('purchase_date')->get();

    expect($assets)->toHaveCount(5)
        ->and($assets->pluck('asset_code')->unique())->toHaveCount(5)
        ->and($assets->every(fn ($a) => AssetCodeGenerator::parse($a->asset_code) !== null))->toBeTrue()
        ->and($assets->where('brand', 'HP')->pluck('purchase_cost')->map(fn ($v) => (float) $v)->unique()->values()->all())->toBe([6999.0])
        ->and($assets->firstWhere('brand', 'APC')->part_number)->toBe('BV800I-MSX')
        ->and($assets->every(fn ($a) => str_starts_with($a->description, 'SYNTHETIC DEMONSTRATION DATA')))->toBeTrue()
        ->and($assets->every(fn ($a) => $a->qr_code && Storage::disk('public')->exists($a->qr_code)))->toBeTrue()
        ->and(DB::table(SyntheticDatasetWriter::REGISTRY)->where('dataset', 'ghana-shop-2026')->count())->toBe(5);

    $meta = json_decode(DB::table(SyntheticDatasetWriter::REGISTRY)->where('dataset', 'ghana-shop-2026')->value('meta'), true);
    expect($meta)->toMatchArray(['seller' => 'CompuGhana', 'retrieved_at' => '2026-11-05']);
});

test('running it again does not add duplicates', function () {
    $this->artisan('demo:ghana-shop-assets --execute --confirm=GHANA-SHOP-2026 --skip-media')->assertSuccessful();
    $this->artisan('demo:ghana-shop-assets --execute --confirm=GHANA-SHOP-2026 --skip-media')->assertSuccessful();

    expect(Asset::count())->toBe(5);
});

test('remove deletes only these demo assets', function () {
    $other = GhanaShopDemoAssets::make()->plan()[0];
    $this->artisan('demo:ghana-shop-assets --execute --confirm=GHANA-SHOP-2026 --skip-media')->assertSuccessful();

    // An unrelated asset registered normally must survive.
    $kept = Asset::query()->first()->replicate(['asset_code', 'uuid', 'scan_token', 'serial_number']);
    $kept->forceFill(['asset_code' => 'GES\\HRMD\\HQ\\LTP\\99\\2026\\99', 'uuid' => (string) Str::uuid(), 'scan_token' => (string) Str::uuid(), 'serial_number' => 'REAL-1'])->save();

    $this->artisan('demo:ghana-shop-assets --remove --confirm=GHANA-SHOP-2026')->assertSuccessful();

    expect(Asset::pluck('id')->all())->toBe([$kept->id])
        ->and(DB::table(SyntheticDatasetWriter::REGISTRY)->where('dataset', 'ghana-shop-2026')->count())->toBe(0)
        ->and($other['price_ghs'])->toBe(6999.0);
});
