<?php

namespace App\Services\DemoData;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Builds the complete synthetic demo dataset as plain arrays.
 *
 * Pure and deterministic: the same config, seed and lookup context always
 * produce the same plan. Nothing here touches the database. People are
 * referenced by string keys and assets by plan index; the writer maps them to
 * real IDs after inserting.
 *
 * Context shape:
 *   types     [abbr => ['id', 'name', 'life']]
 *   units     [abbr => ['id', 'name', 'location', 'location_name', 'organization_id']]
 *   suppliers [id, ...]   providers [id, ...]   sources [name => id]
 *   images    [abbr => public-disk path|null]
 */
class SyntheticDatasetPlanner
{
    private SeededRandom $rng;

    private CarbonImmutable $start;

    private CarbonImmutable $end;

    private array $users = [];

    /** @var array<string, array<int, string>> unit abbr => user keys (first = unit asset officer) */
    private array $unitUsers = [];

    private array $assets = [];

    private array $batches = [];

    private array $maintenance = [];

    private array $transfers = [];

    private array $lifecycle = [];

    private array $audit = [];

    private array $usedSerials = [];

    private array $supplierWindows = [];

    public function __construct(private array $config, private array $context)
    {
        $this->start = CarbonImmutable::parse($config['start_date'])->startOfDay();
        $this->end = CarbonImmutable::parse($config['end_date'])->startOfDay();
    }

    public function build(): array
    {
        $this->validateContext();
        $this->rng = new SeededRandom((int) $this->config['seed']);

        $this->planUsers();
        $this->planSupplierWindows();
        $this->planAssets();
        $this->planStatusChanges();
        $this->planMaintenance();
        $this->planTransfers();
        $this->finaliseAssets();

        return [
            'dataset' => $this->config['dataset'],
            'version' => $this->config['version'],
            'seed' => $this->config['seed'],
            'as_of' => $this->end->toDateString(),
            'users' => $this->users,
            'batches' => $this->batches,
            'assets' => $this->assets,
            'maintenance' => $this->maintenance,
            'transfers' => $this->transfers,
            'lifecycle' => $this->lifecycle,
            'audit' => $this->audit,
        ];
    }

    /* =========================================================
       CONTEXT
    ========================================================= */

    private function validateContext(): void
    {
        $missing = array_diff(array_keys($this->config['types']), array_keys($this->context['types'] ?? []));

        if ($missing !== []) {
            // Types absent from asset_names are skipped, never invented.
            foreach ($missing as $abbr) {
                unset($this->config['types'][$abbr]);
            }
        }

        foreach (['units', 'suppliers', 'providers', 'sources'] as $key) {
            if (empty($this->context[$key])) {
                throw new RuntimeException("Synthetic dataset needs at least one row in {$key}.");
            }
        }

        if ($this->config['types'] === []) {
            throw new RuntimeException('None of the configured asset types exist in asset_names.');
        }

        if (array_sum($this->config['year_counts']) !== 2000) {
            throw new RuntimeException('Configured year counts must total exactly 2,000.');
        }
    }

    /* =========================================================
       PEOPLE
    ========================================================= */

    private function planUsers(): void
    {
        $usedNames = [];
        $nextName = function () use (&$usedNames): array {
            for ($i = 0; $i < 500; $i++) {
                $first = $this->rng->pick($this->config['first_names']);
                $last = $this->rng->pick($this->config['last_names']);

                if (! isset($usedNames["{$first} {$last}"])) {
                    $usedNames["{$first} {$last}"] = true;

                    return [$first, $last];
                }
            }

            throw new RuntimeException('Ran out of unique synthetic names.');
        };

        $domain = $this->config['user_email_domain'];
        [$minPerUnit, $maxPerUnit] = $this->config['custodians_per_unit'];

        foreach ($this->context['units'] as $abbr => $unit) {
            $count = $this->rng->int($minPerUnit, $maxPerUnit);

            for ($i = 0; $i < $count; $i++) {
                [$first, $last] = $nextName();
                $key = 'custodian-'.Str::slug($abbr).'-'.($i + 1);

                $this->users[$key] = [
                    'key' => $key,
                    'kind' => 'custodian',
                    'name' => "{$first} {$last} (Demo)",
                    'email' => Str::slug("{$first} {$last}", '.').'.'.Str::slug($abbr).'@'.$domain,
                    'unit' => $abbr,
                    'unit_id' => $unit['id'],
                    'title' => $i === 0 ? 'Unit asset officer' : 'Officer',
                    'role' => null,
                ];
                $this->unitUsers[$abbr][] = $key;
            }
        }

        foreach ($this->config['staff'] as $staff) {
            $unitAbbr = isset($this->context['units'][$staff['unit']])
                ? $staff['unit']
                : array_key_first($this->context['units']);
            [$first, $last] = $nextName();

            $this->users[$staff['key']] = [
                'key' => $staff['key'],
                'kind' => 'staff',
                'name' => "{$first} {$last} (Demo)",
                'email' => Str::slug("{$first} {$last}", '.').'.'.$staff['key'].'@'.$domain,
                'unit' => $unitAbbr,
                'unit_id' => $this->context['units'][$unitAbbr]['id'],
                'title' => $staff['title'],
                'role' => $staff['role'],
            ];
        }
    }

    private function planSupplierWindows(): void
    {
        foreach ($this->context['suppliers'] as $id) {
            $from = 2012 + $this->rng->int(0, 6);
            $this->supplierWindows[$id] = [$from, $from + $this->rng->int(6, 14)];
        }
    }

    /* =========================================================
       ASSETS AND PROCUREMENT BATCHES
    ========================================================= */

