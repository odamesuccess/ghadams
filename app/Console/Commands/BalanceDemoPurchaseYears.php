<?php
namespace App\Console\Commands;
use App\Models\Asset;
use App\Services\DemoAssetQrService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BalanceDemoPurchaseYears extends Command
{
    protected $signature = 'assets:balance-demo-purchases {--apply : Apply dates, warranties, codes and QR labels} {--expired=1500 : Exact active expired warranty count}';
    protected $description = 'Distribute explicitly marked demo purchases over fifteen years while preserving genuine dates';

    public function handle(): int
    {
        $backup = null;
        $qrBackups = [];
        $apply = (bool) $this->option('apply');
        try {
            $target = filter_var($this->option('expired'), FILTER_VALIDATE_INT);
            if ($target === false || $target < 0) throw new RuntimeException('Expired count must be a non-negative integer.');
            $today = Carbon::today();
            $start = $today->year - 14;
            $result = DB::transaction(function () use ($target, $today, $start, $apply, &$backup, &$qrBackups) {
                $all = Asset::withTrashed()->orderBy('id')->lockForUpdate()->get();
                $live = $all->filter(fn ($a) => $a->deleted_at === null);
                // Exact seed marker prevents changing unmarked/genuine purchase dates.
                $isDemo = fn ($a) => trim((string) $a->description) === 'Bulk generated asset for dashboard stress testing';
                $demo = $live->filter($isDemo);
                if ($demo->isEmpty()) throw new RuntimeException('No assets carry the exact demo seed marker. Nothing changed.');
                $genuineExpired = $live->reject($isDemo)->filter(fn ($a) => $a->status === 'active' && $a->warranty_end_date && $a->warranty_end_date->lt($today))->count();
                $needed = $target - $genuineExpired;
                if ($needed < 0) throw new RuntimeException('Genuine expired warranties already exceed the requested count. Genuine records will not be altered.');
                $buckets = [];
                for ($year = $start; $year <= $today->year; $year++) $buckets[$year] = ['count' => 0, 'value' => 0.0];
                // Deliberately varied synthetic purchase counts, preserving total assets and costs.
                $weights = [100, 130, 95, 110, 140, 105, 100, 130, 95, 110, 155, 110, 100, 95, 80];
                $quotas = []; $remainders = []; $allocated = 0;
                foreach (array_keys($buckets) as $index => $year) {
                    $exact = $demo->count() * $weights[$index] / array_sum($weights);
                    $quotas[$year] = (int) floor($exact);
                    $remainders[$year] = $exact - $quotas[$year];
                    $allocated += $quotas[$year];
                }
                arsort($remainders);
                foreach (array_keys($remainders) as $year) {
                    if ($allocated >= $demo->count()) break;
                    $quotas[$year]++; $allocated++;
                }
                // Largest costs first; equalise average cost, not total value, within varied quotas.
                $plan = [];
                $ordered = $demo->sort(fn ($a, $b) => ((float) $b->purchase_cost <=> (float) $a->purchase_cost) ?: ($a->id <=> $b->id));
                foreach ($ordered as $a) {
                    $years = array_values(array_filter(array_keys($buckets), fn ($y) => $buckets[$y]['count'] < $quotas[$y]));
                    usort($years, fn ($x, $y) => (($buckets[$x]['count'] / max(1, $quotas[$x])) <=> ($buckets[$y]['count'] / max(1, $quotas[$y]))) ?: (($buckets[$x]['value'] / max(1, $quotas[$x])) <=> ($buckets[$y]['value'] / max(1, $quotas[$y]))) ?: ($x <=> $y));
                    $year = $years[0];
                    $first = Carbon::create($year, 1, 1)->startOfDay();
                    $last = $year === $today->year ? $today : Carbon::create($year, 12, 31)->startOfDay();
                    $days = (int) $first->diffInDays($last) + 1;
                    $purchase = $first->copy()->addDays(((int) $a->id * 37) % $days);
                    $plan[$a->id] = ['asset' => $a, 'purchase' => $purchase];
                    $buckets[$year]['count']++;
                    $buckets[$year]['value'] += (float) $a->purchase_cost;
                }
                $candidates = array_filter($plan, fn ($p) => $p['asset']->status === 'active' && $p['purchase']->copy()->addMonthsNoOverflow(1)->lt($today));
                uasort($candidates, fn ($a, $b) => $a['purchase']->year <=> $b['purchase']->year ?: $a['asset']->id <=> $b['asset']->id);
                if (count($candidates) < $needed) throw new RuntimeException('Not enough active demo purchases can have an expired warranty. No changes applied.');
                $expiredIds = array_fill_keys(array_slice(array_keys($candidates), 0, $needed), true);
                foreach ($plan as $id => &$row) {
                    $a = $row['asset']; $purchase = $row['purchase'];
                    $months = max(1, (int) ($a->warranty_duration ?: 12));
                    if ($a->status === 'active') {
                        if (isset($expiredIds[$id])) {
                            // Preserve a normal demo duration when it is already expired.
                            if (!$purchase->copy()->addMonthsNoOverflow($months)->lt($today)) $months = 1;
                        } else {
                            // Extended demo coverage through at least one year from today.
                            $minimumEnd = $today->copy()->addYearNoOverflow();
                            while ($purchase->copy()->addMonthsNoOverflow($months)->lt($minimumEnd)) {
                                $months++;
                                if ($months > 600) throw new RuntimeException('Demo warranty duration exceeds 600 months.');
                            }
                        }
                    }
                    $row['months'] = $months;
                    $row['expiry'] = $purchase->copy()->addMonthsNoOverflow($months);
                }
                unset($row);
                $this->table(['Purchase year', 'Demo assets', 'Demo purchase value (GH₵)'], array_map(fn ($year) => [$year, $buckets[$year]['count'], number_format($buckets[$year]['value'], 2)], array_keys($buckets)));
                $this->info("Genuine expired active warranties: {$genuineExpired}; demo expired target: {$needed}; final target: {$target}.");
                $this->warn('Historical demo assets with unexpired coverage receive extended warranty durations. Genuine dates and warranties remain unchanged.');
                if (!$apply) return ['demo' => $demo->count(), 'preview' => true];

                $backup = dirname(base_path()).DIRECTORY_SEPARATOR.'GhanaDAMS_BACKUPS'.DIRECTORY_SEPARATOR.'demo-purchases-'.now()->format('Ymd-His').'-'.Str::random(5);
                if (!mkdir($backup, 0777, true) && !is_dir($backup)) throw new RuntimeException('Cannot create external backup directory.');
                $json = json_encode($all->map(fn ($a) => $a->getAttributes())->values()->all(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                if (file_put_contents($backup.'/assets-before.json', $json) === false) throw new RuntimeException('Asset backup failed.');
                $disk = Storage::disk('public');
                // Back up both recorded QR paths and the deterministic service output paths.
                foreach ($live as $a) {
                    foreach (array_unique(array_filter([$a->qr_code, 'assets/qrcodes/asset-'.$a->id.'.png'])) as $path) {
                        if (str_contains($path, '..') || !str_starts_with($path, 'assets/qrcodes/')) throw new RuntimeException('Unexpected QR path on asset '.$a->id.'. Nothing committed.');
                        if (array_key_exists($path, $qrBackups)) continue;
                        $saved = $backup.'/qr-files/'.$path;
                        if ($disk->exists($path)) {
                            if (!is_dir(dirname($saved))) mkdir(dirname($saved), 0777, true);
                            if (!copy($disk->path($path), $saved)) throw new RuntimeException('Cannot back up QR file '.$path);
                            $qrBackups[$path] = $saved;
                        } else $qrBackups[$path] = null;
                    }
                }
                file_put_contents($backup.'/qr-manifest.json', json_encode($qrBackups, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $this->line('External backup: '.$backup);
                foreach ($plan as $id => $row) {
                    DB::table('assets')->where('id', $id)->update([
                        'purchase_date' => $row['purchase']->toDateString(),
                        'warranty_duration' => $row['months'],
                        'warranty_end_date' => $row['expiry']->toDateString(),
                        'updated_at' => now(),
                    ]);
                }
                // Existing project logic preserves GhanaDAMS quantity/type sequence conventions.
                if ($this->call('assets:rebuild-warranty-codes', ['--apply' => true]) !== self::SUCCESS) throw new RuntimeException('Code rebuild failed.');
                $actual = Asset::where('status', 'active')->whereDate('warranty_end_date', '<', $today->toDateString())->count();
                if ($actual !== $target) throw new RuntimeException("Expired count verification failed: {$actual}, expected {$target}.");
                $service = app(DemoAssetQrService::class);
                $done = 0;
                foreach (Asset::orderBy('id')->get() as $a) {
                    $path = $service->generateForAsset($a);
                    if (!$disk->exists($path) || $disk->size($path) === 0) throw new RuntimeException('QR generation failed for asset '.$a->id);
                    if (++$done % 100 === 0) $this->line("QR labels rebuilt: {$done}");
                }
                return ['demo' => $demo->count(), 'preview' => false, 'expired' => $actual, 'qr' => $done];
            });
            if ($result['preview']) $this->info('Preview only. Use --apply to save this plan.');
            else $this->info("Completed: {$result['demo']} demo purchase dates, {$result['expired']} expired active warranties, {$result['qr']} QR labels. Backup: {$backup}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            // The database transaction rolls back dates and codes; restore overwritten QR files.
            $restoreErrors = [];
            foreach ($qrBackups as $path => $saved) {
                try {
                    $disk = Storage::disk('public');
                    if ($saved !== null) {
                        $disk->makeDirectory(dirname($path));
                        if (!copy($saved, $disk->path($path))) throw new RuntimeException('Copy failed');
                    } elseif ($disk->exists($path) && !$disk->delete($path)) throw new RuntimeException('Delete failed');
                } catch (\Throwable $restore) { $restoreErrors[] = $path; }
            }
            $this->error('Operation failed; database changes rolled back: '.$e->getMessage());
            if ($restoreErrors) $this->error('QR files needing manual restore: '.implode(', ', $restoreErrors));
            if ($backup) $this->line('External backup: '.$backup);
            return self::FAILURE;
        }
    }
}
