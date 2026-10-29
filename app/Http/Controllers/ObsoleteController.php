<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;

class ObsoleteController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->integer('per_page', 100), 1000);

        $assets = Asset::query()
            ->where('status', 'disposed')
            ->with([
                'assetName:id,assetname as name',
                'unit:id,name,locationname', // ✅ FIXED
            ])
            ->when($request->filled('search'), function ($query) use ($request) {

                $rawSearch = trim($request->search);

                $search = addcslashes($rawSearch, '%_\\');
                $statusSearch = str_replace(' ', '_', strtolower($rawSearch));

                $query->where(function ($q) use ($search, $statusSearch) {

                    $q->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$statusSearch}%")
                        ->orWhereHas('assetName', fn ($qq) => $qq->where('assetname', 'like', "%{$search}%")
                        )
                        ->orWhereHas('unit', fn ($qq) => $qq->where('name', 'like', "%{$search}%")
                            ->orWhere('locationname', 'like', "%{$search}%")
                        );
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Assets/Index', [
            'assets' => $assets,
            'filters' => $request->only('search', 'per_page'),
        ]);
    }
}
