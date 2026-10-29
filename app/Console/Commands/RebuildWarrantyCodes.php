<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RebuildWarrantyCodes extends Command
{
    protected $signature = 'assets:rebuild-warranty-codes
                            {--apply : Commit the rebuilt codes (default is preview only)}';

    protected $description = 'Rebuild GhanaDAMS asset codes in purchase-date and ID order';

    public function handle(): int
    {
        try {
            $apply = (bool) $this->option('apply');
            $result = DB::transaction(function () use ($apply): array {
                // Lock rows while planning and writing. Run while registration is paused.
                $assets = Asset::withTrashed()->orderBy('purchase_date')
                    ->orderBy('id')->lockForUpdate()->get();
                $live = $assets->filter(fn (Asset $a) => $a->deleted_at === null);
                $archivedCodes = $assets->filter(fn (Asset $a) => $a->deleted_at !== null)
                    ->mapWithKeys(fn (Asset $a) => [strtolower($a->asset_code) => true]);
                $units = DB::table('units')->whereIn('id', $live->pluck('unit_id')->unique())
                    ->get()->keyBy('id');
                $names = DB::table('asset_names')->whereIn('id', $live->pluck('assetname_id')->unique())
                    ->get()->keyBy('id');
                $locationCounts = [];
                $typeCounts = [];
                $codes = [];
                $changes = [];

                foreach ($live as $asset) {
                    $unit = $units->get($asset->unit_id);
                    $name = $names->get($asset->assetname_id);
                    if (!$unit || !$name || !$asset->purchase_date) {
                        throw new RuntimeException("Asset {$asset->id} has a missing unit, type or purchase date.");
                    }

                    $unitAbbr = strtoupper(trim((string) $unit->unit_abbreviation));
                    $locationAbbr = strtoupper(trim((string) $unit->location_abbreviation));
                    $typeAbbr = strtoupper(trim((string) $name->assetname_abbreviation));
                    if ($unitAbbr === '' || $locationAbbr === '' || $typeAbbr === '') {
                        throw new RuntimeException("Asset {$asset->id} has an empty code abbreviation.");
                    }
                    if ($name->deleted_at !== null || $unit->deleted_at !== null) {
                        throw new RuntimeException("Asset {$asset->id} refers to a hidden unit or asset type.");
                    }

                    $year = Carbon::parse($asset->purchase_date)->year;
                    $locationKey = implode('|', [$asset->unit_id, $locationAbbr, $year]);
                    $typeKey = implode('|', [$asset->unit_id, $typeAbbr, $year]);
                    $quantity = $locationCounts[$locationKey] = ($locationCounts[$locationKey] ?? 0) + 1;
                    $typeNumber = $typeCounts[$typeKey] = ($typeCounts[$typeKey] ?? 0) + 1;
                    $code = sprintf('GES\\%s\\%s\\%s\\%d\\%d\\%02d',
                        $unitAbbr, $locationAbbr, $typeAbbr, $quantity, $year, $typeNumber);

                    if (strlen($code) > 255 || isset($codes[strtolower($code)]) ||
                        $archivedCodes->has(strtolower($code))) {
                        throw new RuntimeException("Duplicate or invalid new code for asset {$asset->id}: {$code}");
                    }
                    $codes[strtolower($code)] = true;
                    $changes[] = ['id' => $asset->id, 'old' => $asset->asset_code, 'new' => $code];
                }

                if ($apply) {
                    // Two passes avoid unique-key clashes with codes still held by other rows.
                    foreach ($changes as $row) {
                        $temporary = '__GHANADAMS_RECODE_' . $row['id'] . '__';
                        if (isset($codes[strtolower($temporary)]) ||
                            $assets->contains(fn (Asset $a) => strcasecmp($a->asset_code, $temporary) === 0)) {
                            throw new RuntimeException("Temporary code already exists: {$temporary}");
                        }
                        DB::table('assets')->where('id', $row['id'])
                            ->update(['asset_code' => $temporary]);
                    }
                    foreach ($changes as $row) {
                        DB::table('assets')->where('id', $row['id'])
                            ->update(['asset_code' => $row['new']]);
                    }
                }

                return ['rows' => $changes, 'archived' => $assets->count() - $live->count()];
            }, 3);

            $rows = $result['rows'];
            $this->table(['Asset ID', 'Old code', 'New code'],
                array_map(fn ($row) => array_values($row), array_slice($rows, 0, 10)));
            $this->info(count($rows) . ' active asset codes ' .
                ($apply ? 'rebuilt.' : 'ready to rebuild (preview only).'));
            if ($result['archived']) {
                $this->warn($result['archived'] . ' soft-deleted assets were left unchanged.');
            }
            $this->line('QR staging follows the code rebuild.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('No codes committed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