    private function planAssets(): void
    {
        foreach ($this->config['year_counts'] as $year => $count) {
            $items = $this->planYear((int) $year, (int) $count);

            usort($items, fn ($a, $b) => [$a['purchase_date'], $a['batch_seq'], $a['item_seq']]
                <=> [$b['purchase_date'], $b['batch_seq'], $b['item_seq']]);

            foreach ($items as $item) {
                // Assigned on write by AssetCodeGenerator (in this purchase order), so the
                // numbers continue after the codes already in the database.
                $item['asset_code'] = null;
                unset($item['batch_seq'], $item['item_seq']);
                $item['index'] = count($this->assets);
                $this->assets[] = $item;
            }
        }
    }

    private function planYear(int $year, int $count): array
    {
        $lastMonth = $year === $this->end->year ? $this->end->month : 12;
        $monthWeights = [];

        foreach (range(1, $lastMonth) as $month) {
            $weight = $this->config['month_weights'][$month] * $this->rng->between(0.4, 1.6);

            if ($year === $this->end->year && $month === $this->end->month) {
                $weight *= $this->end->day / $this->end->daysInMonth;
            }

            $monthWeights[$month] = $weight;
        }

        // A year has one or two quiet months with no purchasing at all.
        $quiet = $this->rng->sample(array_fill_keys(array_keys($monthWeights), 1), $this->rng->chance(0.5) ? 2 : 1);

        foreach ($quiet as $month) {
            $monthWeights[$month] = 0;
        }

        $typeWeights = $this->typeWeightsFor($year);
        $profiles = array_map(fn ($p) => $p['weight'], $this->config['batch_profiles']);
        $items = [];
        $remaining = $count;
        $batchSeq = 0;

        while ($remaining > 0) {
            $batchSeq++;
            $profileName = $this->rng->weighted($profiles);
            $profile = $this->config['batch_profiles'][$profileName];
            $size = min($remaining, $this->rng->int($profile['min'], $profile['max']));
            $remaining -= $size;

            $month = (int) $this->rng->weighted($monthWeights);
            $maxDay = ($year === $this->end->year && $month === $this->end->month)
                ? $this->end->day
                : CarbonImmutable::create($year, $month, 1)->daysInMonth;
            $date = $this->businessDay(CarbonImmutable::create($year, $month, $this->rng->int(1, $maxDay)));

            $lead = (string) $this->rng->weighted($typeWeights);
            $theme = $size >= 4 ? $this->themeFor($lead) : null;
            $primaryUnit = $this->unitFor($lead, null);
            $unitSet = $size >= 15
                ? array_values(array_unique([$primaryUnit, ...$this->rng->sample(array_fill_keys(array_keys($this->context['units']), 1), $this->rng->int(2, 5))]))
                : [$primaryUnit];

            $ref = sprintf('SYN-PB-%d-%03d', $year, $batchSeq);
            $batch = [
                'ref' => $ref,
                'po' => sprintf('SYN-PO-%d-%04d', $year, $batchSeq * 7 + $this->rng->int(0, 6)),
                'year' => $year,
                'date' => $date->toDateString(),
                'profile' => $profileName,
                'theme' => $theme,
                'lead_type' => $lead,
                'supplier_id' => $this->supplierFor($year),
                'source_id' => $this->sourceFor(),
                'provider_id' => (int) $this->rng->pick($this->context['providers']),
                'size' => $size,
                'units' => $unitSet,
            ];

            // Choose type + model per item, then price once per (type, model).
            $batchModels = [];
            $lines = [];

            for ($i = 0; $i < $size; $i++) {
                $type = $lead;

                if ($theme !== null && $i > 0 && ! $this->rng->chance(0.35)) {
                    $themeWeights = array_intersect_key($typeWeights, array_flip($this->config['themes'][$theme]));
                    $themeWeights = array_filter($themeWeights, fn ($w) => $w > 0);

                    if ($themeWeights !== []) {
                        $type = (string) $this->rng->weighted($themeWeights);
                    }
                }

                if (isset($batchModels[$type]) && $this->rng->chance(0.8)) {
                    $modelIndex = $this->rng->pick($batchModels[$type]);
                } else {
                    $modelIndex = $this->rng->pick(array_keys($this->modelsFor($type, $year)));
                    $batchModels[$type][] = $modelIndex;
                }

                $lines[] = ['type' => $type, 'model' => $modelIndex];
            }

            $prices = [];
            $warranties = [];
            $lineCounts = [];

            foreach ($lines as $line) {
                $lineCounts[$line['type'].'#'.$line['model']] = ($lineCounts[$line['type'].'#'.$line['model']] ?? 0) + 1;
            }

            foreach ($lines as $i => $line) {
                $type = $line['type'];
                $typeConfig = $this->config['types'][$type];
                $model = $typeConfig['models'][$line['model']];
                $lineKey = $type.'#'.$line['model'];

                $prices[$lineKey] ??= $this->unitPrice($type, $model, $year, $lineCounts[$lineKey]);
                if (! array_key_exists($type, $warranties)) {
                    $warranties[$type] = $this->warrantyMonths($typeConfig['warranty']);
                }

                $unit = $size >= 15 ? $this->unitFor($type, $this->rng->pick($unitSet)) : $this->unitFor($type, $primaryUnit);
                $registered = $this->businessDay($date->addDays($this->rng->int(0, 21)));
                $registered = $registered->greaterThan($this->end) ? $this->end : $registered;
                $months = $warranties[$type];

                $items[] = [
                    'batch_seq' => $batchSeq,
                    'item_seq' => $i,
                    'batch_ref' => $ref,
                    'po' => $batch['po'],
                    'type' => $type,
                    'type_id' => $this->context['types'][$type]['id'],
                    'type_name' => $this->context['types'][$type]['name'],
                    'useful_life' => (int) ($this->context['types'][$type]['life'] ?? 5),
                    'brand' => $model['brand'],
                    'model_number' => $model['model'],
                    'part_number' => $model['part'] ?? null,
                    'config_text' => $model['config'],
                    'purchase_date' => $date->toDateString(),
                    'purchase_cost' => $prices[$lineKey],
                    'warranty_duration' => $months,
                    'warranty_end_date' => $months ? $date->addMonths($months)->toDateString() : null,
                    'serial_number' => $this->serial($type, $year),
                    'unit' => $unit,
                    'initial_unit' => $unit,
                    'initial_unit_id' => $this->context['units'][$unit]['id'],
                    'user_key' => $this->custodianFor($unit, $type),
                    'supplier_id' => $batch['supplier_id'],
                    'source_id' => $batch['source_id'],
                    'maintenance_id' => $this->rng->chance(0.85) ? $batch['provider_id'] : (int) $this->rng->pick($this->context['providers']),
                    'asset_image' => $this->context['images'][$type] ?? null,
                    'created_at' => $this->stamp($registered),
                    'events' => [],
                    'status' => 'active',
                    'status_date' => null,
                ];
            }

            $this->batches[$ref] = $batch;
        }

        return $items;
    }

