<?php

namespace App\Console\Commands;

use App\Services\DemoData\GhanaShopDemoAssets;
use App\Services\DemoData\SyntheticDatasetGuard;
use App\Services\DemoData\SyntheticDatasetWriter;
use Illuminate\Console\Command;
use Throwable;

class GhanaShopDemoAssetsCommand extends Command
{
    public const CONFIRM = 'GHANA-SHOP-2026';

    protected $signature = 'demo:ghana-shop-assets
                            {--execute : Create the assets (without this the command is a dry run)}
                            {--remove : Delete only the assets this command created}
                            {--confirm= : Safety token, must be '.self::CONFIRM.' for --execute/--remove}
                            {--skip-media : Do not generate QR labels}';

    protected $description = 'Development-only: add SYNTHETIC demo assets modelled on products Ghanaian shops list in Ghana cedis (config/demo_ghana_shop.php).';

    public function handle(SyntheticDatasetGuard $guard): int
    {
        $set = GhanaShopDemoAssets::make();
        $writing = $this->option('execute') || $this->option('remove');

        $this->warn('These are SYNTHETIC DEMONSTRATION records modelled on shop listings, not real GES purchases.');

        if (! (new SyntheticDatasetWriter((array) config('demo_dataset')))->registryExists()) {
            $this->error('Table '.SyntheticDatasetWriter::REGISTRY.' is missing. Run the demo dataset migration first.');

            return self::FAILURE;
        }

        if ($writing) {
            $safety = $guard->inspect();

            if (! $safety['allowed']) {
                foreach ($safety['reasons'] as $reason) {
                    $this->error('BLOCKED: '.$reason);
                }

                return self::FAILURE;
            }

            if ($this->option('confirm') !== self::CONFIRM) {
                $this->error('Writing requires --confirm='.self::CONFIRM);

                return self::FAILURE;
            }
        }

        try {
            if ($this->option('remove')) {
                $this->info($set->remove().' Ghana shop demo assets removed.');

                return self::SUCCESS;
            }

            $plan = $set->plan();
            $existing = count($set->ownedAssetIds());

            $this->table(['Purchased', 'Type', 'Brand / model', 'GHS', 'Shop', 'Unit'], array_map(fn ($r) => [
                $r['purchase_date'], $r['type'], trim($r['brand'].' '.$r['model'].($r['part'] ? " ({$r['part']})" : '')),
                number_format($r['price_ghs'], 2), $r['seller'], $r['unit'],
            ], $plan));
            $this->line(count($plan).' assets, total GHS '.number_format(array_sum(array_column($plan, 'price_ghs')), 2).'.');

            if (! $this->option('execute')) {
                $this->warn('DRY RUN: nothing was written. Codes are assigned on write by AssetCodeGenerator.');
                $this->line('To add them: php artisan demo:ghana-shop-assets --execute --confirm='.self::CONFIRM);

                return self::SUCCESS;
            }

            if ($existing > 0) {
                $this->info("The Ghana shop demo assets already exist ({$existing}). Nothing to do; use --remove first to rebuild them.");

                return self::SUCCESS;
            }

            $created = $set->write($plan);
            $this->table(['Asset ID', 'Asset code', 'Serial'], collect($created)->map(fn ($c, $id) => [$id, $c[0], $c[1]])->values()->all());
            $this->info(count($created).' Ghana shop demo assets created.');

            if (! $this->option('skip-media')) {
                $this->info($set->generateQrCodes().' QR labels generated.');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Nothing changed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
