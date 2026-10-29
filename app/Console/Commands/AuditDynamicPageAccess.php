<?php

namespace App\Console\Commands;

use App\Services\RoleLandingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

class AuditDynamicPageAccess extends Command
{
    protected $signature = 'access:audit-pages {--role= : Exact role name}';
    protected $description = 'Review page routes, assigned permissions and special-role landing choices without changing data';

    public function handle(RoleLandingService $landing): int
    {
        $catalog = config('page_access', []);
        $missing = array_filter(array_keys($catalog), fn ($name) => !Route::has($name));
        if ($missing) { $this->error('Missing catalog routes: '.implode(', ', $missing)); return self::FAILURE; }
        $query = Role::query()->with('permissions');
        if ($this->option('role')) $query->where('name', $this->option('role'));
        $roles = $query->get();
        if ($roles->isEmpty()) { $this->error('Role not found.'); return self::FAILURE; }
        foreach ($roles as $role) {
            $pages = $landing->landingOptions($role);
            $this->info($role->name.': '.$role->permissions->count().' permissions; '.count($pages).' permitted pages');
            $this->table(['Page', 'Route', 'URL'], array_map(fn ($page) => [$page['label'], $page['route'], $page['url']], $pages));
        }
        $this->info('Read-only audit. No role assignments changed.');
        return self::SUCCESS;
    }
}
