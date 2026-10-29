<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleLandingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    /* ============================
       INDEX
    ============================ */
    public function index()
    {
        return Inertia::render('Roles/Index', [
            'roles' => Role::query()
                ->select('id', 'name', 'is_special', 'landing_route')
                ->withCount('permissions')
                ->with('users:id,name') // ✅ required for "Used by Users"
                ->orderBy('name')
                ->get(),
        ]);
    }

    /* ============================
       CREATE
    ============================ */
    public function create()
    {
        return Inertia::render('Roles/Create', [
            'permissions' => Permission::orderBy('name')->pluck('name'),
            'landingOptions' => app(RoleLandingService::class)->landingOptions(),
        ]);
    }

    /* ============================
       STORE
    ============================ */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'is_special' => ['required', 'boolean'],
            'landing_route' => ['nullable', 'string', 'max:255'],
        ]);

        $this->validateSpecialLanding($validated);
        DB::transaction(function () use ($validated): void {
            $role = Role::create([
                'name' => $validated['name'],
                'is_special' => (bool) $validated['is_special'],
                'landing_route' => $validated['is_special'] ? $validated['landing_route'] : null,
            ]);
            $role->syncPermissions($validated['permissions'] ?? []);
        });

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role created successfully.');
    }

    /* ============================
       SHOW
    ============================ */
    public function show(Role $role)
    {
        $role->load([
            'permissions:id,name',
            'users:id,name',
        ]);

        return Inertia::render('Roles/Show', [
            'role' => $role,
        ]);
    }

    /* ============================
       EDIT
    ============================ */
    public function edit(Role $role)
    {
        return Inertia::render('Roles/Edit', [
            'role' => $role->load('permissions:id,name'),
            'permissions' => Permission::orderBy('name')->pluck('name'),
            'landingOptions' => app(RoleLandingService::class)->landingOptions(),
        ]);
    }

    /* ============================
       UPDATE
    ============================ */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:roles,name,'.$role->id,
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
            'is_special' => ['required', 'boolean'],
            'landing_route' => ['nullable', 'string', 'max:255'],
        ]);

        if ($role->name === 'Super Admin' && ($validated['name'] !== 'Super Admin' || $validated['is_special'])) {
            throw ValidationException::withMessages(['name' => 'Super Admin must keep its name and normal dashboard access.']);
        }
        $this->validateSpecialLanding($validated);
        DB::transaction(function () use ($role, $validated): void {
            $role->update([
                'name' => $validated['name'],
                'is_special' => (bool) $validated['is_special'],
                'landing_route' => $validated['is_special'] ? $validated['landing_route'] : null,
            ]);
            $role->syncPermissions($validated['permissions'] ?? []);
        });

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role updated successfully.');
    }

    /* ============================
       DESTROY (HARD BLOCK IF IN USE)
    ============================ */
    public function destroy(Role $role)
    {
        // ❌ Prevent deletion if role is assigned to users
        if ($role->users()->exists()) {
            return back()->with(
                'error',
                'Role is assigned to users and cannot be deleted.'
            );
        }

        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role deleted successfully.');
    }
    private function validateSpecialLanding(array $validated): void
    {
        $special = (bool) $validated['is_special'];
        if ($validated['name'] === RoleLandingService::SPECIAL_ROLE_NAME && !$special) {
            throw ValidationException::withMessages(['is_special' => 'The legacy Special Role must remain marked special. Rename it to make it normal.']);
        }
        if ($validated['name'] === 'Super Admin' && $special) {
            throw ValidationException::withMessages(['is_special' => 'Super Admin keeps normal dashboard access.']);
        }
        if ($special && !empty($validated['landing_route']) && !app(RoleLandingService::class)->landingAllowedForPermissions($validated['landing_route'] ?? null, $validated['permissions'] ?? [])) {
            throw ValidationException::withMessages(['landing_route' => 'Select a landing page whose required View permissions are assigned to this role.']);
        }
    }

}
