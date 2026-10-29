<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCondition;
use App\Models\AssetLifecycleHistory;
use App\Models\AssetLocation;
use App\Models\AssetName;
use App\Models\AssetRequest;
use App\Models\AssetTransfer;
use App\Models\AssetScanLog;
use App\Models\AssetSource;
use App\Models\Link;
use App\Models\LinkScan;
use App\Models\Location;
use App\Models\MaintenanceProvider;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Source;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityTrackerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TrashController extends Controller
{
    /** @return array<string, class-string<Model>> */
    private function modules(): array
    {
        return [
            'assets' => Asset::class,
            'asset-conditions' => AssetCondition::class,
            'asset-lifecycle-histories' => AssetLifecycleHistory::class,
            'asset-locations' => AssetLocation::class,
            'asset-names' => AssetName::class,
            'asset-requests' => AssetRequest::class,
            'asset-transfers' => AssetTransfer::class,
            'asset-scan-logs' => AssetScanLog::class,
            'asset-sources' => AssetSource::class,
            'links' => Link::class,
            'link-scans' => LinkScan::class,
            'locations' => Location::class,
            'maintenance-providers' => MaintenanceProvider::class,
            'maintenance-requests' => MaintenanceRequest::class,
            'organizations' => Organization::class,
            'permissions' => Permission::class,
            'roles' => Role::class,
            'sources' => Source::class,
            'suppliers' => Supplier::class,
            'units' => Unit::class,
            'users' => User::class,
        ];
    }

    public function index(Request $request): Response
    {
        $module = $request->input('module', 'assets');
        $search = $request->input('search');
        $modelClass = $this->modules()[$module] ?? Asset::class;

        $items = $modelClass::query()
            ->onlyTrashed()
            ->when($search, function ($query, string $search) use ($modelClass): void {
                $fields = collect(['name', 'title', 'request_code', 'asset_name', 'asset_code', 'email', 'unitname', 'organizationname', 'suppliername', 'locationname'])
                    ->filter(fn (string $field): bool => Schema::hasColumn((new $modelClass)->getTable(), $field))
                    ->values();

                if ($fields->isEmpty()) {
                    return;
                }

                $query->where(function ($q) use ($search, $fields): void {
                    foreach ($fields as $field) {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                });
            })
            ->latest('deleted_at')
            ->paginate(50)
            ->through(fn (Model $item): array => [
                'id' => $item->getKey(),
                'label' => $this->label($item),
                'deleted_at' => optional($item->deleted_at)->format('Y-m-d H:i:s'),
                'created_at' => optional($item->created_at)->format('Y-m-d H:i:s'),
            ])
            ->withQueryString();

        return Inertia::render('Trash/Index', [
            'filters' => ['module' => $module, 'search' => $search],
            'modules' => collect($this->modules())->keys()->map(fn ($key) => [
                'key' => $key,
                'label' => Str::headline($key),
            ])->values(),
            'items' => $items,
        ]);
    }

    public function restore(Request $request, string $module, int $id): RedirectResponse
    {
        $model = $this->findTrashedModel($module, $id);
        $model->restore();

        ActivityTrackerService::record(
            $request,
            Str::headline(class_basename($model)),
            'restored',
            Str::headline(class_basename($model)).' Restored',
            Str::headline(class_basename($model)).' restored from trash: '.$this->label($model),
            $model,
            null,
            $model->getAttributes()
        );

        return back()->with('success', 'Record restored successfully.');
    }

    public function forceDelete(Request $request, string $module, int $id): RedirectResponse
    {
        $model = $this->findTrashedModel($module, $id);
        $oldValues = $model->getOriginal();
        $label = $this->label($model);
        $model->forceDelete();

        ActivityTrackerService::record(
            $request,
            Str::headline(class_basename($model)),
            'force_deleted',
            Str::headline(class_basename($model)).' Permanently Deleted',
            Str::headline(class_basename($model)).' permanently deleted: '.$label,
            null,
            $oldValues,
            null
        );

        return back()->with('success', 'Record permanently deleted.');
    }

    private function findTrashedModel(string $module, int $id): Model
    {
        abort_unless(array_key_exists($module, $this->modules()), 404);

        $modelClass = $this->modules()[$module];

        return $modelClass::withTrashed()->whereKey($id)->firstOrFail();
    }

    private function label(Model $model): string
    {
        foreach (['name', 'title', 'request_code', 'asset_name', 'asset_code', 'email', 'unitname', 'organizationname', 'suppliername', 'locationname'] as $field) {
            if (! empty($model->{$field})) {
                return (string) $model->{$field};
            }
        }

        return '#'.$model->getKey();
    }
}
