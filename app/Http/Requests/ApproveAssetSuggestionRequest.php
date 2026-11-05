<?php

namespace App\Http\Requests;

use App\Models\AssetSuggestion;
use App\Services\AssetResearchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Approve a suggestion, optionally with reviewer edits. Only the model, the category's
 * spec fields and the price summary can be edited; anything else in the payload is ignored.
 */
class ApproveAssetSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('suggestion'));
    }

    public function rules(): array
    {
        /** @var AssetSuggestion $suggestion */
        $suggestion = $this->route('suggestion');
        $category = (string) ($suggestion->payload['category'] ?? '');

        $number = ['nullable', 'numeric', 'min:0', 'max:9999999999'];

        return [
            'payload' => ['nullable', 'array'],
            'payload.model' => ['nullable', 'string', 'max:255'],
            'payload.specifications' => ['nullable', 'array'],
            ...app(AssetResearchService::class)->specificationRules($category, 'payload.specifications.'),
            'payload.price' => ['nullable', 'array'],
            'payload.price.amount' => $number,
            'payload.price.low' => $number,
            'payload.price.high' => $number,
            'payload.price.currency' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/'],
            'payload.price.price_type' => ['nullable', Rule::in(['new', 'used', 'refurbished', 'range'])],
        ];
    }
}
