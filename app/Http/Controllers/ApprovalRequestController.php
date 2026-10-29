<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Asset;
use App\Services\ActivityTrackerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApprovalRequestController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('ApprovalRequests/Index', [
            'filters' => $request->only(['search','type','status']),
            'approvalRequests' => ApprovalRequest::query()
                ->with(['asset.assetName:id,assetname', 'requester:id,name,email', 'reviewer:id,name,email'])
                ->when($request->input('search'), function ($query, $search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereHas('asset', fn ($a) => $a->where('asset_code', 'like', "%{$search}%"));
                })
                ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->latest()->paginate(100)->withQueryString(),
            'assets' => Asset::query()->select('id','asset_code','assetname_id')->with('assetName:id,assetname')->orderByDesc('id')->limit(500)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => ['nullable','exists:assets,id'],
            'type' => ['required','in:asset_transfer,asset_disposal,maintenance_request,asset_assignment'],
            'title' => ['required','string','max:255'],
            'reason' => ['nullable','string','max:2000'],
        ]);
        $approval = ApprovalRequest::create($validated + ['requested_by' => auth()->id(), 'status' => 'pending']);
        ActivityTrackerService::record($request, 'Approval Workflow', 'created', 'Approval request created', $approval->title, $approval, null, $approval->toArray());
        return back()->with('success', 'Approval request submitted successfully.');
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest)
    {
        $approvalRequest->forceFill([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'review_comment' => $request->input('review_comment'),
            'reviewed_at' => now(),
        ])->save();
        ActivityTrackerService::record($request, 'Approval Workflow', 'approved', 'Approval request approved', $approvalRequest->title, $approvalRequest);
        return back()->with('success', 'Approval request approved successfully.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest)
    {
        $approvalRequest->forceFill([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'review_comment' => $request->input('review_comment'),
            'reviewed_at' => now(),
        ])->save();
        ActivityTrackerService::record($request, 'Approval Workflow', 'rejected', 'Approval request rejected', $approvalRequest->title, $approvalRequest);
        return back()->with('success', 'Approval request rejected successfully.');
    }

    public function update(Request $request, ApprovalRequest $approvalRequest)
    {
        $validated = $request->validate([
            'asset_id' => ['nullable', 'exists:assets,id'],
            'type' => ['required', 'in:asset_transfer,asset_disposal,maintenance_request,asset_assignment'],
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $approvalRequest, $validated) {
            $record = ApprovalRequest::query()->lockForUpdate()->findOrFail($approvalRequest->id);
            abort_unless($record->status === 'pending', 409, 'Only pending approval requests can be edited.');
            $before = $record->toArray();
            $record->fill($validated)->save();
            ActivityTrackerService::record($request, 'Approval Workflow', 'updated', 'Approval request updated', $record->title, $record, $before, $record->fresh()->toArray());
        });
        return back()->with('success', 'Approval request updated successfully.');
    }

    public function destroy(ApprovalRequest $approvalRequest)
    {
        $approvalRequest->delete();
        return back()->with('success', 'Approval request deleted successfully.');
    }
}
