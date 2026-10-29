<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CorrectLaptopAndBookshelfPhotos extends Command
{
    protected $signature = 'assets:correct-laptop-bookshelf-photos';
    protected $description = 'Correct the laptop and bookshelf representative photos and the reported bookshelf record';

    public function handle(): int
    {
        @set_time_limit(0);

        $map = [
            'Laptop Computer' => ['file' => 'HP EliteBook 840 G8.png'],
            'Bookshelf' => ['file' => 'Empty bookcase.jpg'],
        ];

        if ($map === []) {
            $this->error('The exact photo override catalogue is empty.');
            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $dir = 'assets/type-images';

        $disk->makeDirectory($dir);

        $backupDir = dirname(base_path()) . DIRECTORY_SEPARATOR . 'GhanaDAMS_BACKUPS'
            . DIRECTORY_SEPARATOR . 'laptop-bookshelf-' . now()->format('Ymd-His')
            . '-' . Str::lower(Str::random(5));
        \Illuminate\Support\Facades\File::ensureDirectoryExists($backupDir);
        $rows = Asset::whereHas('assetName', fn ($q) => $q->whereIn('assetname', ['Laptop Computer', 'Bookshelf']))->get();
        $snapshot = json_encode($rows->map(fn ($a) => $a->getAttributes())->all(),
            JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (\Illuminate\Support\Facades\File::put($backupDir . DIRECTORY_SEPARATOR . 'asset-records.json', $snapshot) === false) {
            throw new RuntimeException('Could not back up asset records.');
        }
        foreach ($disk->files($dir) as $oldPath) {
            if (Str::startsWith(basename($oldPath), ['laptop-computer', 'bookshelf']) || basename($oldPath) === 'SOURCES.json') {
                $target = $backupDir . DIRECTORY_SEPARATOR . basename($oldPath);
                if (!\Illuminate\Support\Facades\File::copy($disk->path($oldPath), $target)) {
                    throw new RuntimeException('Could not back up old laptop photo or manifest.');
                }
            }
        }
        foreach ($rows as $row) {
            $imagePath = str_replace('\\', '/', trim((string) $row->asset_image));
            $imagePath = preg_replace('#^/?storage/#', '', $imagePath);
            $imagePath = ltrim($imagePath, '/');
            if ($imagePath !== '' && ! str_contains($imagePath, '..') && $disk->exists($imagePath)) {
                if (! \Illuminate\Support\Facades\File::copy($disk->path($imagePath), $backupDir . DIRECTORY_SEPARATOR . 'asset-' . $row->id . '-' . basename($imagePath))) {
                    throw new RuntimeException('Could not back up existing asset image.');
                }
            }
        }
        $this->line('Backup: ' . $backupDir);


        $manifestPath = $dir . '/SOURCES.json';

        $manifest = [
            'catalog_version' => 'ghadams-real-photo-catalog-v4',
            'generated_at' => now()->toIso8601String(),
            'storage_path' => storage_path('app/public/' . $dir),
            'types' => [],
            'unresolved' => [],
        ];

        if ($disk->exists($manifestPath)) {
            $decoded = json_decode($disk->get($manifestPath), true);

            if (is_array($decoded)) {
                $manifest = array_replace_recursive($manifest, $decoded);
            }
        }

        $resolved = 0;
        $failed = 0;
        $assetsUpdated = 0;

        $this->info('============================================================');
        $this->info(' GhanaDAMS - CORRECT LAPTOP AND BOOKSHELF PHOTOS');
        $this->info('============================================================');
        $this->newLine();
        $this->line('Correct photo folder:');
        $this->line(storage_path('app/public/assets/type-images'));
        $this->newLine();

        foreach ($map as $assetType => $definition) {
            $fileName = trim((string) ($definition['file'] ?? ''));

            if ($fileName === '') {
                $this->error("{$assetType}: exact Commons filename is missing.");
                $failed++;
                continue;
            }

            $this->line("Resolving {$assetType} ...");

            try {
                $resolvedPhoto = $this->resolveExactCommonsPhoto($assetType, $fileName);
                $localPath = $this->downloadPhoto($assetType, $resolvedPhoto, $dir);

                $manifest['types'][$assetType] = [
                    'local_path' => $localPath,
                    'commons_title' => $resolvedPhoto['title'],
                    'commons_page' => $resolvedPhoto['description_url'] ?? null,
                    'source_url' => $resolvedPhoto['url'],
                    'license' => $resolvedPhoto['license'] ?? null,
                    'artist' => $resolvedPhoto['artist'] ?? null,
                    'description' => $resolvedPhoto['description'] ?? null,
                    'selection' => 'manual-exact-file-v5',
                ];

                unset($manifest['unresolved'][$assetType]);

                $updatedForType = $this->assignToAssetsOfType($assetType, $localPath);
                $assetsUpdated += $updatedForType;

                $resolved++;

                $this->info("  OK -> {$localPath} | assets updated: {$updatedForType}");
            } catch (Throwable $e) {
                report($e);

                $manifest['unresolved'][$assetType] = $e->getMessage();
                $failed++;

                $this->error('  FAILED -> ' . $e->getMessage());
            }

            $manifest['generated_at'] = now()->toIso8601String();

            $disk->put(
                $manifestPath,
                json_encode(
                    $manifest,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                )
            );
        }

        $remainingUnresolved = array_keys($manifest['unresolved'] ?? []);

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Exact photo types attempted', count($map)],
                ['Exact photo types resolved now', $resolved],
                ['Exact photo types failed now', $failed],
                ['Asset records updated now', $assetsUpdated],
                ['Remaining unresolved in SOURCES.json', count($remainingUnresolved)],
            ]
        );

        if ($failed > 0) {
            $this->warn('Remaining unresolved types:');

            foreach ($remainingUnresolved as $name) {
                $this->line('  - ' . $name);
            }

            return self::FAILURE;
        }

        $this->info('Photo correction completed. Refresh both asset pages.');

        return self::SUCCESS;
    }

    private function resolveExactCommonsPhoto(string $assetType, string $fileName): array
    {
        $title = Str::startsWith($fileName, 'File:')
            ? $fileName
            : 'File:' . $fileName;

        $response = Http::withHeaders([
            'User-Agent' => 'GhanaDAMS/5.0 (exact representative asset photo resolver)',
            'Accept' => 'application/json',
        ])
            ->connectTimeout(15)
            ->timeout(60)
            ->retry(3, 1000)
            ->get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'format' => 'json',
                'formatversion' => 2,
                'titles' => $title,
                'prop' => 'imageinfo',
                'iiprop' => 'url|mime|size|extmetadata',
                'iiurlwidth' => 1200,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Wikimedia Commons API failed for {$assetType} with HTTP {$response->status()}."
            );
        }

        $pages = $response->json('query.pages', []);

        if (! is_array($pages) || $pages === []) {
            throw new RuntimeException("Exact Commons file was not found for {$assetType}: {$fileName}");
        }

        $page = $pages[0];

        if (($page['missing'] ?? false) === true) {
            throw new RuntimeException("Exact Commons file is marked missing for {$assetType}: {$fileName}");
        }

        $imageInfo = $page['imageinfo'][0] ?? null;

        if (! is_array($imageInfo)) {
            throw new RuntimeException("No image information returned for {$assetType}: {$fileName}");
        }

        $mime = Str::lower((string) ($imageInfo['mime'] ?? ''));

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException(
                "The exact source for {$assetType} is not a bitmap photograph ({$mime})."
            );
        }

        $url = (string) ($imageInfo['thumburl'] ?? $imageInfo['url'] ?? '');

        if ($url === '') {
            throw new RuntimeException("No downloadable URL returned for {$assetType}.");
        }

        $meta = $imageInfo['extmetadata'] ?? [];

        return [
            'title' => (string) ($page['title'] ?? $title),
            'url' => $url,
            'description_url' => $imageInfo['descriptionurl'] ?? null,
            'mime' => $mime,
            'description' => $this->stripMetadata(
                (string) (
                    $meta['ImageDescription']['value']
                    ?? $meta['ObjectName']['value']
                    ?? ''
                )
            ),
            'artist' => $this->stripMetadata(
                (string) ($meta['Artist']['value'] ?? '')
            ),
            'license' => trim(
                implode(' ', array_filter([
                    $this->stripMetadata(
                        (string) ($meta['LicenseShortName']['value'] ?? '')
                    ),
                    $this->stripMetadata(
                        (string) ($meta['UsageTerms']['value'] ?? '')
                    ),
                ]))
            ),
        ];
    }

    private function downloadPhoto(string $assetType, array $photo, string $dir): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'GhanaDAMS/5.0 (exact representative asset photo cache)',
            'Accept' => 'image/jpeg,image/png,image/webp,image/*;q=0.8',
        ])
            ->connectTimeout(15)
            ->timeout(90)
            ->retry(3, 1000)
            ->get((string) $photo['url']);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Photo download failed for {$assetType} with HTTP {$response->status()}."
            );
        }

        $bytes = $response->body();

        if ($bytes === '' || @getimagesizefromstring($bytes) === false) {
            throw new RuntimeException("Downloaded file for {$assetType} is not a valid image.");
        }

        $extension = match (Str::lower((string) ($photo['mime'] ?? ''))) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $slug = Str::slug($assetType) ?: 'asset';
        $suffix = $assetType === 'Laptop Computer' ? 'modern-g8' : 'clean-v3';
        $path = "{$dir}/{$slug}-{$suffix}.{$extension}";

        if (! Storage::disk('public')->put($path, $bytes)) {
            throw new RuntimeException('Could not store downloaded photo.');
        }

        $absolute = Storage::disk('public')->path($path);

        if (! is_file($absolute) || @getimagesize($absolute) === false) {
            Storage::disk('public')->delete($path);
            throw new RuntimeException("Stored image validation failed for {$assetType}.");
        }

        return $path;
    }

    private function assignToAssetsOfType(string $assetType, string $localPath): int
    {
        $count = 0;

        Asset::query()
            ->whereHas('assetName', function ($query) use ($assetType): void {
                $query->where('assetname', $assetType);
            })
            ->orderBy('id')
            ->chunkById(100, function ($assets) use ($localPath, &$count): void {
                foreach ($assets as $asset) {
                    if (! $this->shouldUseManagedTypePhoto($asset)) {
                        continue;
                    }

                    $asset->updateQuietly([
                        'asset_image' => $localPath,
                    ]);

                    $count++;
                }
            });

        return $count;
    }

    private function shouldUseManagedTypePhoto(Asset $asset): bool
    {
        if (str_replace('\\', '/', (string) $asset->asset_code) === 'GES/TED/TDB/BSH/1/2006/01') {
            return true;
        }

        // Bulk-generated demo assets can hold old mismatched image paths.
        // Replace those with the verified photograph for their current type.
        if ($asset->description === 'Bulk generated asset for dashboard stress testing') {
            return true;
        }

        $currentPath = $asset->asset_image;
        if (blank($currentPath)) {
            return true;
        }

        $path = str_replace('\\', '/', trim((string) $currentPath));

        if (Str::startsWith($path, '/storage/')) {
            $path = ltrim(Str::after($path, '/storage/'), '/');
        } elseif (Str::startsWith($path, 'storage/')) {
            $path = ltrim(Str::after($path, 'storage/'), '/');
        } else {
            $path = ltrim($path, '/');
        }

        if (Str::startsWith($path, 'assets/type-images/')) {
            return true;
        }

        // A broken path may safely fall back to the representative type photo.
        return ! Storage::disk('public')->exists($path);
    }

    private function stripMetadata(string $value): string
    {
        $value = html_entity_decode(
            strip_tags($value),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
