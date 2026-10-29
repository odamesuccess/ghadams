<?php
namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetLifecycleHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ReduceDemoAssetStatuses extends Command
{
    protected $signature = 'assets:reduce-demo-statuses {--apply : Apply the backed-up correction}';
    protected $description = 'Keep 30 disposed and 20 obsolete assets by reclassifying excess demo records';
    private const DEMO_DESCRIPTIONS = [
        'Bulk generated asset for dashboard stress testing',
        'GhanaDAMS realistic demonstration data - synthetic record; not a verified physical GES asset.',
    ];

    public function handle(): int
    {
        try {
            DB::transaction(function (): void {
                $rows = Asset::whereIn('status', ['disposed', 'obsolete'])
                    ->orderBy('purchase_date')->orderBy('id')->lockForUpdate()->get();
                $changes = collect();
                $summary = [];
                foreach (['disposed' => 30, 'obsolete' => 20] as $status => $target) {
                    $group = $rows->where('status', $status);
                    $demo = $group->filter(fn ($asset) => in_array($asset->description, self::DEMO_DESCRIPTIONS, true))->values();
                    $protected = $group->count() - $demo->count();
                    if ($protected > $target) {
                        throw new RuntimeException("{$status}: {$protected} non-demo records exceed target {$target}. No statuses changed.");
                    }
                    if ($group->count() < $target) {
                        throw new RuntimeException("{$status}: only {$group->count()} records exist; this patch only reduces counts. No statuses changed.");
                    }
                    $excess = $demo->slice($target - $protected);
                    $changes = $changes->concat($excess);
                    $summary[] = [$status, $group->count(), $protected, $target, $excess->count()];
                }
                $this->table(['Status', 'Before', 'Protected non-demo', 'After', 'Change to Active'], $summary);
                if (!$this->option('apply')) {
                    $this->info('Preview only. No changes made.');
                    return;
                }
                if ($changes->isEmpty()) {
                    $this->info('Already at 30 disposed and 20 obsolete.');
                    return;
                }
                $backup = dirname(base_path()) . DIRECTORY_SEPARATOR . 'GhanaDAMS_BACKUPS'
                    . DIRECTORY_SEPARATOR . 'status-counts-' . now()->format('Ymd-His') . '-' . Str::random(5);
                File::ensureDirectoryExists($backup);
                $json = json_encode([
                    'created_at' => now()->toIso8601String(),
                    'targets' => ['disposed' => 30, 'obsolete' => 20],
                    'assets_before' => $changes->map(fn ($a) => $a->getAttributes())->values()->all(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $file = $backup . DIRECTORY_SEPARATOR . 'assets-before.json';
                if (File::put($file, $json) === false || File::get($file) !== $json) {
                    throw new RuntimeException('Could not verify backup; no statuses changed.');
                }
                foreach ($changes as $asset) {
                    $old = $asset->status;
                    $asset->updateQuietly(['status' => 'active']);
                    AssetLifecycleHistory::create([
                        'asset_id' => $asset->id,
                        'changed_by' => null,
                        'old_status' => $old,
                        'new_status' => 'active',
                        'reason' => 'Demo dataset correction: keep 30 disposed and 20 obsolete assets.',
                        'metadata' => ['backup' => $file, 'command' => 'assets:reduce-demo-statuses'],
                        'changed_at' => now(),
                    ]);
                }
                foreach (['disposed' => 30, 'obsolete' => 20] as $status => $target) {
                    if (Asset::where('status', $status)->count() !== $target) {
                        throw new RuntimeException('Final count check failed; database changes rolled back.');
                    }
                }
                $this->info($changes->count() . ' excess demo assets changed to Active.');
                $this->line('Backup: ' . $backup);
            });
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