    private function typeWeightsFor(int $year): array
    {
        $weights = [];

        foreach ($this->config['types'] as $abbr => $type) {
            $weights[$abbr] = $this->modelsFor($abbr, $year) === []
                ? 0
                : $type['weight'] * (1 + $type['trend']) ** ($year - 2012);
        }

        return $weights;
    }

    private function modelsFor(string $type, int $year): array
    {
        return array_filter(
            $this->config['types'][$type]['models'],
            fn ($m) => $m['from'] <= $year && $m['to'] >= $year,
        );
    }

    private function themeFor(string $type): ?string
    {
        $themes = array_keys(array_filter($this->config['themes'], fn ($types) => in_array($type, $types, true)));

        return $themes === [] ? null : $this->rng->pick($themes);
    }

    /** Preferred office for the type 70% of the time, otherwise the given/random unit. */
    private function unitFor(string $type, ?string $fallback): string
    {
        $preferred = array_values(array_filter(
            $this->config['types'][$type]['units'] ?? [],
            fn ($abbr) => isset($this->context['units'][$abbr]),
        ));

        if ($preferred !== [] && ($fallback === null || ! in_array($fallback, $preferred, true)) && $this->rng->chance(0.7)) {
            return $this->rng->pick($preferred);
        }

        return $fallback ?? $this->rng->pick(array_keys($this->context['units']));
    }

    private function custodianFor(string $unit, string $type): string
    {
        $keys = $this->unitUsers[$unit];

        return ($this->config['types'][$type]['shared'] ?? false) ? $keys[0] : $this->rng->pick($keys);
    }

    private function supplierFor(int $year): int
    {
        $active = array_keys(array_filter($this->supplierWindows, fn ($w) => $w[0] <= $year && $w[1] >= $year));

        return (int) $this->rng->pick(count($active) >= 3 ? $active : array_keys($this->supplierWindows));
    }

    private function sourceFor(): int
    {
        $weights = array_intersect_key($this->config['source_weights'], $this->context['sources']);

        if ($weights === []) {
            return (int) $this->rng->pick($this->context['sources']);
        }

        return (int) $this->context['sources'][$this->rng->weighted($weights)];
    }

    private function unitPrice(string $type, array $model, int $year, int $quantity): float
    {
        [$low, $high] = $this->config['types'][$type]['band'];
        $volume = $quantity >= 10 ? 0.94 : ($quantity >= 5 ? 0.97 : 1.0);
        $price = ($low + ($high - $low) * $this->rng->centred())
            * $this->config['price_index'][$year]
            * $model['tier']
            * $volume
            * $this->rng->between(0.97, 1.03);

        // Local workshops tend to quote whole cedis.
        if ($model['brand'] === 'Local Fabrication' && $this->rng->chance(0.6)) {
            return (float) (round($price / 5) * 5);
        }

        return round(floor($price) + $this->rng->int(0, 99) / 100, 2);
    }

    private function warrantyMonths(string $profile): ?int
    {
        $pick = $this->rng->weighted($this->config['warranty_profiles'][$profile]);

        return $pick === 'none' ? null : (int) $pick;
    }

    private function serial(string $type, int $year): string
    {
        do {
            $serial = sprintf('%s-%s-%02d-%s', $this->config['serial_prefix'], $type, $year % 100, $this->rng->code(6));
        } while (isset($this->usedSerials[$serial]));

        $this->usedSerials[$serial] = true;

        return $serial;
    }

    /* =========================================================
       OBSOLESCENCE AND DISPOSAL
    ========================================================= */

    private function planStatusChanges(): void
    {
        $cfg = $this->config['lifecycle'];

        foreach ($this->assets as $i => $asset) {
            $purchase = CarbonImmutable::parse($asset['purchase_date']);
            $age = $purchase->diffInDays($this->end) / 365.25;
            $life = $asset['useful_life'];
            $pastLife = $age > $life;

            $pDisposed = $age > $life + 1
                ? min($cfg['p_disposed_max'], $cfg['p_disposed_past_life'] + $cfg['p_disposed_per_extra_year'] * ($age - $life - 1))
                : ($age > 1 ? $cfg['p_early_disposal'] : 0);
            $obsoleteType = in_array($asset['type'], $cfg['obsolete_types'], true);

            if ($this->rng->chance($pDisposed)) {
                $earliest = $purchase->addDays((int) max(120, $life * 365 * ($pastLife ? 0.8 : 0.2)));
                $date = $this->randomBusinessDay($earliest, $this->end->subDays(20));

                if ($obsoleteType && $this->rng->chance(0.5) && $earliest->lessThan($date->subDays(60))) {
                    $obsoleteDate = $this->randomBusinessDay($earliest, $date->subDays(40));
                    $this->statusEvent($i, 'active', 'obsolete', $obsoleteDate, 'Declared obsolete after technical assessment (synthetic).');
                    $this->statusEvent($i, 'obsolete', 'disposed', $date, $this->disposalReason());
                } else {
                    $this->statusEvent($i, 'active', 'disposed', $date, $this->disposalReason());
                }
            } elseif ($obsoleteType && $pastLife && $this->rng->chance($cfg['p_obsolete_past_life'])) {
                $date = $this->randomBusinessDay($purchase->addDays((int) ($life * 365)), $this->end->subDays(10));
                $this->statusEvent($i, 'active', 'obsolete', $date, $this->rng->pick([
                    'Declared obsolete: no longer supported by current software.',
                    'Declared obsolete: spare parts no longer available locally.',
                    'Declared obsolete after technical assessment; awaiting board of survey.',
                ]));
            }
        }
    }

