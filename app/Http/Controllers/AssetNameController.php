<?php

namespace App\Http\Controllers;

use App\Models\AssetName;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AssetNameController extends Controller
{
    /* =========================
       INDEX
    ========================= */
    public function index(Request $request)
    {
        $assetNames = AssetName::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('assetname', 'like', "%{$search}%")
                        ->orWhere('assetname_abbreviation', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                });
            })
            ->orderBy('assetname')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($asset) => [
                'id' => $asset->id,
                'assetname' => $asset->assetname,
                'assetname_abbreviation' => $asset->assetname_abbreviation,
                'brand' => $asset->brand,
                'usefullife' => $asset->usefullife,
                'updated_at' => optional($asset->updated_at)->toDateTimeString(),
            ]);

        return Inertia::render('AssetNames/Index', [
            'assetNames' => $assetNames,
            'filters' => $request->only('search'),
        ]);
    }

    /* =========================
       CREATE
    ========================= */
    public function create()
    {
        return Inertia::render('AssetNames/Create');
    }

    /* =========================
       STORE
    ========================= */
    public function store(Request $request)
    {
        // Auto-uppercase abbreviation
        $request->merge([
            'assetname_abbreviation' => strtoupper($request->assetname_abbreviation),
        ]);

        $validated = $request->validate([
            'assetname' => 'required|string|max:255',
            'assetname_abbreviation' => [
                'required',
                'string',
                'max:255',
                Rule::unique('asset_names', 'assetname_abbreviation'),
            ],
            'brand' => 'nullable|string|max:255',
            'usefullife' => 'nullable|integer|min:0',
        ]);

        AssetName::create($validated);

        return redirect()
            ->route('assetnames.index')
            ->with('success', 'Asset name created successfully.');
    }

    /* =========================
       SHOW
    ========================= */
    public function show(AssetName $assetname)
    {
        return Inertia::render('AssetNames/Show', [
            'assetName' => [
                'id' => $assetname->id,
                'assetname' => $assetname->assetname,
                'assetname_abbreviation' => $assetname->assetname_abbreviation,
                'brand' => $assetname->brand,
                'usefullife' => $assetname->usefullife,
                'created_at' => optional($assetname->created_at)->toDateTimeString(),
                'updated_at' => optional($assetname->updated_at)->toDateTimeString(),
            ],
        ]);
    }

    /* =========================
       EDIT
    ========================= */
    public function edit(AssetName $assetname)
    {
        return Inertia::render('AssetNames/Edit', [
            'assetName' => [
                'id' => $assetname->id,
                'assetname' => $assetname->assetname,
                'assetname_abbreviation' => $assetname->assetname_abbreviation,
                'brand' => $assetname->brand,
                'usefullife' => $assetname->usefullife,
            ],
        ]);
    }

    /* =========================
       UPDATE
    ========================= */
    public function update(Request $request, AssetName $assetname)
    {
        // Auto-uppercase abbreviation
        $request->merge([
            'assetname_abbreviation' => strtoupper($request->assetname_abbreviation),
        ]);

        $validated = $request->validate([
            'assetname' => 'required|string|max:255',
            'assetname_abbreviation' => [
                'required',
                'string',
                'max:255',
                Rule::unique('asset_names', 'assetname_abbreviation')->ignore($assetname->id),
            ],
            'brand' => 'nullable|string|max:255',
            'usefullife' => 'nullable|integer|min:0',
        ]);

        $assetname->update($validated);

        return redirect()
            ->route('assetnames.index')
            ->with('success', 'Asset name updated successfully.');
    }

    /* =========================
       DESTROY
    ========================= */
    public function destroy(AssetName $assetname)
    {
        $assetname->delete();

        return redirect()
            ->route('assetnames.index')
            ->with('success', 'Asset name deleted successfully.');
    }
}
