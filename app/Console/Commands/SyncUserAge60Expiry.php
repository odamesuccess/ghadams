<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
class SyncUserAge60Expiry extends Command {
    protected $signature = 'users:sync-age60-expiry {--apply : Recalculate existing accounts after backup}';
    protected $description = 'Back up account dates and recalculate expiry on the 60th birthday';
    public function handle(): int
    {
        try {
            DB::transaction(function (): void {
                $users = User::withTrashed()->orderBy('id')->lockForUpdate()->get();
                $backup = dirname(base_path()) . '/GhanaDAMS_BACKUPS/user-age60-' . now()->format('Ymd-His') . '-' . Str::random(5);
                File::ensureDirectoryExists($backup);
                $rows = $users->map(fn ($u) => [
                    'id' => $u->id, 'date_of_birth' => $u->getRawOriginal('date_of_birth'),
                    'account_expires_at' => $u->getRawOriginal('account_expires_at'),
                ])->all();
                $json = json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                if (File::put($backup . '/account-dates-before.json', $json) === false || File::get($backup . '/account-dates-before.json') !== $json) {
                    throw new RuntimeException('Account date backup failed.');
                }
                $this->line('Backup: ' . $backup);
                if (!$this->option('apply')) { $this->info('Backup completed; no account dates changed.'); return; }
                $changed = 0; $missing = []; $exempt = 0;
                foreach ($users as $user) {
                    if ($user->user_status === 'IsSystem') {
                        $user->account_expires_at = null;
                        $user->saveQuietly();
                        $exempt++;
                        continue;
                    }
                    if (!$user->date_of_birth) { $missing[] = $user->id; continue; }
                    if ($user->date_of_birth->isFuture()) { throw new RuntimeException('Future birth date on user ' . $user->id . '. No expiry changes committed.'); }
                    $user->account_expires_at = $user->sixtiethBirthday();
                    $user->saveQuietly();
                    $changed++;
                }
                $this->info($changed . ' account expiry dates set to the 60th birthday.');
                if ($missing) { $this->warn('Birth date missing; existing expiry preserved for user IDs: ' . implode(', ', $missing)); }
                $this->info($exempt . ' IsSystem accounts exempted; stored expiry cleared.');
                $this->line('Other accounts expire at midnight starting the 60th birthday.');
            });
            return self::SUCCESS;
        } catch (\Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}