    private function disposalReason(): string
    {
        return $this->rng->pick([
            'Disposed through board of survey: beyond economic repair.',
            'Disposed by public auction after board of survey.',
            'Written off: damaged beyond repair.',
            'Disposed: replaced under equipment refresh; unit unserviceable.',
        ]);
    }

    private function statusEvent(int $asset, string $old, string $new, CarbonImmutable $date, string $reason): void
    {
        $this->assets[$asset]['status'] = $new;
        $this->assets[$asset]['status_date'] = $date->toDateString();
        $this->assets[$asset]['events'][] = $date->toDateString();

        $this->lifecycle[] = [
            'asset' => $asset,
            'changed_by' => $this->rng->pick(['approver-1', 'approver-2']),
            'old_status' => $old,
            'new_status' => $new,
            'reason' => $reason,
            'metadata' => ['action' => 'status_changed'],
            'changed_at' => $this->stamp($date),
        ];
    }

    /* =========================================================
       MAINTENANCE
    ========================================================= */

    private function planMaintenance(): void
    {
        $cfg = $this->config['maintenance'];
        $weights = $this->config['maintenance_type_weights'];
        $openAssets = [];

        $candidateWeights = function (callable $filter) use ($weights): array {
            $out = [];

            foreach ($this->assets as $i => $asset) {
                if (! isset($this->config['issues'][$asset['type']]) || ! $filter($asset, $i)) {
                    continue;
                }

                $age = CarbonImmutable::parse($asset['purchase_date'])->diffInDays($this->end) / 365.25;
                $out[$i] = ($weights[$asset['type']] ?? 0.5) * (1 + $age * 0.08);
            }

            return $out;
        };

        // Open jobs: only on assets that are currently in service.
        $openEligible = $candidateWeights(fn ($a) => $a['status'] === 'active'
            && $a['purchase_date'] <= $this->end->subDays(90)->toDateString());
        $openCount = $cfg['in_progress'] + $cfg['pending_approval'] + $cfg['approved_awaiting_schedule'];
        $openPicked = $this->rng->sample($openEligible, $openCount);

        foreach ($openPicked as $n => $assetIndex) {
            $kind = $n < $cfg['in_progress']
                ? 'in_progress'
                : ($n < $cfg['in_progress'] + $cfg['pending_approval'] ? 'pending' : 'approved');
            $openAssets[$assetIndex] = $this->openJob($assetIndex, $kind);
        }

        // Completed jobs: historical, on any asset, before it left service.
        $completedTarget = $cfg['completed_paid'] + $cfg['completed_unpaid'] + $cfg['completed_payment_processing'];
        $completedEligible = $candidateWeights(fn ($a) => $a['purchase_date'] <= $this->end->subDays(150)->toDateString());
        $order = $this->rng->sample($completedEligible, count($completedEligible));
        $completed = [];
        $multiLeft = $cfg['multi_job_assets'];

        foreach ($order as $assetIndex) {
            if (count($completed) >= $completedTarget) {
                break;
            }

            $wanted = 1;

            if ($multiLeft > 0) {
                $wanted = (int) $this->rng->weighted([2 => 60, 3 => 28, 4 => 12]);
            }

            $wanted = min($wanted, $completedTarget - count($completed));
            $jobs = $this->completedJobs($assetIndex, $wanted, $openAssets[$assetIndex] ?? null);

            if ($jobs === []) {
                continue;
            }

            if (count($jobs) > 1) {
                $multiLeft--;
            }

            array_push($completed, ...$jobs);
        }

        if (count($completed) !== $completedTarget) {
            throw new RuntimeException('Could not place all completed maintenance jobs.');
        }

        $this->assignPayments($completed);

        // Stable order by request date.
        usort($this->maintenance, fn ($a, $b) => [$a['created_at'], $a['asset']] <=> [$b['created_at'], $b['asset']]);
    }

