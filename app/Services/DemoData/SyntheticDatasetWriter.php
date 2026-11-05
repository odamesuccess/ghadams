<?php

namespace App\Services\DemoData;

use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\User;
use App\Services\AssetCodeGenerator;
use App\Services\DemoAssetQrService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Writes, replaces and removes the synthetic demo dataset.
 *
 * Every created row is recorded in `demo_dataset_records`; replace/remove only
 * ever touch rows listed there.
 */
class SyntheticDatasetWriter
{
    public const REGISTRY = 'demo_dataset_records';

    public const TYPE_PHOTO_MANIFEST = 'assets/type-images/SOURCES.json';

    private const CHUNK = 500;

    private array $columns = [];

    public function __construct(private array $config) {}

    public static function make(): self
    {
        return new self((array) config('demo_dataset'));
    }

    public function config(): array
    {
        return $this->config;
    }

    public function dataset(): string
    {
        return (string) $this->config['dataset'];
    }

    /* =========================================================
       CONTEXT
    ========================================================= */

    public function loadContext(): array
    {
        $types = [];

        foreach (DB::table('asset_names')->whereNull('deleted_at')
            ->whereIn('assetname_abbreviation', array_keys($this->config['types']))
            ->orderBy('id')->get() as $row) {
            $types[$row->assetname_abbreviation] ??= [
                'id' => (int) $row->id,
                'name' => $row->assetname,
                'life' => (int) ($row->usefullife ?: 5),
            ];
        }

        $gesId = DB::table('organizations')->whereNull('deleted_at')
            ->where('organization_abbreviation', 'GES')->value('id');

        $units = [];

        foreach (DB::table('units')->whereNull('deleted_at')
            ->when($gesId, fn ($q) => $q->where('organization_id', $gesId), fn ($q) => $q->whereNotNull('organization_id'))
            ->orderBy('id')->get() as $row) {
            $units[$row->unit_abbreviation] ??= [
                'id' => (int) $row->id,
                'name' => $row->name,
                'location' => $row->location_abbreviation,
                'location_name' => $row->locationname,
                'organization_id' => (int) $row->organization_id,
            ];
        }

        return [
            'types' => $types,
            'units' => $units,
            'suppliers' => DB::table('suppliers')->whereNull('deleted_at')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'providers' => DB::table('maintenance_providers')->whereNull('deleted_at')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'sources' => DB::table('sources')->whereNull('deleted_at')->orderBy('id')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all(),
            'images' => $this->typeImages($types),
        ];
    }

    /** Representative type photographs from the licensed local catalogue. */
    public function typeImages(array $types): array
    {
        $disk = Storage::disk('public');
        $catalogue = $disk->exists(self::TYPE_PHOTO_MANIFEST)
            ? (array) (json_decode((string) $disk->get(self::TYPE_PHOTO_MANIFEST), true)['types'] ?? [])
            : [];
        $images = [];

        foreach ($types as $abbr => $type) {
            $path = $catalogue[$type['name']]['local_path'] ?? null;
            $images[$abbr] = is_string($path) && $disk->exists($path) ? $path : null;
        }

        return $images;
    }

    public function plan(?array $context = null): array
    {
        return (new SyntheticDatasetPlanner($this->config, $context ?? $this->loadContext()))->build();
    }

    /* =========================================================
       OWNERSHIP
    ========================================================= */

    public function registryExists(): bool
    {
        return Schema::hasTable(self::REGISTRY);
    }

    /** @return array<string, int> record_type => count */
    public function ownedCounts(): array
    {
        if (! $this->registryExists()) {
            return [];
        }

        return DB::table(self::REGISTRY)->where('dataset', $this->dataset())
            ->selectRaw('record_type, count(*) as c')->groupBy('record_type')
            ->pluck('c', 'record_type')->map(fn ($c) => (int) $c)->all();
    }

