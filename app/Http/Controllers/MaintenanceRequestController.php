<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\MaintenanceProvider;
use App\Models\MaintenanceRequest;
use App\Services\ActivityTrackerService;
use App\Support\PdfWatermark;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MaintenanceRequestController extends Controller
{
    /**
     * Update an unpaid maintenance job's operational status.
     *
     * Completed is handled by complete() because completion requires:
     * invoice PDF, actual cost, completion date and completion note.
     *
     * Once Accounts marks payment Paid, Job Status is locked.
     */
    public function updateJobStatus(
        Request $request,
        MaintenanceRequest $maintenanceRequest
    ) {
        if (
            strtolower(
                (string) ($maintenanceRequest->payment_status ?? 'unpaid')
            ) === 'paid'
        ) {
            return back()->with(
                'error',
                'This maintenance job has been paid and its Job Status is locked.'
            );
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'in:pending,approved,in_progress',
            ],
        ]);

        $nextStatus = $validated['status'];
        $oldStatus = (string) $maintenanceRequest->status;

        if ($oldStatus === $nextStatus) {
            return back();
        }

        $oldValues = [
            'status' => $maintenanceRequest->status,
            'approved_by' => $maintenanceRequest->approved_by,
            'approval_comment' => $maintenanceRequest->approval_comment,
            'actual_cost' => $maintenanceRequest->actual_cost,
            'actual_completion_date' => $maintenanceRequest->actual_completion_date,
            'completion_note' => $maintenanceRequest->completion_note,
            'completed_by' => $maintenanceRequest->completed_by,
            'payment_status' => $maintenanceRequest->payment_status,
            'payment_date' => $maintenanceRequest->payment_date,
        ];

        $updates = [
            'status' => $nextStatus,
        ];

        if ($nextStatus === 'pending') {
            $updates['approved_by'] = null;
            $updates['approval_comment'] = null;
        }

        if (
            in_array($nextStatus, ['approved', 'in_progress'], true)
            && ! $maintenanceRequest->approved_by
        ) {
            $updates['approved_by'] = auth()->id();
        }

        /*
         * Re-opening a completed but unpaid job must also remove stale
         * completion/payment data so the job leaves Accounts until formally
         * completed again.
         */
        if ($oldStatus === 'completed') {
            $updates['actual_cost'] = null;
            $updates['actual_completion_date'] = null;
            $updates['completion_note'] = null;
            $updates['completed_by'] = null;
            $updates['payment_status'] = 'unpaid';
            $updates['payment_date'] = null;
        }

        $maintenanceRequest->forceFill($updates)->save();

        if (method_exists($this, 'syncAssetMaintenanceStatus')) {
            $this->syncAssetMaintenanceStatus(
                (int) $maintenanceRequest->asset_id
            );
        }

        ActivityTrackerService::record(
            $request,
            'Maintenance Request',
            'status_changed',
            'Maintenance Job Status changed',
            "{$oldStatus} -> {$nextStatus}",
            $maintenanceRequest,
            $oldValues,
            [
                'status' => $maintenanceRequest->status,
                'approved_by' => $maintenanceRequest->approved_by,
                'payment_status' => $maintenanceRequest->payment_status,
            ]
        );

        return back()->with(
            'success',
            'Maintenance Job Status updated successfully.'
        );
    }

    /**
     * Active/completed jobs are protected from ordinary edit/delete operations.
     *
     * New maintenance jobs now enter the workflow directly as "in_progress".
     * The "approved" value is retained only for compatibility with legacy
     * records created before this workflow revision.
     */
    private const LOCKED_JOB_STATUSES = ['approved', 'in_progress', 'completed'];

    public function index(Request $request)
    {
        return Inertia::render('MaintenanceRequests/Index', [
            'filters' => $request->only(['search', 'status', 'priority', 'payment_status']),

            'maintenanceRequests' => MaintenanceRequest::query()
                ->with([
                    'asset.assetName:id,assetname',
                    'requester:id,name,email',
                    'approver:id,name,email',
                    'maintenanceProvider:id,providername',
                ])
                ->when($request->input('search'), function ($query, $search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('fault_description', 'like', "%{$search}%")
                            ->orWhere('payment_status', 'like', "%{$search}%")
                            ->orWhereHas('asset', function ($asset) use ($search): void {
                                $asset->where('asset_code', 'like', "%{$search}%")
                                    ->orWhereHas('assetName', function ($assetName) use ($search): void {
                                        $assetName->where('assetname', 'like', "%{$search}%");
                                    });
                            })
                            ->orWhereHas('requester', function ($requester) use ($search): void {
                                $requester->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                            ->orWhereHas('maintenanceProvider', function ($provider) use ($search): void {
                                $provider->where('providername', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->input('priority'), fn ($q, $v) => $q->where('priority', $v))
                ->when($request->input('payment_status'), function ($query, $value): void {
                    if ($value === 'unpaid') {
                        $query->where(function ($q): void {
                            $q->whereNull('payment_status')->orWhere('payment_status', 'unpaid');
                        });

                        return;
                    }

                    $query->where('payment_status', $value);
                })
                ->latest()
                ->paginate(100)
                ->withQueryString(),

            'providers' => MaintenanceProvider::query()
                ->select('id', 'providername')
                ->orderBy('providername')
                ->get(),
        ]);
    }

    public function userMaintenanceRequest(Request $request)
    {
        return Inertia::render('MaintenanceRequests/UserMaintenanceRequest', [
            'filters' => $request->only(['search', 'status', 'priority']),

            'maintenanceRequests' => MaintenanceRequest::query()
                ->with([
                    'asset.assetName:id,assetname',
                    'requester:id,name,email',
                    'approver:id,name,email',
                    'maintenanceProvider:id,providername',
                ])
                ->where('requested_by', auth()->id())
                ->when($request->input('search'), function ($query, $search): void {
                    $query->where(function ($q) use ($search): void {
                        $q->where('fault_description', 'like', "%{$search}%")
                            ->orWhereHas('asset', function ($asset) use ($search): void {
                                $asset->where('asset_code', 'like', "%{$search}%")
                                    ->orWhereHas('assetName', function ($assetName) use ($search): void {
                                        $assetName->where('assetname', 'like', "%{$search}%");
                                    });
                            })
                            ->orWhereHas('maintenanceProvider', function ($provider) use ($search): void {
                                $provider->where('providername', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->input('priority'), fn ($q, $v) => $q->where('priority', $v))
                ->latest()
                ->paginate(100)
                ->withQueryString(),

            'assets' => Asset::query()
                ->select('id', 'asset_code', 'assetname_id')
                ->with('assetName:id,assetname')
                ->orderByDesc('id')
                ->limit(500)
                ->get(),

            'providers' => MaintenanceProvider::query()
                ->select('id', 'providername')
                ->orderBy('providername')
                ->get(),
        ]);
    }

    /**
     * Live asset-code lookup for the maintenance-job form.
     *
     * The browser sends the text being typed and receives only the best
     * matching assets. This avoids loading hundreds/thousands of assets into
     * a native select box.
     */
    public function assetSearch(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['data' => []]);
        }

        $assets = Asset::query()
            ->select('id', 'asset_code', 'assetname_id', 'unit_id', 'status')
            ->with([
                'assetName:id,assetname',
                'unit:id,name,locationname',
            ])
            ->where(function ($assetQuery) use ($query): void {
                $assetQuery
                    ->where('asset_code', 'like', "%{$query}%")
                    ->orWhereHas('assetName', function ($assetNameQuery) use ($query): void {
                        $assetNameQuery->where('assetname', 'like', "%{$query}%");
                    });
            })
            ->orderByRaw(
                'CASE WHEN asset_code LIKE ? THEN 0 ELSE 1 END',
                ["{$query}%"]
            )
            ->orderBy('asset_code')
            ->limit(15)
            ->get()
            ->map(function (Asset $asset): array {
                return [
                    'id' => $asset->id,
                    'asset_code' => $asset->asset_code,
                    'asset_name' => $asset->assetName?->assetname,
                    'unit_name' => $asset->unit?->name,
                    'location_name' => $asset->unit?->locationname,
                    'status' => $asset->status,
                ];
            })
            ->values();

        return response()->json(['data' => $assets]);
    }

    /**
     * Accounts-facing maintenance payment register.
     */
    public function maintenancePayment(Request $request)
    {
        /*
         * Accounts must see only completed maintenance jobs.
         * In-progress/pending/approved jobs are operational work items and are
         * deliberately excluded from the payment register.
         */
        $maintenanceRequests = MaintenanceRequest::query()
            ->with([
                'asset.assetName:id,assetname',
                'requester:id,name,email',
                'approver:id,name,email',
                'completer:id,name,email',
                'maintenanceProvider:id,providername',
            ])
            ->where('status', 'completed')
            ->when($request->input('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('fault_description', 'like', "%{$search}%")
                        ->orWhere('priority', 'like', "%{$search}%")
                        ->orWhere('payment_status', 'like', "%{$search}%")
                        ->orWhereHas('asset', function ($asset) use ($search): void {
                            $asset->where('asset_code', 'like', "%{$search}%")
                                ->orWhereHas('assetName', function ($assetName) use ($search): void {
                                    $assetName->where('assetname', 'like', "%{$search}%");
                                });
                        })
                        ->orWhereHas('requester', function ($requester) use ($search): void {
                            $requester->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('maintenanceProvider', function ($provider) use ($search): void {
                            $provider->where('providername', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->input('priority'), fn ($q, $v) => $q->where('priority', $v))
            ->when($request->input('provider_id'), fn ($q, $v) => $q->where('maintenance_provider_id', $v))
            ->when($request->input('payment_status'), function ($query, $value): void {
                if ($value === 'unpaid') {
                    $query->where(function ($q): void {
                        $q->whereNull('payment_status')->orWhere('payment_status', 'unpaid');
                    });

                    return;
                }

                $query->where('payment_status', $value);
            })
            ->latest('actual_completion_date')
            ->latest('id')
            ->paginate(100)
            ->withQueryString();

        $summaryQuery = MaintenanceRequest::query()
            ->where('status', 'completed');

        $summary = [
            'total_requests' => (clone $summaryQuery)->count(),
            'pending_requests' => 0,
            'approved_requests' => 0,
            'completed_requests' => (clone $summaryQuery)->count(),
            'paid_requests' => (clone $summaryQuery)->where('payment_status', 'paid')->count(),
            'payment_pending_requests' => (clone $summaryQuery)->where('payment_status', 'pending')->count(),
            'unpaid_requests' => (clone $summaryQuery)->where(function ($query): void {
                $query->whereNull('payment_status')
                    ->orWhere('payment_status', 'unpaid');
            })->count(),
            'total_estimated_cost' => (float) (clone $summaryQuery)->sum('estimated_cost'),
            'total_actual_cost' => (float) (clone $summaryQuery)->sum('actual_cost'),
            'total_paid_amount' => (float) (clone $summaryQuery)
                ->where('payment_status', 'paid')
                ->sum(DB::raw('COALESCE(actual_cost, estimated_cost, 0)')),
        ];

        return Inertia::render('Accounts/MaintenancePayments', [
            'filters' => $request->only(['search', 'priority', 'provider_id', 'payment_status']),
            'maintenanceRequests' => $maintenanceRequests,
            'providers' => MaintenanceProvider::query()
                ->select('id', 'providername')
                ->orderBy('providername')
                ->get(),
            'summary' => $summary,
        ]);
    }

        /**
     * Create a maintenance job.
     *
     * The asset becomes under maintenance only when a service date exists.
     */
    public function store(Request $request)
    {
        $validated = $this->validateMaintenanceJob($request);

        $maintenanceRequest = DB::transaction(function () use ($validated): MaintenanceRequest {
            return MaintenanceRequest::create($validated + [
                'requested_by' => auth()->id(),
                'status' => 'in_progress',
                'payment_status' => 'unpaid',
                'payment_date' => null,
            ]);
        });

        $this->syncAssetMaintenanceStatus(
            (int) $maintenanceRequest->asset_id
        );

        ActivityTrackerService::record(
            $request,
            'Maintenance Request',
            'created',
            $maintenanceRequest->scheduled_date
                ? 'Maintenance job created and scheduled for service'
                : 'Maintenance job created without a scheduled service date',
            $maintenanceRequest->fault_description,
            $maintenanceRequest,
            null,
            $maintenanceRequest->toArray()
        );

        return back()->with(
            'success',
            $maintenanceRequest->scheduled_date
                ? 'Maintenance job created. The asset is now listed under scheduled maintenance.'
                : 'Maintenance job created. The asset will appear under Under Maintenance Assets after a service date is scheduled.'
        );
    }

            /**
     * Edit a maintenance job until Accounts has completed payment.
     *
     * Business rule:
     * - Pending / Approved / In Progress / Completed + unpaid/pending:
     *   EDIT ALLOWED.
     * - payment_status = paid:
     *   EDIT LOCKED permanently for audit/PV integrity.
     *
     * The backend enforces this independently of the Vue interface.
     */
    public function update(
        Request $request,
        MaintenanceRequest $maintenanceRequest
    ) {
        if (
            strtolower((string) ($maintenanceRequest->payment_status ?? 'unpaid'))
            === 'paid'
        ) {
            return back()->with(
                'error',
                'This maintenance job has been paid and is locked against editing.'
            );
        }

        $oldAssetId = (int) $maintenanceRequest->asset_id;
        $validated = $this->validateMaintenanceJob($request);
        $oldValues = $maintenanceRequest->only(
            array_keys($validated)
        );

        $maintenanceRequest->update($validated);

        /*
         * Preserve the scheduled-maintenance synchronization introduced by
         * the Under Maintenance Assets workflow.
         */
        if (method_exists($this, 'syncAssetMaintenanceStatus')) {
            $this->syncAssetMaintenanceStatus($oldAssetId);
            $this->syncAssetMaintenanceStatus(
                (int) $maintenanceRequest->asset_id
            );
        }

        ActivityTrackerService::record(
            $request,
            'Maintenance Request',
            'updated',
            'Maintenance request edited before payment completion',
            $maintenanceRequest->fault_description,
            $maintenanceRequest,
            $oldValues,
            $maintenanceRequest->only(array_keys($validated))
        );

        return back()->with(
            'success',
            'Maintenance job updated successfully.'
        );
    }

        public function approve(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        if ($maintenanceRequest->status !== 'pending') {
            return back()->with('error', 'Only legacy pending maintenance jobs can be started.');
        }

        $oldValues = [
            'status' => $maintenanceRequest->status,
            'approved_by' => $maintenanceRequest->approved_by,
            'approval_comment' => $maintenanceRequest->approval_comment,
        ];

        $maintenanceRequest->forceFill([
            'status' => 'in_progress',
            'approved_by' => auth()->id(),
            'approval_comment' => $request->input('approval_comment'),
        ])->save();

        $this->syncAssetMaintenanceStatus(
            (int) $maintenanceRequest->asset_id
        );

        ActivityTrackerService::record(
            $request,
            'Maintenance Request',
            'started',
            'Maintenance job moved to in progress',
            $maintenanceRequest->fault_description,
            $maintenanceRequest,
            $oldValues,
            [
                'status' => $maintenanceRequest->status,
                'approved_by' => $maintenanceRequest->approved_by,
                'approval_comment' => $maintenanceRequest->approval_comment,
            ]
        );

        return back()->with('success', 'Maintenance job is now In Progress.');
    }

        /**
     * Complete the job and automatically resynchronise the asset.
     */
    public function complete(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        if (! in_array($maintenanceRequest->status, ['pending', 'approved', 'in_progress'], true)) {
            return back()->with('error', 'Only an In Progress maintenance job can be marked as completed.');
        }

        if (! $this->invoiceExists($maintenanceRequest)) {
            return back()->with('error', 'Attach the maintenance job invoice PDF before marking this job as Completed.');
        }

        $validated = $request->validate([
            'actual_cost' => ['required', 'numeric', 'min:0'],
            'actual_completion_date' => ['required', 'date'],
            'completion_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $oldValues = [
            'status' => $maintenanceRequest->status,
            'actual_cost' => $maintenanceRequest->actual_cost,
            'actual_completion_date' => $maintenanceRequest->actual_completion_date,
            'completion_note' => $maintenanceRequest->completion_note,
            'completed_by' => $maintenanceRequest->completed_by,
        ];

        $maintenanceRequest->forceFill($validated + [
            'status' => 'completed',
            'completed_by' => auth()->id(),
        ])->save();

        $this->syncAssetMaintenanceStatus(
            (int) $maintenanceRequest->asset_id
        );

        ActivityTrackerService::record(
            $request,
            'Maintenance Request',
            'completed',
            'Maintenance request completed',
            $maintenanceRequest->fault_description,
            $maintenanceRequest,
            $oldValues,
            [
                'status' => $maintenanceRequest->status,
                'actual_cost' => $maintenanceRequest->actual_cost,
                'actual_completion_date' => $maintenanceRequest->actual_completion_date,
                'completion_note' => $maintenanceRequest->completion_note,
                'completed_by' => $maintenanceRequest->completed_by,
            ]
        );

        return back()->with(
            'success',
            'Maintenance job marked as Completed. The asset was updated automatically and is now available to Accounts and Maintenance Cost History.'
        );
    }

    /**
     * Attach or replace the service-provider invoice PDF for one maintenance job.
     *
     * The requester can attach the invoice to their own job. Head/Accounts/
     * Super Admin users with the relevant maintenance permissions may also
     * attach it when necessary.
     */
    public function uploadInvoice(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        if (! $this->canManageInvoice($maintenanceRequest)) {
            abort(403, 'You are not authorised to attach an invoice to this maintenance job.');
        }

        /*
         * Once a paid job already has an invoice, preserve the supporting
         * document as part of the payment audit trail. A paid legacy record
         * with no invoice may still receive its first invoice for backfilling.
         */
        if (
            $maintenanceRequest->payment_status === 'paid'
            && $this->invoiceExists($maintenanceRequest)
        ) {
            return back()->with('error', 'The invoice is locked because this maintenance job has already been paid.');
        }

        $validated = $request->validate([
            'invoice_pdf' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf,application/x-pdf',
                'max:10240',
            ],
        ]);

        $file = $validated['invoice_pdf'];

        $oldValues = [
            'invoice_pdf_path' => $maintenanceRequest->invoice_pdf_path,
            'invoice_original_name' => $maintenanceRequest->invoice_original_name,
            'invoice_uploaded_by' => $maintenanceRequest->invoice_uploaded_by,
            'invoice_uploaded_at' => $maintenanceRequest->invoice_uploaded_at,
        ];

        if ($maintenanceRequest->invoice_pdf_path) {
            Storage::disk('local')->delete($maintenanceRequest->invoice_pdf_path);
        }

        $safeFilename = sprintf(
            'maintenance-job-%06d-invoice-%s.pdf',
            $maintenanceRequest->id,
            now()->format('Ymd_His')
        );

        $storedPath = $file->storeAs(
            'maintenance-job-invoices/' . $maintenanceRequest->id,
            $safeFilename,
            'local'
        );

        $maintenanceRequest->forceFill([
            'invoice_pdf_path' => $storedPath,
            'invoice_original_name' => $file->getClientOriginalName(),
            'invoice_uploaded_by' => auth()->id(),
            'invoice_uploaded_at' => now(),
        ])->save();

        ActivityTrackerService::record(
            $request,
            'Maintenance Invoice',
            'uploaded',
            'Maintenance job invoice PDF attached',
            $maintenanceRequest->invoice_original_name,
            $maintenanceRequest,
            $oldValues,
            [
                'invoice_pdf_path' => $maintenanceRequest->invoice_pdf_path,
                'invoice_original_name' => $maintenanceRequest->invoice_original_name,
                'invoice_uploaded_by' => $maintenanceRequest->invoice_uploaded_by,
                'invoice_uploaded_at' => $maintenanceRequest->invoice_uploaded_at,
            ]
        );

        return back()->with('success', 'Maintenance job invoice PDF attached successfully.');
    }

    /**
     * Securely view/print the original uploaded job invoice.
     *
     * The file remains on Laravel's private local disk and is never exposed as
     * a public storage URL.
     */
    public function invoicePdf(MaintenanceRequest $maintenanceRequest)
    {
        if (! $this->canViewInvoice($maintenanceRequest)) {
            abort(403, 'You are not authorised to view this maintenance invoice.');
        }

        if (! $this->invoiceExists($maintenanceRequest)) {
            abort(404, 'No invoice PDF has been attached to this maintenance job.');
        }

        $fullPath = Storage::disk('local')->path($maintenanceRequest->invoice_pdf_path);

        $downloadName = preg_replace(
            '/[^A-Za-z0-9._ -]/',
            '_',
            (string) ($maintenanceRequest->invoice_original_name ?: 'maintenance-job-invoice.pdf')
        );

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /** Print one completed maintenance job for Payment Voucher attachment. */
    public function jobPaymentPdf(MaintenanceRequest $maintenanceRequest)
    {
        if ($maintenanceRequest->status !== 'completed') {
            abort(403, 'Only completed maintenance jobs can be printed for Accounts/PV processing.');
        }

        $maintenanceRequest->load([
            'asset.assetName',
            'asset.unit',
            'asset.organization',
            'requester.unit',
            'approver',
            'completer',
            'maintenanceProvider',
        ]);

        $pdf = Pdf::loadView('pdf.maintenance-job-payment', [
            'job' => $maintenanceRequest,
            'generatedAt' => now(),
            'currentuser' => auth()->user()?->name ?? 'System',
            'jobReference' => 'MNT-' . str_pad((string) $maintenanceRequest->id, 6, '0', STR_PAD_LEFT),
        ])->setPaper('a4', 'portrait');

        /*
         * PV supporting documents use one centred Coat of Arms.
         * Other GhanaDAMS PDFs retain their existing watermark style.
         */
        PdfWatermark::applyCentered($pdf, true, 140, 0.58);

        return $pdf->stream(
            'maintenance-job-' . str_pad((string) $maintenanceRequest->id, 6, '0', STR_PAD_LEFT) . '-pv-support.pdf'
        );
    }

    public function updatePaymentStatus(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        if ($maintenanceRequest->status !== 'completed') {
            return back()->with('error', 'Accounts can process payment only after the maintenance job has been marked Completed.');
        }

        $validated = $request->validate([
            'payment_status' => ['required', 'in:unpaid,pending,paid'],
            'payment_date' => ['nullable', 'date'],
        ]);

        if (
            in_array($validated['payment_status'], ['pending', 'paid'], true)
            && ! $this->invoiceExists($maintenanceRequest)
        ) {
            return back()->with('error', 'The job invoice PDF is required before Accounts can process payment.');
        }

        $oldValues = [
            'payment_status' => $maintenanceRequest->payment_status,
            'payment_date' => $maintenanceRequest->payment_date,
        ];

        $paymentDate = null;

        if ($validated['payment_status'] === 'paid') {
            $paymentDate = $validated['payment_date'] ?? now();
        }

        $maintenanceRequest->forceFill([
            'payment_status' => $validated['payment_status'],
            'payment_date' => $paymentDate,
        ])->save();

        ActivityTrackerService::record(
            $request,
            'Maintenance Payment',
            'updated',
            'Maintenance payment status updated',
            $maintenanceRequest->fault_description,
            $maintenanceRequest,
            $oldValues,
            [
                'payment_status' => $maintenanceRequest->payment_status,
                'payment_date' => $maintenanceRequest->payment_date,
            ]
        );

        return back()->with('success', 'Payment status updated successfully.');
    }

        public function destroy(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        if ($this->jobIsLocked($maintenanceRequest)) {
            return back()->with('error', 'In Progress or Completed maintenance jobs cannot be deleted.');
        }

        $assetId = (int) $maintenanceRequest->asset_id;
        $oldValues = $maintenanceRequest->toArray();

        $maintenanceRequest->delete();

        $this->syncAssetMaintenanceStatus($assetId);

        ActivityTrackerService::record(
            $request,
            'Maintenance Request',
            'deleted',
            'Maintenance request moved to trash',
            $maintenanceRequest->fault_description,
            $maintenanceRequest,
            $oldValues,
            null
        );

        return back()->with('success', 'Maintenance request moved to trash successfully.');
    }

    private function validateMaintenanceJob(Request $request): array
    {
        return $request->validate([
            'asset_id' => ['required', 'exists:assets,id'],
            'maintenance_provider_id' => ['nullable', 'exists:maintenance_providers,id'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'fault_description' => ['required', 'string', 'max:3000'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'scheduled_date' => ['nullable', 'date'],
            'expected_completion_date' => ['nullable', 'date'],
        ]);
    }

    /**
     * Synchronise asset.status with scheduled OPEN maintenance jobs.
     *
     * Scheduled + open => under_maintenance
     * No scheduled open job => active
     *
     * Obsolete/disposed statuses are never overwritten.
     */
    private function syncAssetMaintenanceStatus(?int $assetId): void
    {
        if (! $assetId) {
            return;
        }

        $asset = Asset::query()->find($assetId);

        if (! $asset) {
            return;
        }

        if (in_array($asset->status, ['obsolete', 'disposed'], true)) {
            return;
        }

        $hasScheduledOpenJob = MaintenanceRequest::query()
            ->where('asset_id', $assetId)
            ->whereNotNull('scheduled_date')
            ->whereIn('status', ['pending', 'approved', 'in_progress'])
            ->exists();

        $targetStatus = $hasScheduledOpenJob
            ? 'under_maintenance'
            : 'active';

        if ($asset->status !== $targetStatus) {
            $asset->forceFill([
                'status' => $targetStatus,
            ])->save();
        }
    }

    private function invoiceExists(MaintenanceRequest $maintenanceRequest): bool
    {
        return (bool) $maintenanceRequest->invoice_pdf_path
            && Storage::disk('local')->exists($maintenanceRequest->invoice_pdf_path);
    }

        private function canManageInvoice(MaintenanceRequest $maintenanceRequest): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((int) $maintenanceRequest->requested_by === (int) $user->id) {
            return true;
        }

        return method_exists($user, 'hasRole')
            && $user->hasRole('Super Admin');
    }

    private function canViewInvoice(MaintenanceRequest $maintenanceRequest): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((int) $maintenanceRequest->requested_by === (int) $user->id) {
            return true;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
            return true;
        }

        foreach ([
            'maintenance-requests.view',
            'view.maintenance-requests',
            'maintenance-requests.complete',
            'complete.maintenance-requests',
            'maintenance-requests.edit',
            'edit.maintenance-requests',
        ] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    private function jobIsLocked(MaintenanceRequest $maintenanceRequest): bool
    {
        return in_array($maintenanceRequest->status, self::LOCKED_JOB_STATUSES, true);
    }
}
