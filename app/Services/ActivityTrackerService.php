<?php

namespace App\Services;

use App\Models\ActivityTracker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ActivityTrackerService
{
    public static function record(
        ?Request $request,
        string $module,
        string $action,
        ?string $title = null,
        ?string $description = null,
        ?Model $trackable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): void {
        try {
            ActivityTracker::create([
                'user_id' => $userId ?? auth()->id(),
                'module' => $module,
                'action' => $action,
                'trackable_type' => $trackable ? get_class($trackable) : null,
                'trackable_id' => $trackable?->getKey(),
                'title' => $title,
                'description' => $description,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);

            AuditLogService::record(
                $request,
                $module,
                $action,
                $title,
                $description,
                $trackable,
                $oldValues,
                $newValues,
                $userId
            );

            if (in_array(strtolower($action), ['created', 'updated', 'deleted', 'soft_deleted', 'force_deleted', 'restored', 'approved', 'rejected', 'completed', 'assigned', 'transferred', 'disposed', 'login', 'logout', 'password_reset_link_sent', 'password_reset_completed'], true)) {
                SystemNotificationService::notifyAdmins(
                    type: 'activity',
                    title: $title ?? ($module.' '.$action),
                    message: $description,
                    actorId: $userId ?? auth()->id(),
                    modelType: $trackable ? get_class($trackable) : null,
                    modelId: $trackable?->getKey(),
                    data: ['module' => $module, 'action' => $action]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Activity tracker failed: '.$e->getMessage());
        }
    }
}