    /** @return string created_at of the open job */
    private function openJob(int $assetIndex, string $kind): string
    {
        $asset = $this->assets[$assetIndex];
        $issue = $this->rng->pick($this->config['issues'][$asset['type']]);
        $purchase = CarbonImmutable::parse($asset['purchase_date']);
        $cost = $this->issueCost($issue, $this->end->year);
        $requester = $this->rng->pick(['requester-1', 'requester-2']);

        $job = [
            'asset' => $assetIndex,
            'kind' => $kind,
            'status' => $kind,
            'priority' => $this->priority($issue[3]),
            'fault_description' => $this->faultText($issue[0], $asset),
            'requested_by' => $requester,
            'approved_by' => null,
            'completed_by' => null,
            'maintenance_provider_id' => null,
            'estimated_cost' => null,
            'actual_cost' => null,
            'payment_status' => 'unpaid',
            'payment_date' => null,
            'scheduled_date' => null,
            'expected_completion_date' => null,
            'actual_completion_date' => null,
            'approval_comment' => null,
            'completion_note' => null,
            'invoice' => null,
            'payment_ref' => null,
        ];

        if ($kind === 'in_progress') {
            $duration = $this->rng->int(3, 21);
            $earliest = $purchase->addDays(30)->max($this->end->subDays(130));
            $scheduled = $this->randomBusinessDay($earliest, $this->end->subDays($duration));
            $created = $this->businessDay($scheduled->subDays($this->rng->int(1, 10)), true)->max($purchase->addDays(20));

            $job['scheduled_date'] = $scheduled->toDateString();
            $job['expected_completion_date'] = $scheduled->addDays($duration)->toDateString();
            $job['approved_by'] = $this->rng->pick(['approver-1', 'approver-2']);
            $job['approval_comment'] = $this->rng->pick([
                'Approved. Provider to collect the unit for workshop repair.',
                'Approved; proceed with on-site repair.',
                'Approved. Awaiting replacement part from provider.',
                'Approved subject to final invoice not exceeding the estimate.',
            ]);
            $job['maintenance_provider_id'] = $this->providerFor($asset);
            $job['estimated_cost'] = $this->estimate($cost);
            $job['completion_note'] = null;
            $job['created_at'] = $this->stamp($created);
            $job['updated_at'] = $this->stamp($scheduled);
            $this->assets[$assetIndex]['events'][] = $scheduled->toDateString();
        } elseif ($kind === 'approved') {
            $created = $this->randomBusinessDay($this->end->subDays(70), $this->end->subDays(5));
            $approvedOn = $this->businessDay($created->addDays($this->rng->int(1, 5)))->min($this->end);

            $job['approved_by'] = $this->rng->pick(['approver-1', 'approver-2']);
            $job['approval_comment'] = $this->rng->pick([
                'Approved. Schedule once the provider confirms availability.',
                'Approved; scheduling pending provider quotation.',
                'Approved. To be scheduled after the current examinations period.',
            ]);
            $job['maintenance_provider_id'] = $this->providerFor($asset);
            $job['estimated_cost'] = $this->estimate($cost);
            $job['created_at'] = $this->stamp($created);
            $job['updated_at'] = $this->stamp($approvedOn);
        } else {
            $created = $this->randomBusinessDay($this->end->subDays(45), $this->end->subDay());

            $job['maintenance_provider_id'] = $this->rng->chance(0.5) ? $this->providerFor($asset) : null;
            $job['estimated_cost'] = $this->rng->chance(0.6) ? $this->estimate($cost) : null;
            $job['created_at'] = $this->stamp($created);
            $job['updated_at'] = $job['created_at'];
        }

        $this->maintenance[] = $job;

        return $job['created_at'];
    }

    private function completedJobs(int $assetIndex, int $wanted, ?string $openJobCreated): array
    {
        $asset = $this->assets[$assetIndex];
        $windowStart = CarbonImmutable::parse($asset['purchase_date'])->addDays(45);
        $windowEnd = $this->end->subDays(10);

        if ($asset['status_date']) {
            $windowEnd = $windowEnd->min(CarbonImmutable::parse($asset['status_date'])->subDays(15));
        }

        if ($openJobCreated) {
            $windowEnd = $windowEnd->min(CarbonImmutable::parse($openJobCreated)->subDays(10));
        }

        $span = (int) $windowStart->diffInDays($windowEnd, false);

        while ($wanted > 0 && $span / $wanted < 40) {
            $wanted--;
        }

        if ($wanted === 0) {
            return [];
        }

        $segment = intdiv($span, $wanted);
        $jobs = [];

        for ($k = 0; $k < $wanted; $k++) {
            $segmentStart = $windowStart->addDays($k * $segment);
            $requested = $this->businessDay($segmentStart->addDays($this->rng->int(0, $segment - 30)));
            $scheduled = $this->businessDay($requested->addDays($this->rng->int(1, 8)));
            $completedOn = $this->businessDay($scheduled->addDays($this->rng->int(1, 12)));

            if ($completedOn->greaterThan($windowEnd)) {
                $completedOn = $windowEnd;
            }

            $issue = $this->rng->pick($this->config['issues'][$asset['type']]);
            $cost = $this->issueCost($issue, $completedOn->year);
            $note = $issue[1];

            $underWarranty = $asset['warranty_end_date'] && $completedOn->toDateString() <= $asset['warranty_end_date'];

            if ($underWarranty && $this->rng->chance(0.6)) {
                $cost = round($cost * 0.25, 2);
                $note .= ' Covered by supplier warranty; transport and labour charged only.';
            } elseif ($this->rng->chance(0.3)) {
                $note .= ' '.$this->rng->pick([
                    'Parts sourced locally.',
                    'Old part returned to stores for records.',
                    'Custodian advised on proper use.',
                    'Recommended for replacement at next budget cycle.',
                    'Tested with the custodian before hand-over.',
                ]);
            }

            $requester = $this->rng->pick(['requester-1', 'requester-2']);

            $job = [
                'asset' => $assetIndex,
                'kind' => 'completed',
                'status' => 'completed',
                'priority' => $this->priority($issue[3]),
                'fault_description' => $this->faultText($issue[0], $asset),
                'requested_by' => $requester,
                'approved_by' => $this->rng->pick(['approver-1', 'approver-2']),
                'completed_by' => $requester,
                'maintenance_provider_id' => $this->providerFor($asset),
                'estimated_cost' => $this->estimate($cost),
                'actual_cost' => $cost,
                'payment_status' => 'unpaid',
                'payment_date' => null,
                'scheduled_date' => $scheduled->toDateString(),
                'expected_completion_date' => $scheduled->addDays($this->rng->int(2, 14))->toDateString(),
                'actual_completion_date' => $completedOn->toDateString(),
                'approval_comment' => $this->rng->pick([
                    'Approved.',
                    'Approved; proceed.',
                    'Approved within the unit maintenance budget.',
                    'Approved after inspection by Estates.',
                    null,
                ]),
                'completion_note' => $note,
                'created_at' => $this->stamp($requested),
                'updated_at' => $this->stamp($completedOn),
                'invoice' => null,
                'payment_ref' => null,
            ];

            $this->maintenance[] = $job;
            $jobs[] = array_key_last($this->maintenance);
            $this->assets[$assetIndex]['events'][] = $completedOn->toDateString();
        }

        return $jobs;
    }

