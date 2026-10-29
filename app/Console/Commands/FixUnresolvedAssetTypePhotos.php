<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FixUnresolvedAssetTypePhotos extends Command
{
    protected $signature = 'assets:fix-unresolved-type-photos';
    protected $description = 'Resolve missing representative asset photos using exact Commons file titles';

    public function handle(): int
    {
        @set_time_limit(0);

        $map = config('asset_type_exact_photo_overrides', []);

        if ($map === []) {
            $this->error('The exact photo override catalogue is empty.');
            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $dir = 'assets/type-images';

        $disk->makeDirectory($dir);

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
        $this->info(' GhanaDAMS - FIX UNRESOLVED REAL ASSET PHOTOS');
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

        if ($remainingUnresolved !== []) {
            $this->warn('Remaining unresolved types:');

            foreach ($remainingUnresolved as $name) {
                $this->line('  - ' . $name);
            }

            return self::FAILURE;
        }

        $this->info('All configured GhanaDAMS asset types now have resolved representative-photo entries.');

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
        $path = "{$dir}/{$slug}.{$extension}";

        foreach (['jpg', 'jpeg', 'png', 'webp', 'svg'] as $oldExtension) {
            $oldPath = "{$dir}/{$slug}.{$oldExtension}";

            if ($oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        Storage::disk('public')->put($path, $bytes);

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
