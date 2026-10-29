<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Services\RoleLandingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ReviewSpecialRoles extends Command
{
    protected $signature = 'roles:special-review {--backup : Save original role metadata outside the project}';
    protected $description = 'Review special role flags, selected landings and permission-backed destinations';

    public function handle(RoleLandingService $landing): int
    {
        $roles = Role::with(['permissions', 'users'])->orderBy('name')->get();
        if ($this->option('backup')) {
            $directory = dirname(base_path()).'/GhanaDAMS_BACKUPS/special-role-data-'.now()->format('Ymd-His').'-'.Str::random(5);
            File::ensureDirectoryExists($directory);
            $json = json_encode($roles->map(fn ($role) => [
                'id' => $role->id, 'name' => $role->name,
                'is_special' => $role->getRawOriginal('is_special'),
                'landing_route' => $role->getRawOriginal('landing_route'),
                'permissions' => $role->permissions->pluck('name')->all(),
            ])->all(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (File::put($directory.'/role-metadata-before.json', $json) === false) {
                $this->error('Role backup failed.');
                return self::FAILURE;
            }
            $this->line('Role data backup: '.$directory);
        }
        $this->table(['Role', 'Type', 'Assigned users', 'Chosen landing', 'Permitted pages'], $roles->map(fn ($role) => [
            $role->name, $landing->isSpecialDefinition($role) ? 'Special' : 'Normal',
            $role->users->count(), $role->landing_route ?? '-', count($landing->landingOptions($role)),
        ])->all());
        foreach ($roles as $role) {
            if ($landing->isSpecialDefinition($role) && $landing->landingOptions($role) === []) {
                $this->warn($role->name.': no permitted landing page. Assign View permissions and choose a page in Edit Role.');
            }
        }
        return self::SUCCESS;
    }
}
