<?php

namespace App\Http\Controllers;

use App\Models\Source;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SourceController extends Controller
{
    /**
     * Display a listing of sources
     */
    public function index(Request $request)
    {
        // Query sources with optional search
        $sources = Source::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString(); // Preserves query parameters in pagination links

        return Inertia::render('Sources/Index', [
            'sources' => $sources,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show the form for creating a new source
     */
    public function create()
    {
        return Inertia::render('Sources/Create');
    }

    /**
     * Store a newly created source
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        Source::create($validated);

        return redirect()->route('sources.index')
            ->with('success', 'Source created successfully.');
    }

    /**
     * Display the specified source
     */
    public function show(Source $source)
    {
        return Inertia::render('Sources/Show', [
            'source' => $source,
        ]);
    }

    /**
     * Show the form for editing a source
     */
    public function edit(Source $source)
    {
        return Inertia::render('Sources/Edit', [
            'source' => $source,
        ]);
    }

    /**
     * Update the specified source
     */
    public function update(Request $request, Source $source)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $source->update($validated);

        return redirect()->route('sources.index')
            ->with('success', 'Source updated successfully.');
    }

    /**
     * Remove the specified source
     */
    public function destroy(Source $source)
    {
        $source->delete();

        return redirect()->route('sources.index')
            ->with('success', 'Source deleted successfully.');
    }
}
