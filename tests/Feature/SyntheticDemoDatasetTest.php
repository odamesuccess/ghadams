<?php

namespace Tests\Feature;

use App\Services\DemoData\SyntheticDatasetGuard;
use App\Services\DemoData\SyntheticDatasetPlanner;
use App\Services\DemoData\SyntheticDatasetVerifier;
use App\Services\DemoData\SyntheticDatasetWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Integrity tests for the synthetic demo dataset (Phase 13), run against the
 * isolated in-memory test database.
 */
class SyntheticDemoDatasetTest extends TestCase
{
    use RefreshDatabase;

    private const TYPES = [
        'DPC' => ['Desktop Computer', 5], 'LTP' => ['Laptop Computer', 5], 'LJP' => ['LaserJet Printer', 4],
        'PCM' => ['Photocopier Machine', 6], 'SCN' => ['Scanner', 4], 'PRJ' => ['Projector', 5],
        'UPS' => ['UPS Battery Backup', 3], 'NSW' => ['Network Switch', 7], 'WRT' => ['Wireless Router', 4],
        'EHD' => ['External Hard Drive', 4], 'ODK' => ['Office Desk', 10], 'OFC' => ['Office Chair', 8],
        'FBC' => ['Filing Cabinet', 12], 'BSH' => ['Bookshelf', 10], 'CFT' => ['Conference Table', 12],
        'ACR' => ['Air Conditioner', 8], 'STF' => ['Standing Fan', 4], 'WDP' => ['Water Dispenser', 5],
        'TEL' => ['Telephone Set', 6], 'TVS' => ['Television Set', 7], 'SRV' => ['Server Machine', 8],
        'NRC' => ['Network Rack Cabinet', 10], 'BAD' => ['Biometric Attendance Device', 5],
        'PAS' => ['Public Address System', 7], 'GEN' => ['Generator Set', 15], 'CLF' => ['Ceiling Fan', 8],
    ];

    private const UNITS = [
        'ODG' => 'HQ', 'GAU' => 'ADM', 'FIN' => 'FB', 'HRD' => 'HR', 'PSC' => 'LB', 'ICTD' => 'ICT', 'SRIM' => 'DC',
        'TED' => 'TDB', 'IMU' => 'WD', 'SICU' => 'CS', 'PRCS' => 'FD', 'IAU' => 'AB', 'ECU' => 'ASB', 'CRDD' => 'CB',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        $this->seedLookups();
    }

