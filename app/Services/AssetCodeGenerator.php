<?php

namespace App\Services;

use App\Models\Asset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssetCodeGenerator
{
    /**
     * OFFICIAL FORMAT
     *
     * GES\UNIT\LOCATION\ASSET_TYPE\LOCATION_SEQUENCE\PURCHASE_YEAR\TYPE_SEQUENCE
     * e.g. GES\ICTD\ICT\LJP\2\2026\01
     *
     * RULES:
     * - LOCATION_SEQUENCE counts ALL asset types in the same unit/location and purchase year
     * - TYPE_SEQUENCE counts ONLY this asset type in the same unit and purchase year (2+ digits)
     * - BOTH reset when the purchase year changes
     * - Collision-safe: the next number is never below a number already used in an existing
     *   code (soft-deleted assets included), and a code that already exists is never returned
     * - Concurrency-safe: generation for a unit is serialised by locking the unit row
     *
     * This class is the single source of the format: registration, the demo seeders
     * and assets:rebuild-codes all build codes through it.
     */
    public const PREFIX = 'GES';

    public static function format(
        string $unitAbbreviation,
        string $locationAbbreviation,
        string $assetAbbreviation,
        int $locationSequence,
        int $year,
        int $typeSequence
    ): string {
        return sprintf(
            '%s\\%s\\%s\\%s\\%d\\%d\\%02d',
            self::PREFIX,
            self::abbr($unitAbbreviation),
            self::abbr($locationAbbreviation),
            self::abbr($assetAbbreviation),
            $locationSequence,
            $year,
            $typeSequence,
        );
    }

    /**
     * @param  int[]  $excludeAssetIds  Assets to leave out of the numbering, e.g. assets that are
     *                                  themselves being recoded and still hold their old code.
     */
    public static function generate(
        string $assetAbbreviation,
        string $locationAbbreviation,
        string $purchaseDate,
        string $unitAbbreviation,
        int $unitId,
        array $excludeAssetIds = []
    ): string {
        $year = Carbon::parse($purchaseDate)->year;
        $unitAbbreviation = self::abbr($unitAbbreviation);
        $locationAbbreviation = self::abbr($locationAbbreviation);
        $assetAbbreviation = self::abbr($assetAbbreviation);

        return DB::transaction(function () use (
            $assetAbbreviation,
            $locationAbbreviation,
            $unitAbbreviation,
            $unitId,
            $year,
            $excludeAssetIds
        ) {
            // Serialise generation per unit: concurrent registrations wait here.
            DB::table('units')->where('id', $unitId)->lockForUpdate()->first();

            $inScope = fn () => Asset::query()
                ->where('unit_id', $unitId)
                ->whereYear('purchase_date', $year)
                ->when($excludeAssetIds !== [], fn ($q) => $q->whereNotIn('id', $excludeAssetIds));

            // 1. Live assets of ALL types at this unit/location in this year.
            $locationCount = $inScope()
                ->whereHas('unit', fn ($q) => $q->where('location_abbreviation', $locationAbbreviation))
                ->count();

            // 2. Live assets of THIS type in this unit and year.
            $typeCount = $inScope()
                ->whereHas('assetName', fn ($q) => $q->where('assetname_abbreviation', $assetAbbreviation))
                ->count();

            // 3. Highest numbers already used in codes with this prefix and year, including
            //    soft-deleted assets and assets that have since moved to another unit.
            [$maxLocation, $maxType] = self::highestUsed(
                $unitAbbreviation, $locationAbbreviation, $assetAbbreviation, $year, $excludeAssetIds
            );

            $locationSequence = max($locationCount, $maxLocation) + 1;
            $typeSequence = max($typeCount, $maxType) + 1;

            // 4. Final guard: never return a code that is already taken.
            do {
                $code = self::format(
                    $unitAbbreviation, $locationAbbreviation, $assetAbbreviation,
                    $locationSequence, $year, $typeSequence
                );
                $taken = Asset::withTrashed()->where('asset_code', $code)->exists();
                $locationSequence++;
            } while ($taken);

            return $code;
        });
    }

    /**
     * @return array{0: int, 1: int} highest location sequence (any type) and type sequence used
     */
    protected static function highestUsed(
        string $unitAbbreviation,
        string $locationAbbreviation,
        string $assetAbbreviation,
        int $year,
        array $excludeAssetIds
    ): array {
        $prefix = self::PREFIX.'\\'.$unitAbbreviation.'\\'.$locationAbbreviation.'\\';
        // substr() rather than LIKE: "\" is LIKE's escape character in MySQL.
        $codes = Asset::withTrashed()
            ->whereRaw('substr(asset_code, 1, ?) = ?', [strlen($prefix), $prefix])
            ->when($excludeAssetIds !== [], fn ($q) => $q->whereNotIn('id', $excludeAssetIds))
            ->pluck('asset_code');

        $maxLocation = 0;
        $maxType = 0;

        foreach ($codes as $code) {
            $parts = self::parse($code);

            if (! $parts || $parts['year'] !== $year
                || strcasecmp($parts['unit'], $unitAbbreviation) !== 0
                || strcasecmp($parts['location'], $locationAbbreviation) !== 0) {
                continue;
            }

            $maxLocation = max($maxLocation, $parts['location_sequence']);

            if (strcasecmp($parts['type'], $assetAbbreviation) === 0) {
                $maxType = max($maxType, $parts['type_sequence']);
            }
        }

        return [$maxLocation, $maxType];
    }

    /**
     * @return array{unit: string, location: string, type: string, location_sequence: int, year: int, type_sequence: int}|null
     */
    public static function parse(?string $code): ?array
    {
        if (! is_string($code) || ! preg_match(
            '/^'.self::PREFIX.'\\\\([^\\\\]+)\\\\([^\\\\]+)\\\\([^\\\\]+)\\\\(\d+)\\\\(\d{4})\\\\(\d{2,})$/i',
            $code,
            $m
        )) {
            return null;
        }

        return [
            'unit' => $m[1],
            'location' => $m[2],
            'type' => $m[3],
            'location_sequence' => (int) $m[4],
            'year' => (int) $m[5],
            'type_sequence' => (int) $m[6],
        ];
    }

    /** Use the stored abbreviation as-is apart from trimming and upper-casing. */
    protected static function abbr(string $value): string
    {
        return strtoupper(trim($value));
    }
}
