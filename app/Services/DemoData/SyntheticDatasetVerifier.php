<?php

namespace App\Services\DemoData;

use App\Services\AssetCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Integrity checks and reports for the synthetic demo dataset, run against
 * the rows actually stored in the database.
 */
class SyntheticDatasetVerifier
{
    private array $checks = [];

    public function __construct(private SyntheticDatasetWriter $writer) {}

    public static function make(): self
    {
        return new self(SyntheticDatasetWriter::make());
    }

    /**
     * @return array{passed: bool, checks: array, stats: array}
     */
    public function run(): array
    {
        $this->checks = [];
        $config = $this->writer->config();
        $end = $config['end_date'];
        $endStamp = $end.' 23:59:59';

        $assetIds = $this->writer->ownedIds('asset');
        $assets = $this->rows('assets', $assetIds);
        $registry = DB::table(SyntheticDatasetWriter::REGISTRY)->where('dataset', $this->writer->dataset())
            ->where('record_type', 'asset')->get()->keyBy('record_id');
        $jobs = $this->rows('maintenance_requests', $this->writer->ownedIds('maintenance_request'));
        $transfers = $this->rows('asset_transfers', $this->writer->ownedIds('asset_transfer'));
        $types = DB::table('asset_names')->pluck('assetname', 'id');
        $abbr = DB::table('asset_names')->pluck('assetname_abbreviation', 'id');
        $jobMeta = DB::table(SyntheticDatasetWriter::REGISTRY)->where('record_type', 'maintenance_request')
            ->whereIn('record_id', $jobs->keys()->all() ?: [0])->pluck('meta', 'record_id')
            ->map(fn ($m) => json_decode((string) $m, true));

        // 1. Exactly 2,000 synthetic assets, all present and not soft-deleted.
        $live = $assets->whereNull('deleted_at')->count();
        $this->check('exactly_2000_assets', count($assetIds) === 2000 && $live === 2000, 'registry='.count($assetIds).", live rows={$live}");

        // 2. Per-year counts and the fluctuating shape.
        $byYear = [];
        $valueByYear = [];
        foreach ($assets as $asset) {
            $year = (int) substr((string) $asset->purchase_date, 0, 4);
            $byYear[$year] = ($byYear[$year] ?? 0) + 1;
            $valueByYear[$year] = round(($valueByYear[$year] ?? 0) + (float) $asset->purchase_cost, 2);
        }
        ksort($byYear);
        ksort($valueByYear);
        $expected = $config['year_counts'];
        $this->check('annual_counts_match', $byYear == $expected && array_sum($byYear) === 2000, json_encode($byYear));
        $peaks = [2013, 2016, 2019, 2022];
        $isPeak = fn ($y) => ($byYear[$y] ?? 0) > ($byYear[$y - 1] ?? 0) && ($byYear[$y] ?? 0) > ($byYear[$y + 1] ?? 0);
        $monotonic = $byYear === [] || array_values($byYear) === array_values(collect($byYear)->sort()->all());
        $this->check('trend_fluctuates_with_peaks', collect($peaks)->every($isPeak) && ! $monotonic, 'peaks at '.implode(', ', $peaks));

        // 3-4. Uniqueness across the whole assets table.
        // A synthetic value must not repeat anywhere in the table (duplicates
        // only among pre-existing rows are reported separately, not failed).
        foreach (['uuid', 'asset_code', 'scan_token', 'serial_number'] as $column) {
            $dupes = DB::table('assets')->whereNotNull($column)->select($column)->groupBy($column)->havingRaw('count(*) > 1')->pluck($column)->all();
            $synthetic = array_intersect($dupes, $assets->pluck($column)->all());
            $this->check("unique_{$column}", $synthetic === [],
                count($synthetic).' synthetic value(s) duplicated; '.(count($dupes) - count($synthetic)).' duplicate(s) among pre-existing rows only');
        }

        // 4b. Official GES code: abbreviations match the asset's type, its original unit and that
        //     unit's location, and the year matches the purchase date.
        $units = DB::table('units')->get(['id', 'unit_abbreviation', 'location_abbreviation'])->keyBy('id');
        $badCodes = [];
        foreach ($assets as $asset) {
            $parts = AssetCodeGenerator::parse((string) $asset->asset_code);
            $meta = json_decode((string) ($registry[$asset->id]->meta ?? ''), true) ?: [];
            $unit = $units[$meta['initial_unit_id'] ?? $asset->unit_id] ?? null;
            $ok = $parts && $unit
                && strcasecmp($parts['unit'], trim((string) $unit->unit_abbreviation)) === 0
                && strcasecmp($parts['location'], trim((string) $unit->location_abbreviation)) === 0
                && strcasecmp($parts['type'], trim((string) ($abbr[$asset->assetname_id] ?? ''))) === 0
                && $parts['year'] === (int) substr((string) $asset->purchase_date, 0, 4);
            if (! $ok) {
                $badCodes[] = $asset->asset_code;
            }
        }
        $this->check('official_asset_code_format', $badCodes === [],
            count($badCodes).' code(s) not in GES\\UNIT\\LOCATION\\TYPE\\SEQ\\YEAR\\NN form'.($badCodes ? ', e.g. '.$badCodes[0] : ''));

        // 5. QR images exist and their payload route resolves to the asset.
        $qrMissing = 0;
        $qrBadRoute = 0;
        $disk = Storage::disk('public');
        foreach ($assets as $asset) {
            if (! $asset->qr_code || ! $disk->exists($asset->qr_code) || @getimagesizefromstring((string) $disk->get($asset->qr_code)) === false) {
                $qrMissing++;
            }
            if (! $this->scanRouteResolves((string) $asset->uuid)) {
                $qrBadRoute++;
            }
        }
        $this->check('qr_images_valid', $qrMissing === 0, "{$qrMissing} asset(s) missing a readable QR image");
        $this->check('qr_destinations_valid', $qrBadRoute === 0, "{$qrBadRoute} scan URL(s) did not resolve to assets.scan");

        // 6. Representative images: exist, readable, supported, right type.
        $catalogue = $this->writer->typeImages(
            DB::table('asset_names')->get()->mapWithKeys(fn ($r) => [$r->assetname_abbreviation => ['name' => $r->assetname]])->all()
        );
        $badImage = 0;
        $wrongType = 0;
        $imageCache = [];
        foreach ($assets as $asset) {
            $path = (string) $asset->asset_image;
            $imageCache[$path] ??= $path !== '' && $disk->exists($path)
                && in_array(@getimagesizefromstring((string) $disk->get($path))[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
            $badImage += $imageCache[$path] ? 0 : 1;
            $wrongType += ($catalogue[$abbr[$asset->assetname_id] ?? ''] ?? null) === $path ? 0 : 1;
        }
        $this->check('images_valid', $badImage === 0, "{$badImage} asset(s) with a missing or unreadable image");
        $this->check('images_match_type', $wrongType === 0, "{$wrongType} asset(s) whose image is not their type's catalogue photo");

        // 7. Foreign keys.
        $fkProblems = 0;
        foreach (['assetname_id' => 'asset_names', 'source_id' => 'sources', 'supplier_id' => 'suppliers', 'maintenance_id' => 'maintenance_providers',
            'user_id' => 'users', 'unit_id' => 'units', 'organization_id' => 'organizations'] as $column => $table) {
            $ids = $assets->pluck($column)->unique()->filter()->values()->all();
            $fkProblems += count($ids) - DB::table($table)->whereIn('id', $ids ?: [0])->count();
        }
        $fkProblems += $assets->filter(fn ($a) => DB::table('units')->where('id', $a->unit_id)->value('organization_id') != $a->organization_id)->count();
        $this->check('foreign_keys_valid', $fkProblems === 0, "{$fkProblems} broken reference(s)");

        // 8. Maintenance chronology and workflow consistency.
        $chrono = 0;
        foreach ($jobs as $job) {
            $asset = $assets[$job->asset_id] ?? null;
            $created = substr((string) $job->created_at, 0, 10);
            $bad = ! $asset || $created < (string) $asset->purchase_date
                || ($job->scheduled_date && $job->scheduled_date < $created)
                || ($job->actual_completion_date && $job->actual_completion_date < ($job->scheduled_date ?? $created))
                || ($job->payment_date && substr((string) $job->payment_date, 0, 10) < (string) $job->actual_completion_date);
            $bad = $bad || match ($job->status) {
                'completed' => ! $job->actual_completion_date || $job->actual_cost === null || ! $job->invoice_pdf_path
                    || ! Storage::disk('local')->exists((string) $job->invoice_pdf_path),
                'in_progress' => ! $job->scheduled_date || ! $job->approved_by || $job->actual_completion_date || $job->payment_status !== 'unpaid',
                'approved' => ! $job->approved_by || $job->actual_completion_date || $job->payment_status !== 'unpaid',
                'pending' => $job->approved_by || $job->scheduled_date || $job->actual_completion_date || $job->payment_status !== 'unpaid',
                default => true,
            };
            $chrono += $bad ? 1 : 0;
        }
        $this->check('maintenance_chronology', $chrono === 0, "{$chrono} job(s) out of order or inconsistent with workflow");

        // 9. Payment balances.
        $completed = $jobs->where('status', 'completed');
        $paid = $completed->where('payment_status', 'paid');
        $outstanding = $completed->whereIn('payment_status', ['unpaid', 'pending']);
        $balanceBad = $paid->filter(fn ($j) => ! $j->payment_date || ($jobMeta[$j->id]['outstanding'] ?? 1) != 0
            || abs(($jobMeta[$j->id]['amount_paid'] ?? 0) - (float) $j->actual_cost) > 0.001)->count()
            + $outstanding->filter(fn ($j) => $j->payment_date || ($jobMeta[$j->id]['amount_paid'] ?? 1) != 0
            || abs(($jobMeta[$j->id]['outstanding'] ?? 0) - (float) $j->actual_cost) > 0.001)->count()
            + $jobs->where('status', '!=', 'completed')->where('payment_status', 'paid')->count();
        $this->check('payment_balances', $balanceBad === 0, "{$balanceBad} job(s) with inconsistent payment state");

        // 10. Transfer approval states.
        $transferBad = 0;
        foreach ($transfers as $t) {
            $ok = match ($t->status) {
                'pending' => ! $t->approved_by && ! $t->approved_at && ! $t->completed_at && ! $t->rejected_at,
                'approved' => $t->approved_by && $t->approved_at && ! $t->completed_at && ! $t->rejected_at && $t->approved_at > $t->requested_at,
                'completed' => $t->approved_at && $t->completed_at && $t->completed_by && $t->approved_at > $t->requested_at && $t->completed_at > $t->approved_at,
                'rejected' => $t->rejected_at && $t->remarks && ! $t->completed_at && $t->rejected_at > $t->requested_at,
                default => false,
            };
            $asset = $assets[$t->asset_id] ?? null;
            $ok = $ok && $asset && $t->from_unit_id != $t->to_unit_id
                && substr((string) $t->requested_at, 0, 10) > (string) $asset->purchase_date
                && (! in_array($t->status, ['pending', 'approved'], true) || in_array($asset->status, ['active', 'under_maintenance'], true));
            $transferBad += $ok ? 0 : 1;
        }
        $this->check('transfer_states_valid', $transferBad === 0, "{$transferBad} transfer(s) in an impossible state");

        // 11. Current custody = initial custody replayed through completed transfers only.
        $custodyBad = 0;
        $byAsset = $transfers->sortBy('requested_at')->groupBy('asset_id');
        foreach ($assets as $asset) {
            $meta = json_decode((string) ($registry[$asset->id]->meta ?? '{}'), true);
            $unit = $meta['initial_unit_id'] ?? null;
            $user = $meta['initial_user_id'] ?? null;
            foreach ($byAsset[$asset->id] ?? [] as $t) {
                if ($t->from_unit_id != $unit || $t->from_user_id != $user) {
                    $custodyBad++;
                }
                if ($t->status === 'completed') {
                    [$unit, $user] = [$t->to_unit_id, $t->to_user_id];
                }
            }
            $custodyBad += ($asset->unit_id == $unit && $asset->user_id == $user) ? 0 : 1;
        }
        $this->check('custody_consistent', $custodyBad === 0, "{$custodyBad} custody mismatch(es)");

        // 12. Financial aggregation: SQL SUM/COUNT per year == row-level totals.
        $sql = $this->yearAggregates($assetIds);
        $aggOk = true;
        foreach ($byYear as $year => $count) {
            $aggOk = $aggOk && ($sql[$year]['count'] ?? null) == $count && abs(($sql[$year]['value'] ?? -1) - $valueByYear[$year]) < 0.01;
        }
        $ratios = collect($byYear)->map(fn ($c, $y) => $valueByYear[$y] / max(1, $c))->values();
        $independent = $ratios->max() / max(1, $ratios->min()) > 1.5;
        $this->check('financial_aggregation', $aggOk, 'SQL COUNT/SUM per year reconcile with stored rows');
        $this->check('chart_series_independent', $independent, 'average cost per asset varies by year, so value is not a scaled count');

        // 13. Search by code, serial, brand and model.
        $searchBad = 0;
        foreach ($assets->take(25) as $asset) {
            foreach (['asset_code', 'serial_number', 'brand', 'model_number'] as $column) {
                // Explicit escape character: MySQL's default LIKE escape is "\", which asset codes contain.
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $asset->{$column}).'%';
                if ($asset->{$column} && ! DB::table('assets')->whereRaw("{$column} like ? escape '!'", [$term])->where('id', $asset->id)->exists()) {
                    $searchBad++;
                }
            }
        }
        $this->check('search_finds_assets', $searchBad === 0, "{$searchBad} failed lookup(s)");

        // 14. AI research inputs.
        $aiTypes = ['DPC', 'LTP', 'LJP', 'PCM', 'SCN', 'PRJ', 'NSW', 'WRT', 'EHD', 'SRV', 'TEL', 'BAD'];
        $aiAssets = $assets->filter(fn ($a) => in_array($abbr[$a->assetname_id] ?? '', $aiTypes, true));
        $withModel = $aiAssets->filter(fn ($a) => filled($a->model_number))->count();
        $incomplete = $assets->filter(fn ($a) => blank($a->brand) || ! $a->purchase_date || $a->purchase_cost === null || ! $a->assetname_id)->count();
        $polluted = $assets->filter(fn ($a) => $a->specifications !== null || $a->market_price_amount !== null)->count();
        $this->check('ai_inputs_complete', $incomplete === 0 && $withModel === $aiAssets->count() && $polluted === 0,
            "{$incomplete} missing core field(s); {$withModel}/{$aiAssets->count()} ICT assets have a model number; {$polluted} with specs/market price");

        // 17-18. No future dates.
        $future = $assets->filter(fn ($a) => $a->purchase_date > $end || $a->created_at > $endStamp || $a->updated_at > $endStamp)->count()
            + $jobs->filter(fn ($j) => collect([$j->created_at, $j->updated_at, $j->scheduled_date, $j->expected_completion_date, $j->actual_completion_date, $j->payment_date, $j->invoice_uploaded_at ?? null])
                ->filter()->contains(fn ($d) => substr((string) $d, 0, 10) > $end))->count()
            + $transfers->filter(fn ($t) => collect([$t->requested_at, $t->approved_at, $t->completed_at, $t->rejected_at])
                ->filter()->contains(fn ($d) => substr((string) $d, 0, 10) > $end))->count();
        $this->check('no_future_dates', $future === 0, "{$future} record(s) dated after {$end}");
        $late2026 = $assets->filter(fn ($a) => str_starts_with((string) $a->purchase_date, '2026') && $a->purchase_date > $end)->count();
        $this->check('no_2026_after_cutoff', $late2026 === 0, "{$late2026} 2026 acquisition(s) after {$end}");

        $stats = [
            'by_year' => collect($byYear)->map(fn ($c, $y) => ['count' => $c, 'value' => $valueByYear[$y]])->all(),
            'total_value' => round(array_sum($valueByYear), 2),
            'by_category' => $assets->groupBy(fn ($a) => $types[$a->assetname_id] ?? '?')->map->count()->sortDesc()->all(),
            'by_status' => $assets->groupBy('status')->map->count()->all(),
            'maintenance' => [
                'by_status' => $jobs->groupBy(fn ($j) => $j->status.($j->status === 'completed' ? '/'.$j->payment_status : ''))->map->count()->all(),
                'paid_total' => round($paid->sum('actual_cost'), 2),
                'outstanding_total' => round($outstanding->sum('actual_cost'), 2),
                'completed_total' => round($completed->sum('actual_cost'), 2),
                'assets_with_jobs' => $jobs->pluck('asset_id')->unique()->count(),
                'assets_with_multiple_jobs' => $jobs->groupBy('asset_id')->filter(fn ($g) => $g->count() > 1)->count(),
            ],
            'transfers' => $transfers->groupBy('status')->map->count()->all(),
            'qr' => ['assets' => $assets->count(), 'missing_image' => $qrMissing, 'bad_route' => $qrBadRoute],
            'images' => ['bad' => $badImage, 'wrong_type' => $wrongType, 'distinct' => $assets->pluck('asset_image')->unique()->count()],
            'ai' => ['ict_assets' => $aiAssets->count(), 'ict_with_model' => $withModel, 'with_part_number' => $assets->whereNotNull('part_number')->count()],
        ];

        return [
            'passed' => collect($this->checks)->every(fn ($c) => $c['passed']),
            'checks' => $this->checks,
            'stats' => $stats,
        ];
    }

    /**
     * Write the manifest, data dictionary and audit reports next to each other
     * on the private disk.
     *
     * @return array<int, string> written paths
     */
    public function writeReports(array $result, int $sampleSize = 30): array
    {
        $config = $this->writer->config();
        $dir = 'demo-dataset/'.$config['dataset'];
        $disk = Storage::disk('local');
        $assets = $this->rows('assets', $this->writer->ownedIds('asset'));
        $types = DB::table('asset_names')->pluck('assetname', 'id');
        $written = [];

        $manifest = [
            'dataset' => $config['dataset'],
            'label' => $config['label'],
            'generator' => 'App\\Services\\DemoData\\SyntheticDatasetPlanner',
            'version' => $config['version'],
            'seed' => $config['seed'],
            'date_bounds' => [$config['start_date'], $config['end_date']],
            'year_counts' => $config['year_counts'],
            'price_index' => $config['price_index'],
            'price_assumptions' => 'All GHS bands and the price index in config/demo_dataset.php are rough testing estimates, not market research, not GES prices and not exchange rates.',
            'provenance' => 'Generated from config/demo_dataset.php with a fixed seed. Suppliers and maintenance providers are the application\'s existing fictional records. Brands/models are used only within their stated year windows; generic entries have no model number.',
            'images' => 'Representative type photographs from storage/app/public/'.SyntheticDatasetWriter::TYPE_PHOTO_MANIFEST.' (licence and attribution recorded there). They show the asset type, not the exact model or unit.',
            'ownership' => 'Every generated row is listed in table '.SyntheticDatasetWriter::REGISTRY.' with dataset = '.$config['dataset'].'. Asset codes use the official GES format (not a marker: use this registry), serials with "'.$config['serial_prefix'].'-", transfer codes with "SYN-AT-", users have emails @'.$config['user_email_domain'].' and are Inactive.',
            'limitations' => [
                'Synthetic records; any accuracy measured on them says nothing about real GES predictive performance.',
                'The live schema has no invoice amount, amount-paid or payment-reference columns; invoice/payment references and amounts are in '.SyntheticDatasetWriter::REGISTRY.'.meta and in the invoice PDF.',
                'No partial payments: the workflow supports unpaid / pending / paid only.',
                'Asset condition is not stored on assets in this database; condition is implied by status and maintenance history.',
                'specifications and market_price_* are left empty on purpose; they are filled only by human-approved AI research.',
                'No synthetic QR scan history is generated.',
            ],
            'verification' => $result,
            'generated_at' => now()->toIso8601String(),
        ];
        $disk->put("{$dir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $written[] = "{$dir}/manifest.json";

        $disk->put("{$dir}/data-dictionary.json", json_encode($this->dictionary(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $written[] = "{$dir}/data-dictionary.json";

        // Per-year category mix.
        $mix = [];
        foreach ($assets as $asset) {
            $mix[$types[$asset->assetname_id] ?? '?'][(int) substr((string) $asset->purchase_date, 0, 4)] = ($mix[$types[$asset->assetname_id] ?? '?'][(int) substr((string) $asset->purchase_date, 0, 4)] ?? 0) + 1;
        }
        ksort($mix);
        $years = array_keys($config['year_counts']);
        $csv = 'category,'.implode(',', $years).",total\n";
        foreach ($mix as $category => $counts) {
            $csv .= '"'.$category.'",'.implode(',', array_map(fn ($y) => $counts[$y] ?? 0, $years)).','.array_sum($counts)."\n";
        }
        $disk->put("{$dir}/category-mix-by-year.csv", $csv);
        $written[] = "{$dir}/category-mix-by-year.csv";

        // Random sample audit (seeded, so repeatable).
        $rng = new SeededRandom((int) $config['seed'] + 30);
        $sampleIds = array_slice($rng->shuffle($assets->keys()->all()), 0, $sampleSize);
        $sample = [];
        foreach ($sampleIds as $id) {
            $sample[] = $this->assetCase((int) $id);
        }
        $disk->put("{$dir}/sample-audit.json", json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $written[] = "{$dir}/sample-audit.json";

        $disk->put("{$dir}/example-cases.json", json_encode($this->exampleCases($assets), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $written[] = "{$dir}/example-cases.json";

        return $written;
    }

    /** End-to-end stories that cover the scenarios called for in the brief. */
    public function exampleCases($assets): array
    {
        $jobs = $this->rows('maintenance_requests', $this->writer->ownedIds('maintenance_request'));
        $transfers = $this->rows('asset_transfers', $this->writer->ownedIds('asset_transfer'));
        $jobCount = $jobs->groupBy('asset_id')->map->count();
        $pick = fn (callable $filter) => $assets->first(fn ($a) => $filter($a))?->id;

        $wanted = [
            'oldest asset still active, no maintenance' => $pick(fn ($a) => $a->status === 'active' && ! isset($jobCount[$a->id]) && $a->purchase_date < '2013-06-01'),
            'new 2026 asset, no history' => $pick(fn ($a) => str_starts_with((string) $a->purchase_date, '2026') && ! isset($jobCount[$a->id])),
            'multiple repairs' => $jobCount->sortDesc()->keys()->first(),
            'completed and paid repair' => $jobs->where('status', 'completed')->where('payment_status', 'paid')->first()?->asset_id,
            'completed repair, payment outstanding' => $jobs->where('status', 'completed')->where('payment_status', 'unpaid')->first()?->asset_id,
            'completed repair, payment processing' => $jobs->where('status', 'completed')->where('payment_status', 'pending')->first()?->asset_id,
            'repair in progress (under maintenance)' => $jobs->where('status', 'in_progress')->first()?->asset_id,
            'repair awaiting approval' => $jobs->where('status', 'pending')->first()?->asset_id,
            'approved transfer completed (custody moved)' => $transfers->where('status', 'completed')->first()?->asset_id,
            'approved transfer awaiting hand-over' => $transfers->where('status', 'approved')->first()?->asset_id,
            'pending transfer (custody unchanged)' => $transfers->where('status', 'pending')->first()?->asset_id,
            'rejected transfer' => $transfers->where('status', 'rejected')->first()?->asset_id,
            'disposed after obsolescence' => DB::table('asset_lifecycle_histories')->where('old_status', 'obsolete')->where('new_status', 'disposed')
                ->whereIn('asset_id', $assets->keys()->all() ?: [0])->orderBy('asset_id')->value('asset_id'),
            'disposed directly (damage / write-off)' => DB::table('asset_lifecycle_histories')->where('old_status', 'active')->where('new_status', 'disposed')
                ->whereIn('asset_id', $assets->keys()->all() ?: [0])->orderBy('asset_id')->value('asset_id'),
            'replacement review candidate (past useful life, repaired)' => $assets->filter(fn ($a) => $a->status === 'active' && ($jobCount[$a->id] ?? 0) >= 2)
                ->sortBy('purchase_date')->first()?->id,
        ];

        $cases = [];
        foreach (array_filter($wanted) as $label => $id) {
            $cases[] = ['scenario' => $label] + $this->assetCase((int) $id);
        }

        return $cases;
    }

    public function assetCase(int $assetId): array
    {
        $asset = DB::table('assets')->where('id', $assetId)->first();

        return [
            'asset_id' => $assetId,
            'asset_code' => $asset->asset_code,
            'type' => DB::table('asset_names')->where('id', $asset->assetname_id)->value('assetname'),
            'brand' => $asset->brand,
            'model_number' => $asset->model_number,
            'serial_number' => $asset->serial_number,
            'purchase_date' => $asset->purchase_date,
            'purchase_cost' => (float) $asset->purchase_cost,
            'warranty_end_date' => $asset->warranty_end_date,
            'status' => $asset->status,
            'unit' => DB::table('units')->where('id', $asset->unit_id)->value('name'),
            'custodian' => DB::table('users')->where('id', $asset->user_id)->value('name'),
            'image' => $asset->asset_image,
            'qr_code' => $asset->qr_code,
            'maintenance' => DB::table('maintenance_requests')->where('asset_id', $assetId)->orderBy('created_at')
                ->get(['id', 'status', 'payment_status', 'created_at', 'actual_completion_date', 'actual_cost', 'payment_date', 'fault_description'])->all(),
            'transfers' => DB::table('asset_transfers')->where('asset_id', $assetId)->orderBy('requested_at')
                ->get(['transfer_code', 'status', 'from_unit_id', 'to_unit_id', 'requested_at', 'approved_at', 'completed_at', 'rejected_at'])->all(),
            'lifecycle' => DB::table('asset_lifecycle_histories')->where('asset_id', $assetId)->orderBy('changed_at')
                ->get(['old_status', 'new_status', 'reason', 'changed_at'])->all(),
        ];
    }

    private function dictionary(): array
    {
        return [
            'assets.asset_code' => 'GES\\UNIT\\LOCATION\\TYPE\\LOCATION-SEQUENCE\\YEAR\\TYPE-SEQUENCE (2+ digits), assigned by AssetCodeGenerator at write time, numbered after the existing codes; both sequences reset each purchase year. Code keeps the original unit after transfers.',
            'assets.serial_number' => 'SYN-TYPE-YY-XXXXXX. Synthetic; not a manufacturer serial.',
            'assets.brand / model_number / part_number' => 'From config catalogue; model only within release-year window; null model for generic items.',
            'assets.purchase_cost' => 'GHS, 2 dp. band x price_index[year] x model tier x volume factor x +/-3%. Assumption, not market data.',
            'assets.purchase_date' => 'Working day in a weighted month of the acquisition year; never after end_date.',
            'assets.warranty_duration / warranty_end_date' => 'Months from a per-type profile; null where no warranty is on record.',
            'assets.description' => 'Labelled synthetic; includes configuration "as recorded" and purchase order reference.',
            'assets.specifications' => 'Always null: reserved for human-approved AI research.',
            'assets.market_price_*' => 'Always null: reserved for human-approved AI research.',
            'assets.asset_image' => 'Shared representative photograph for the asset type (licensed, see SOURCES.json).',
            'assets.qr_code' => 'PNG from DemoAssetQrService encoding route(assets.scan, uuid).',
            'assets.status' => 'active / under_maintenance (open scheduled job) / obsolete / disposed (with lifecycle history).',
            'assets.user_id / unit_id' => 'Current custody after completed transfers only.',
            'maintenance_requests.status' => 'pending (awaiting approval) / approved (awaiting scheduling) / in_progress / completed.',
            'maintenance_requests.payment_status' => 'unpaid / pending (with Accounts) / paid. Paid requires payment_date and invoice PDF.',
            'maintenance_requests.invoice_pdf_path' => 'Synthetic invoice PDF on the private disk for every completed job.',
            'asset_transfers.status' => 'pending / approved (not yet handed over) / completed (custody moved) / rejected (remarks hold reason).',
            'asset_lifecycle_histories' => 'Status changes (obsolete/disposed) and completed transfers; metadata.synthetic = true.',
            'audit_logs' => 'Transfer request/decision/completion entries; properties.synthetic = true.',
            'demo_dataset_records' => 'Ownership registry: dataset, record_type, record_id, batch_ref (procurement batch), meta (PO, initial custody, invoice/payment refs, amounts).',
            'users (synthetic)' => 'Name ends "(Demo)", email @'.$this->writer->config()['user_email_domain'].', Inactive, unknown random password. Request/approval staff hold the existing Admin role.',
        ];
    }

    private function scanRouteResolves(string $uuid): bool
    {
        try {
            $url = route('assets.scan', $uuid);
            $route = Route::getRoutes()->match(Request::create($url));

            return $route->getName() === 'assets.scan' && ($route->parameter('uuid') ?? $route->parameters()['uuid'] ?? null) === $uuid;
        } catch (Throwable) {
            return false;
        }
    }

    private function yearAggregates(array $assetIds): array
    {
        $out = [];

        foreach (array_chunk($assetIds, 900) as $chunk) {
            $rows = DB::table('assets')->whereIn('id', $chunk)
                ->selectRaw('substr(purchase_date, 1, 4) as y, count(*) as c, sum(purchase_cost) as v')
                ->groupBy(DB::raw('substr(purchase_date, 1, 4)'))->get();

            foreach ($rows as $row) {
                $out[(int) $row->y]['count'] = ($out[(int) $row->y]['count'] ?? 0) + (int) $row->c;
                $out[(int) $row->y]['value'] = round(($out[(int) $row->y]['value'] ?? 0) + (float) $row->v, 2);
            }
        }

        return $out;
    }

    private function rows(string $table, array $ids)
    {
        $rows = collect();

        foreach (array_chunk($ids, 900) as $chunk) {
            $rows = $rows->merge(DB::table($table)->whereIn('id', $chunk)->get());
        }

        return $rows->keyBy('id');
    }

    private function check(string $name, bool $passed, string $detail): void
    {
        $this->checks[$name] = ['passed' => $passed, 'detail' => $detail];
    }
}
