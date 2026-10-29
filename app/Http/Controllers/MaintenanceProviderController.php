<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceProvider;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceProviderController extends Controller
{
    /* ============================
       INDEX
    ============================ */
    public function index(Request $request)
    {
        $maintenanceProviders = MaintenanceProvider::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('providername', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phonenumber', 'like', "%{$search}%");
                });
            })
            ->orderBy('providername')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('MaintenanceProviders/Index', [
            'maintenanceProviders' => $maintenanceProviders,
            'filters' => $request->only('search'),
        ]);
    }

    /* ============================
       CREATE
    ============================ */
    public function create()
    {
        return Inertia::render('MaintenanceProviders/Create');
    }

    /* ============================
       STORE
    ============================ */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'providername' => 'required|string|max:255|unique:maintenance_providers,providername',
            'phonenumber' => 'required|string|max:255|unique:maintenance_providers,phonenumber',
            'email' => 'required|email|max:255|unique:maintenance_providers,email',
        ]);

        MaintenanceProvider::create($validated);

        return redirect()
            ->route('maintenanceproviders.index')
            ->with('success', 'Maintenance provider created successfully.');
    }

    /* ============================
       SHOW
    ============================ */
    public function show(MaintenanceProvider $maintenanceprovider)
    {
        return Inertia::render('MaintenanceProviders/Show', [
            'maintenanceProvider' => $maintenanceprovider,
        ]);
    }

    /* ============================
       EDIT
    ============================ */
    public function edit(MaintenanceProvider $maintenanceprovider)
    {
        return Inertia::render('MaintenanceProviders/Edit', [
            'maintenanceProvider' => $maintenanceprovider,
        ]);
    }

    /* ============================
       UPDATE
    ============================ */
    public function update(Request $request, MaintenanceProvider $maintenanceprovider)
    {
        $validated = $request->validate([
            'providername' => 'required|string|max:255|unique:maintenance_providers,providername,'.$maintenanceprovider->id,
            'phonenumber' => 'required|string|max:255|unique:maintenance_providers,phonenumber,'.$maintenanceprovider->id,
            'email' => 'required|email|max:255|unique:maintenance_providers,email,'.$maintenanceprovider->id,
        ]);

        $maintenanceprovider->update($validated);

        return redirect()
            ->route('maintenanceproviders.index')
            ->with('success', 'Maintenance provider updated successfully.');
    }

    /* ============================
       DESTROY
    ============================ */
    public function destroy(MaintenanceProvider $maintenanceprovider)
    {
        $maintenanceprovider->delete();

        return redirect()
            ->route('maintenanceproviders.index')
            ->with('success', 'Maintenance provider deleted successfully.');
    }
}
