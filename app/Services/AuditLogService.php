<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditLogService
{
    /**
     * Backward-compatible audit recorder for GHADAMS.
     */
    public static function record(
        ?Request $request,
        string $module,
        string $action,
        ?string $title = null,
        ?string $description = null,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?array $properties = null
    ): void {
        try {
            $request ??= request();
            $ipAddress = self::clientIp($request);
            $location = self::ipLocation($ipAddress);

            $properties ??= [];

            if ($oldValues !== null || $newValues !== null) {
                $properties['changed_fields'] = array_values(array_unique(array_merge(
                    array_keys($oldValues ?? []),
                    array_keys($newValues ?? [])
                )));
            }

            AuditLog::create([
                'user_id' => $userId ?? Auth::id(),
                'module' => $module,
                'action' => $action,
                'auditable_type' => $auditable ? get_class($auditable) : null,
                'auditable_id' => $auditable?->getKey(),
                'title' => $title,
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'properties' => $properties ?: null,
                'ip_address' => $ipAddress,
                'ip_country' => $location['country'],
                'ip_city' => $location['city'],
                'user_agent' => Str::limit((string) $request?->userAgent(), 1000),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Audit log failed: '.$e->getMessage());
        }
    }

    /**
     * LMS-style recorder for new code that prefers action + description first.
     */
    public static function recordEvent(
        string $action,
        string $description,
        ?Model $auditable = null,
        array $properties = [],
        ?Request $request = null,
        ?string $module = null,
        ?string $title = null
    ): void {
        self::record(
            request: $request ?? request(),
            module: $module ?? Str::headline(Str::before($action, '.')),
            action: $action,
            title: $title ?? Str::headline(str_replace(['.', '_'], ' ', $action)),
            description: $description,
            auditable: $auditable,
            oldValues: null,
            newValues: null,
            userId: Auth::id(),
            properties: $properties
        );
    }

    public static function modelCreated(Model $model, array $values = []): void
    {
        self::record(
            request(),
            self::modelName($model),
            'created',
            self::modelName($model).' created',
            self::modelName($model).' created: '.self::displayName($model),
            $model,
            null,
            $values ?: self::safeAttributes($model->getAttributes()),
        );
    }

    public static function modelUpdated(Model $model, array $oldValues, array $newValues): void
    {
        self::record(
            request(),
            self::modelName($model),
            'updated',
            self::modelName($model).' updated',
            self::updatedDescription($model, $oldValues, $newValues),
            $model,
            $oldValues,
            $newValues,
        );
    }

    public static function modelDeleted(Model $model, array $oldValues = []): void
    {
        self::record(
            request(),
            self::modelName($model),
            method_exists($model, 'isForceDeleting') && $model->isForceDeleting() ? 'force_deleted' : 'soft_deleted',
            self::modelName($model).' deleted',
            self::modelName($model).' deleted: '.self::displayName($model),
            $model,
            $oldValues ?: self::safeAttributes($model->getOriginal()),
            null,
        );
    }

    public static function modelRestored(Model $model): void
    {
        self::record(
            request(),
            self::modelName($model),
            'restored',
            self::modelName($model).' restored',
            self::modelName($model).' restored: '.self::displayName($model),
            $model,
            null,
            self::safeAttributes($model->getAttributes()),
        );
    }

    public static function authentication(string $event, ?Model $user, ?string $email = null, ?string $description = null): void
    {
        self::record(
            request(),
            'Authentication',
            $event,
            Str::headline($event),
            $description ?? Str::headline($event).($email ? ': '.$email : ''),
            $user,
            null,
            ['email' => $email ?? $user?->email, 'event' => $event],
            $user?->getKey(),
            ['event' => $event]
        );
    }

    private static function clientIp(?Request $request): ?string
    {
        if (! $request) {
            return null;
        }

        $candidates = [
            $request->header('CF-Connecting-IP'),
            $request->header('X-Forwarded-For') ? trim(explode(',', $request->header('X-Forwarded-For'))[0]) : null,
            $request->header('X-Real-IP'),
            $request->ip(),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return $request->ip();
    }

    private static function ipLocation(?string $ipAddress): array
    {
        $empty = ['country' => null, 'city' => null];

        if (! $ipAddress || in_array($ipAddress, ['127.0.0.1', '::1'], true) || ! filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $empty;
        }

        return Cache::remember('audit_ip_location_'.$ipAddress, now()->addDays(7), function () use ($ipAddress, $empty): array {
            try {
                $response = Http::timeout(3)->acceptJson()->get("https://ipapi.co/{$ipAddress}/json/");

                if (! $response->successful()) {
                    return $empty;
                }

                $json = $response->json();

                return [
                    'country' => $json['country_name'] ?? $json['country'] ?? null,
                    'city' => $json['city'] ?? null,
                ];
            } catch (\Throwable) {
                return $empty;
            }
        });
    }

    private static function modelName(Model $model): string
    {
        return match (class_basename($model)) {
            'Asset' => 'Asset',
            'AssetName' => 'Asset Name',
            'AssetCondition' => 'Asset Condition',
            'AssetLocation' => 'Asset Location',
            'AssetSource' => 'Asset Source',
            'AssetScanLog' => 'Asset Scan Log',
            'AssetLifecycleHistory' => 'Asset Lifecycle',
            'ApprovalRequest' => 'Approval Request',
            'MaintenanceRequest' => 'Maintenance Request',
            'MaintenanceProvider' => 'Maintenance Provider',
            'Organization' => 'Organization',
            'Unit' => 'Unit',
            'Supplier' => 'Supplier',
            'Location' => 'Location',
            'Source' => 'Source',
            'Link' => 'Link',
            'LinkScan' => 'Link Scan',
            'QrReprintHistory' => 'QR Reprint History',
            'LoginTracker' => 'Login Tracker',
            'ActivityTracker' => 'Activity Tracker',
            'User' => 'User',
            'Role' => 'Role',
            'Permission' => 'Permission',
            default => Str::headline(class_basename($model)),
        };
    }

    private static function displayName(Model $model): string
    {
        foreach (['asset_code', 'name', 'title', 'email', 'unitname', 'organizationname', 'suppliername', 'locationname', 'fault_description', 'code'] as $field) {
            if (! empty($model->{$field})) {
                return (string) $model->{$field};
            }
        }

        return '#'.$model->getKey();
    }

    private static function updatedDescription(Model $model, array $oldValues, array $newValues): string
    {
        $changes = [];

        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;
            $changes[] = Str::headline(str_replace('_', ' ', $field)).' from "'.self::formatValue($oldValue).'" to "'.self::formatValue($newValue).'"';
        }

        return self::modelName($model).' updated: '.self::displayName($model).'. Changed: '.implode(', ', $changes);
    }

    private static function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'empty';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'array';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $value = (string) $value;
        return strlen($value) > 120 ? substr($value, 0, 120).'...' : $value;
    }

    private static function safeAttributes(array $attributes): array
    {
        foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'created_at', 'updated_at', 'deleted_at'] as $field) {
            unset($attributes[$field]);
        }

        return $attributes;
    }
}
