<?php
namespace App\Http\Controllers;
use App\Models\Role;
use App\Services\RoleLandingService;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
class PermissionGuideController extends Controller
{
    public function index(RoleLandingService $landing): Response
    {
        return Inertia::render('Roles/PermissionGuide', [
            'pages' => $landing->landingOptions(),
            'permissions' => Permission::query()->orderBy('name')->get(['id', 'name', 'guard_name']),
            'roles' => Role::query()->with('permissions:id,name,guard_name')->orderBy('name')->get()
                ->map(fn ($role) => [
                    'id' => $role->id, 'name' => $role->name,
                    'is_special' => $landing->isSpecialDefinition($role),
                    'landing_route' => $role->landing_route,
                    'permissions' => $role->permissions->pluck('name')->values(),
                ]),
        ]);
    }
}
