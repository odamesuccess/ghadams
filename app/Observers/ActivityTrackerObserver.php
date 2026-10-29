<?php

namespace App\Observers;

use App\Services\ActivityTrackerService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityTrackerObserver
{
    private array $ignoredFields = [
        'updated_at',
        'created_at',
        'deleted_at',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'password',
    ];

    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $this->safeAttributes($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $oldValues = [];
        $newValues = [];

        foreach ($model->getChanges() as $key => $value) {
            if (in_array($key, $this->ignoredFields, true)) {
                continue;
            }

            $oldValues[$key] = $model->getOriginal($key);
            $newValues[$key] = $value;
        }

        if ($oldValues === [] && $newValues === []) {
            return;
        }

        $this->record($model, 'updated', $oldValues, $newValues);
    }

    public function deleted(Model $model): void
    {
        $action = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
            ? 'force_deleted'
            : 'soft_deleted';

        $this->record($model, $action, $this->safeAttributes($model->getOriginal()), null);
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restored', null, $this->safeAttributes($model->getAttributes()));
    }

    public function forceDeleted(Model $model): void
    {
        $this->record($model, 'force_deleted', $this->safeAttributes($model->getOriginal()), null);
    }

    private function record(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        $module = Str::headline(class_basename($model));
        $label = $this->label($model);
        $title = $module.' '.Str::headline($action);

        ActivityTrackerService::record(
            request(),
            $module,
            $action,
            $title,
            trim($title.($label ? ': '.$label : '')),
            $model,
            $oldValues,
            $newValues
        );
    }

    private function label(Model $model): ?string
    {
        foreach (['name', 'title', 'asset_code', 'email', 'unitname', 'organizationname', 'suppliername', 'locationname'] as $field) {
            if (! empty($model->{$field})) {
                return (string) $model->{$field};
            }
        }

        return $model->getKey() ? '#'.$model->getKey() : null;
    }

    private function safeAttributes(array $attributes): array
    {
        foreach ($this->ignoredFields as $field) {
            unset($attributes[$field]);
        }

        return $attributes;
    }
}
