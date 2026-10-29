<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class CleanupSyntheticStressTestAssets extends Command
{
    protected $signature = 'assets:cleanup-synthetic
                            {--execute : Actually soft-delete matching synthetic asset records}
                            {--confirm= : Safety token; must be DELETE-SYNTHETIC when --execute is used}
                            {--restore-latest : Restore the most recent cleanup backup by clearing deleted_at}';

    protected $description = 'Safely identify and soft-delete GhanaDAMS dashboard stress-test assets while preserving manual assets and all Asset Types';

    private const DESCRIPTION = 'Bulk generated asset for dashboard stress testing';
    private const CODE_PREFIX = 'GES\\AUTO\\GEN\\';

    public function handle(): int
    {
        @set_time_limit(0);

        if ((bool) $this->option('restore-latest')) {
            return $this->restoreLatest();
        }

        $query = $this->candidateQuery();
        $count = (clone $query)->count();

        $this->newLine();
        $this->info('============================================================');
        $this->info(' GhanaDAMS - SYNTHETIC/STRESS-TEST ASSET CLEANUP');
        $this->info('============================================================');
        $this->newLine();

        $this->line('MATCH RULES (ALL MUST MATCH):');
        $this->line('  Description : ' . self::DESCRIPTION);
        $this->line('  Asset code  : starts with ' . self::CODE_PREFIX);
        $this->line('  deleted_at  : NULL');
        $this->newLine();

        $this->line('Synthetic records currently matching: ' . $count);
        $this->newLine();

        $this->showTypeSummary();

        $manualCount = DB::table('assets')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('description', '<>', self::DESCRIPTION)
                    ->orWhereNull('description')
                    ->orWhereRaw('LEFT(asset_code, 13) <> ?', [self::CODE_PREFIX]);
            })
            ->count();

        $this->line('Active/manual records that will be preserved: ' . $manualCount);
        $this->line('Asset Types / Asset Names will NOT be deleted.');
        $this->line('QR/type-photo files will NOT be physically deleted.');
        $this->newLine();

        if (! (bool) $this->option('execute')) {
            $this->warn('DRY RUN ONLY. No database records were changed.');
            $this->line('');
            $this->line('Execution command:');
            $this->line('  php artisan assets:cleanup-synthetic --execute --confirm=DELETE-SYNTHETIC');
            return self::SUCCESS;
        }

        if ((string) $this->option('confirm') !== 'DELETE-SYNTHETIC') {
            $this->error('Execution blocked: --confirm=DELETE-SYNTHETIC is required.');
            return self::FAILURE;
        }

        if ($count === 0) {
            $this->info('Nothing to clean. No matching active synthetic records remain.');
            return self::SUCCESS;
        }

        $backup = $this->writeBackup();

        $this->info('Backup written BEFORE cleanup:');
        $this->line('  ' . $backup);
        $this->newLine();

        $now = now();

        $updated = $this->candidateQuery()->update([
            'deleted_at' => $now,
            'updated_at' => $now,
        ]);

        $remaining = $this->candidateQuery()->count();

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Matching records before cleanup', $count],
                ['Soft-deleted now', $updated],
                ['Matching active records remaining', $remaining],
                ['Manual/non-matching records preserved', $manualCount],
            ]
        );

        if ($remaining !== 0) {
            $this->error('Cleanup did not fully complete. Some matching synthetic records remain active.');
            return self::FAILURE;
        }

        $this->info('Synthetic/stress-test cleanup completed successfully.');
        $this->line('These are SOFT DELETES and remain recoverable.');
        $this->line('Restore latest cleanup with:');
        $this->line('  php artisan assets:cleanup-synthetic --restore-latest');

        return self::SUCCESS;
    }

    private function candidateQuery()
    {
        return DB::table('assets')
            ->whereNull('deleted_at')
            ->where('description', self::DESCRIPTION)
            ->whereRaw('LEFT(asset_code, 13) = ?', [self::CODE_PREFIX]);
    }

    private function showTypeSummary(): void
    {
        $rows = DB::table('assets')
            ->join('asset_names', 'asset_names.id', '=', 'assets.assetname_id')
            ->whereNull('assets.deleted_at')
            ->where('assets.description', self::DESCRIPTION)
            ->whereRaw('LEFT(assets.asset_code, 13) = ?', [self::CODE_PREFIX])
            ->select('asset_names.assetname', DB::raw('COUNT(*) AS total'))
            ->groupBy('asset_names.id', 'asset_names.assetname')
            ->orderBy('asset_names.assetname')
            ->get();

        if ($rows->isEmpty()) {
            $this->line('No active synthetic assets are currently present.');
            $this->newLine();
            return;
        }

        $this->table(
            ['Asset Type', 'Synthetic Records To Soft-Delete'],
            $rows->map(fn ($row) => [
                $row->assetname,
                (int) $row->total,
            ])->all()
        );
    }

    private function backupDirectory(): string
    {
        return storage_path('app/cleanup_backups/synthetic_assets');
    }

    private function writeBackup(): string
    {
        $directory = $this->backupDirectory();

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $timestamp = now()->format('Ymd_His');
        $path = $directory . DIRECTORY_SEPARATOR . "synthetic_assets_{$timestamp}.json";

        $rows = $this->candidateQuery()
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $payload = [
            'created_at' => now()->toIso8601String(),
            'reason' => 'GhanaDAMS synthetic/stress-test asset cleanup',
            'match' => [
                'description' => self::DESCRIPTION,
                'asset_code_prefix' => self::CODE_PREFIX,
            ],
            'count' => count($rows),
            'asset_ids' => array_values(array_map(
                fn (array $row) => (int) $row['id'],
                $rows
            )),
            'records' => $rows,
        ];

        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        if ($json === false || File::put($path, $json) === false) {
            throw new RuntimeException('Could not write cleanup backup file.');
        }

        return $path;
    }

    private function restoreLatest(): int
    {
        $directory = $this->backupDirectory();

        if (! File::isDirectory($directory)) {
            $this->error('No cleanup backup directory exists.');
            return self::FAILURE;
        }

        $files = collect(File::files($directory))
            ->filter(fn ($file) => Str::endsWith($file->getFilename(), '.json'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        if ($files->isEmpty()) {
            $this->error('No synthetic-asset cleanup backup file was found.');
            return self::FAILURE;
        }

        $latest = $files->first()->getPathname();
        $payload = json_decode(File::get($latest), true);

        if (! is_array($payload) || ! isset($payload['asset_ids']) || ! is_array($payload['asset_ids'])) {
            $this->error('Latest cleanup backup is invalid.');
            return self::FAILURE;
        }

        $ids = array_values(array_filter(array_map('intval', $payload['asset_ids'])));

        if ($ids === []) {
            $this->warn('Latest cleanup backup contains no asset IDs.');
            return self::SUCCESS;
        }

        $restored = 0;

        foreach (array_chunk($ids, 500) as $chunk) {
            $restored += DB::table('assets')
                ->whereIn('id', $chunk)
                ->where('description', self::DESCRIPTION)
                ->whereRaw('LEFT(asset_code, 13) = ?', [self::CODE_PREFIX])
                ->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
        }

        $this->info('Latest synthetic-asset cleanup restored.');
        $this->line('Backup: ' . $latest);
        $this->line('Records restored/updated: ' . $restored);

        return self::SUCCESS;
    }
}
