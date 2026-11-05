<?php

namespace Database\Seeders;

use App\Console\Commands\SyntheticDemoDataset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Development-only: creates the 2,000-asset SYNTHETIC demonstration dataset.
 * Not called from DatabaseSeeder. Idempotent: does nothing if the dataset is
 * already present. The command performs the local-database safety checks.
 *
 *   php artisan db:seed --class=SyntheticDemoDatasetSeeder
 *
 * For a dry run, replacement or removal use `php artisan demo:synthetic-dataset`.
 */
class SyntheticDemoDatasetSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('demo:synthetic-dataset', [
            '--execute' => true,
            '--confirm' => SyntheticDemoDataset::CONFIRM,
        ], $this->command?->getOutput());
    }
}