    /** @return array<int, int> */
    public function ownedIds(string $type): array
    {
        if (! $this->registryExists()) {
            return [];
        }

        return DB::table(self::REGISTRY)->where('dataset', $this->dataset())->where('record_type', $type)
            ->orderBy('record_id')->pluck('record_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Rows NOT owned by the dataset that point at owned rows. Replacing or
     * removing the dataset would break or delete them, so they block it.
     *
     * @return array<int, string>
     */
    public function blockingReferences(): array
    {
        $assets = $this->ownedIds('asset');
        $users = $this->ownedIds('user');
        $owned = [
            'maintenance_requests' => $this->ownedIds('maintenance_request'),
            'asset_transfers' => $this->ownedIds('asset_transfer'),
            'asset_lifecycle_histories' => $this->ownedIds('asset_lifecycle_history'),
            'assets' => $assets,
        ];

        $checks = [
            ['asset_suggestions', 'asset_id', $assets],
            ['asset_scan_logs', 'asset_id', $assets],
            ['maintenance_requests', 'asset_id', $assets],
            ['asset_transfers', 'asset_id', $assets],
            ['asset_lifecycle_histories', 'asset_id', $assets],
            ['approval_requests', 'asset_id', $assets],
            ['asset_requests', 'asset_id', $assets],
            ['qr_reprint_histories', 'asset_id', $assets],
            ['assets', 'user_id', $users],
            ['maintenance_requests', 'requested_by', $users],
            ['maintenance_requests', 'approved_by', $users],
            ['maintenance_requests', 'completed_by', $users],
            ['asset_transfers', 'from_user_id', $users],
            ['asset_transfers', 'to_user_id', $users],
            ['asset_transfers', 'requested_by', $users],
            ['asset_transfers', 'approved_by', $users],
        ];

        $problems = [];

        foreach ($checks as [$table, $column, $ids]) {
            if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $count = 0;

            foreach (array_chunk($ids, 900) as $chunk) {
                $count += DB::table($table)->whereIn($column, $chunk)
                    ->when(isset($owned[$table]) && $owned[$table] !== [], function ($q) use ($table, $owned) {
                        foreach (array_chunk($owned[$table], 900) as $ownedChunk) {
                            $q->whereNotIn('id', $ownedChunk);
                        }
                    })
                    ->count();
            }

            if ($count > 0) {
                $problems[] = "{$count} row(s) in {$table}.{$column} were not created by the demo dataset but reference it.";
            }
        }

        return $problems;
    }

    /** Planned identifiers that already exist on rows the dataset does not own. */
    public function collisions(array $plan): array
    {
        $ownedAssets = $this->ownedIds('asset');
        $ownedUsers = $this->ownedIds('user');
        $problems = [];

        foreach ([
            ['assets', 'serial_number', array_column($plan['assets'], 'serial_number'), $ownedAssets],
            ['users', 'email', array_column($plan['users'], 'email'), $ownedUsers],
        ] as [$table, $column, $values, $ownedIds]) {
            $count = 0;

            foreach (array_chunk($values, 900) as $chunk) {
                $count += DB::table($table)->whereIn($column, $chunk)
                    ->when($ownedIds !== [], fn ($q) => $q->whereNotIn('id', $ownedIds))
                    ->count();
            }

            if ($count > 0) {
                $problems[] = "{$count} planned {$table}.{$column} value(s) already exist on rows outside the demo dataset.";
            }
        }

        return $problems;
    }

    /* =========================================================
       REMOVE
    ========================================================= */

    /** @return array<string, int> */
    public function remove(): array
    {
        $removed = [];

        DB::transaction(function () use (&$removed): void {
            $removed['audit_log'] = $this->deleteOwned('audit_log', 'audit_logs');
            $removed['asset_lifecycle_history'] = $this->deleteOwned('asset_lifecycle_history', 'asset_lifecycle_histories');
            $removed['asset_transfer'] = $this->deleteOwned('asset_transfer', 'asset_transfers');

            $maintenanceIds = $this->ownedIds('maintenance_request');
            foreach ($maintenanceIds as $id) {
                Storage::disk('local')->deleteDirectory("maintenance-job-invoices/{$id}");
            }
            $removed['maintenance_request'] = $this->deleteOwned('maintenance_request', 'maintenance_requests');

            foreach (array_chunk($this->ownedIds('asset'), self::CHUNK) as $chunk) {
                foreach (DB::table('assets')->whereIn('id', $chunk)->pluck('qr_code', 'id') as $id => $path) {
                    // Only the asset's own QR file. Type photos are shared and never deleted.
                    if ($path === "assets/qrcodes/asset-{$id}.png") {
                        Storage::disk('public')->delete($path);
                    }
                }
            }
            $removed['asset'] = $this->deleteOwned('asset', 'assets');

            $userIds = $this->ownedIds('user');
            if ($userIds !== [] && Schema::hasTable('model_has_roles')) {
                DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $userIds)->delete();
            }
            $removed['user'] = $this->deleteOwned('user', 'users');
            $removed['asset_condition'] = $this->deleteOwned('asset_condition', 'asset_conditions');
            $removed['asset_location'] = $this->deleteOwned('asset_location', 'asset_locations');

            DB::table(self::REGISTRY)->where('dataset', $this->dataset())->delete();
        });

        $this->forgetPermissionCache();

        return array_filter($removed);
    }

    private function deleteOwned(string $type, string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $deleted = 0;

        foreach (array_chunk($this->ownedIds($type), self::CHUNK) as $chunk) {
            $deleted += DB::table($table)->whereIn('id', $chunk)->delete();
        }

        return $deleted;
    }

    /* =========================================================
       WRITE
    ========================================================= */

    /** @return array<string, int> */
    public function write(array $plan, ?callable $progress = null): array
    {
        $progress ??= fn () => null;
        $now = now()->format('Y-m-d H:i:s');
        $registry = [];
        $counts = [];
        $invoices = [];

        DB::transaction(function () use ($plan, $now, $progress, &$registry, &$counts, &$invoices): void {
            // ---- People -------------------------------------------------
            $passwordHash = Hash::make(Str::random(64)); // never disclosed; sign-in is also disabled
            $userIds = [];
            $roleIds = Schema::hasTable('roles')
                ? DB::table('roles')->where('guard_name', 'web')->pluck('id', 'name')->all()
                : [];

            foreach ($plan['users'] as $key => $user) {
                $userIds[$key] = DB::table('users')->insertGetId($this->only('users', [
                    'unit_id' => $user['unit_id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'password' => $passwordHash,
                    'user_status' => 'Inactive',
                    'deactivation_reason' => 'Synthetic demonstration record; sign-in disabled.',
                    'deactivated_at' => $now,
                    'force_password_change' => 1,
                    'created_at' => $this->config['start_date'].' 08:00:00',
                    'updated_at' => $now,
                ]));
                $registry[] = $this->registryRow('user', $userIds[$key], null, ['key' => $key, 'title' => $user['title'], 'role' => $user['role']]);

                if ($user['role'] && isset($roleIds[$user['role']]) && Schema::hasTable('model_has_roles')) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $roleIds[$user['role']],
                        'model_type' => User::class,
                        'model_id' => $userIds[$key],
                    ]);
                }
            }
            $counts['users'] = count($userIds);
            $progress('users', $counts['users']);

            $legacy = $this->legacyAssetColumns($registry);

            // ---- Assets -------------------------------------------------
            $assetIds = [];
            $initialUser = [];

            foreach ($plan['transfers'] as $transfer) {
                $initialUser[$transfer['asset']] ??= $transfer['from_user'];
            }

            foreach ($plan['assets'] as $index => $asset) {
                // Official GES code, numbered after everything already inserted.
                $asset['asset_code'] = $plan['assets'][$index]['asset_code'] = $this->officialCode(
                    $asset['type'], (int) $asset['initial_unit_id'], $asset['purchase_date']
                );

                $assetIds[$index] = DB::table('assets')->insertGetId($this->only('assets', $legacy + [
                    'uuid' => (string) Str::uuid(),
                    'scan_token' => (string) Str::uuid(),
                    'asset_code' => $asset['asset_code'],
                    'assetname_id' => $asset['type_id'],
                    'brand' => $asset['brand'],
                    'model_number' => $asset['model_number'],
                    'part_number' => $asset['part_number'],
                    'source_id' => $asset['source_id'],
                    'supplier_id' => $asset['supplier_id'],
                    'maintenance_id' => $asset['maintenance_id'],
                    'purchase_cost' => $asset['purchase_cost'],
                    'purchase_date' => $asset['purchase_date'],
                    'warranty_duration' => $asset['warranty_duration'],
                    'warranty_end_date' => $asset['warranty_end_date'],
                    'description' => $asset['description'],
                    'serial_number' => $asset['serial_number'],
                    'asset_image' => $asset['asset_image'],
                    'qr_code' => null,
                    'user_id' => $userIds[$asset['user_key']],
                    'unit_id' => $asset['unit_id'],
                    'organization_id' => $asset['organization_id'],
                    'status' => $asset['status'],
                    'created_at' => $asset['created_at'],
                    'updated_at' => $asset['updated_at'],
                ]));

                $registry[] = $this->registryRow('asset', $assetIds[$index], $asset['batch_ref'], [
                    'po' => $asset['po'],
                    'batch_profile' => $plan['batches'][$asset['batch_ref']]['profile'],
                    'initial_unit_id' => $asset['initial_unit_id'],
                    'initial_user_id' => $userIds[$initialUser[$index] ?? $asset['user_key']],
                    'image' => $asset['asset_image'] ? 'representative type photograph (not this exact unit)' : 'missing',
                ]);

                if ($index % 250 === 249) {
                    $progress('assets', $index + 1);
                }
            }
            $counts['assets'] = count($assetIds);

            // ---- Maintenance --------------------------------------------
            foreach ($plan['maintenance'] as $job) {
                $id = DB::table('maintenance_requests')->insertGetId($this->only('maintenance_requests', [
                    'asset_id' => $assetIds[$job['asset']],
                    'requested_by' => $userIds[$job['requested_by']],
                    'approved_by' => $job['approved_by'] ? $userIds[$job['approved_by']] : null,
                    'completed_by' => $job['completed_by'] ? $userIds[$job['completed_by']] : null,
                    'maintenance_provider_id' => $job['maintenance_provider_id'],
                    'status' => $job['status'],
                    'priority' => $job['priority'],
                    'fault_description' => $job['fault_description'],
                    'estimated_cost' => $job['estimated_cost'],
                    'actual_cost' => $job['actual_cost'],
                    'payment_status' => $job['payment_status'],
                    'payment_date' => $job['payment_date'],
                    'scheduled_date' => $job['scheduled_date'],
                    'expected_completion_date' => $job['expected_completion_date'],
                    'actual_completion_date' => $job['actual_completion_date'],
                    'approval_comment' => $job['approval_comment'],
                    'completion_note' => $job['completion_note'],
                    'created_at' => $job['created_at'],
                    'updated_at' => $job['updated_at'],
                ]));

                if ($job['invoice']) {
                    $path = "maintenance-job-invoices/{$id}/{$job['invoice']['ref']}.pdf";
                    DB::table('maintenance_requests')->where('id', $id)->update($this->only('maintenance_requests', [
                        'invoice_pdf_path' => $path,
                        'invoice_original_name' => $job['invoice']['ref'].'.pdf',
                        'invoice_uploaded_by' => $userIds[$job['requested_by']],
                        'invoice_uploaded_at' => $job['invoice']['uploaded_at'],
                    ]));
                    $invoices[] = [$path, $job, $plan['assets'][$job['asset']], $id];
                }

                $registry[] = $this->registryRow('maintenance_request', $id, null, [
                    'scenario' => $job['kind'] === 'completed' ? 'completed_'.$job['payment_status'] : $job['kind'],
                    'invoice_ref' => $job['invoice']['ref'] ?? null,
                    'invoice_date' => $job['invoice']['date'] ?? null,
                    'payment_ref' => $job['payment_ref'],
                    'amount_paid' => $job['payment_status'] === 'paid' ? $job['actual_cost'] : 0,
                    'outstanding' => $job['status'] === 'completed' && $job['payment_status'] !== 'paid' ? $job['actual_cost'] : 0,
                ]);
            }
            $counts['maintenance_requests'] = count($plan['maintenance']);
            $progress('maintenance_requests', $counts['maintenance_requests']);

            // ---- Transfers ----------------------------------------------
            $transferIds = [];

            foreach ($plan['transfers'] as $t => $transfer) {
                $transferIds[$t] = DB::table('asset_transfers')->insertGetId($this->only('asset_transfers', [
                    'transfer_code' => $transfer['transfer_code'],
                    'asset_id' => $assetIds[$transfer['asset']],
                    'from_unit_id' => $transfer['from_unit_id'],
                    'to_unit_id' => $transfer['to_unit_id'],
                    'from_user_id' => $userIds[$transfer['from_user']],
                    'to_user_id' => $userIds[$transfer['to_user']],
                    'requested_by' => $userIds[$transfer['requested_by']],
                    'approved_by' => $transfer['approved_by'] ? $userIds[$transfer['approved_by']] : null,
                    'completed_by' => $transfer['completed_by'] ? $userIds[$transfer['completed_by']] : null,
                    'reason' => $transfer['reason'],
                    'remarks' => $transfer['remarks'],
                    'status' => $transfer['status'],
                    'requested_at' => $transfer['requested_at'],
                    'approved_at' => $transfer['approved_at'],
                    'completed_at' => $transfer['completed_at'],
                    'rejected_at' => $transfer['rejected_at'],
                    'created_at' => $transfer['requested_at'],
                    'updated_at' => $transfer['completed_at'] ?? $transfer['rejected_at'] ?? $transfer['approved_at'] ?? $transfer['requested_at'],
                ]));
                $registry[] = $this->registryRow('asset_transfer', $transferIds[$t], null, ['scenario' => $transfer['kind']]);
            }
            $counts['asset_transfers'] = count($transferIds);
            $progress('asset_transfers', $counts['asset_transfers']);

            // ---- Lifecycle history --------------------------------------
            foreach ($plan['lifecycle'] as $event) {
                $metadata = $event['metadata'] + ['synthetic' => true, 'dataset' => $this->dataset()];
                $id = DB::table('asset_lifecycle_histories')->insertGetId($this->only('asset_lifecycle_histories', [
                    'asset_id' => $assetIds[$event['asset']],
                    'changed_by' => $userIds[$event['changed_by']],
                    'old_status' => $event['old_status'],
                    'new_status' => $event['new_status'],
                    'reason' => $event['reason'],
                    'metadata' => json_encode($metadata),
                    'changed_at' => $event['changed_at'],
                    'created_at' => $event['changed_at'],
                    'updated_at' => $event['changed_at'],
                ]));
                $registry[] = $this->registryRow('asset_lifecycle_history', $id, null, ['action' => $event['metadata']['action']]);
            }
            $counts['asset_lifecycle_histories'] = count($plan['lifecycle']);

            // ---- Audit trail for transfer decisions ---------------------
            foreach ($plan['audit'] as $entry) {
                $transfer = $plan['transfers'][$entry['transfer']];
                $isAsset = $entry['auditable'] === 'asset';
                $id = DB::table('audit_logs')->insertGetId($this->only('audit_logs', [
                    'user_id' => $userIds[$entry['user']],
                    'module' => $entry['module'],
                    'action' => $entry['action'],
                    'auditable_type' => $isAsset ? Asset::class : AssetTransfer::class,
                    'auditable_id' => $isAsset ? $assetIds[$entry['asset']] : $transferIds[$entry['transfer']],
                    'title' => $entry['title'],
                    'description' => $isAsset ? $plan['assets'][$entry['asset']]['asset_code'] : $transfer['transfer_code'],
                    'new_values' => json_encode(['status' => $isAsset ? null : $entry['action'], 'transfer_code' => $transfer['transfer_code']]),
                    'properties' => json_encode(['synthetic' => true, 'dataset' => $this->dataset(), 'version' => $this->config['version']]),
                    'user_agent' => 'GhanaDAMS synthetic demo generator',
                    'created_at' => $entry['at'],
                    'updated_at' => $entry['at'],
                ]));
                $registry[] = $this->registryRow('audit_log', $id, null, null);
            }
            $counts['audit_logs'] = count($plan['audit']);

            foreach (array_chunk($registry, self::CHUNK) as $chunk) {
                DB::table(self::REGISTRY)->insert($chunk);
            }
        });

