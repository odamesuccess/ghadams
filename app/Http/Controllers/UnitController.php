<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnitController extends Controller
{
    /* ============================
       INDEX
    ============================ */
    public function index(Request $request)
    {
        $units = Unit::with(['organization'])
            ->withCount('users')
            ->when($request->filled('search'), function ($query) use ($request) {

                $search = $request->search;

                $query->where(function ($q) use ($search) {

                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('unit_abbreviation', 'like', "%{$search}%")
                        ->orWhere('locationname', 'like', "%{$search}%")
                        ->orWhere('location_abbreviation', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phonenumber', 'like', "%{$search}%")
                        ->orWhereHas('organization', function ($org) use ($search) {
                            $org->where('organizations_name', 'like', "%{$search}%")
                                ->orWhere('organization_abbreviation', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('updated_at')
            ->paginate(100)
            ->withQueryString();

        return Inertia::render('Units/Index', [
            'units' => $units,
            'filters' => $request->only('search'),
        ]);
    }

    /* ============================
       CREATE
    ============================ */
    public function create()
    {
        return Inertia::render('Units/Create', [
            'organizations' => Organization::orderBy('organizations_name')->get([
                'id',
                'organizations_name',
                'organization_abbreviation',
            ]),
        ]);
    }

    /* ============================
       STORE
    ============================ */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255', 'unique:units,name'],
            'unit_abbreviation' => ['required', 'string', 'max:20', 'unique:units,unit_abbreviation'],
            'locationname' => ['nullable', 'string', 'max:255'],
            'location_abbreviation' => ['nullable', 'string', 'max:20'],
            'officenumber' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', 'unique:units,email'],
            'phonenumber' => ['nullable', 'string', 'max:50', 'unique:units,phonenumber'],
        ]);

        /* ===== Force Uppercase ===== */
        $validated['unit_abbreviation'] = strtoupper($validated['unit_abbreviation']);

        if (! empty($validated['location_abbreviation'])) {
            $validated['location_abbreviation'] = strtoupper($validated['location_abbreviation']);
        }

        /* ===== Auto-set organization abbreviation ===== */
        if (! empty($validated['organization_id'])) {
            $org = Organization::find($validated['organization_id']);
            $validated['organization_abbreviation'] = $org?->organization_abbreviation;
        }

        Unit::create($validated);

        return redirect()
            ->route('units.index')
            ->with('success', 'Unit created successfully.');
    }

    /* ============================
       SHOW
    ============================ */
    public function show(Unit $unit)
    {
        $unit->load(['organization', 'users']);

        return Inertia::render('Units/Show', [
            'unit' => $unit,
        ]);
    }

    /* ============================
       EDIT
    ============================ */
    public function edit(Unit $unit)
    {
        return Inertia::render('Units/Edit', [
            'unit' => $unit,
            'organizations' => Organization::orderBy('organizations_name')->get([
                'id',
                'organizations_name',
                'organization_abbreviation',
            ]),
        ]);
    }

    /* ============================
       UPDATE
    ============================ */
    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255', 'unique:units,name,'.$unit->id],
            'unit_abbreviation' => ['required', 'string', 'max:20', 'unique:units,unit_abbreviation,'.$unit->id],
            'locationname' => ['nullable', 'string', 'max:255'],
            'location_abbreviation' => ['nullable', 'string', 'max:20'],
            'officenumber' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255', 'unique:units,email,'.$unit->id],
            'phonenumber' => ['nullable', 'string', 'max:50', 'unique:units,phonenumber,'.$unit->id],
        ]);

        /* ===== Force Uppercase ===== */
        $validated['unit_abbreviation'] = strtoupper($validated['unit_abbreviation']);

        if (! empty($validated['location_abbreviation'])) {
            $validated['location_abbreviation'] = strtoupper($validated['location_abbreviation']);
        }

        /* ===== Sync organization abbreviation ===== */
        if (! empty($validated['organization_id'])) {
            $org = Organization::find($validated['organization_id']);
            $validated['organization_abbreviation'] = $org?->organization_abbreviation;
        } else {
            $validated['organization_abbreviation'] = null;
        }

        $unit->update($validated);

        return redirect()
            ->route('units.index')
            ->with('success', 'Unit updated successfully.');
    }

    /* ============================
       DESTROY
    ============================ */
    public function destroy(Unit $unit)
    {
        if ($unit->users()->exists()) {
            return back()->with(
                'error',
                'This unit is assigned to one or more users and cannot be deleted.'
            );
        }

        $unit->delete();

        return redirect()
            ->route('units.index')
            ->with('success', 'Unit deleted successfully.');
    }
}
