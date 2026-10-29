<?php

namespace App\Services;

use App\Models\Asset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssetCodeGenerator
{
    /**
     * FINAL ENTERPRISE FORMAT
     *
     * GES\UNIT\LOC\ASSET\ALL_TYPES_TOTAL_QTY_AT_LOCATION\YEAR\TYPECOUNT
     *
     * RULES:
     * - QTY counts ALL asset types at SAME location + unit
     * - TYPECOUNT counts ONLY this asset type in SAME unit
     * - BOTH reset automatically when YEAR changes
     * - Fully concurrency-safe via DB transaction + row locking
     */
    public static function generate(
        string $assetAbbreviation,
        string $locationAbbreviation,
        string $purchaseDate,
        string $unitAbbreviation,
        int $unitId
    ): string {

        $year = Carbon::parse($purchaseDate)->year;

        return DB::transaction(function () use (
            $assetAbbreviation,
            $locationAbbreviation,
            $unitAbbreviation,
            $unitId,
            $year
        ) {

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ ALL TYPES TOTAL QTY AT SAME LOCATION IN SAME YEAR
            |--------------------------------------------------------------------------
            | Counts EVERY asset regardless of type.
            | Scope:
            |   - Same UNIT
            |   - Same LOCATION (via unit relation)
            |   - Same YEAR
            |--------------------------------------------------------------------------
            */
            $allTypesQtyAtLocation = Asset::where('unit_id', $unitId)
                ->whereHas('unit', fn ($q) => $q->where('location_abbreviation', $locationAbbreviation)
                )
                ->whereYear('purchase_date', $year)
                ->lockForUpdate()
                ->count() + 1;

            /*
            |--------------------------------------------------------------------------
            | 2️⃣ TYPECOUNT → PER ASSET TYPE + UNIT + YEAR
            |--------------------------------------------------------------------------
            */
            $typeCount = Asset::where('unit_id', $unitId)
                ->whereHas('assetName', fn ($q) => $q->where('assetname_abbreviation', $assetAbbreviation)
                )
                ->whereYear('purchase_date', $year)
                ->lockForUpdate()
                ->count() + 1;

            /*
            |--------------------------------------------------------------------------
            | 3️⃣ FINAL ENTERPRISE ASSET CODE
            |--------------------------------------------------------------------------
            */
            return sprintf(
                'GES\\%s\\%s\\%s\\%d\\%d\\%02d',
                strtoupper($unitAbbreviation),
                strtoupper($locationAbbreviation),
                strtoupper($assetAbbreviation),
                $allTypesQtyAtLocation, // ALL asset types at location
                $year,
                $typeCount              // per asset type
            );
        });
    }
}
