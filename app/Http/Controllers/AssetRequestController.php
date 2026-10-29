<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\Unit;
use App\Services\ActivityTrackerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AssetRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $assetRequests = AssetRequest::query()
            ->with([
                'unit:id,name,unit_abbreviation',
                'requester:id,name,email',
                'reviewer:id,name,email',
                'asset:id,asset_code,assetname_id',
                'asset.assetName:id,assetname',
            ])
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('request_code', 'like', "%{$search}%")
                        ->orWhere('asset_name', 'like', "%{$search}%")
                        ->orWhere('asset_category', 'like', "%{$search}%")
                        ->orWhere('preferred_specification', 'like', "%{$search}%")
                        ->orWhere('justification', 'like', "%{$search}%")
                        ->orWhereHas('unit', function ($unit) use ($search): void {
                            $unit->where('name', 'like', "%{$search}%")
                                ->orWhere('unit_abbreviation', 'like', "%{$search}%");
                        })
                        ->orWhereHas('requester', function ($user) use ($search): void {
                            $user->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('asset', function ($asset) use ($search): void {
                            $asset->where('asset_code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('asset.assetName', function ($assetName) use ($search): void {
                            $assetName->where('assetname', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('priority'), fn ($q, $v) => $q->where('priority', $v))
            ->when($request->input('unit_id'), fn ($q, $v) => $q->where('unit_id', $v))
            ->latest()
            ->paginate(100)
            ->withQueryString();

        return Inertia::render('AssetRequests/Index', [
            'minimumNeededDate' => today()->toDateString(),
            'filters' => $request->only([
                'search',
                'status',
                'priority',
                'unit_id',
            ]),

            'assetRequests' => $assetRequests,

            'units' => Unit::query()
                ->select('id', 'name', 'unit_abbreviation')
                ->orderBy('name')
                ->get(),

            'assets' => $this->assetDropdownOptions(),
        ]);
    }

    public function myRequests(Request $request): Response
    {
        $assetRequests = AssetRequest::query()
            ->with([
                'unit:id,name,unit_abbreviation',
                'requester:id,name,email',
                'reviewer:id,name,email',
                'asset:id,asset_code,assetname_id',
                'asset.assetName:id,assetname',
            ])
            ->where('requested_by', auth()->id())
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('request_code', 'like', "%{$search}%")
                        ->orWhere('asset_name', 'like', "%{$search}%")
                        ->orWhere('asset_category', 'like', "%{$search}%")
                        ->orWhere('preferred_specification', 'like', "%{$search}%")
                        ->orWhere('justification', 'like', "%{$search}%")
                        ->orWhereHas('unit', function ($unit) use ($search): void {
                            $unit->where('name', 'like', "%{$search}%")
                                ->orWhere('unit_abbreviation', 'like', "%{$search}%");
                        })
                        ->orWhereHas('asset', function ($asset) use ($search): void {
                            $asset->where('asset_code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('asset.assetName', function ($assetName) use ($search): void {
                            $assetName->where('assetname', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('priority'), fn ($q, $v) => $q->where('priority', $v))
            ->latest()
            ->paginate(100)
            ->withQueryString();

        return Inertia::render('AssetRequests/MyRequests', [
            'minimumNeededDate' => today()->toDateString(),
            'filters' => $request->only([
                'search',
                'status',
                'priority',
            ]),

            'assetRequests' => $assetRequests,

            'units' => Unit::query()
                ->select('id', 'name', 'unit_abbreviation')
                ->orderBy('name')
                ->get(),

            'assets' => $this->assetDropdownOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'asset_id' => ['required', 'exists:assets,id'],
            'asset_category' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'preferred_specification' => ['nullable', 'string', 'max:500'],
            'justification' => ['required', 'string', 'max:3000'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'needed_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $asset = Asset::query()
            ->select('id', 'asset_code', 'assetname_id')
            ->with('assetName:id,assetname')
            ->findOrFail($validated['asset_id']);

        $assetName = $asset->assetName?->assetname
            ?: $asset->asset_code
            ?: 'Selected Asset';

        $assetCategory = $validated['asset_category'] ?? null;

        $assetRequest = AssetRequest::create([
            'unit_id' => $validated['unit_id'] ?: auth()->user()?->unit_id,
            'requested_by' => auth()->id(),
            'asset_id' => $asset->id,
            'asset_name' => $assetName,
            'asset_category' => $assetCategory,
            'quantity' => $validated['quantity'],
            'preferred_specification' => $validated['preferred_specification'] ?? null,
            'justification' => $validated['justification'],
            'priority' => $validated['priority'],
            'needed_date' => $validated['needed_date'] ?? null,
            'status' => 'pending',
        ]);

        ActivityTrackerService::record(
            $request,
            'Asset Request',
            'created',
            'Asset request submitted',
            $assetRequest->request_code . ' - ' . $assetRequest->asset_name,
            $assetRequest,
            null,
            $assetRequest->toArray()
        );

        return back()->with('success', 'Asset request submitted successfully.');
    }

    public function update(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'asset_id' => ['required', 'exists:assets,id'],
            'asset_category' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'preferred_specification' => ['nullable', 'string', 'max:500'],
            'justification' => ['required', 'string', 'max:3000'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'needed_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $assetRequest, $validated) {
            $record = AssetRequest::query()->lockForUpdate()->findOrFail($assetRequest->id);
            abort_unless((int) $record->requested_by === (int) auth()->id(), 403);
            abort_unless($record->status === 'pending', 409, 'Only pending requests can be edited.');
            $asset = Asset::with('assetName:id,assetname')->findOrFail($validated['asset_id']);
            $before = $record->toArray();
            $record->fill($validated + ['asset_name' => $asset->assetName?->assetname ?: $asset->asset_code])->save();
            ActivityTrackerService::record($request, 'Asset Request', 'updated', 'Asset request updated', $record->request_code, $record, $before, $record->fresh()->toArray());
        });
        return back()->with('success', 'Asset request updated successfully.');
    }

    public function approve(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $request->validate([
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldValues = $assetRequest->getOriginal();

        $assetRequest->forceFill([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_comment' => $request->input('review_comment'),
        ])->save();

        ActivityTrackerService::record(
            $request,
            'Asset Request',
            'approved',
            'Asset request approved',
            $assetRequest->request_code,
            $assetRequest,
            $oldValues,
            $assetRequest->toArray()
        );

        return back()->with('success', 'Asset request approved successfully.');
    }

    public function reject(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $request->validate([
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $oldValues = $assetRequest->getOriginal();

        $assetRequest->forceFill([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_comment' => $request->input('review_comment'),
        ])->save();

        ActivityTrackerService::record(
            $request,
            'Asset Request',
            'rejected',
            'Asset request rejected',
            $assetRequest->request_code,
            $assetRequest,
            $oldValues,
            $assetRequest->toArray()
        );

        return back()->with('success', 'Asset request rejected successfully.');
    }

    public function supply(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $validated = $request->validate([
            'asset_id' => ['required', 'exists:assets,id'],
            'review_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $asset = Asset::query()
            ->select('id', 'asset_code', 'assetname_id')
            ->with('assetName:id,assetname')
            ->findOrFail($validated['asset_id']);

        $oldValues = $assetRequest->getOriginal();

        $assetRequest->forceFill([
            'status' => 'supplied',
            'asset_id' => $asset->id,
            'asset_name' => $asset->assetName?->assetname ?: $assetRequest->asset_name,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => $assetRequest->reviewed_at ?: now(),
            'supplied_at' => now(),
            'review_comment' => $validated['review_comment'] ?? $assetRequest->review_comment,
        ])->save();

        ActivityTrackerService::record(
            $request,
            'Asset Request',
            'supplied',
            'Asset request supplied',
            $assetRequest->request_code,
            $assetRequest,
            $oldValues,
            $assetRequest->toArray()
        );

        return back()->with('success', 'Asset request marked as supplied successfully.');
    }

    public function cancel(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        abort_unless(
            $assetRequest->requested_by === auth()->id() && $assetRequest->status === 'pending',
            403
        );

        $oldValues = $assetRequest->getOriginal();

        $assetRequest->forceFill([
            'status' => 'cancelled',
        ])->save();

        ActivityTrackerService::record(
            $request,
            'Asset Request',
            'cancelled',
            'Asset request cancelled',
            $assetRequest->request_code,
            $assetRequest,
            $oldValues,
            $assetRequest->toArray()
        );

        return back()->with('success', 'Asset request cancelled successfully.');
    }

    public function destroy(Request $request, AssetRequest $assetRequest): RedirectResponse
    {
        $oldValues = $assetRequest->toArray();

        $assetRequest->delete();

        ActivityTrackerService::record(
            $request,
            'Asset Request',
            'deleted',
            'Asset request deleted',
            $oldValues['request_code'] ?? 'Asset request',
            null,
            $oldValues,
            null
        );

        return back()->with('success', 'Asset request deleted successfully.');
    }

    private function assetDropdownOptions(): Collection
    {
        return Asset::query()
            ->select('id', 'asset_code', 'assetname_id')
            ->with('assetName:id,assetname')
            ->orderByDesc('id')
            ->limit(1000)
            ->get()
            ->map(fn (Asset $asset): array => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'assetname_id' => $asset->assetname_id,
                'asset_name' => $asset->assetName?->assetname ?: $asset->asset_code,
                'label' => trim(
                    ($asset->assetName?->assetname ?: 'Unnamed Asset')
                    . ($asset->asset_code ? ' - ' . $asset->asset_code : '')
                ),
            ])
            ->values();
    }
}