    /** @param array<int, int> $completed indexes into $this->maintenance */
    private function assignPayments(array $completed): void
    {
        $cfg = $this->config['maintenance'];
        $recent = $this->end->subDays(400)->toDateString();
        $weights = [];

        foreach ($completed as $jobIndex) {
            $weights[$jobIndex] = $this->maintenance[$jobIndex]['actual_completion_date'] >= $recent ? 6 : 1;
        }

        $outstanding = $this->rng->sample($weights, $cfg['completed_unpaid'] + $cfg['completed_payment_processing']);
        // The most recent outstanding invoices are the ones already with Accounts.
        usort($outstanding, fn ($a, $b) => $this->maintenance[$b]['actual_completion_date'] <=> $this->maintenance[$a]['actual_completion_date']);
        $processing = array_flip(array_slice($outstanding, 0, $cfg['completed_payment_processing']));
        $outstanding = array_flip($outstanding);
        $invoiceSeq = [];

        $byCompletion = $completed;
        usort($byCompletion, fn ($a, $b) => $this->maintenance[$a]['actual_completion_date'] <=> $this->maintenance[$b]['actual_completion_date']);

        foreach ($byCompletion as $jobIndex) {
            $job = &$this->maintenance[$jobIndex];
            $completedOn = CarbonImmutable::parse($job['actual_completion_date']);
            $invoiceDate = $completedOn->addDays($this->rng->int(0, 4))->min($this->end);
            $year = $invoiceDate->year;
            $invoiceSeq[$year] = ($invoiceSeq[$year] ?? 0) + 1;

            $job['invoice'] = [
                'ref' => sprintf('SYN-INV-%d-%04d', $year, $invoiceSeq[$year]),
                'date' => $invoiceDate->toDateString(),
                'uploaded_at' => $this->stamp($invoiceDate),
            ];

            if (isset($outstanding[$jobIndex])) {
                $job['payment_status'] = isset($processing[$jobIndex]) ? 'pending' : 'unpaid';
                $job['updated_at'] = $job['invoice']['uploaded_at'];
            } else {
                $roll = $this->rng->float();
                $gap = $roll < 0.25 ? $this->rng->int(1, 7) : ($roll < 0.75 ? $this->rng->int(8, 60) : $this->rng->int(61, 180));
                $paidOn = $this->businessDay($invoiceDate->addDays($gap))->min($this->end);

                if ($paidOn->lessThan($invoiceDate)) {
                    $paidOn = $invoiceDate;
                }

                $job['payment_status'] = 'paid';
                $job['payment_date'] = $this->laterStamp($paidOn, $job['invoice']['uploaded_at']);
                $job['payment_ref'] = sprintf('SYN-PV-%d-%04d', $paidOn->year, $jobIndex + 1);
                $job['updated_at'] = $job['payment_date'];
            }

            unset($job);
        }
    }

    private function issueCost(array $issue, int $year): float
    {
        [$low, $high] = $issue[2];

        return round(($low + ($high - $low) * $this->rng->centred()) * $this->config['price_index'][$year] + $this->rng->int(0, 99) / 100, 2);
    }

    private function estimate(float $cost): float
    {
        return round($cost * $this->rng->between(0.85, 1.15) / 5) * 5;
    }

    private function priority(string $default): string
    {
        if ($this->rng->chance(0.75)) {
            return $default;
        }

        return (string) $this->rng->weighted(['low' => 20, 'normal' => 50, 'high' => 22, 'urgent' => 8]);
    }

    private function providerFor(array $asset): int
    {
        return $this->rng->chance(0.7) ? (int) $asset['maintenance_id'] : (int) $this->rng->pick($this->context['providers']);
    }

    private function faultText(string $issue, array $asset): string
    {
        $where = $this->context['units'][$asset['unit']]['name'];

        return $this->rng->pick([
            "{$issue}.",
            "{$issue}. Reported by the custodian at {$where}.",
            "{$issue}. Unit cannot be used for daily work.",
            "{$issue}. Fault first noticed during routine use.",
        ]);
    }

    /* =========================================================
       TRANSFERS
    ========================================================= */

    private function planTransfers(): void
    {
        $cfg = $this->config['transfers'];
        $openMaintenance = array_flip(array_column(
            array_filter($this->maintenance, fn ($m) => $m['status'] !== 'completed'),
            'asset',
        ));

        $eligible = [];

        foreach ($this->assets as $i => $asset) {
            if (($this->config['types'][$asset['type']]['movable'] ?? false)
                && $asset['status'] === 'active'
                && ! isset($openMaintenance[$i])
                && $asset['purchase_date'] <= $this->end->subDays(240)->toDateString()) {
                $eligible[$i] = 1;
            }
        }

        $order = $this->rng->sample($eligible, count($eligible));
        $timelines = [];
        $need = ['pending' => $cfg['pending'], 'approved' => $cfg['approved'], 'completed' => $cfg['completed'], 'rejected' => $cfg['rejected']];
        $twoCompletedLeft = 10;

        // Each asset gets a small story; at most one open request, always the latest.
        foreach ($order as $assetIndex) {
            if (array_sum($need) === 0) {
                break;
            }

            $story = [];

            if ($need['completed'] > 0) {
                $story[] = 'completed';
                $need['completed']--;

                if ($twoCompletedLeft > 0 && $need['completed'] > 0 && $this->rng->chance(0.3)) {
                    $story[] = 'completed';
                    $need['completed']--;
                    $twoCompletedLeft--;
                }

                if ($this->rng->chance(0.3)) {
                    $open = $need['pending'] > 0 ? 'pending' : ($need['approved'] > 0 ? 'approved' : null);

                    if ($open) {
                        $story[] = $open;
                        $need[$open]--;
                    }
                }
            } elseif ($need['pending'] > 0 || $need['approved'] > 0) {
                $open = $need['approved'] > 0 && $this->rng->chance(0.3) ? 'approved' : ($need['pending'] > 0 ? 'pending' : 'approved');
                $story[] = $open;
                $need[$open]--;
            }

            if ($need['rejected'] > 0 && ($story === [] || $this->rng->chance(0.12))) {
                array_unshift($story, 'rejected');
                $need['rejected']--;
            }

            $timelines[$assetIndex] = $story;
        }

        if (array_sum($need) !== 0) {
            throw new RuntimeException('Not enough eligible assets to place all transfers.');
        }

        $codes = [];

        foreach ($timelines as $assetIndex => $story) {
            $this->planAssetTransfers($assetIndex, $story, $codes);
        }

        usort($this->transfers, fn ($a, $b) => [$a['requested_at'], $a['asset']] <=> [$b['requested_at'], $b['asset']]);

        foreach ($this->transfers as $t => $transfer) {
            $this->transferAudit($t, $transfer);
        }
    }

