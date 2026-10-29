<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetName;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Margin\Margin;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BulkAssetMediaService
{
    private const PHOTO_DIR = 'assets/type-images';
    private const QR_DIR = 'assets/qrcodes';
    private const MANIFEST_PATH = self::PHOTO_DIR . '/SOURCES.json';
    private const CATALOG_VERSION = 'ghadams-real-photo-catalog-v4';

    /**
     * Compatibility method used by the previous button/controller/command.
     *
     * This V4 implementation automatically checks the catalogue version.
     * When the old SVG/wrong-photo catalogue is detected, it refreshes the
     * managed type photos before updating asset records.
     *
     * @return array{
     *   processed:int,
     *   qr_generated:int,
     *   images_assigned:int,
     *   image_download_failures:int,
     *   configured_types:int,
     *   database_types:int,
     *   unresolved_types:int
     * }
     */
    public function backfill(
        bool $forceQr = false,
        int $chunkSize = 100,
        ?callable $onProgress = null,
        bool $forceTypePhotoRefresh = false,
    ): array {
        @set_time_limit(0);

        $this->ensureDirectories();

        $catalog = config('asset_type_real_photos', []);

        if ($catalog === []) {
            throw new RuntimeException(
                'The GhanaDAMS real-photo catalogue is empty.'
            );
        }

        $databaseTypes = AssetName::query()
            ->whereNull('deleted_at')
            ->orderBy('assetname')
            ->pluck('assetname')
            ->all();

        $manifest = $this->readManifest();

        $catalogNeedsRefresh =
            $forceTypePhotoRefresh
            || ($manifest['catalog_version'] ?? null) !== self::CATALOG_VERSION
            || $this->managedFolderContainsSvg();

        if ($catalogNeedsRefresh) {
            $manifest = $this->refreshTypePhotoCatalogue($catalog, $onProgress);
        } else {
            // Repair any individually missing cached files.
            $manifest = $this->ensureConfiguredTypePhotos($catalog, $manifest, $onProgress);
        }

        $stats = [
            'processed' => 0,
            'qr_generated' => 0,
            'images_assigned' => 0,
            'image_download_failures' => count($manifest['unresolved'] ?? []),
            'configured_types' => count($catalog),
            'database_types' => count($databaseTypes),
            'unresolved_types' => count($manifest['unresolved'] ?? []),
        ];

        Asset::query()
            ->with('assetName:id,assetname')
            ->orderBy('id')
            ->chunkById(max(1, $chunkSize), function ($assets) use (
                &$stats,
                $forceQr,
                $manifest,
                $onProgress
            ): void {
                foreach ($assets as $asset) {
                    $this->ensurePublicIdentifiers($asset);

                    if ($forceQr || $this->qrIsMissing($asset)) {
                        $this->generateQrCode($asset);
                        $stats['qr_generated']++;
                    }

                    if ($this->assetNeedsManagedTypePhoto($asset)) {
                        $assetType = trim((string) ($asset->assetName?->assetname ?? ''));
                        $path = $manifest['types'][$assetType]['local_path'] ?? null;

                        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
                            $asset->updateQuietly([
                                'asset_image' => $path,
                            ]);

                            $stats['images_assigned']++;
                        }
                    }

                    $stats['processed']++;

                    if ($onProgress) {
                        $onProgress('asset', $asset->id, $stats);
                    }
                }
            });

        $this->removeSvgFiles();

        return $stats;
    }

    /**
     * One-click "fix all" operation.
     */
    public function fixAll(
        bool $forceQr = false,
        int $chunkSize = 100,
        ?callable $onProgress = null,
    ): array {
        return $this->backfill(
            forceQr: $forceQr,
            chunkSize: $chunkSize,
            onProgress: $onProgress,
            forceTypePhotoRefresh: false,
        );
    }

    /**
     * Give a newly-created/edited asset its cached representative type photo.
     * No network request is performed here.
     *
     * A genuine manually-uploaded asset photograph is always preserved.
     */
    public function assignCachedTypePhoto(Asset $asset): bool
    {
        $asset->loadMissing('assetName:id,assetname');

        if (! $this->assetNeedsManagedTypePhoto($asset)) {
            return false;
        }

        $assetType = trim((string) ($asset->assetName?->assetname ?? ''));

        if ($assetType === '') {
            return false;
        }

        $manifest = $this->readManifest();
        $path = $manifest['types'][$assetType]['local_path'] ?? null;

        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            return false;
        }

        $asset->updateQuietly([
            'asset_image' => $path,
        ]);

        return true;
    }

    /**
     * Shared representative type photographs MUST NOT be deleted when one
     * individual asset is edited or deleted.
     */
    public function isManagedTypePhotoPath(?string $path): bool
    {
        if (blank($path)) {
            return false;
        }

        $relative = $this->normalizePublicPath((string) $path);

        return Str::startsWith($relative, self::PHOTO_DIR . '/');
    }

    private function ensureDirectories(): void
    {
        Storage::disk('public')->makeDirectory(self::PHOTO_DIR);
        Storage::disk('public')->makeDirectory(self::QR_DIR);
    }

    /**
     * @param array<string,array<string,mixed>> $catalog
     */
    private function refreshTypePhotoCatalogue(array $catalog, ?callable $onProgress = null): array
    {
        $disk = Storage::disk('public');

        // Keep existing downloaded photos while refreshing the catalogue.
        // A temporary network failure must not erase previously usable media.

        $manifest = [
            'catalog_version' => self::CATALOG_VERSION,
            'generated_at' => now()->toIso8601String(),
            'storage_path' => storage_path('app/public/' . self::PHOTO_DIR),
            'types' => [],
            'unresolved' => [],
        ];

        foreach ($catalog as $assetType => $rules) {
            try {
                $resolved = $this->resolveCommonsPhoto($assetType, $rules);
                $localPath = $this->downloadResolvedPhoto($assetType, $resolved);

                $manifest['types'][$assetType] = [
                    'local_path' => $localPath,
                    'commons_title' => $resolved['title'],
                    'commons_page' => $resolved['description_url'] ?? null,
                    'source_url' => $resolved['url'],
                    'license' => $resolved['license'] ?? null,
                    'artist' => $resolved['artist'] ?? null,
                    'description' => $resolved['description'] ?? null,
                ];
            } catch (Throwable $e) {
                report($e);

                $manifest['unresolved'][$assetType] = $e->getMessage();
            }

            $this->writeManifest($manifest);

            if ($onProgress) {
                $onProgress('type', $assetType, $manifest);
            }
        }

        return $manifest;
    }

    /**
     * @param array<string,array<string,mixed>> $catalog
     */
    private function ensureConfiguredTypePhotos(
        array $catalog,
        array $manifest,
        ?callable $onProgress = null
    ): array {
        foreach ($catalog as $assetType => $rules) {
            $existing = $manifest['types'][$assetType]['local_path'] ?? null;

            if (
                is_string($existing)
                && $existing !== ''
                && Storage::disk('public')->exists($existing)
                && $this->validLocalPhoto($existing)
            ) {
                continue;
            }

            try {
                $resolved = $this->resolveCommonsPhoto($assetType, $rules);
                $localPath = $this->downloadResolvedPhoto($assetType, $resolved);

                $manifest['types'][$assetType] = [
                    'local_path' => $localPath,
                    'commons_title' => $resolved['title'],
                    'commons_page' => $resolved['description_url'] ?? null,
                    'source_url' => $resolved['url'],
                    'license' => $resolved['license'] ?? null,
                    'artist' => $resolved['artist'] ?? null,
                    'description' => $resolved['description'] ?? null,
                ];

                unset($manifest['unresolved'][$assetType]);
            } catch (Throwable $e) {
                report($e);
                $manifest['unresolved'][$assetType] = $e->getMessage();
            }

            $this->writeManifest($manifest);

            if ($onProgress) {
                $onProgress('type', $assetType, $manifest);
            }
        }

        return $manifest;
    }

    /**
     * Search Wikimedia Commons and deliberately choose a photographic bitmap.
     *
     * @param array<string,mixed> $rules
     * @return array<string,mixed>
     */
    private function resolveCommonsPhoto(string $assetType, array $rules): array
    {
        $query = trim((string) ($rules['query'] ?? $assetType));

        $response = Http::withHeaders([
            'User-Agent' => 'GhanaDAMS/4.0 (real asset-type photo resolver)',
            'Accept' => 'application/json',
        ])
            ->connectTimeout(15)
            ->timeout(60)
            ->retry(3, 1000)
            ->get('https://commons.wikimedia.org/w/api.php', [
                'action' => 'query',
                'format' => 'json',
                'formatversion' => 2,
                'generator' => 'search',
                'gsrnamespace' => 6,
                'gsrsearch' => $query,
                'gsrlimit' => 25,
                'prop' => 'imageinfo',
                'iiprop' => 'url|mime|size|extmetadata',
                'iiurlwidth' => 1200,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Wikimedia Commons search failed for {$assetType} with HTTP {$response->status()}."
            );
        }

        $pages = $response->json('query.pages', []);

        if (! is_array($pages) || $pages === []) {
            throw new RuntimeException("No Wikimedia Commons result found for {$assetType}.");
        }

        $candidates = [];

        foreach ($pages as $page) {
            $title = (string) ($page['title'] ?? '');
            $imageInfo = $page['imageinfo'][0] ?? null;

            if (! is_array($imageInfo)) {
                continue;
            }

            $mime = Str::lower((string) ($imageInfo['mime'] ?? ''));

            // Real photographic bitmap only. SVGs/icons are excluded.
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                continue;
            }

            $width = (int) ($imageInfo['width'] ?? 0);
            $height = (int) ($imageInfo['height'] ?? 0);

            if ($width < 300 || $height < 250) {
                continue;
            }

            $meta = $imageInfo['extmetadata'] ?? [];

            $description = $this->stripMetadata(
                (string) (
                    $meta['ImageDescription']['value']
                    ?? $meta['ObjectName']['value']
                    ?? ''
                )
            );

            $searchable = Str::lower($title . ' ' . $description);

            if ($this->containsAny($searchable, $rules['reject'] ?? [])) {
                continue;
            }

            if (! $this->containsAny($searchable, $rules['required_any'] ?? [])) {
                continue;
            }

            $score = 0;

            foreach (($rules['prefer'] ?? []) as $term) {
                if ($this->containsTerm($searchable, (string) $term)) {
                    $score += 12;
                }
            }

            foreach (($rules['required_any'] ?? []) as $term) {
                if ($this->containsTerm($searchable, (string) $term)) {
                    $score += 8;
                }
            }

            // Prefer a title that directly names the asset.
            $assetWords = array_filter(explode(' ', Str::lower($assetType)));

            foreach ($assetWords as $word) {
                if (mb_strlen($word) >= 4 && str_contains(Str::lower($title), $word)) {
                    $score += 4;
                }
            }

            // Prefer medium/high-resolution photographs, but cap influence.
            $score += min(10, (int) floor(($width * $height) / 1_000_000));

            $url = (string) ($imageInfo['thumburl'] ?? $imageInfo['url'] ?? '');

            if ($url === '') {
                continue;
            }

            $candidates[] = [
                'score' => $score,
                'title' => $title,
                'url' => $url,
                'description_url' => $imageInfo['descriptionurl'] ?? null,
                'mime' => $mime,
                'width' => $width,
                'height' => $height,
                'description' => $description,
                'artist' => $this->stripMetadata((string) ($meta['Artist']['value'] ?? '')),
                'license' => trim(
                    implode(' ', array_filter([
                        $this->stripMetadata((string) ($meta['LicenseShortName']['value'] ?? '')),
                        $this->stripMetadata((string) ($meta['UsageTerms']['value'] ?? '')),
                    ]))
                ),
            ];
        }

        if ($candidates === []) {
            throw new RuntimeException(
                "No acceptable REAL photograph passed the GhanaDAMS filters for {$assetType}."
            );
        }

        usort(
            $candidates,
            fn (array $a, array $b): int => $b['score'] <=> $a['score']
        );

        $best = $candidates[0];

        if (($best['score'] ?? 0) < 8) {
            throw new RuntimeException(
                "Commons results for {$assetType} were too ambiguous; GhanaDAMS refused to attach a wrong photo."
            );
        }

        return $best;
    }

    /**
     * @param array<string,mixed> $resolved
     */
    private function downloadResolvedPhoto(string $assetType, array $resolved): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'GhanaDAMS/4.0 (representative asset-type photo cache)',
            'Accept' => 'image/jpeg,image/png,image/webp,image/*;q=0.8',
        ])
            ->connectTimeout(15)
            ->timeout(90)
            ->retry(3, 1000)
            ->get((string) $resolved['url']);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Photo download failed for {$assetType} with HTTP {$response->status()}."
            );
        }

        $bytes = $response->body();

        if ($bytes === '' || @getimagesizefromstring($bytes) === false) {
            throw new RuntimeException("Downloaded file for {$assetType} is not a valid image.");
        }

        $mime = Str::lower((string) ($resolved['mime'] ?? ''));

        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $slug = Str::slug($assetType) ?: 'asset';
        $path = self::PHOTO_DIR . "/{$slug}.{$extension}";

        // Remove obsolete old extension variants for this type.
        foreach (['jpg', 'jpeg', 'png', 'webp', 'svg'] as $oldExt) {
            $old = self::PHOTO_DIR . "/{$slug}.{$oldExt}";

            if ($old !== $path && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        Storage::disk('public')->put($path, $bytes);

        if (! $this->validLocalPhoto($path)) {
            Storage::disk('public')->delete($path);
            throw new RuntimeException("Stored photo validation failed for {$assetType}.");
        }

        return $path;
    }

    private function validLocalPhoto(string $path): bool
    {
        if (! Storage::disk('public')->exists($path)) {
            return false;
        }

        $absolute = Storage::disk('public')->path($path);

        return is_file($absolute) && @getimagesize($absolute) !== false;
    }

    private function assetNeedsManagedTypePhoto(Asset $asset): bool
    {
        if (blank($asset->asset_image)) {
            return true;
        }

        $path = $this->normalizePublicPath((string) $asset->asset_image);

        if ($this->isManagedTypePhotoPath($path)) {
            return true;
        }

        // Broken manually-recorded path can safely fall back to the type photo.
        return ! Storage::disk('public')->exists($path);
    }

    private function normalizePublicPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if (Str::startsWith($path, '/storage/')) {
            return ltrim(Str::after($path, '/storage/'), '/');
        }

        if (Str::startsWith($path, 'storage/')) {
            return ltrim(Str::after($path, 'storage/'), '/');
        }

        return ltrim($path, '/');
    }

    private function qrIsMissing(Asset $asset): bool
    {
        if (blank($asset->qr_code)) {
            return true;
        }

        $path = $this->normalizePublicPath((string) $asset->qr_code);

        return ! Storage::disk('public')->exists($path);
    }

    private function ensurePublicIdentifiers(Asset $asset): void
    {
        $updates = [];

        if (blank($asset->uuid)) {
            $updates['uuid'] = (string) Str::uuid();
        }

        if (blank($asset->scan_token)) {
            $updates['scan_token'] = (string) Str::uuid();
        }

        if ($updates !== []) {
            $asset->updateQuietly($updates);
            $asset->refresh();
        }
    }

    private function generateQrCode(Asset $asset): void
    {
        Storage::disk('public')->makeDirectory(self::QR_DIR);

        $qrPath = self::QR_DIR . "/asset-{$asset->id}.png";
        $scanUrl = route('assets.scan', $asset->uuid);

        $qrCode = new QrCode(
            data: $scanUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 900,
            margin: 12,
        );

        $label = new Label(
            text: $asset->asset_code,
            margin: new Margin(6, 0, 12, 0),
            font: new OpenSans(40),
        );

        $writer = new PngWriter();
        $logo = $this->getValidQrLogo();

        $result = $logo
            ? $writer->write($qrCode, $logo, $label)
            : $writer->write($qrCode, null, $label);

        Storage::disk('public')->put($qrPath, $result->getString());

        $asset->updateQuietly([
            'qr_code' => $qrPath,
        ]);
    }

    private function getValidQrLogo(): ?Logo
    {
        $logoPaths = [
            public_path('images/ghadams_logo.jpg'),
            public_path('images/ghadams_logo.png'),
            public_path('applogo.jpg'),
            public_path('applogo.png'),
            storage_path('app/public/logo/ghadams_logo.jpg'),
            storage_path('app/public/logo/ghadams_logo.png'),
        ];

        foreach ($logoPaths as $logoPath) {
            if (
                file_exists($logoPath)
                && is_file($logoPath)
                && @getimagesize($logoPath) !== false
            ) {
                return new Logo(
                    path: $logoPath,
                    resizeToWidth: 150,
                );
            }
        }

        return null;
    }

    private function managedFolderContainsSvg(): bool
    {
        foreach (Storage::disk('public')->files(self::PHOTO_DIR) as $file) {
            if (Str::endsWith(Str::lower($file), '.svg')) {
                return true;
            }
        }

        return false;
    }

    private function removeSvgFiles(): void
    {
        foreach (Storage::disk('public')->files(self::PHOTO_DIR) as $file) {
            if (Str::endsWith(Str::lower($file), '.svg')) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    private function readManifest(): array
    {
        if (! Storage::disk('public')->exists(self::MANIFEST_PATH)) {
            return [
                'catalog_version' => null,
                'types' => [],
                'unresolved' => [],
            ];
        }

        $decoded = json_decode(
            Storage::disk('public')->get(self::MANIFEST_PATH),
            true
        );

        return is_array($decoded)
            ? $decoded
            : [
                'catalog_version' => null,
                'types' => [],
                'unresolved' => [],
            ];
    }

    private function writeManifest(array $manifest): void
    {
        Storage::disk('public')->put(
            self::MANIFEST_PATH,
            json_encode(
                $manifest,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            )
        );
    }

    private function containsAny(string $haystack, array $terms): bool
    {
        foreach ($terms as $term) {
            if ($this->containsTerm($haystack, (string) $term)) {
                return true;
            }
        }

        return false;
    }

    private function containsTerm(string $haystack, string $term): bool
    {
        $term = Str::lower(trim($term));

        return $term !== '' && str_contains($haystack, $term);
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
