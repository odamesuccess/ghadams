<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveAssetSuggestionRequest;
use App\Http\Requests\ResearchAssetRequest;
use App\Jobs\ResearchAssetJob;
use App\Models\Asset;
use App\Models\AssetSuggestion;
use App\Services\ActivityTrackerService;
use App\Services\AssetResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * JSON endpoints for AI asset research. Research results are stored as suggestions;
 * an asset only changes when a reviewer approves a suggestion.
 */
class AssetSuggestionController extends Controller
{
    public function __construct(private AssetResearchService $research) {}

    /* =========================================================
       START RESEARCH (queued)
    ========================================================= */
    public function research(ResearchAssetRequest $request, Asset $asset): JsonResponse
    {
        $mode = $request->mode();

        if (! $request->boolean('force')) {
            $recent = $asset->suggestions()
                ->pending()
                ->whereNull('error_message')
                ->where('mode', $mode)
                ->where('created_at', '>=', now()->subHours((int) config('asset_research.dedupe_hours', 24)))
                ->latest()
                ->first();

            if ($recent) {
                return response()->json([
                    'status' => 'existing',
                    'message' => 'A recent suggestion is already waiting for review.',
                    'suggestion_id' => $recent->id,
                ]);
            }
        }

        ResearchAssetJob::dispatch($asset->id, $mode, $request->user()->id);

        return response()->json([
            'status' => 'queued',
            'mode' => $mode,
            'requested_at' => now()->toIso8601String(),
        ], 202);
    }

    /* =========================================================
       LIST SUGGESTIONS FOR AN ASSET
    ========================================================= */
    public function index(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('viewAny', [AssetSuggestion::class, $asset]);

        $asset->loadMissing('assetName');
        $category = $this->research->categoryFor($asset);

        $suggestions = $asset->suggestions()
            ->with(['reviewer:id,name', 'requester:id,name'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AssetSuggestion $suggestion) => $this->present($suggestion));

        return response()->json([
            'category' => $category,
            'schema' => $category ? $this->research->specSchema($category) : [],
            'current' => $this->currentValues($asset),
            'can_research' => $request->user()->can('create', [AssetSuggestion::class, $asset]),
            'can_review' => $request->user()->can('assets.review-suggestions'),
            'suggestions' => $suggestions,
        ]);
    }

