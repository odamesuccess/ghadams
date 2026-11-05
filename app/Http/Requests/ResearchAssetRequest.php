<?php

namespace App\Http\Requests;

use App\Models\Asset;
use App\Models\AssetSuggestion;
use App\Services\AssetResearchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ResearchAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [AssetSuggestion::class, $this->route('asset')]);
    }

    public function rules(): array
    {
        return [
            'mode' => ['nullable', Rule::in([AssetSuggestion::MODE_FULL, AssetSuggestion::MODE_PRICE_ONLY])],
            'force' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Asset $asset */
                $asset = $this->route('asset');

                if (! app(AssetResearchService::class)->categoryFor($asset)) {
                    $validator->errors()->add('asset', 'AI research is not available for this asset type.');

                    return;
                }

                if (blank($asset->model_number) && blank($asset->part_number)) {
                    $validator->errors()->add('model_number', 'Add a model number or part number before running AI research.');
                }
            },
        ];
    }

    public function mode(): string
    {
        return $this->input('mode') ?: AssetSuggestion::MODE_FULL;
    }
}
