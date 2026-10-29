<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Permission;

class PermissionController extends Controller
{
    /* ============================
       INDEX
    ============================ */
    public function index()
    {
        return Inertia::render('Permissions/Index', [
            'permissions' => Permission::query()
                ->select('id', 'name')
                ->with([
                    'roles:id,name',
                    'roles.users:id,name',
                ])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /* ============================
       CREATE
    ============================ */
    public function create()
    {
        return Inertia::render('Permissions/Create');
    }

    /* ============================
       STORE
    ============================ */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:permissions,name',
            ],
        ]);

        Permission::create($validated);

        return redirect()
            ->route('permissions.index')
            ->with('success', 'Permission created successfully.');
    }

    /* ============================
       SHOW
    ============================ */
    public function show(Permission $permission)
    {
        $permission->load([
            'roles:id,name',
            'roles.users:id,name',
        ]);

        return Inertia::render('Permissions/Show', [
            'permission' => $permission,
        ]);
    }

    /* ============================
       EDIT
    ============================ */
    public function edit(Permission $permission)
    {
        return Inertia::render('Permissions/Edit', [
            'permission' => $permission->only('id', 'name'),
        ]);
    }

    /* ============================
       UPDATE
    ============================ */
    public function update(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:permissions,name,'.$permission->id,
            ],
        ]);

        $permission->update($validated);

        return redirect()
            ->route('permissions.index')
            ->with('success', 'Permission updated successfully.');
    }

    /* ============================
       DESTROY (HARD BLOCK IF IN USE)
    ============================ */
    public function destroy(Permission $permission)
    {
        // ❌ Block deletion if assigned to ANY role
        if ($permission->roles()->exists()) {
            return back()->with(
                'error',
                'Permission is in use and cannot be deleted.'
            );
        }

        $permission->delete();

        return redirect()
            ->route('permissions.index')
            ->with('success', 'Permission deleted successfully.');
    }
}
