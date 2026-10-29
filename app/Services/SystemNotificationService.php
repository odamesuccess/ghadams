<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SystemNotificationService
{
    /**
     * Create a notification with duplicate protection.
     *
     * user_id is the recipient. Null means a global notification visible to permitted users.
     */
    public static function create(
        string $type,
        string $title,
        ?string $message = null,
        ?int $actorId = null,
        ?int $recipientId = null,
        ?string $modelType = null,
        ?int $modelId = null,
        array $data = [],
        int $duplicateWindowSeconds = 10
    ): ?SystemNotification {
        try {
            $resolvedActorId = $actorId ?? Auth::id();

            $duplicateExists = SystemNotification::query()
                ->where('actor_id', $resolvedActorId)
                ->where('user_id', $recipientId)
                ->where('type', $type)
                ->where('title', $title)
                ->where('message', $message)
                ->where('notifiable_record_type', $modelType)
                ->where('notifiable_record_id', $modelId)
                ->where('created_at', '>=', now()->subSeconds($duplicateWindowSeconds))
                ->exists();

            if ($duplicateExists) {
                return null;
            }

            return SystemNotification::create([
                'user_id' => $recipientId,
                'actor_id' => $resolvedActorId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'notifiable_record_type' => $modelType,
                'notifiable_record_id' => $modelId,
                'data' => $data ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('System notification failed: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Backward-compatible helper used by existing services/controllers.
     */
    public static function notify(
        ?int $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?Model $record = null,
        ?array $data = null,
        ?int $actorId = null
    ): void {
        self::create(
            type: $type,
            title: $title,
            message: $message,
            actorId: $actorId,
            recipientId: $userId,
            modelType: $record ? get_class($record) : null,
            modelId: $record?->getKey(),
            data: $data ?? [],
        );
    }

    public static function notifyAdmins(
        string $type,
        string $title,
        ?string $message = null,
        ?int $actorId = null,
        ?string $modelType = null,
        ?int $modelId = null,
        array $data = [],
        int $duplicateWindowSeconds = 10
    ): void {
        $admins = User::query()
            ->whereHas('roles', function ($query): void {
                $query->whereIn('name', [
                    'admin',
                    'Admin',
                    'super-admin',
                    'Super Admin',
                    'SuperAdmin',
                    'Super Admin',
                ]);
            })
            ->get();

        if ($admins->isEmpty()) {
            self::create($type, $title, $message, $actorId, null, $modelType, $modelId, $data, $duplicateWindowSeconds);
            return;
        }

        foreach ($admins as $admin) {
            self::create($type, $title, $message, $actorId, $admin->id, $modelType, $modelId, $data, $duplicateWindowSeconds);
        }
    }

    public static function notifyUser(
        int $recipientId,
        string $type,
        string $title,
        ?string $message = null,
        ?int $actorId = null,
        ?string $modelType = null,
        ?int $modelId = null,
        array $data = [],
        int $duplicateWindowSeconds = 10
    ): void {
        self::create($type, $title, $message, $actorId, $recipientId, $modelType, $modelId, $data, $duplicateWindowSeconds);
    }

    public static function notifyAdminsAndUser(
        int $recipientId,
        string $type,
        string $title,
        ?string $message = null,
        ?int $actorId = null,
        ?string $modelType = null,
        ?int $modelId = null,
        array $data = [],
        int $duplicateWindowSeconds = 10
    ): void {
        self::notifyAdmins($type, $title, $message, $actorId, $modelType, $modelId, $data, $duplicateWindowSeconds);
        self::notifyUser($recipientId, $type, $title, $message, $actorId, $modelType, $modelId, $data, $duplicateWindowSeconds);
    }

    public static function userLogin(object $user): void
    {
        self::notifyAdmins(
            type: 'user_login_success',
            title: 'User logged in',
            message: ($user->name ?? 'A user').' logged in successfully.',
            actorId: $user->id ?? null,
            modelType: 'users',
            modelId: $user->id ?? null,
            data: [
                'user_id' => $user->id ?? null,
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
                'time' => now()->toDateTimeString(),
            ],
            duplicateWindowSeconds: 15
        );
    }

    public static function userLogout(object $user): void
    {
        self::notifyAdmins(
            type: 'user_logout_success',
            title: 'User logged out',
            message: ($user->name ?? 'A user').' logged out successfully.',
            actorId: $user->id ?? null,
            modelType: 'users',
            modelId: $user->id ?? null,
            data: [
                'user_id' => $user->id ?? null,
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
                'time' => now()->toDateTimeString(),
            ],
            duplicateWindowSeconds: 15
        );
    }

    public static function modelActivity(string $module, string $action, object $record): void
    {
        $label = self::recordLabel($record);

        self::notifyAdmins(
            type: str($module)->snake()->append('_'.$action)->toString(),
            title: $module.' '.str_replace('_', ' ', $action),
            message: self::actorName()." {$action} {$module}: {$label}",
            actorId: Auth::id(),
            modelType: get_class($record),
            modelId: $record->id ?? null,
            data: [
                'module' => $module,
                'action' => $action,
                'record_id' => $record->id ?? null,
                'record_label' => $label,
            ]
        );
    }

    public static function assetActivity(string $action, object $asset): void
    {
        self::modelActivity('Asset', $action, $asset);
    }

    public static function approvalActivity(string $action, object $approval): void
    {
        self::modelActivity('Approval Request', $action, $approval);
    }

    public static function maintenanceActivity(string $action, object $maintenance): void
    {
        self::modelActivity('Maintenance Request', $action, $maintenance);
    }

    private static function actorName(): string
    {
        return Auth::user()?->name ?? 'System';
    }

    private static function recordLabel(object $record): string
    {
        foreach (['asset_code', 'name', 'title', 'email', 'unitname', 'organizationname', 'suppliername', 'locationname', 'fault_description'] as $field) {
            if (! empty($record->{$field})) {
                return (string) $record->{$field};
            }
        }

        return isset($record->id) ? '#'.$record->id : 'record';
    }
}
