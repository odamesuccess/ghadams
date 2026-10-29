<?php

namespace App\Console\Commands;

use App\Mail\AssetExpiryAlertMail;
use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TestAssetExpiryEmails extends Command
{
    protected $signature = 'assets:test-expiry-emails
                            {--to= : REQUIRED test recipient email address}
                            {--single-only : Send only the three single-asset emails}
                            {--grouped-only : Send only the three consolidated emails}';

    protected $description =
        'Send test copies of every GhanaDAMS asset-expiry email format to one explicit recipient.';

    public function handle(): int
    {
        $to = trim((string) $this->option('to'));

        if ($to === '') {
            $this->error(
                'A test recipient is required. Example: php artisan assets:test-expiry-emails --to=you@example.com'
            );

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['email' => $to],
            ['email' => ['required', 'email']]
        );

        if ($validator->fails()) {
            $this->error('The --to value is not a valid email address.');

            return self::FAILURE;
        }

        if (
            $this->option('single-only')
            && $this->option('grouped-only')
        ) {
            $this->error(
                'Use either --single-only or --grouped-only, not both.'
            );

            return self::FAILURE;
        }

        $assets = Asset::query()
            ->with([
                'assetName:id,assetname',
                'unit:id,name,locationname',
            ])
            ->whereNotNull('asset_code')
            ->whereNotNull('warranty_end_date')
            ->where(function ($query) {
                $query
                    ->whereNotNull('scan_token')
                    ->orWhereNotNull('uuid');
            })
            ->orderByDesc('warranty_end_date')
            ->limit(3)
            ->get();

        if ($assets->isEmpty()) {
            $this->error(
                'No suitable asset was found. At least one asset needs asset_code, warranty_end_date, and scan_token/uuid.'
            );

            return self::FAILURE;
        }

        $singleAsset = collect([$assets->first()]);

        $groupedAssets = $assets;

        if ($groupedAssets->count() < 2) {
            $groupedAssets = new Collection([
                $assets->first(),
                $assets->first(),
            ]);
        }

        $tests = [
            [
                'label' => '3-Month — Single Asset',
                'type' => 'three_months',
                'remaining' => '3 Months',
                'status' => 'Approaching Expiry',
                'assets' => $singleAsset,
                'grouped' => false,
            ],
            [
                'label' => '1-Month — Single Asset',
                'type' => 'one_month',
                'remaining' => '1 Month',
                'status' => 'Approaching Expiry',
                'assets' => $singleAsset,
                'grouped' => false,
            ],
            [
                'label' => 'Expiry Day — Single Asset',
                'type' => 'expiry_day',
                'remaining' => 'Today',
                'status' => 'Expires Today',
                'assets' => $singleAsset,
                'grouped' => false,
            ],
            [
                'label' => '3-Month — Consolidated',
                'type' => 'three_months',
                'remaining' => '3 Months',
                'status' => 'Approaching Expiry',
                'assets' => $groupedAssets,
                'grouped' => true,
            ],
            [
                'label' => '1-Month — Consolidated',
                'type' => 'one_month',
                'remaining' => '1 Month',
                'status' => 'Approaching Expiry',
                'assets' => $groupedAssets,
                'grouped' => true,
            ],
            [
                'label' => 'Expiry Day — Consolidated',
                'type' => 'expiry_day',
                'remaining' => 'Today',
                'status' => 'Expires Today',
                'assets' => $groupedAssets,
                'grouped' => true,
            ],
        ];

        if ($this->option('single-only')) {
            $tests = array_values(
                array_filter(
                    $tests,
                    fn (array $test) => ! $test['grouped']
                )
            );
        }

        if ($this->option('grouped-only')) {
            $tests = array_values(
                array_filter(
                    $tests,
                    fn (array $test) => $test['grouped']
                )
            );
        }

        $this->info('GhanaDAMS Asset Expiry Email Test');
        $this->line("Recipient: {$to}");
        $this->line(
            sprintf(
                'Suitable asset records found: %d',
                $assets->count()
            )
        );
        $this->newLine();

        $sent = 0;
        $failed = 0;

        foreach ($tests as $test) {
            try {
                Mail::to($to)->send(
                    new AssetExpiryAlertMail(
                        assets: $test['assets'],
                        alertType: $test['type'],
                        remainingPeriod: $test['remaining'],
                        statusLabel: $test['status'],
                    )
                );

                $sent++;

                $this->info(
                    sprintf(
                        '[SENT] %s (%d asset row%s)',
                        $test['label'],
                        $test['assets']->count(),
                        $test['assets']->count() === 1 ? '' : 's'
                    )
                );
            } catch (Throwable $exception) {
                $failed++;

                $this->error(
                    sprintf(
                        '[FAILED] %s — %s',
                        $test['label'],
                        $exception->getMessage()
                    )
                );
            }
        }

        $this->newLine();

        $this->info(
            sprintf(
                'Test complete: %d sent, %d failed.',
                $sent,
                $failed
            )
        );

        $this->warn(
            'TEST MODE: these emails do not write to asset_expiry_alert_logs and do not affect scheduled-alert duplicate protection.'
        );

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