    private function seedLookups(): void
    {
        $now = now();
        $orgId = DB::table('organizations')->insertGetId([
            'organizations_name' => 'Ghana Education Service', 'organization_abbreviation' => 'GES',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $n = 0;
        foreach (self::UNITS as $abbr => $location) {
            $n++;
            DB::table('units')->insert([
                'name' => "{$abbr} Unit", 'unit_abbreviation' => $abbr, 'email' => strtolower($abbr).'@example.test',
                'phonenumber' => '02000000'.$n, 'locationname' => "{$location} Block", 'location_abbreviation' => $location,
                'officenumber' => (string) $n, 'organization_id' => $orgId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $catalogue = [];
        foreach (self::TYPES as $abbr => [$name, $life]) {
            DB::table('asset_names')->insert([
                'assetname' => $name, 'assetname_abbreviation' => $abbr, 'brand' => "Brand {$abbr}",
                'usefullife' => $life, 'created_at' => $now, 'updated_at' => $now,
            ]);

            $path = 'assets/type-images/'.strtolower($abbr).'.jpg';
            $image = imagecreatetruecolor(4, 4);
            ob_start();
            imagejpeg($image);
            Storage::disk('public')->put($path, (string) ob_get_clean());
            $catalogue[$name] = ['local_path' => $path, 'license' => 'test fixture'];
        }
        Storage::disk('public')->put(SyntheticDatasetWriter::TYPE_PHOTO_MANIFEST, json_encode(['types' => $catalogue]));

        for ($i = 1; $i <= 6; $i++) {
            DB::table('suppliers')->insert(['suppliername' => "Supplier {$i}", 'phonenumber' => "024100000{$i}", 'email' => "s{$i}@example.test", 'created_at' => $now, 'updated_at' => $now]);
            DB::table('maintenance_providers')->insert(['providername' => "Provider {$i}", 'phonenumber' => "024200000{$i}", 'email' => "p{$i}@example.test", 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (['Government Procurement', 'Purchase - Local Vendor', 'Donations', 'Replacement Purchase'] as $source) {
            DB::table('sources')->insert(['name' => $source, 'created_at' => $now, 'updated_at' => $now]);
        }

        Role::findOrCreate('Admin', 'web');
    }

    private function seedDataset(): array
    {
        $writer = SyntheticDatasetWriter::make();
        $plan = $writer->plan();
        $counts = $writer->write($plan);

        return [$writer, $plan, $counts];
    }

    public function test_guard_allows_the_isolated_test_database(): void
    {
        $result = app(SyntheticDatasetGuard::class)->inspect();

        $this->assertTrue($result['allowed'], implode('; ', $result['reasons']));
        $this->assertSame('sqlite', $result['facts']['driver']);
    }

    public function test_plan_is_deterministic_and_matches_the_year_targets(): void
    {
        $writer = SyntheticDatasetWriter::make();
        $context = $writer->loadContext();
        $config = config('demo_dataset');

        $first = (new SyntheticDatasetPlanner($config, $context))->build();
        $second = (new SyntheticDatasetPlanner($config, $context))->build();

        $this->assertSame(md5(serialize($first)), md5(serialize($second)));
        $this->assertSame(2000, array_sum($config['year_counts']));

        $byYear = collect($first['assets'])->countBy(fn ($a) => (int) substr($a['purchase_date'], 0, 4))->sortKeys()->all();
        $this->assertSame($config['year_counts'], $byYear);

        // Peaks around 2013, 2016, 2019 and 2022 with declines between them.
        foreach ([2013, 2016, 2019, 2022] as $peak) {
            $this->assertGreaterThan($byYear[$peak - 1], $byYear[$peak]);
            $this->assertGreaterThan($byYear[$peak + 1], $byYear[$peak]);
        }
    }

    public function test_models_are_never_used_before_their_release_window(): void
    {
        $plan = SyntheticDatasetWriter::make()->plan();
        $catalogue = config('demo_dataset.types');

        foreach ($plan['assets'] as $asset) {
            $year = (int) substr($asset['purchase_date'], 0, 4);
            $match = collect($catalogue[$asset['type']]['models'])->first(fn ($m) => $m['brand'] === $asset['brand']
                && $m['model'] === $asset['model_number'] && $m['config'] === $asset['config_text']);

            $this->assertNotNull($match, "Unknown model on {$asset['asset_code']}");
            $this->assertGreaterThanOrEqual($match['from'], $year, "{$asset['asset_code']} predates its model window");
            $this->assertLessThanOrEqual($match['to'], $year);
        }
    }

    public function test_seeded_dataset_passes_every_integrity_check(): void
    {
        [$writer] = $this->seedDataset();

        // QR images for every asset are slow to render; render a few here and
        // check the rest of the QR rules (unique token, resolvable URL) for all.
        $this->assertSame(5, $writer->generateQrCodes(limit: 5));

        $result = SyntheticDatasetVerifier::make()->run();
        $failed = collect($result['checks'])->reject(fn ($c) => $c['passed']);

        $this->assertSame(['qr_images_valid'], $failed->keys()->all(), json_encode($failed->all(), JSON_PRETTY_PRINT));
        $this->assertStringStartsWith('1995 ', $result['checks']['qr_images_valid']['detail']);

        $stats = $result['stats'];
        $this->assertSame(2000, array_sum(array_column($stats['by_year'], 'count')));
        $this->assertSame(['approved' => 25, 'completed/paid' => 120, 'completed/pending' => 12, 'completed/unpaid' => 48, 'in_progress' => 100, 'pending' => 45],
            collect($stats['maintenance']['by_status'])->sortKeys()->all());
        $this->assertSame(['approved' => 20, 'completed' => 80, 'pending' => 60, 'rejected' => 20], collect($stats['transfers'])->sortKeys()->all());
    }

    public function test_qr_codes_encode_the_existing_scan_route(): void
    {
        [$writer] = $this->seedDataset();
        $writer->generateQrCodes(limit: 3);

        $assets = DB::table('assets')->whereNotNull('qr_code')->get();
        $this->assertCount(3, $assets);

        foreach ($assets as $asset) {
            $this->assertSame("assets/qrcodes/asset-{$asset->id}.png", $asset->qr_code);
            $this->assertNotFalse(getimagesizefromstring(Storage::disk('public')->get($asset->qr_code)));

            // The scan endpoint shows the right asset.
            $this->get(route('assets.scan', $asset->uuid))->assertOk();
            $this->assertDatabaseHas('asset_scan_logs', ['asset_id' => $asset->id]);
        }
    }

    public function test_reseeding_replaces_only_synthetic_rows_without_duplicates(): void
    {
        $now = now();
        $realUser = DB::table('users')->insertGetId(['unit_id' => 1, 'name' => 'Real Admin', 'email' => 'real@example.test', 'password' => 'x', 'created_at' => $now, 'updated_at' => $now]);
        $writer = SyntheticDatasetWriter::make();
        $context = $writer->loadContext();
        $real = DB::table('assets')->insertGetId(array_intersect_key([
            'uuid' => 'real-uuid', 'scan_token' => 'real-token', 'asset_code' => 'GES\\REAL\\1', 'assetname_id' => 1,
            'source_id' => 1, 'supplier_id' => 1, 'maintenance_id' => 1, 'user_id' => $realUser, 'unit_id' => 1, 'organization_id' => 1,
            'asset_condition_id' => DB::table('asset_conditions')->insertGetId(['conditionname' => 'Good', 'created_at' => $now, 'updated_at' => $now]),
            'asset_location_id' => DB::table('asset_locations')->insertGetId(['locationname' => 'Main', 'Officenumber' => '1', 'created_at' => $now, 'updated_at' => $now]),
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ], array_flip(\Schema::getColumnListing('assets'))));

        $writer->write($writer->plan($context));
        $first = DB::table('assets')->whereNot('id', $real)->orderBy('asset_code')->pluck('purchase_cost', 'asset_code')->all();

        $this->assertSame([], $writer->blockingReferences());
        $writer->remove();
        $writer->write($writer->plan($context));

        $this->assertSame(2001, DB::table('assets')->count());
        $this->assertSame(2000, count($writer->ownedIds('asset')));
        $this->assertSame(350, DB::table('maintenance_requests')->count());
        $this->assertSame(180, DB::table('asset_transfers')->count());
        $this->assertDatabaseHas('assets', ['id' => $real, 'asset_code' => 'GES\\REAL\\1']);
        $this->assertDatabaseHas('users', ['id' => $realUser]);
        $this->assertSame($first, DB::table('assets')->whereNot('id', $real)->orderBy('asset_code')->pluck('purchase_cost', 'asset_code')->all());
    }

    public function test_command_is_idempotent_and_refuses_without_the_token(): void
    {
        $this->artisan('demo:synthetic-dataset', ['--execute' => true])->assertFailed();
        $this->assertSame(0, DB::table('assets')->count());

        $this->artisan('demo:synthetic-dataset')->assertSuccessful(); // dry run
        $this->assertSame(0, DB::table('assets')->count());

        $this->artisan('demo:synthetic-dataset', ['--execute' => true, '--confirm' => 'SYNTHETIC-2000', '--skip-media' => true]);
        $this->assertSame(2000, DB::table('assets')->count());

        $this->artisan('demo:synthetic-dataset', ['--execute' => true, '--confirm' => 'SYNTHETIC-2000', '--skip-media' => true])
            ->expectsOutputToContain('already present')->assertSuccessful();
        $this->assertSame(2000, DB::table('assets')->count());
    }

    public function test_replace_is_blocked_when_real_records_reference_synthetic_assets(): void
    {
        [$writer] = $this->seedDataset();
        $assetId = $writer->ownedIds('asset')[0];

        DB::table('asset_scan_logs')->insert(['asset_id' => $assetId, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertNotEmpty($writer->blockingReferences());
        $this->artisan('demo:synthetic-dataset', ['--replace' => true, '--confirm' => 'SYNTHETIC-2000', '--skip-media' => true])->assertFailed();
        $this->assertSame(2000, count($writer->ownedIds('asset')));
    }

    public function test_synthetic_users_cannot_sign_in_and_only_staff_get_the_admin_role(): void
    {
        [$writer, $plan] = $this->seedDataset();
        $users = DB::table('users')->whereIn('id', $writer->ownedIds('user'))->get();

        $this->assertCount(count($plan['users']), $users);
        $this->assertTrue($users->every(fn ($u) => $u->user_status === 'Inactive' && str_ends_with($u->email, '@demo.ghadams.test') && str_ends_with($u->name, '(Demo)')));
        $this->assertSame(4, DB::table('model_has_roles')->whereIn('model_id', $users->pluck('id'))->count());
    }

    public function test_search_filters_return_synthetic_assets(): void
    {
        [$writer] = $this->seedDataset();
        $asset = DB::table('assets')->whereIn('id', $writer->ownedIds('asset'))->whereNotNull('model_number')->orderBy('id')->first();

        foreach (['asset_code', 'serial_number', 'brand', 'model_number'] as $column) {
            $this->assertTrue(DB::table('assets')->where($column, 'like', '%'.$asset->{$column}.'%')->where('id', $asset->id)->exists());
        }

        $year = substr($asset->purchase_date, 0, 4);
        $this->assertSame(config("demo_dataset.year_counts.{$year}"), DB::table('assets')->whereIn('id', $writer->ownedIds('asset'))->where('purchase_date', 'like', "{$year}-%")->count());
        $this->assertGreaterThan(0, DB::table('assets')->where('supplier_id', $asset->supplier_id)->where('unit_id', $asset->unit_id)->count());
    }

    public function test_dashboard_chart_uses_independent_count_and_sum_with_labelled_axes(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/DashboardController.php'));
        $this->assertStringContainsString('COUNT(*) as total, COALESCE(SUM(purchase_cost), 0) as value', $controller);

        $vue = file_get_contents(resource_path('js/pages/Dashboard/Index.vue'));
        $this->assertStringContainsString("yAxisID: 'count'", $vue);
        $this->assertStringContainsString("yAxisID: 'value'", $vue);
        $this->assertMatchesRegularExpression("/count: \\{\\s*type: 'linear', position: 'left'[^}]*Number of assets/s", $vue);
        $this->assertMatchesRegularExpression("/value: \\{\\s*type: 'linear', position: 'right'[^}]*Purchase value \\(GH₵\\)/s", $vue);
    }
}
