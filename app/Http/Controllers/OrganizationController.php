<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationController extends Controller
{
    /* ============================
       INDEX
    ============================ */
    public function index(Request $request)
    {
        return Inertia::render('Organizations/Index', [
            'organizations' => Organization::withCount('units')
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->search;

                    $query->where(function ($q) use ($search) {
                        $q->where('organizations_name', 'like', "%{$search}%")
                            ->orWhere('organizations_email', 'like', "%{$search}%")
                            ->orWhere('organizations_phone', 'like', "%{$search}%")
                            ->orWhere('organization_abbreviation', 'like', "%{$search}%");
                    });
                })
                ->orderBy('organizations_name')
                ->paginate(100)
                ->withQueryString(),

            'filters' => $request->only('search'),
        ]);
    }

    /* ============================
       CREATE
    ============================ */
    public function create()
    {
        return Inertia::render('Organizations/Create');
    }

    /* ============================
       STORE
    ============================ */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organizations_name' => ['required', 'string', 'max:255'],
            'organizations_email' => ['nullable', 'email', 'max:255'],
            'organizations_phone' => ['nullable', 'string', 'max:50'],
            'organization_abbreviation' => ['nullable', 'string', 'max:50'],
            'organizations_address' => ['nullable', 'string', 'max:255'],
        ]);

        Organization::create($validated);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    /* ============================
       SHOW
    ============================ */
    public function show(Organization $organization)
    {
        $organization->load('units');

        return Inertia::render('Organizations/Show', [
            'organization' => $organization,
        ]);
    }

    /* ============================
       EDIT
    ============================ */
    public function edit(Organization $organization)
    {
        return Inertia::render('Organizations/Edit', [
            'organization' => $organization,
        ]);
    }

    /* ============================
       UPDATE
    ============================ */
    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'organizations_name' => ['required', 'string', 'max:255'],
            'organizations_email' => ['nullable', 'email', 'max:255'],
            'organizations_phone' => ['nullable', 'string', 'max:50'],
            'organization_abbreviation' => ['nullable', 'string', 'max:50'],
            'organizations_address' => ['nullable', 'string', 'max:255'],
        ]);

        $organization->update($validated);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    /* ============================
       DESTROY (SAFE DELETE)
    ============================ */
    public function destroy(Organization $organization)
    {
        // Backend protection: prevent delete if related units exist
        if ($organization->units()->exists()) {
            return back()->with(
                'error',
                'This organization is assigned to one or more units and cannot be deleted.'
            );
        }

        $organization->delete();

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }
}
