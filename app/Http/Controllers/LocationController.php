<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $locations = Location::query()
            ->when($request->search, function ($query, $search) {
                $query->where('locationname', 'like', "%{$search}%")
                    ->orWhere('officenumber', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Locations/Index', [
            'locations' => $locations,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Locations/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'locationname' => 'required|string|max:255|unique:locations,locationname',
            'officenumber' => 'required|string|max:255|unique:locations,officenumber',
        ]);

        Location::create($validated);

        return redirect()
            ->route('locations.index')
            ->with('success', 'Location created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Location $location)
    {
        return Inertia::render('Locations/Show', [
            'location' => $location,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Location $location)
    {
        return Inertia::render('Locations/Edit', [
            'location' => $location,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'locationname' => 'required|string|max:255|unique:locations,locationname,'.$location->id,
            'officenumber' => 'required|string|max:255|unique:locations,officenumber,'.$location->id,
        ]);

        $location->update($validated);

        return redirect()
            ->route('locations.index')
            ->with('success', 'Location updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Location $location)
    {
        $location->delete();

        return redirect()
            ->route('locations.index')
            ->with('success', 'Location deleted successfully.');
    }
}
