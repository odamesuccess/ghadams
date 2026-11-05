<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetName;
use App\Models\AssetSuggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "AI Research" workspace: research-ready assets and their AI suggestions in one
 * place, for users who have the AI permissions but not general asset access.
 */
class AssetResearchController extends Controller
{
    public const STATUS_FILTERS = ['pending', 'failed', 'none', 'approved'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only('search', 'status', 'per_page');
        // The page picks this from the screen height ("Auto") or the "Show" menu.
        $perPage = min(100, max(5, $request->integer('per_page', 25)));

        $assets = $this->scopedAssets($user)
            ->with([
                'assetName:id,assetname,assetname_abbreviation',
                'unit:id,name',
                'latestSuggestion',
            ])
            ->withExists(['suggestions as has_pending_review' => fn ($q) => $this->pendingReview($q)])
            ->when($request->filled('search'), fn (Builder $q) => $this->applySearch($q, (string) $request->input('search')))
            ->when(in_array($request->input('status'), self::STATUS_FILTERS, true), fn (Builder $q) => $this->applyStatus($q, $request->input('status')))
            ->orderByDesc('has_pending_review')
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Asset $asset) => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'type' => $asset->assetName?->assetname,
                'brand' => $asset->brand,
                'model_number' => $asset->model_number,
                'part_number' => $asset->part_number,
                'unit' => $asset->unit?->name,
                'has_pending_review' => (bool) $asset->has_pending_review,
                'latest' => $asset->latestSuggestion ? [
                    'id' => $asset->latestSuggestion->id,
                    'mode' => $asset->latestSuggestion->mode,
                    'status' => $asset->latestSuggestion->status,
                    'confidence' => $asset->latestSuggestion->confidence,
                    'failed' => $asset->latestSuggestion->hasFailed(),
                    'created_at' => $asset->latestSuggestion->created_at?->toIso8601String(),
                ] : null,
            ]);

        return Inertia::render('AssetResearch/Index', [
            'assets' => $assets,
            'counts' => [
                'pending' => $this->applyStatus($this->scopedAssets($user), 'pending')->count(),
                'failed' => $this->applyStatus($this->scopedAssets($user), 'failed')->count(),
                'none' => $this->applyStatus($this->scopedAssets($user), 'none')->count(),
                'approved' => $this->applyStatus($this->scopedAssets($user), 'approved')->count(),
            ],
            'filters' => $filters,
            'canEditIdentifiers' => $user->can('assets.research'),
            'otherTypeMatches' => $assets->total() === 0 && $request->filled('search')
                ? $this->otherTypeMatches($user, (string) $request->input('search'))
                : null,
            'supportedTypes' => $this->supportedTypeNames(),
        ]);
    }

    /**
     * Match the search against the asset's identifiers, type name, unit and description.
     * Every word must match somewhere, so "HP EliteBook" or "Laptop Finance" both work.
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        $words = preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach (array_slice($words, 0, 6) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(fn ($q) => $q
                ->where('asset_code', 'like', $like)
                ->orWhere('brand', 'like', $like)
                ->orWhere('model_number', 'like', $like)
                ->orWhere('part_number', 'like', $like)
                ->orWhere('serial_number', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('assetName', fn ($n) => $n->where('assetname', 'like', $like))
                ->orWhereHas('unit', fn ($u) => $u->where('name', 'like', $like)->orWhere('locationname', 'like', $like)));
        }

        return $query;
    }

    /**
     * When nothing research-ready matches, report matching assets of other types so the
     * user knows why (e.g. a photocopier or UPS), instead of an unexplained empty list.
     */
    protected function otherTypeMatches(User $user, string $search): ?array
    {
        if ($this->allTypesResearchable()) {
            return null;
        }

        $abbreviations = array_keys((array) config('asset_research.asset_type_categories', []));

        $matches = $this->applySearch(
            Asset::query()
                ->whereDoesntHave('assetName', fn ($q) => $q->whereIn('assetname_abbreviation', $abbreviations))
                ->when(! $user->hasRole('Super Admin'), fn ($q) => $q->where('unit_id', $user->unit_id)),
            $search,
        );

        $total = (clone $matches)->count();

        if ($total === 0) {
            return null;
        }

        return [
            'total' => $total,
            'types' => AssetName::query()
                ->whereIn('id', (clone $matches)->select('assetname_id'))
                ->orderBy('assetname')
                ->limit(5)
                ->pluck('assetname')
                ->all(),
            'example' => (clone $matches)->value('asset_code'),
        ];
    }

    protected function supportedTypeNames(): array
    {
        $abbreviations = array_keys((array) config('asset_research.asset_type_categories', []));

        return AssetName::query()
            ->when(! $this->allTypesResearchable(), fn ($q) => $q->whereIn('assetname_abbreviation', $abbreviations))
            ->orderBy('assetname')
            ->pluck('assetname')
            ->all();
    }

    /**
     * True when unlisted asset types fall back to a default spec category.
     */
    protected function allTypesResearchable(): bool
    {
        $default = config('asset_research.default_category');

        return is_string($default) && config("asset_research.categories.{$default}");
    }

    /**
     * Set the model / part number used for research. Users with the research permission
     * may change only these two fields, even without general asset edit access.
     */
    public function updateIdentifiers(Request $request, Asset $asset): JsonResponse
    {
        Gate::authorize('create', [AssetSuggestion::class, $asset]);

        $validated = $request->validate([
            'model_number' => ['nullable', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:255'],
        ]);

        $asset->update([
            'model_number' => filled($validated['model_number'] ?? null) ? trim($validated['model_number']) : null,
            'part_number' => filled($validated['part_number'] ?? null) ? trim($validated['part_number']) : null,
        ]);

        return response()->json([
            'message' => 'Model and part number saved.',
            'model_number' => $asset->model_number,
            'part_number' => $asset->part_number,
        ]);
    }

    /**
     * Number of assets with a suggestion waiting for review (for the dashboard card).
     */
    public static function pendingReviewCount(User $user): int
    {
        $controller = new self;

        return $controller->applyStatus($controller->scopedAssets($user), 'pending')->count();
    }

    /* =========================================================
       QUERY HELPERS
    ========================================================= */

    /**
     * Research-ready assets: those with a model or part number (every type, unless the
     * default category is turned off), limited to the user's unit unless Super Admin
     * (the same rule AssetSuggestionPolicy uses).
     */
    protected function scopedAssets(User $user): Builder
    {
        $abbreviations = array_keys((array) config('asset_research.asset_type_categories', []));

        return Asset::query()
            ->where(fn ($q) => $q
                ->where(fn ($m) => $m->whereNotNull('model_number')->whereRaw("TRIM(model_number) <> ''"))
                ->orWhere(fn ($p) => $p->whereNotNull('part_number')->whereRaw("TRIM(part_number) <> ''")))
            ->when(! $this->allTypesResearchable(), fn ($q) => $q
                ->whereHas('assetName', fn ($n) => $n->whereIn('assetname_abbreviation', $abbreviations)))
            ->when(! $user->hasRole('Super Admin'), fn ($q) => $q->where('unit_id', $user->unit_id));
    }

    protected function pendingReview($query)
    {
        return $query->where('status', AssetSuggestion::STATUS_PENDING)->whereNull('error_message');
    }

    protected function applyStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'pending' => $query->whereHas('suggestions', fn ($q) => $this->pendingReview($q)),
            'failed' => $query->whereHas('suggestions', fn ($q) => $q
                ->where('status', AssetSuggestion::STATUS_PENDING)
                ->whereNotNull('error_message')),
            'none' => $query->whereDoesntHave('suggestions'),
            'approved' => $query->whereHas('suggestions', fn ($q) => $q->where('status', AssetSuggestion::STATUS_APPROVED)),
            default => $query,
        };
    }
}
