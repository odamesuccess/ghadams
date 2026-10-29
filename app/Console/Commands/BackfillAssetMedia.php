<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\BulkAssetMediaService;
use Illuminate\Console\Command;

class BackfillAssetMedia extends Command
{
    protected $signature = 'assets:backfill-media
                            {--force-qr : Regenerate QR codes even when a working QR already exists}
                            {--chunk=100 : Number of assets processed in each database batch}';

    protected $description = 'Backfill existing assets with working QR codes and real representative asset-type photographs';

    public function handle(BulkAssetMediaService $service): int
    {
        @set_time_limit(0);

        $this->info('GhanaDAMS existing-asset media backfill');
        $this->line('Assets to inspect: ' . Asset::query()->count());
        $this->line('Real type photograph folder:');
        $this->line(storage_path('app/public/assets/type-images'));
        $this->line('Existing user-uploaded asset photographs are preserved.');
        $this->newLine();

        $stats = $service->backfill(
            forceQr: (bool) $this->option('force-qr'),
            chunkSize: max(1, (int) $this->option('chunk')),
        );

        $this->table(
            ['Metric', 'Count'],
            [
                ['Assets processed', $stats['processed']],
                ['QR codes generated/repaired', $stats['qr_generated']],
                ['Real type photos assigned/upgraded', $stats['images_assigned']],
                ['Unresolved photo types', $stats['unresolved_types']],
            ]
        );

        return self::SUCCESS;
    }
}
