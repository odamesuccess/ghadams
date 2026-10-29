<?php

namespace App\Http\Controllers;

use App\Support\PdfWatermark;

use App\Models\Asset;
use App\Services\BulkAssetMediaService;
use App\Models\QrReprintHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssetQrCodeController extends Controller
{
    /**
     * GET /assetqrcodes
     */
    public function index(Request $request)
    {
        abort_unless(Auth::user()->can('assetqrcodes.view'), 403);

        $assets = Asset::query()
            ->with([
                'assetName:id,assetname as name',
                'unit:id,name,locationname',
            ])
            ->whereNotNull('qr_code')

            // Unit restriction only for non-super admins
            ->when(! Auth::user()->hasRole('Super Admin'), function ($query) {
                $query->where('unit_id', Auth::user()->unit_id);
            })

            ->when($request->filled('search'), function ($query) use ($request) {
                $search = addcslashes($request->search, '%_\\');

                $query->where(function ($q) use ($search) {
                    $q->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('assetName', function ($qq) use ($search) {
                            $qq->where('assetname', 'like', "%{$search}%");
                        })
                        ->orWhereHas('unit', function ($qq) use ($search) {
                            $qq->where('name', 'like', "%{$search}%")
                                ->orWhere('locationname', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(3000)
            ->withQueryString();

        return inertia('AssetQrCodes/Index', [
            'assets' => $assets,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * POST /assetqrcodes/backfill-existing
     *
     * Backfills media for EXISTING assets only:
     * - generates QR codes when missing;
     * - assigns a representative Asset-Type image when asset_image is missing;
     * - preserves existing uploaded asset photographs.
     */
    public function backfillExistingMedia(Request $request, BulkAssetMediaService $service)
    {
        abort_unless(Auth::user()->hasRole('Super Admin'), 403);

        @set_time_limit(0);

        $stats = $service->backfill(
            forceQr: (bool) $request->boolean('force_qr'),
            chunkSize: 100,
        );

        return back()->with(
            'success',
            'Existing asset media completed: '
                . $stats['qr_generated'] . ' QR code(s) generated and '
                . $stats['images_assigned'] . ' type-based image(s) assigned.'
        );
    }

    /**
     * GET /assetqrcodes/{assetqrcode}/print
     */
    public function print(Asset $assetqrcode)
    {
        abort_unless(Auth::user()->can('assetqrcodes.print'), 403);

        $this->authorizeAssetAccess($assetqrcode);

        abort_if(! $assetqrcode->qr_code, 404, 'This asset has no QR code.');

        QrReprintHistory::create([
            'asset_id' => $assetqrcode->id,
            'printed_by' => Auth::id(),
            'reason' => request()->input('reason', 'QR code printed or reprinted.'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $safeFilename = Str::of($assetqrcode->asset_code)
            ->replace(['/', '\\'], '-')
            ->slug('-');

        $pdf = Pdf::loadView('pdf.asset-qrcode', [
            'asset' => $assetqrcode->load('assetName', 'unit'),
            'currentuser' => Auth::user()->name,
        ])->setPaper('A4', 'portrait');

        PdfWatermark::apply($pdf);

        return $pdf->stream("asset-qrcode-{$safeFilename}.pdf");
    }

    /**
     * DELETE /assetqrcodes/{assetqrcode}
     *
     * Deletes only the QR code image and clears the qr_code column.
     * It does not delete the asset record.
     */
    public function destroy(Asset $assetqrcode)
    {
        abort_unless(Auth::user()->can('assetqrcodes.delete'), 403);

        $this->authorizeAssetAccess($assetqrcode);

        if ($assetqrcode->qr_code && Storage::disk('public')->exists($assetqrcode->qr_code)) {
            Storage::disk('public')->delete($assetqrcode->qr_code);
        }

        $assetqrcode->updateQuietly([
            'qr_code' => null,
        ]);

        return redirect()
            ->route('assetqrcodes.index')
            ->with('success', 'Asset QR code deleted successfully.');
    }

    /**
     * Restrict non-super admins to their own unit assets.
     */
    private function authorizeAssetAccess(Asset $asset): void
    {
        $user = Auth::user();

        if (! $user->hasRole('Super Admin') && (int) $asset->unit_id !== (int) $user->unit_id) {
            abort(403, 'You are not authorized to access this asset QR code.');
        }
    }
}