    /* =========================================================
       APPROVE (applies to the asset)
    ========================================================= */
    public function approve(ApproveAssetSuggestionRequest $request, AssetSuggestion $suggestion): JsonResponse
    {
        $edits = $request->validated('payload') ?? [];

        $asset = DB::transaction(function () use ($request, $suggestion, $edits) {
            $suggestion = AssetSuggestion::query()->lockForUpdate()->findOrFail($suggestion->id);

            abort_unless($suggestion->isPending(), 409, 'This suggestion has already been reviewed.');
            abort_if($suggestion->hasFailed() || ! $suggestion->payload, 409, 'A failed research result cannot be approved.');

            $asset = Asset::query()->lockForUpdate()->findOrFail($suggestion->asset_id);
            $payload = $this->mergeEdits($suggestion->payload, $edits);
            $changes = $this->mapToAssetColumns($payload, $suggestion->mode, $asset);
            $before = Arr::only($asset->getAttributes(), array_keys($changes));

            if ($changes) {
                $asset->update($changes);
            }

            $suggestion->update([
                'payload' => $payload,
                'status' => AssetSuggestion::STATUS_APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            ActivityTrackerService::record(
                $request,
                'Asset Research',
                'suggestion_approved',
                'AI suggestion approved',
                'AI research suggestion #'.$suggestion->id.' applied to asset '.$asset->asset_code,
                $asset,
                $before,
                $changes,
            );

            return $asset;
        });

        return response()->json([
            'message' => 'Suggestion approved and applied to the asset.',
            'current' => $this->currentValues($asset->fresh()),
            'suggestion' => $this->present($suggestion->fresh(['reviewer:id,name', 'requester:id,name'])),
        ]);
    }

    /* =========================================================
       REJECT
    ========================================================= */
    public function reject(Request $request, AssetSuggestion $suggestion): JsonResponse
    {
        Gate::authorize('reject', $suggestion);

        abort_unless($suggestion->isPending(), 409, 'This suggestion has already been reviewed.');

        $suggestion->update([
            'status' => AssetSuggestion::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Suggestion rejected.',
            'suggestion' => $this->present($suggestion->fresh(['reviewer:id,name', 'requester:id,name'])),
        ]);
    }

    /* =========================================================
       HELPERS
    ========================================================= */

    /**
     * Overlay the reviewer's edits on the stored payload (only editable fields).
     */
    private function mergeEdits(array $payload, array $edits): array
    {
        if (array_key_exists('model', $edits)) {
            $payload['model'] = $edits['model'];
        }

        if (isset($edits['specifications']) && is_array($payload['specifications'] ?? null)) {
            $payload['specifications'] = $this->research->normalizeSpecifications(
                array_replace($payload['specifications'], $edits['specifications']),
                (string) $payload['category'],
            );
        }

        if (isset($edits['price']) && is_array($payload['price'] ?? null)) {
            $editable = Arr::only($edits['price'], ['amount', 'low', 'high', 'currency', 'price_type']);
            $payload['price'] = array_replace($payload['price'], $editable);
            $payload['price']['edited_by_reviewer'] = true;
        }

        return $payload;
    }

    /**
     * Suggestion field -> asset column mapping.
     *
     *   model           -> model_number   (full mode, only when the asset has none)
     *   specifications  -> specifications (full mode, replaces the whole JSON)
     *   price.amount    -> market_price_amount
     *   price.low       -> market_price_low
     *   price.high      -> market_price_high
     *   price.currency  -> market_price_currency
     *   price.price_type      -> market_price_type
     *   price.retrieved_at    -> market_price_retrieved_at
     *
     * Never written: brand (manufacturer is shown only), purchase_cost, description,
     * serial_number, part_number.
     */
    private function mapToAssetColumns(array $payload, string $mode, Asset $asset): array
    {
        $changes = [];

        if ($mode === AssetSuggestion::MODE_FULL) {
            if (blank($asset->model_number) && filled($payload['model'] ?? null)) {
                $changes['model_number'] = $payload['model'];
            }

            if (is_array($payload['specifications'] ?? null)) {
                $changes['specifications'] = $payload['specifications'];
            }
        }

        $price = $payload['price'] ?? null;

        if (is_array($price)) {
            $changes += [
                'market_price_amount' => $price['amount'] ?? null,
                'market_price_low' => $price['low'] ?? null,
                'market_price_high' => $price['high'] ?? null,
                'market_price_currency' => $price['currency'] ?? null,
                'market_price_type' => $price['price_type'] ?? null,
                'market_price_retrieved_at' => isset($price['retrieved_at']) ? Carbon::parse($price['retrieved_at']) : now(),
            ];
        }

        return $changes;
    }

    private function currentValues(Asset $asset): array
    {
        return [
            'brand' => $asset->brand,
            'model_number' => $asset->model_number,
            'part_number' => $asset->part_number,
            'serial_number' => $asset->serial_number,
            'specifications' => $asset->specifications,
            'price' => [
                'amount' => $asset->market_price_amount,
                'low' => $asset->market_price_low,
                'high' => $asset->market_price_high,
                'currency' => $asset->market_price_currency,
                'price_type' => $asset->market_price_type,
                'retrieved_at' => $asset->market_price_retrieved_at?->toIso8601String(),
            ],
        ];
    }

    private function present(AssetSuggestion $suggestion): array
    {
        return [
            'id' => $suggestion->id,
            'mode' => $suggestion->mode,
            'status' => $suggestion->status,
            'confidence' => $suggestion->confidence,
            'payload' => $suggestion->payload,
            'sources' => $suggestion->sources ?? [],
            'error_message' => $suggestion->error_message,
            'retrieved_at' => $suggestion->retrieved_at?->toIso8601String(),
            'created_at' => $suggestion->created_at?->toIso8601String(),
            'reviewed_at' => $suggestion->reviewed_at?->toIso8601String(),
            'reviewer' => $suggestion->reviewer?->only(['id', 'name']),
            'requester' => $suggestion->requester?->only(['id', 'name']),
        ];
    }
}