        foreach ($invoices as [$path, $job, $asset, $id]) {
            Storage::disk('local')->put($path, SyntheticInvoicePdf::render($job, $asset, $id));
        }
        $counts['invoice_pdfs'] = count($invoices);

        $this->forgetPermissionCache();

        return $counts;
    }

    /**
     * The migrations still define assets.asset_condition_id / asset_location_id
     * (NOT NULL). Some databases dropped them; fill them only when present.
     */
    private function legacyAssetColumns(array &$registry): array
    {
        $values = [];

        foreach ([['asset_condition_id', 'asset_conditions', 'asset_condition', ['conditionname' => 'Synthetic demo condition (unspecified)']],
            ['asset_location_id', 'asset_locations', 'asset_location', ['locationname' => 'Synthetic demo location (see unit)', 'Officenumber' => 'N/A']]] as [$column, $table, $type, $row]) {
            if (! Schema::hasColumn('assets', $column)) {
                continue;
            }

            $id = DB::table($table)->orderBy('id')->value('id');

            if (! $id) {
                $id = DB::table($table)->insertGetId($row + ['created_at' => now(), 'updated_at' => now()]);
                $registry[] = $this->registryRow($type, $id, null, null);
            }

            $values[$column] = $id;
        }

        return $values;
    }

    private function registryRow(string $type, int $id, ?string $batch, ?array $meta): array
    {
        $now = now()->format('Y-m-d H:i:s');

        return [
            'dataset' => $this->dataset(),
            'record_type' => $type,
            'record_id' => $id,
            'batch_ref' => $batch,
            'meta' => $meta === null ? null : json_encode($meta),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /** Keep only columns the table actually has (schemas differ between databases). */
    private function only(string $table, array $row): array
    {
        $this->columns[$table] ??= array_flip(Schema::getColumnListing($table));

        return array_intersect_key($row, $this->columns[$table]);
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /* =========================================================
       ASSET CODES
    ========================================================= */

    /** @var array<int, object> */
    private array $unitCache = [];

    /** The official GES code for an asset registered in this unit on this date. */
    private function officialCode(string $typeAbbreviation, int $unitId, string $purchaseDate, array $excludeAssetIds = []): string
    {
        $unit = $this->unitCache[$unitId] ??= DB::table('units')->find($unitId)
            ?? throw new RuntimeException("Unit {$unitId} not found.");

        return AssetCodeGenerator::generate(
            $typeAbbreviation,
            (string) $unit->location_abbreviation,
            $purchaseDate,
            (string) $unit->unit_abbreviation,
            $unitId,
            $excludeAssetIds,
        );
    }

    /**
     * Give the dataset's own assets official GES codes, in purchase-date order, keeping each
     * asset's id, UUID and scan token. Only rows listed in the registry are touched; codes of
     * every other asset are left exactly as they are. Audit-log text that quotes an old code
     * is updated too. Returns [asset id => [old, new]] (planned only, unless $apply).
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function recodeAssets(bool $apply): array
    {
        $ids = $this->ownedIds('asset');

        if ($ids === []) {
            throw new RuntimeException('No synthetic assets found to recode.');
        }

        $changes = [];

        // A preview runs the same writes and rolls them back, so it shows exactly the codes
        // a real run would assign.
        DB::beginTransaction();

        try {
            $initialUnits = DB::table(self::REGISTRY)
                ->where('dataset', $this->dataset())
                ->where('record_type', 'asset')
                ->pluck('meta', 'record_id')
                ->map(fn ($meta) => (int) (json_decode((string) $meta, true)['initial_unit_id'] ?? 0));

            $assets = DB::table('assets')
                ->join('asset_names', 'asset_names.id', '=', 'assets.assetname_id')
                ->whereIn('assets.id', $ids)
                ->orderBy('assets.purchase_date')
                ->orderBy('assets.id')
                ->lockForUpdate()
                ->get(['assets.id', 'assets.asset_code', 'assets.unit_id', 'assets.purchase_date', 'asset_names.assetname_abbreviation']);

            // Park the current codes first, so no new code clashes with one still held by a
            // synthetic asset (the same two-pass approach as assets:rebuild-codes).
            foreach ($assets as $asset) {
                DB::table('assets')->where('id', $asset->id)->update(['asset_code' => '__GHANADAMS_RECODE_'.$asset->id.'__']);
            }

            // Assets not yet recoded are left out of the numbering.
            $pending = array_fill_keys($ids, true);

            foreach ($assets as $asset) {
                unset($pending[$asset->id]);
                $unitId = $initialUnits[$asset->id] ?: (int) $asset->unit_id;

                $code = $this->officialCode(
                    (string) $asset->assetname_abbreviation,
                    $unitId,
                    (string) $asset->purchase_date,
                    [...array_keys($pending), (int) $asset->id],
                );

                $changes[$asset->id] = [$asset->asset_code, $code];
                DB::table('assets')->where('id', $asset->id)->update(['asset_code' => $code]);
            }

            if ($apply) {
                foreach (array_chunk($this->ownedIds('audit_log'), self::CHUNK) as $chunk) {
                    foreach (DB::table('audit_logs')->whereIn('id', $chunk)
                        ->where('auditable_type', Asset::class)
                        ->whereIn('auditable_id', array_keys($changes))
                        ->get(['id', 'auditable_id', 'description']) as $log) {
                        [$old, $new] = $changes[$log->auditable_id];

                        if (str_contains((string) $log->description, $old)) {
                            DB::table('audit_logs')->where('id', $log->id)
                                ->update(['description' => str_replace($old, $new, (string) $log->description)]);
                        }
                    }
                }
            }

            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if ($apply) {
            $this->recodeInvoicePdfs($changes);
        }

        return $changes;
    }

    /** Synthetic invoice PDFs print the asset code: swap in the new one. Returns files changed. */
    private function recodeInvoicePdfs(array $changes): int
    {
        $disk = Storage::disk('local');
        $updated = 0;

        foreach (array_chunk($this->ownedIds('maintenance_request'), self::CHUNK) as $chunk) {
            foreach (DB::table('maintenance_requests')->whereIn('id', $chunk)
                ->whereNotNull('invoice_pdf_path')->get(['asset_id', 'invoice_pdf_path']) as $job) {
                if (! isset($changes[$job->asset_id]) || ! $disk->exists($job->invoice_pdf_path)) {
                    continue;
                }

                [$old, $new] = $changes[$job->asset_id];
                $pdf = SyntheticInvoicePdf::replaceAssetCode((string) $disk->get($job->invoice_pdf_path), $old, $new);

                if ($pdf !== null) {
                    $disk->put($job->invoice_pdf_path, $pdf);
                    $updated++;
                }
            }
        }

        return $updated;
    }

    /* =========================================================
       MEDIA
    ========================================================= */

    /** Generate QR images with the app's existing QR service (encodes route('assets.scan', uuid)). */
    public function generateQrCodes(?callable $progress = null, ?int $limit = null): int
    {
        $service = app(DemoAssetQrService::class);
        $ids = $this->ownedIds('asset');

        if ($limit !== null) {
            $ids = array_slice($ids, 0, $limit);
        }

        $done = 0;

        foreach (array_chunk($ids, 100) as $chunk) {
            foreach (Asset::query()->whereIn('id', $chunk)->get() as $asset) {
                $service->generateForAsset($asset);
                $done++;
            }

            if ($progress) {
                $progress('qr_codes', $done);
            }
        }

        if (count($ids) === 0) {
            throw new RuntimeException('No synthetic assets found to generate QR codes for.');
        }

        return $done;
    }
}