    private function planAssetTransfers(int $assetIndex, array $story, array &$codes): void
    {
        $asset = &$this->assets[$assetIndex];
        $type = $asset['type'];

        // Closed requests (rejected/completed) share the window before the
        // last 80 days, one segment each; an open request, if any, comes last.
        $hasOpen = in_array(end($story), ['pending', 'approved'], true);
        $closedCount = count($story) - ($hasOpen ? 1 : 0);
        $closedStart = CarbonImmutable::parse($asset['purchase_date'])->addDays(60);
        $closedEnd = $this->end->subDays($hasOpen ? 80 : 35);
        $segment = $closedCount > 0 ? intdiv(max(0, (int) $closedStart->diffInDays($closedEnd, false)), $closedCount) : 0;
        $closedIndex = 0;

        foreach ($story as $kind) {
            if ($kind === 'pending') {
                $requested = $this->randomBusinessDay($this->end->subDays(75), $this->end->subDay());
            } elseif ($kind === 'approved') {
                $requested = $this->randomBusinessDay($this->end->subDays(60), $this->end->subDays(8));
            } else {
                $segmentStart = $closedStart->addDays($closedIndex * $segment);
                $requested = $this->businessDay($segmentStart->addDays($this->rng->int(0, max(0, $segment - 35))));
                $closedIndex++;
            }

            $fromUnit = $asset['unit'];
            $fromUser = $asset['user_key'];
            $toUnit = $this->destinationFor($type, $fromUnit);
            $toUser = $this->custodianFor($toUnit, $type);

            do {
                $code = 'SYN-AT-'.$requested->format('Ymd').'-'.$this->rng->code(6);
            } while (isset($codes[$code]));

            $codes[$code] = true;

            $transfer = [
                'asset' => $assetIndex,
                'transfer_code' => $code,
                'kind' => $kind,
                'status' => $kind,
                'from_unit' => $fromUnit,
                'to_unit' => $toUnit,
                'from_unit_id' => $this->context['units'][$fromUnit]['id'],
                'to_unit_id' => $this->context['units'][$toUnit]['id'],
                'from_user' => $fromUser,
                'to_user' => $toUser,
                'requested_by' => $this->rng->pick(['requester-1', 'requester-2']),
                'approved_by' => null,
                'completed_by' => null,
                'reason' => $this->rng->pick($this->config['transfer_reasons']),
                'remarks' => null,
                'requested_at' => $this->stamp($requested),
                'approved_at' => null,
                'completed_at' => null,
                'rejected_at' => null,
            ];

            if ($kind === 'rejected') {
                $rejected = $this->businessDay($requested->addDays($this->rng->int(1, 10)))->min($this->end);
                $transfer['approved_by'] = $this->rng->pick(['approver-1', 'approver-2']);
                $transfer['rejected_at'] = $this->laterStamp($rejected, $transfer['requested_at']);
                $transfer['remarks'] = $this->rng->pick($this->config['rejection_reasons']);
            } elseif ($kind === 'approved' || $kind === 'completed') {
                $approved = $this->businessDay($requested->addDays($this->rng->int(1, $kind === 'approved' ? 6 : 14)))->min($this->end);
                $transfer['approved_by'] = $this->rng->pick(['approver-1', 'approver-2']);
                $transfer['approved_at'] = $this->laterStamp($approved, $transfer['requested_at']);
                $transfer['remarks'] = $this->rng->pick($this->config['approval_remarks']);

                if ($kind === 'completed') {
                    $completed = $this->businessDay($approved->addDays($this->rng->int(0, 10)))->min($this->end);
                    $transfer['completed_by'] = 'requester-1';
                    $transfer['completed_at'] = $this->laterStamp($completed, $transfer['approved_at']);

                    // Only a completed transfer moves custody.
                    $asset['unit'] = $toUnit;
                    $asset['user_key'] = $toUser;

                    $this->lifecycle[] = [
                        'asset' => $assetIndex,
                        'changed_by' => 'requester-1',
                        'old_status' => 'active',
                        'new_status' => 'active',
                        'reason' => 'Asset transferred from '.$this->context['units'][$fromUnit]['name'].' to '.$this->context['units'][$toUnit]['name'].'.',
                        'metadata' => [
                            'action' => 'asset_transfer_completed',
                            'transfer_code' => $code,
                            'from_unit' => $this->context['units'][$fromUnit]['name'],
                            'to_unit' => $this->context['units'][$toUnit]['name'],
                            'reason' => $transfer['reason'],
                        ],
                        'changed_at' => $transfer['completed_at'],
                    ];
                }
            }

            $asset['events'][] = substr($transfer['completed_at'] ?? $transfer['rejected_at'] ?? $transfer['approved_at'] ?? $transfer['requested_at'], 0, 10);
            $this->transfers[] = $transfer;
        }

        unset($asset);
    }

