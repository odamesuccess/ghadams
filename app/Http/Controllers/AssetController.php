<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetLifecycleHistory;
use App\Models\AssetName;
use App\Models\MaintenanceProvider;
use App\Models\Source;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\ActivityTrackerService;
use App\Services\AssetCodeGenerator;
use App\Services\BulkAssetMediaService;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Label\Margin\Margin;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssetController extends Controller
{
    /* =========================================================
       INDEX
    ========================================================= */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 100000);

        $assets = Asset::query()
            ->with([
                'assetName:id,assetname as name',
                'unit:id,name,locationname',
            ])

            /* SEARCH FILTER */
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = addcslashes(trim($request->search), '%_\\');
                $statusSearch = str_replace(' ', '_', strtolower($request->search));

                $query->where(function ($q) use ($search, $statusSearch) {
                    $q->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$statusSearch}%")
                        ->orWhereHas('assetName', function ($qq) use ($search) {
                            $qq->where('assetname', 'like', "%{$search}%");
                        })
                        ->orWhereHas('unit', function ($qq) use ($search) {
                            $qq->where('name', 'like', "%{$search}%");
                        });
                });
            })

            /* STATUS FILTER */
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })

            /* UNIT FILTER */
            ->when($request->filled('unit_id'), function ($query) use ($request) {
                $query->where('unit_id', $request->integer('unit_id'));
            })

            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Assets/Index', [
            'assets' => $assets,
            'units' => Unit::query()
                ->select('id', 'name', 'locationname')
                ->orderBy('name')
                ->orderBy('locationname')
                ->get(),
            'filters' => $request->only('search', 'status', 'unit_id', 'per_page'),
        ]);
    }

    /* =========================================================
       CREATE
    ========================================================= */
    public function create()
    {
        return inertia('Assets/Create', [
            'assetNames' => AssetName::select('id', 'assetname', 'usefullife')->get(),
            'brands' => AssetName::whereNotNull('brand')
                ->distinct()
                ->orderBy('brand')
                ->pluck('brand'),
            'suppliers' => Supplier::select('id', 'suppliername')->get(),
            'maintenanceProviders' => MaintenanceProvider::select('id', 'providername')->get(),
            'sources' => Source::select('id', 'name')->get(),

            'units' => Unit::select(
                'id',
                'name',
                'locationname',
                'unit_abbreviation',
                'location_abbreviation',
                'organization_id'
            )->get(),
        ]);
    }

    /* =========================================================
       STORE
    ========================================================= */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'assetname_id' => ['required', 'exists:asset_names,id'],
            'brand' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', 'exists:units,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'maintenance_id' => ['required', 'exists:maintenance_providers,id'],
            'source_id' => ['required', 'exists:sources,id'],
            'purchase_cost' => ['nullable', 'numeric'],
            'purchase_date' => ['required', 'date'],
            'warranty_duration' => ['nullable', 'integer'],
            'warranty_end_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'serial_number' => ['nullable', 'string'],
            'model_number' => ['nullable', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,under_maintenance,obsolete,disposed'],
            'asset_image' => ['nullable', 'image', 'max:2048'],
        ]);

        DB::transaction(function () use ($request, &$validated) {
            $user = Auth::user();

            $unit = Unit::findOrFail($validated['unit_id']);
            $assetName = AssetName::findOrFail($validated['assetname_id']);

            /* ===============================
               ASSET CODE
            =============================== */
            $validated['asset_code'] = AssetCodeGenerator::generate(
                $assetName->assetname_abbreviation,
                $unit->location_abbreviation,
                $validated['purchase_date'],
                $unit->unit_abbreviation,
                $unit->id
            );

            /* ===============================
               ASSET IMAGE
            =============================== */
            if ($request->hasFile('asset_image')) {
                $fileName = Str::slug($assetName->assetname)
                    . '-'
                    . time()
                    . '.'
                    . $request->file('asset_image')->getClientOriginalExtension();

                $validated['asset_image'] = $request->file('asset_image')
                    ->storeAs('assets', $fileName, 'public');
            }

            $validated['user_id'] = $user->id;
            $validated['organization_id'] = $unit->organization_id;

            /* ===============================
               CREATE ASSET
            =============================== */
            $asset = Asset::create($validated);

            /* ===============================
               GENERATE QR CODE
            =============================== */
            $this->generateAssetQrCode($asset);

            // If no actual photograph was uploaded, use the locally-cached
            // representative REAL photograph for this Asset Type.
            if (blank($asset->asset_image)) {
                app(BulkAssetMediaService::class)
                    ->assignCachedTypePhoto($asset->fresh('assetName'));
            }
        });

        return redirect()
            ->route('assets.index')
            ->with('success', 'Asset created successfully.');
    }

    /* =========================================================
       SHOW
    ========================================================= */
    public function show(Asset $asset)
    {
        $asset->loadMissing([
            'assetName',
            'unit',
            'supplier',
            'maintenanceProvider',
            'source',
            'organization',
            'user',
            'lifecycleHistories.changedBy',
            'maintenanceRequests.requester',
            'maintenanceRequests.maintenanceProvider',
            'approvalRequests.requester',
            'scanLogs.scannedBy',
        ]);

        return inertia('Assets/Show', [
            'asset' => $asset,
        ]);
    }

    /* =========================================================
       EDIT
    ========================================================= */
    public function edit(Asset $asset)
    {
        return inertia('Assets/Edit', [
            'asset' => $asset->load(['assetName', 'unit']),
            'assetNames' => AssetName::select('id', 'assetname', 'usefullife')->get(),
            'brands' => AssetName::whereNotNull('brand')
                ->distinct()
                ->orderBy('brand')
                ->pluck('brand'),
            'units' => Unit::select(
                'id',
                'name',
                'locationname',
                'unit_abbreviation',
                'location_abbreviation',
                'organization_id'
            )->get(),
            'suppliers' => Supplier::select('id', 'suppliername')->get(),
            'maintenanceProviders' => MaintenanceProvider::select('id', 'providername')->get(),
            'sources' => Source::select('id', 'name')->get(),
        ]);
    }

    /* =========================================================
       UPDATE
    ========================================================= */
    public function update(Request $request, Asset $asset)
    {
        $validated = $request->validate([
            'assetname_id' => ['required', 'exists:asset_names,id'],
            'brand' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', 'exists:units,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'maintenance_id' => ['required', 'exists:maintenance_providers,id'],
            'source_id' => ['required', 'exists:sources,id'],
            'purchase_cost' => ['nullable', 'numeric'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_duration' => ['nullable', 'integer'],
            'warranty_end_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'serial_number' => ['nullable', 'string'],
            'model_number' => ['nullable', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,under_maintenance,obsolete,disposed'],
            'asset_image' => ['nullable', 'image', 'max:2048'],
        ]);

        $oldStatus = $asset->status;
        $regenerateQrCode = false;

        DB::transaction(function () use ($request, $asset, &$validated, $oldStatus, &$regenerateQrCode) {
            /*
            =========================================================
            1. REGENERATE ASSET CODE ONLY IF UNIT CHANGES
            =========================================================
            */
            if ((int) $validated['unit_id'] !== (int) $asset->unit_id) {
                $unit = Unit::findOrFail($validated['unit_id']);
                $assetName = AssetName::findOrFail($validated['assetname_id']);

                $validated['asset_code'] = AssetCodeGenerator::generate(
                    $assetName->assetname_abbreviation,
                    $unit->location_abbreviation,
                    $validated['purchase_date'] ?? $asset->purchase_date,
                    $unit->unit_abbreviation,
                    $unit->id
                );

                $regenerateQrCode = true;
            }

            /*
            =========================================================
            2. HANDLE IMAGE REPLACEMENT
            =========================================================
            */
            if ($request->hasFile('asset_image')) {
                $assetName = AssetName::findOrFail($validated['assetname_id']);

                $fileName = Str::slug($assetName->assetname)
                    . '-'
                    . $asset->id
                    . '-'
                    . time()
                    . '.'
                    . $request->file('asset_image')->getClientOriginalExtension();

                $path = $request->file('asset_image')
                    ->storeAs('assets', $fileName, 'public');

                $mediaService = app(BulkAssetMediaService::class);

                if (
                    $asset->asset_image
                    && ! $mediaService->isManagedTypePhotoPath($asset->asset_image)
                    && Storage::disk('public')->exists($asset->asset_image)
                ) {
                    Storage::disk('public')->delete($asset->asset_image);
                }

                $validated['asset_image'] = $path;
            } else {
                unset($validated['asset_image']);
            }

            /*
            =========================================================
            3. UPDATE ASSET
            =========================================================
            */
            $asset->update($validated);

            if (! $request->hasFile('asset_image')) {
                app(BulkAssetMediaService::class)
                    ->assignCachedTypePhoto($asset->fresh('assetName'));
            }

            /*
            =========================================================
            4. STATUS LIFECYCLE HISTORY
            =========================================================
            */
            if (($validated['status'] ?? null) && $oldStatus !== $validated['status']) {
                AssetLifecycleHistory::create([
                    'asset_id' => $asset->id,
                    'changed_by' => auth()->id(),
                    'old_status' => $oldStatus,
                    'new_status' => $validated['status'],
                    'reason' => 'Status updated from asset edit form.',
                    'changed_at' => now(),
                ]);

                ActivityTrackerService::record(
                    $request,
                    'Asset Lifecycle',
                    'status_changed',
                    'Asset status changed',
                    'Asset ' . $asset->asset_code . ' changed from ' . $oldStatus . ' to ' . $validated['status'],
                    $asset,
                    ['status' => $oldStatus],
                    ['status' => $validated['status']]
                );
            }

            /*
            =========================================================
            5. REGENERATE QR CODE IF ASSET CODE CHANGED
            =========================================================
            */
            if ($regenerateQrCode) {
                $this->generateAssetQrCode($asset->fresh());
            }
        });

        return redirect()
            ->route('assets.index')
            ->with('success', 'Asset updated successfully.');
    }

    /* =========================================================
       DESTROY
    ========================================================= */
    public function destroy(Asset $asset)
    {
        $user = Auth::user();

        if (! $user->hasRole('Super Admin') && $asset->unit_id !== $user->unit_id) {
            abort(403, 'You are not authorized to delete this asset.');
        }

        $mediaService = app(BulkAssetMediaService::class);

        if (
            $asset->asset_image
            && ! $mediaService->isManagedTypePhotoPath($asset->asset_image)
            && Storage::disk('public')->exists($asset->asset_image)
        ) {
            Storage::disk('public')->delete($asset->asset_image);
        }

        $asset->delete();

        return back()->with('success', 'Asset deleted successfully.');
    }

    /* =========================================================
       GENERATE ASSET QR CODE
    ========================================================= */
    private function generateAssetQrCode(Asset $asset): void
    {
        $qrDir = 'assets/qrcodes';
        $qrPath = "{$qrDir}/asset-{$asset->id}.png";

        Storage::disk('public')->makeDirectory($qrDir);

        $scanUrl = route('assets.scan', $asset->uuid);

        $qrCode = new QrCode(
            data: $scanUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 900,
            margin: 12
        );

        $label = new Label(
            text: $asset->asset_code,
            margin: new Margin(6, 0, 12, 0),
            font: new OpenSans(40)
        );

        $writer = new PngWriter();

        $logo = $this->getValidQrLogo();

        if ($logo) {
            $result = $writer->write($qrCode, $logo, $label);
        } else {
            $result = $writer->write($qrCode, null, $label);
        }

        Storage::disk('public')->put($qrPath, $result->getString());

        $asset->updateQuietly([
            'qr_code' => $qrPath,
        ]);
    }

    /* =========================================================
       GET VALID QR LOGO
    ========================================================= */
    private function getValidQrLogo(): ?Logo
    {
        $logoPaths = [
            public_path('images/ghadams_logo.jpg'),
            public_path('images/ghadams_logo.png'),
            public_path('applogo.jpg'),
            public_path('applogo.png'),
            storage_path('app/public/logo/ghadams_logo.jpg'),
            storage_path('app/public/logo/ghadams_logo.png'),
        ];

        foreach ($logoPaths as $logoPath) {
            if (! file_exists($logoPath)) {
                continue;
            }

            if (! is_file($logoPath)) {
                continue;
            }

            if (@getimagesize($logoPath) === false) {
                continue;
            }

            return new Logo(
                path: $logoPath,
                resizeToWidth: 150
            );
        }

        return null;
    }
}
