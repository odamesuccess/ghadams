<?php

namespace App\Console\Commands;

use App\Services\AuditLogService;
use App\Services\DemoData\SyntheticDatasetGuard;
use App\Services\DemoData\SyntheticDatasetVerifier;
use App\Services\DemoData\SyntheticDatasetWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SyntheticDemoDataset extends Command
{
    public const CONFIRM = 'SYNTHETIC-2000';

    protected $signature = 'demo:synthetic-dataset
                            {--execute : Write the dataset (without this the command is a dry run)}
                            {--replace : Remove the previously generated synthetic rows first, then regenerate}
                            {--remove : Remove only the rows this dataset created}
                            {--confirm= : Safety token, must be '.self::CONFIRM.' for --execute/--replace/--remove}
                            {--verify : Run the integrity checks and write the reports}
                            {--skip-media : Do not generate QR images}
                            {--force-references : Replace/remove even when other rows reference synthetic rows (they are deleted too)}
                            {--plan-file= : Dry run: also write the full plan as JSON to this path on the private disk}
                            {--sample=8 : Dry run: number of example asset rows to print}';

    protected $description = 'Development-only generator for 2,000 clearly-marked SYNTHETIC demonstration assets with maintenance, payments and transfers.';

    public function handle(SyntheticDatasetGuard $guard): int
    {
        @set_time_limit(0);

        $writer = SyntheticDatasetWriter::make();
        $writing = $this->option('execute') || $this->option('replace') || $this->option('remove');

        $this->line('<options=bold>GhanaDAMS synthetic demo dataset</> ('.$writer->dataset().' v'.$writer->config()['version'].', seed '.$writer->config()['seed'].')');
        $this->warn('All generated records are SYNTHETIC DEMONSTRATION DATA, not real GES records.');

        $safety = $guard->inspect();
        $this->table(['Fact', 'Value'], collect($safety['facts'])->map(fn ($v, $k) => [$k, (string) $v])->values()->all());

        if (! $safety['allowed']) {
            foreach ($safety['reasons'] as $reason) {
                $this->error('BLOCKED: '.$reason);
            }

            if ($writing) {
                return self::FAILURE;
            }

            $this->warn('Continuing as a read-only dry run.');
        } else {
            $this->info('Database verified as a local, isolated development database.');
        }

        if (! $writer->registryExists()) {
            $this->error('Table '.SyntheticDatasetWriter::REGISTRY.' is missing. Run its migration first:');
            $this->line('  php artisan migrate --path=database/migrations/2026_11_05_090000_create_demo_dataset_records_table.php');

            return self::FAILURE;
        }

        if ($writing && $this->option('confirm') !== self::CONFIRM) {
            $this->error('Writing requires --confirm='.self::CONFIRM);

            return self::FAILURE;
        }

        $this->baseline('Current record counts');

        if ($this->option('remove')) {
            return $this->remove($writer);
        }

        $owned = $writer->ownedCounts();
        $plan = $writer->plan();
        $this->summarisePlan($plan, $owned);

        $problems = $writer->collisions($plan);
        if ($owned !== []) {
            $problems = [...$problems, ...$writer->blockingReferences()];
        }

        foreach ($problems as $problem) {
            $this->warn($problem);
        }

        if (! $this->option('execute') && ! $this->option('replace')) {
            if ($path = $this->option('plan-file')) {
                Storage::disk('local')->put($path, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->line('Full plan written to '.Storage::disk('local')->path($path));
            }

            if ($this->option('verify') && $owned !== []) {
                return $this->verify();
            }

            $this->warn('DRY RUN: nothing was written.');
            $this->line('To write:   php artisan demo:synthetic-dataset --execute --confirm='.self::CONFIRM);
            $this->line('To rebuild: php artisan demo:synthetic-dataset --replace --confirm='.self::CONFIRM);

            return self::SUCCESS;
        }

        if ($owned !== [] && ! $this->option('replace')) {
            $this->info('The synthetic dataset is already present ('.($owned['asset'] ?? 0).' assets). Nothing to do; use --replace to rebuild it.');

            return $this->option('verify') ? $this->verify() : self::SUCCESS;
        }

        if ($problems !== [] && ! $this->option('force-references')) {
            $this->error('Stopped: resolve the issues above (or pass --force-references for reference issues).');

            return self::FAILURE;
        }

        try {
            if ($owned !== []) {
                $this->info('Removing previous synthetic rows...');
                $this->table(['Record type', 'Removed'], collect($writer->remove())->map(fn ($c, $t) => [$t, $c])->values()->all());
            }

            $this->info('Writing synthetic dataset...');
            $counts = $writer->write($plan, fn ($stage, $n) => $this->line("  {$stage}: {$n}"));
            $this->table(['Created', 'Rows'], collect($counts)->map(fn ($c, $t) => [$t, $c])->values()->all());

            if (! $this->option('skip-media')) {
                $this->info('Generating QR codes with the existing DemoAssetQrService...');
                $writer->generateQrCodes(fn ($stage, $n) => $n % 500 === 0 || $n === 2000 ? $this->line("  {$stage}: {$n}") : null);
            }

            AuditLogService::record(null, 'Demo Dataset', 'seeded', 'Synthetic demo dataset generated',
                'Generated '.$counts['assets'].' SYNTHETIC demonstration assets ('.$writer->dataset().').', null, null, $counts);
        } catch (Throwable $e) {
            $this->error('Generation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->baseline('Record counts after seeding');

        return $this->verify();
    }

    private function remove(SyntheticDatasetWriter $writer): int
    {
        $problems = $writer->blockingReferences();

        foreach ($problems as $problem) {
            $this->warn($problem);
        }

        if ($problems !== [] && ! $this->option('force-references')) {
            $this->error('Stopped: other records reference the synthetic rows.');

            return self::FAILURE;
        }

        $this->table(['Record type', 'Removed'], collect($writer->remove())->map(fn ($c, $t) => [$t, $c])->values()->all());
        $this->baseline('Record counts after removal');

        return self::SUCCESS;
    }

    private function verify(): int
    {
        $verifier = SyntheticDatasetVerifier::make();
        $result = $verifier->run();

        $this->table(['Check', 'Result', 'Detail'], collect($result['checks'])
            ->map(fn ($c, $name) => [$name, $c['passed'] ? 'PASS' : 'FAIL', $c['detail']])->values()->all());

        $stats = $result['stats'];
        $this->table(['Year', 'Assets (left axis)', 'Purchase value GHS (right axis)'], collect($stats['by_year'])
            ->map(fn ($r, $y) => [$y, $r['count'], number_format($r['value'], 2)])->values()
            ->push(['TOTAL', array_sum(array_column($stats['by_year'], 'count')), number_format($stats['total_value'], 2)])->all());

        foreach ($verifier->writeReports($result) as $path) {
            $this->line('Report: '.Storage::disk('local')->path($path));
        }

        $result['passed'] ? $this->info('All integrity checks passed.') : $this->error('Some integrity checks failed.');

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }

    private function summarisePlan(array $plan, array $owned): void
    {
        $byYear = [];
        foreach ($plan['assets'] as $asset) {
            $y = (int) substr($asset['purchase_date'], 0, 4);
            $byYear[$y]['count'] = ($byYear[$y]['count'] ?? 0) + 1;
            $byYear[$y]['value'] = ($byYear[$y]['value'] ?? 0) + $asset['purchase_cost'];
        }
        ksort($byYear);

        $action = $owned === [] ? 'create' : 'replace';
        $this->newLine();
        $this->line("<options=bold>Planned rows ({$action})</>".($owned ? ' - existing synthetic rows that would be replaced: '.json_encode($owned) : ''));
        $this->table(['Table', 'Rows'], [
            ['users (synthetic custodians + 4 staff, Inactive)', count($plan['users'])],
            ['assets', count($plan['assets'])],
            ['procurement batches (registry batch_ref)', count($plan['batches'])],
            ['maintenance_requests', count($plan['maintenance'])],
            ['  of which completed (invoice PDFs)', count(array_filter($plan['maintenance'], fn ($m) => $m['status'] === 'completed'))],
            ['asset_transfers', count($plan['transfers'])],
            ['asset_lifecycle_histories', count($plan['lifecycle'])],
            ['audit_logs', count($plan['audit'])],
        ]);
        $this->table(['Year', 'Assets', 'Value GHS'], collect($byYear)->map(fn ($r, $y) => [$y, $r['count'], number_format($r['value'], 2)])->values()
            ->push(['TOTAL', count($plan['assets']), number_format(array_sum(array_column($byYear, 'value')), 2)])->all());

        $count = fn ($rows, $key) => collect($rows)->countBy($key)->sortKeys()->all();
        $this->line('Asset status: '.json_encode($count($plan['assets'], 'status')));
        $this->line('Maintenance: '.json_encode(collect($plan['maintenance'])->countBy(fn ($m) => $m['status'].'/'.$m['payment_status'])->all()));
        $this->line('Transfers: '.json_encode($count($plan['transfers'], 'status')));

        $sample = (int) $this->option('sample');
        if ($sample > 0) {
            $this->table(['Asset code', 'Type', 'Brand / model', 'Purchased', 'GHS', 'Unit', 'Status'],
                collect($plan['assets'])->filter(fn ($a, $i) => $i % (int) max(1, floor(2000 / $sample)) === 0)->take($sample)
                    ->map(fn ($a) => [$a['asset_code'], $a['type_name'], trim($a['brand'].' '.$a['model_number']), $a['purchase_date'], number_format($a['purchase_cost'], 2), $a['unit'], $a['status']])
                    ->values()->all());
        }
    }

    private function baseline(string $title): void
    {
        $tables = ['users', 'assets', 'asset_transfers', 'maintenance_requests', 'asset_scan_logs', 'audit_logs', 'asset_lifecycle_histories', 'roles', 'permissions', 'model_has_roles'];
        $rows = [];

        foreach ($tables as $table) {
            try {
                $rows[] = [$table, DB::table($table)->count()];
            } catch (Throwable) {
                $rows[] = [$table, 'n/a'];
            }
        }

        $this->line("<options=bold>{$title}</>");
        $this->table(['Table', 'Rows'], $rows);
    }
}
