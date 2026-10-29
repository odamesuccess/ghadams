<?php

namespace App\Services;

use App\Models\SystemNotification;

class ActivityNotificationService extends SystemNotificationService
{
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
        return parent::create(
            type: $type,
            title: $title,
            message: $message,
            actorId: $actorId,
            recipientId: $recipientId,
            modelType: $modelType,
            modelId: $modelId,
            data: $data,
            duplicateWindowSeconds: $duplicateWindowSeconds
        );
    }
}
