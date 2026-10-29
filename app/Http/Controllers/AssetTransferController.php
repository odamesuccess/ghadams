<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetLifecycleHistory;
use App\Models\AssetTransfer;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityTrackerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AssetTransferController extends Controller
{
    public function index(Request $request): Response
    {
        $assetTransfers = AssetTransfer::query()
            ->with($this->relations())
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('transfer_code', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhere('remarks', 'like', "%{$search}%")
                        ->orWhereHas('asset', function ($asset) use ($search): void {
                            $asset->where('asset_code', 'like', "%{$search}%")
                                ->orWhere('serial_number', 'like', "%{$search}%")
                                ->orWhereHas('assetName', fn ($assetName) => $assetName->where('assetname', 'like', "%{$search}%"));
                        })
                        ->orWhereHas('fromUnit', fn ($unit) => $unit->where('name', 'like', "%{$search}%")->orWhere('unit_abbreviation', 'like', "%{$search}%"))
                        ->orWhereHas('toUnit', fn ($unit) => $unit->where('name', 'like', "%{$search}%")->orWhere('unit_abbreviation', 'like', "%{$search}%"))
                        ->orWhereHas('requester', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('from_unit_id'), fn ($q, $v) => $q->where('from_unit_id', $v))
            ->when($request->input('to_unit_id'), fn ($q, $v) => $q->where('to_unit_id', $v))
            ->latest()
            ->paginate(100)
            ->withQueryString();

        return Inertia::render('AssetTransfers/Index', [
            'filters' => $request->only(['search', 'status', 'from_unit_id', 'to_unit_id']),
            'assetTransfers' => $assetTransfers,
            'assets' => $this->assetOptions(),
            'units' => $this->unitOptions(),
            'users' => $this->userOptions(),
        ]);
    }

    public function myTransfers(Request $request): Response
    {
        $userId = auth()->id();

        return Inertia::render('AssetTransfers/MyTransfers', [
            'filters' => $request->only(['search', 'status']),
            'assetTransfers' => AssetTransfer::query()
                ->with($this->relations())
                ->where(function ($query) use ($userId): void {
                    $query->where('requested_by', $userId)
                        ->orWhere('from_user_id', $userId)
                        ->orWhere('to_user_id', $userId);
                })
                ->when($request->input('search'), function ($query, string $search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('transfer_code', 'like', "%{$search}%")
                            ->orWhere('reason', 'like', "%{$search}%")
                            ->orWhereHas('asset', function ($asset) use ($search): void {
                                $asset->where('asset_code', 'like', "%{$search}%")
                                    ->orWhereHas('assetName', fn ($assetName) => $assetName->where('assetname', 'like', "%{$search}%"));
                            });
                    });
                })
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->latest()
                ->paginate(100)
                ->withQueryString(),
            'assets' => $this->assetOptions(),
            'units' => $this->unitOptions(),
            'users' => $this->userOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_id' => ['required', 'exists:assets,id'],
            'to_unit_id' => ['required', 'exists:units,id'],
            'to_user_id' => ['nullable', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:3000'],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $asset = Asset::query()->findOrFail($validated['asset_id']);

        if ((int) $asset->unit_id === (int) $validated['to_unit_id'] && (int) ($asset->user_id ?? 0) === (int) ($validated['to_user_id'] ?? 0)) {
            return back()->with('error', 'The selected asset is already assigned to the selected destination office/custodian.');
        }

        $assetTransfer = AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_unit_id' => $asset->unit_id,
            'to_unit_id' => $validated['to_unit_id'],
            'from_user_id' => $asset->user_id,
            'to_user_id' => $validated['to_user_id'] ?? null,
            'requested_by' => auth()->id(),
            'reason' => $validated['reason'],
            'remarks' => $validated['remarks'] ?? null,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        ActivityTrackerService::record(
            $request,
            'Asset Transfer',
            'created',
            'Asset transfer request submitted',
            $assetTransfer->transfer_code,
            $assetTransfer,
            null,
            $assetTransfer->toArray()
        );

        return back()->with('success', 'Asset transfer request submitted successfully.');
    }

    public function approve(Request $request, AssetTransfer $assetTransfer): RedirectResponse
    {
        abort_unless($assetTransfer->status === 'pending', 422, 'Only pending transfers can be approved.');

        $request->validate([
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        $oldValues = $assetTransfer->getOriginal();

        $assetTransfer->forceFill([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'remarks' => $request->input('remarks', $assetTransfer->remarks),
        ])->save();

        ActivityTrackerService::record($request, 'Asset Transfer', 'approved', 'Asset transfer approved', $assetTransfer->transfer_code, $assetTransfer, $oldValues, $assetTransfer->toArray());

        return back()->with('success', 'Asset transfer approved successfully.');
    }

    public function reject(Request $request, AssetTransfer $assetTransfer): RedirectResponse
    {
        abort_unless($assetTransfer->status === 'pending', 422, 'Only pending transfers can be rejected.');

        $request->validate([
            'remarks' => ['required', 'string', 'max:3000'],
        ]);

        $oldValues = $assetTransfer->getOriginal();

        $assetTransfer->forceFill([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'rejected_at' => now(),
            'remarks' => $request->input('remarks'),
        ])->save();

        ActivityTrackerService::record($request, 'Asset Transfer', 'rejected', 'Asset transfer rejected', $assetTransfer->transfer_code, $assetTransfer, $oldValues, $assetTransfer->toArray());

        return back()->with('success', 'Asset transfer rejected successfully.');
    }

    public function complete(Request $request, AssetTransfer $assetTransfer): RedirectResponse
    {
        abort_unless(in_array($assetTransfer->status, ['pending', 'approved'], true), 422, 'This transfer cannot be completed.');

        $request->validate([
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $assetTransfer): void {
            $assetTransfer->load(['asset.assetName', 'fromUnit', 'toUnit', 'fromUser', 'toUser']);

            $asset = $assetTransfer->asset;
            $oldAssetValues = $asset->getOriginal();
            $oldTransferValues = $assetTransfer->getOriginal();

            $asset->forceFill([
                'unit_id' => $assetTransfer->to_unit_id,
                'user_id' => $assetTransfer->to_user_id,
            ])->save();

            $assetTransfer->forceFill([
                'status' => 'completed',
                'approved_by' => $assetTransfer->approved_by ?: auth()->id(),
                'approved_at' => $assetTransfer->approved_at ?: now(),
                'completed_by' => auth()->id(),
                'completed_at' => now(),
                'remarks' => $request->input('remarks', $assetTransfer->remarks),
            ])->save();

            AssetLifecycleHistory::create([
                'asset_id' => $asset->id,
                'changed_by' => auth()->id(),
                'old_status' => $oldAssetValues['status'] ?? null,
                'new_status' => $asset->status ?? 'Transferred',
                'reason' => 'Asset transferred from ' . ($assetTransfer->fromUnit?->name ?? 'Unassigned office') . ' to ' . ($assetTransfer->toUnit?->name ?? 'destination office') . '.',
                'metadata' => [
                    'action' => 'asset_transfer_completed',
                    'transfer_code' => $assetTransfer->transfer_code,
                    'from_unit_id' => $assetTransfer->from_unit_id,
                    'from_unit' => $assetTransfer->fromUnit?->name,
                    'to_unit_id' => $assetTransfer->to_unit_id,
                    'to_unit' => $assetTransfer->toUnit?->name,
                    'from_user_id' => $assetTransfer->from_user_id,
                    'from_user' => $assetTransfer->fromUser?->name,
                    'to_user_id' => $assetTransfer->to_user_id,
                    'to_user' => $assetTransfer->toUser?->name,
                    'reason' => $assetTransfer->reason,
                    'remarks' => $assetTransfer->remarks,
                ],
                'changed_at' => now(),
            ]);

            ActivityTrackerService::record($request, 'Asset Transfer', 'completed', 'Asset transfer completed and asset movement recorded', $assetTransfer->transfer_code, $assetTransfer, $oldTransferValues, $assetTransfer->toArray());
            ActivityTrackerService::record($request, 'Asset', 'transferred', 'Asset assigned to new office/custodian', $asset->asset_code ?? ('Asset #' . $asset->id), $asset, $oldAssetValues, $asset->toArray());
        });

        return back()->with('success', 'Asset transfer completed successfully. Asset movement has been recorded.');
    }

    public function cancel(Request $request, AssetTransfer $assetTransfer): RedirectResponse
    {
        abort_unless($assetTransfer->requested_by === auth()->id() && $assetTransfer->status === 'pending', 403);

        $oldValues = $assetTransfer->getOriginal();

        $assetTransfer->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ])->save();

        ActivityTrackerService::record($request, 'Asset Transfer', 'cancelled', 'Asset transfer cancelled', $assetTransfer->transfer_code, $assetTransfer, $oldValues, $assetTransfer->toArray());

        return back()->with('success', 'Asset transfer cancelled successfully.');
    }

    public function destroy(Request $request, AssetTransfer $assetTransfer): RedirectResponse
    {
        $oldValues = $assetTransfer->toArray();
        $assetTransfer->delete();

        ActivityTrackerService::record($request, 'Asset Transfer', 'deleted', 'Asset transfer deleted', $oldValues['transfer_code'] ?? 'Asset transfer', null, $oldValues, null);

        return back()->with('success', 'Asset transfer deleted successfully.');
    }

    private function relations(): array
    {
        return [
            'asset:id,asset_code,assetname_id,serial_number,unit_id,user_id,status',
            'asset.assetName:id,assetname',
            'fromUnit:id,name,unit_abbreviation',
            'toUnit:id,name,unit_abbreviation',
            'fromUser:id,name,email',
            'toUser:id,name,email',
            'requester:id,name,email',
            'approver:id,name,email',
            'completer:id,name,email',
        ];
    }

    private function assetOptions()
    {
        return Asset::query()
            ->select('id', 'asset_code', 'assetname_id', 'serial_number', 'unit_id', 'user_id', 'status')
            ->with([
                'assetName:id,assetname',
                'unit:id,name,unit_abbreviation',
                'user:id,name,email',
            ])
            ->orderByDesc('id')
            ->limit(1000)
            ->get();
    }

    private function unitOptions()
    {
        return Unit::query()
            ->select('id', 'name', 'unit_abbreviation')
            ->orderBy('name')
            ->get();
    }

    private function userOptions()
    {
        return User::query()
            ->select('id', 'name', 'email', 'unit_id')
            ->orderBy('name')
            ->limit(1000)
            ->get();
    }
}
