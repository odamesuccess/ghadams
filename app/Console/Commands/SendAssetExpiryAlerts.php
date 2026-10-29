<?php

namespace App\Console\Commands;

use App\Mail\AssetExpiryAlertMail;
use App\Models\Asset;
use App\Models\AssetExpiryAlertLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAssetExpiryAlerts extends Command
{
    protected $signature = 'assets:send-expiry-alerts
                            {--date= : Optional base date in YYYY-MM-DD format}
                            {--dry-run : Show what would be sent without sending email or writing logs}';

    protected $description =
        'Email active GhanaDAMS users about asset warranty expiry at 3 months, 1 month and on the expiry date.';

    public function handle(): int
    {
        $today = $this->resolveBaseDate();

        if (! $today) {
            return self::FAILURE;
        }

        $windows = [
            'three_months' => [
                'target' => $today->addMonthsNoOverflow(3),
                'remaining' => '3 Months',
                'status' => 'Approaching Expiry',
            ],
            'one_month' => [
                'target' => $today->addMonthNoOverflow(),
                'remaining' => '1 Month',
                'status' => 'Approaching Expiry',
            ],
            'expiry_day' => [
                'target' => $today,
                'remaining' => 'Today',
                'status' => 'Expires Today',
            ],
        ];

        $recipients = $this->eligibleRecipients();

        $this->info(
            sprintf(
                'GhanaDAMS asset expiry check for %s. Eligible recipients: %d.',
                $today->format('d M Y'),
                $recipients->count()
            )
        );

        if ($recipients->isEmpty()) {
            $this->warn('No active users with valid email addresses were found.');

            return self::SUCCESS;
        }

        $totalEmails = 0;
        $totalAssetRecipientAlerts = 0;
        $totalFailures = 0;

        foreach ($windows as $alertType => $window) {
            $assets = $this->assetsExpiringOn($window['target']);

            $this->line(
                sprintf(
                    '%s: %d asset(s) expiring on %s.',
                    $window['remaining'],
                    $assets->count(),
                    $window['target']->format('d M Y')
                )
            );

            if ($assets->isEmpty()) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->table(
                    ['Asset Code', 'Asset Name', 'Unit', 'Location', 'Expiry Date'],
                    $assets->map(fn (Asset $asset) => [
                        $asset->asset_code,
                        $asset->assetName?->assetname ?? 'Unnamed Asset',
                        $asset->unit?->name ?? 'Not assigned',
                        $asset->unit?->locationname ?? 'Not assigned',
                        optional($asset->warranty_end_date)->format('d M Y')
                            ?? $window['target']->format('d M Y'),
                    ])->all()
                );

                continue;
            }

            foreach ($recipients as $user) {
                $pendingAssets = $this->unsentAssetsForRecipient(
                    $assets,
                    $user,
                    $alertType,
                    $window['target']
                );

                if ($pendingAssets->isEmpty()) {
                    continue;
                }

                try {
                    Mail::to($user->email)->send(
                        new AssetExpiryAlertMail(
                            assets: $pendingAssets,
                            alertType: $alertType,
                            remainingPeriod: $window['remaining'],
                            statusLabel: $window['status'],
                        )
                    );

                    DB::transaction(function () use (
                        $pendingAssets,
                        $user,
                        $alertType,
                        $window
                    ) {
                        foreach ($pendingAssets as $asset) {
                            AssetExpiryAlertLog::create([
                                'asset_id' => $asset->id,
                                'user_id' => $user->id,
                                'recipient_email' => $user->email,
                                'alert_type' => $alertType,
                                'expiry_date' => $window['target']->toDateString(),
                                'sent_at' => now(),
                            ]);
                        }
                    });

                    $totalEmails++;
                    $totalAssetRecipientAlerts += $pendingAssets->count();

                    $this->info(
                        sprintf(
                            'Sent %s alert to %s <%s> for %d asset(s).',
                            $window['remaining'],
                            $user->name,
                            $user->email,
                            $pendingAssets->count()
                        )
                    );
                } catch (Throwable $exception) {
                    $totalFailures++;

                    Log::error(
                        'GhanaDAMS asset expiry alert email failed.',
                        [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'alert_type' => $alertType,
                            'expiry_date' => $window['target']->toDateString(),
                            'asset_ids' => $pendingAssets->pluck('id')->all(),
                            'error' => $exception->getMessage(),
                        ]
                    );

                    $this->error(
                        sprintf(
                            'Failed to send to %s <%s>: %s',
                            $user->name,
                            $user->email,
                            $exception->getMessage()
                        )
                    );
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run completed. No email was sent and no alert log was written.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info(
            sprintf(
                'Completed: %d email(s), %d asset-recipient alert(s), %d failure(s).',
                $totalEmails,
                $totalAssetRecipientAlerts,
                $totalFailures
            )
        );

        return $totalFailures > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function resolveBaseDate(): ?CarbonImmutable
    {
        $date = trim((string) $this->option('date'));

        if ($date === '') {
            return CarbonImmutable::now('Africa/Accra')->startOfDay();
        }

        try {
            return CarbonImmutable::createFromFormat(
                'Y-m-d',
                $date,
                'Africa/Accra'
            )->startOfDay();
        } catch (Throwable) {
            $this->error(
                'Invalid --date value. Use YYYY-MM-DD, for example --date=2026-09-30.'
            );

            return null;
        }
    }

    private function eligibleRecipients(): Collection
    {
        return User::query()
            ->whereIn('user_status', ['Active', 'IsSystem'])
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->where(function ($query) {
                $query
                    ->whereNull('account_expires_at')
                    ->orWhere('account_expires_at', '>', now());
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function assetsExpiringOn(CarbonImmutable $date): Collection
    {
        return Asset::query()
            ->with([
                'assetName:id,assetname',
                'unit:id,name,locationname',
            ])
            ->whereNotNull('warranty_end_date')
            ->whereDate('warranty_end_date', $date->toDateString())
            ->where('status', '<>', 'disposed')
            ->orderBy('asset_code')
            ->get();
    }

    private function unsentAssetsForRecipient(
        Collection $assets,
        User $user,
        string $alertType,
        CarbonImmutable $expiryDate
    ): Collection {
        $alreadySentAssetIds = AssetExpiryAlertLog::query()
            ->where('user_id', $user->id)
            ->where('alert_type', $alertType)
            ->whereDate('expiry_date', $expiryDate->toDateString())
            ->whereIn('asset_id', $assets->pluck('id'))
            ->pluck('asset_id');

        return $assets
            ->reject(
                fn (Asset $asset) =>
                    $alreadySentAssetIds->contains($asset->id)
            )
            ->values();
    }
}