    private function destinationFor(string $type, string $fromUnit): string
    {
        for ($i = 0; $i < 10; $i++) {
            $unit = $this->unitFor($type, null);

            if ($unit !== $fromUnit) {
                return $unit;
            }
        }

        $others = array_values(array_diff(array_keys($this->context['units']), [$fromUnit]));

        return $others === [] ? $fromUnit : $this->rng->pick($others);
    }

    private function transferAudit(int $transferIndex, array $transfer): void
    {
        $base = ['transfer' => $transferIndex, 'asset' => $transfer['asset'], 'module' => 'Asset Transfer', 'auditable' => 'transfer'];

        $this->audit[] = $base + ['action' => 'created', 'title' => 'Asset transfer requested', 'user' => $transfer['requested_by'], 'at' => $transfer['requested_at']];

        if ($transfer['rejected_at']) {
            $this->audit[] = $base + ['action' => 'rejected', 'title' => 'Asset transfer rejected', 'user' => $transfer['approved_by'], 'at' => $transfer['rejected_at']];
        }

        if ($transfer['approved_at']) {
            $this->audit[] = $base + ['action' => 'approved', 'title' => 'Asset transfer approved', 'user' => $transfer['approved_by'], 'at' => $transfer['approved_at']];
        }

        if ($transfer['completed_at']) {
            $this->audit[] = $base + ['action' => 'completed', 'title' => 'Asset transfer completed and asset movement recorded', 'user' => $transfer['completed_by'], 'at' => $transfer['completed_at']];
            $this->audit[] = ['transfer' => $transferIndex, 'asset' => $transfer['asset'], 'module' => 'Asset', 'auditable' => 'asset', 'action' => 'transferred', 'title' => 'Asset assigned to new office/custodian', 'user' => $transfer['completed_by'], 'at' => $transfer['completed_at']];
        }
    }

    /* =========================================================
       FINAL ASSET STATE
    ========================================================= */

    private function finaliseAssets(): void
    {
        $underMaintenance = [];

        foreach ($this->maintenance as $job) {
            if ($job['status'] === 'in_progress' && $job['scheduled_date']) {
                $underMaintenance[$job['asset']] = true;
            }
        }

        foreach ($this->assets as $i => &$asset) {
            if ($asset['status'] === 'active' && isset($underMaintenance[$i])) {
                $asset['status'] = 'under_maintenance';
            }

            $asset['organization_id'] = $this->context['units'][$asset['unit']]['organization_id'];
            $asset['unit_id'] = $this->context['units'][$asset['unit']]['id'];

            $latest = max([substr($asset['created_at'], 0, 10), ...$asset['events']]);
            $asset['updated_at'] = $latest === substr($asset['created_at'], 0, 10)
                ? $asset['created_at']
                : $latest.' 16:'.sprintf('%02d', $this->rng->int(0, 59)).':00';
            $asset['description'] = $this->description($asset);

            unset($asset['events'], $asset['status_date']);
        }

        unset($asset);
    }

    private function description(array $asset): string
    {
        $item = trim($asset['brand'].' '.($asset['model_number'] ?? ''));
        $unit = $this->context['units'][$asset['initial_unit']]['name'];
        $date = CarbonImmutable::parse($asset['purchase_date'])->format('F Y');
        $label = '['.$this->config['label'].']';

        $lead = $this->rng->pick([
            "{$label} {$asset['type_name']}: {$item}.",
            "{$label} {$item} {$asset['type_name']}.",
            "{$label} {$asset['type_name']} ({$item}).",
        ]);

        $detail = "Configuration as recorded: {$asset['config_text']}.";
        $procured = $this->rng->pick([
            "Procured in {$date} under {$asset['po']} for the {$unit}.",
            "Received in {$date} ({$asset['po']}) and issued to the {$unit}.",
            "Acquired {$date} under purchase order {$asset['po']}; first issued to the {$unit}.",
        ]);

        return "{$lead} {$detail} {$procured} Not a verified physical GES asset.";
    }

    /* =========================================================
       DATES
    ========================================================= */

    private function isWorkingDay(CarbonImmutable $date): bool
    {
        return ! $date->isWeekend()
            && ! in_array($date->format('m-d'), $this->config['assumed_non_working_days'], true);
    }

    /** Nearest working day (forwards, or backwards when $backwards or when forwards would cross the end date / year). */
    private function businessDay(CarbonImmutable $date, bool $backwards = false): CarbonImmutable
    {
        $step = $backwards ? -1 : 1;
        $candidate = $date;

        for ($i = 0; $i < 10 && ! $this->isWorkingDay($candidate); $i++) {
            $candidate = $candidate->addDays($step);
        }

        if (! $backwards && ($candidate->greaterThan($this->end) || $candidate->year !== $date->year)) {
            return $this->businessDay($date, true);
        }

        return $candidate->lessThan($this->start) ? $this->start : $candidate;
    }

    private function randomBusinessDay(CarbonImmutable $from, CarbonImmutable $to): CarbonImmutable
    {
        $to = $to->min($this->end);

        if ($to->lessThan($from)) {
            $from = $to;
        }

        $day = $from->addDays($this->rng->int(0, (int) $from->diffInDays($to)));
        $day = $this->businessDay($day);

        return $day->greaterThan($to) ? $this->businessDay($to, true)->max($from) : $day;
    }

    /** Timestamp on $date that is strictly after $after (same-day events keep their order). */
    private function laterStamp(CarbonImmutable $date, string $after): string
    {
        $stamp = $this->stamp($date);

        if ($stamp > $after) {
            return $stamp;
        }

        return CarbonImmutable::parse($after)->addMinutes($this->rng->int(20, 90))->format('Y-m-d H:i:s');
    }

    private function stamp(CarbonImmutable $date): string
    {
        return $date->setTime($this->rng->int(8, 16), $this->rng->int(0, 59), $this->rng->int(0, 59))->format('Y-m-d H:i:s');
    }
}
