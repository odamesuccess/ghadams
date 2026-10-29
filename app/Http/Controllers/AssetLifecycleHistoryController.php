<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetLifecycleHistory;
use App\Services\ActivityTrackerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssetLifecycleHistoryController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('AssetLifecycleHistories/Index', [
            'filters' => $request->only(['search', 'status']),
            'histories' => AssetLifecycleHistory::query()
                ->with(['asset.assetName:id,assetname', 'changedBy:id,name,email'])
                ->when($request->input('search'), function ($query, $search) {
                    $query->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('asset', fn ($a) => $a->where('asset_code', 'like', "%{$search}%"));
                })
                ->when($request->input('status'), fn ($q, $v) => $q->where('new_status', $v))
                ->latest('changed_at')
                ->paginate(100)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'new_status' => ['required', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldStatus = $asset->status;
        $asset->forceFill(['status' => $validated['new_status']])->save();

        $history = AssetLifecycleHistory::create([
            'asset_id' => $asset->id,
            'changed_by' => auth()->id(),
            'old_status' => $oldStatus,
            'new_status' => $validated['new_status'],
            'reason' => $validated['reason'] ?? null,
            'changed_at' => now(),
        ]);

        ActivityTrackerService::record($request, 'Asset Lifecycle', 'status_changed', 'Asset status changed', "Asset {$asset->asset_code} changed from {$oldStatus} to {$validated['new_status']}", $asset, ['status' => $oldStatus], ['status' => $validated['new_status']]);

        return back()->with('success', 'Asset lifecycle status updated successfully.');
    }
}
