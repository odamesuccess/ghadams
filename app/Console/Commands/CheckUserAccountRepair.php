<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckUserAccountRepair extends Command
{
    protected $signature = 'users:check-account-repair';
    protected $description = 'Read-only checks for age-60 expiry, system exemptions, roles and stored photos';

    public function handle(): int
    {
        $users = User::with('roles')->get();
        $system = $users->where('user_status', 'IsSystem');
        $photos = $users->filter(fn ($u) => filled($u->profile_image));
        $missing = $photos->filter(fn ($u) => !Storage::disk('public')->exists($u->profile_image));
        $wrongExpiry = $users->filter(function ($u) {
            if ($u->user_status === 'IsSystem' || !$u->date_of_birth) return false;
            return $u->account_expires_at === null ||
                !$u->account_expires_at->equalTo($u->sixtiethBirthday());
        });
        $this->table(['Check', 'Count'], [
            ['Registered users', $users->count()],
            ['IsSystem users', $system->count()],
            ['IsSystem users with stored expiry (expected 0)', $system->filter(fn ($u) => $u->account_expires_at !== null)->count()],
            ['IsSystem users considered expired (expected 0)', $system->filter(fn ($u) => $u->isExpired())->count()],
            ['Incorrect age-60 dates (expected 0)', $wrongExpiry->count()],
            ['Users without birth dates', $users->whereNull('date_of_birth')->count()],
            ['Users with multiple roles; select one when editing', $users->filter(fn ($u) => $u->roles->count() > 1)->count()],
            ['Users without a role', $users->filter(fn ($u) => $u->roles->isEmpty())->count()],
            ['Users with a stored profile image', $photos->count()],
            ['Missing stored profile files', $missing->count()],
        ]);
        if ($missing->isNotEmpty()) $this->warn('Missing photo user IDs: '.$missing->pluck('id')->implode(', '));
        $this->line('Read-only check completed. Browser display must also be checked.');
        return self::SUCCESS;
    }
}
