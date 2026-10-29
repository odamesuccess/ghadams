<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RemoveSmartBoards extends Command
{
    protected $signature = 'assets:remove-smart-boards
                            {--apply : Hide all Smart Board assets and the Smart Board type}';

    protected $description = 'Safely remove Smart Board assets from the active register';

    public function handle(): int
    {
        try {
            $type = AssetName::where('assetname', 'Smart Board')->first();
            if (!$type) {
                $this->info('Smart Board is not an active asset type. Nothing to remove.');
                return self::SUCCESS;
            }

            $assets = Asset::where('assetname_id', $type->id)->orderBy('id')->get();
            $this->line('Active Smart Board assets: ' . $assets->count());
            $this->line('Asset type ID: ' . $type->id);
            if (!$this->option('apply')) {
                $this->warn('Preview only. No changes made. Run with --apply to remove.');
                return self::SUCCESS;
            }

            $project = base_path();
            $backupRoot = dirname($project) . DIRECTORY_SEPARATOR . 'GhanaDAMS_BACKUPS';
            if (Str::startsWith(strtolower(realpath($backupRoot) ?: $backupRoot), strtolower($project . DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('Backup path must be outside the project.');
            }
            $backupDir = $backupRoot . DIRECTORY_SEPARATOR . 'remove-smart-board-'
                . now()->format('Ymd-His') . '-' . Str::lower(Str::random(5));
            File::ensureDirectoryExists($backupDir);

            $snapshot = [
                'asset_type' => $type->getAttributes(),
                'assets' => $assets->map(fn (Asset $asset) => $asset->getAttributes())->all(),
                'created_at' => now()->toIso8601String(),
            ];
            $snapshotPath = $backupDir . DIRECTORY_SEPARATOR . 'smart-board-records.json';
            $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (File::put($snapshotPath, $json) === false) {
                throw new RuntimeException('Could not write Smart Board record backup.');
            }

            $disk = Storage::disk('public');
            $media = [];
            foreach ($assets as $asset) {
                if ($asset->qr_code) {
                    $media[] = (string) $asset->qr_code;
                }
            }
            $media[] = 'assets/type-images/smart-board.jpg';
            $media[] = 'assets/type-images/smart-board.png';
            $media[] = 'assets/type-images/smart-board.webp';
            $media = array_unique($media);

            $backedUpPaths = [];
            foreach ($media as $rawPath) {
                $relative = ltrim(str_replace('\\', '/', $rawPath), '/');
                $relative = preg_replace('#^storage/#', '', $relative);
                if (!Str::startsWith($relative, ['assets/qrcodes/', 'assets/type-images/smart-board.'])) {
                    throw new RuntimeException("Unexpected media path {$rawPath}; no records were changed.");
                }
                if (!$disk->exists($relative)) {
                    continue;
                }
                $destination = $backupDir . DIRECTORY_SEPARATOR . 'media'
                    . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                File::ensureDirectoryExists(dirname($destination));
                if (!File::copy($disk->path($relative), $destination)
                    || hash_file('sha256', $disk->path($relative)) !== hash_file('sha256', $destination)) {
                    throw new RuntimeException("Could not verify media backup: {$relative}");
                }
                $backedUpPaths[] = $relative;
            }

            $manifestPath = 'assets/type-images/SOURCES.json';
            if ($disk->exists($manifestPath)) {
                $manifestBackup = $backupDir . DIRECTORY_SEPARATOR . 'SOURCES.json';
                if (!File::copy($disk->path($manifestPath), $manifestBackup)) {
                    throw new RuntimeException('Could not back up the photo manifest.');
                }
            }

            DB::transaction(function () use ($type, $assets): void {
                $now = now();
                Asset::whereIn('id', $assets->pluck('id'))
                    ->update(['deleted_at' => $now, 'updated_at' => $now]);
                $type->update(['deleted_at' => $now, 'updated_at' => $now]);
            });

            foreach ($backedUpPaths as $relative) {
                if (!$disk->delete($relative)) {
                    $this->warn("Could not remove active media file: {$relative}");
                }
            }
            if ($disk->exists($manifestPath)) {
                $manifest = json_decode($disk->get($manifestPath), true);
                if (is_array($manifest)) {
                    unset($manifest['types']['Smart Board'], $manifest['unresolved']['Smart Board']);
                    $disk->put($manifestPath, json_encode($manifest,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
            }

            $this->info($assets->count() . ' Smart Board assets removed from active views.');
            $this->info('Smart Board asset type hidden from the catalogue.');
            $this->line('Backup: ' . $backupDir);
            $this->line('Other asset codes were not changed.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
