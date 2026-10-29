<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Organization;
use Illuminate\Console\Command;

class DiagnoseOrganizationQrCounts extends Command
{
    protected $signature = 'assetqrcodes:diagnose-organization-counts';
    protected $description = 'Compare QR organization counts derived through Units against legacy assets.organization_id counts';

    public function handle(): int
    {
        $unitDerived = Asset::query()
            ->join('units', 'units.id', '=', 'assets.unit_id')
            ->whereNull('units.deleted_at')
            ->whereNotNull('assets.qr_code')
            ->whereNotNull('units.organization_id')
            ->selectRaw('units.organization_id, COUNT(*) AS total')
            ->groupBy('units.organization_id')
            ->pluck('total', 'units.organization_id');

        $legacy = Asset::query()
            ->whereNotNull('qr_code')
            ->whereNotNull('organization_id')
            ->selectRaw('organization_id, COUNT(*) AS total')
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id');

        $organizations = Organization::query()
            ->orderBy('organizations_name')
            ->get(['id', 'organizations_name']);

        $rows = [];

        foreach ($organizations as $organization) {
            $rows[] = [
                $organization->id,
                $organization->organizations_name,
                (int) ($unitDerived[$organization->id] ?? 0),
                (int) ($legacy[$organization->id] ?? 0),
            ];
        }

        $this->table(
            [
                'Organization ID',
                'Organization',
                'QR Count via Unit (CORRECT)',
                'Legacy assets.organization_id',
            ],
            $rows
        );

        $this->newLine();
        $this->info('QR Register total: ' . Asset::query()->whereNotNull('qr_code')->count());
        $this->info('Organization downloads now use the "QR Count via Unit" value.');

        return self::SUCCESS;
    }
}
