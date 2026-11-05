<?php

use App\Jobs\ResearchAssetJob;
use App\Models\Asset;
use App\Models\AssetSuggestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/* =========================================================
   Helpers
========================================================= */

function researchUser(array $permissions, ?int $unitId = null): User
{
    $user = User::factory()->create(array_filter([
        'force_password_change' => false,
        'unit_id' => $unitId,
    ], fn ($value) => $value !== null));

    // The web stack requires confirmed two-factor auth.
    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    if ($permissions) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

/**
 * A laptop asset (type LTP, research-ready) with the minimum parent rows it needs.
 */
function researchAsset(User $owner, array $attributes = []): Asset
{
    static $n = 0;
    $n++;

    $now = now();
    $unit = DB::table('units')->find($owner->unit_id);

    $assetNameId = DB::table('asset_names')->where('assetname_abbreviation', $attributes['_type'] ?? 'LTP')->value('id')
        ?? DB::table('asset_names')->insertGetId([
            'assetname' => ($attributes['_type'] ?? 'LTP') === 'LTP' ? 'Laptop Computer' : 'Other '.$n,
            'assetname_abbreviation' => $attributes['_type'] ?? 'LTP',
            'brand' => 'Brand '.$n,
            'usefullife' => 4,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    $parent = fn (string $table, array $row) => DB::table($table)->insertGetId($row + ['created_at' => $now, 'updated_at' => $now]);

    unset($attributes['_type']);

    return Asset::forceCreate([
        'asset_code' => 'TST-'.Str::upper(Str::random(8)),
        'assetname_id' => $assetNameId,
        'brand' => 'HP',
        'model_number' => 'EliteBook 840 G8',
        'serial_number' => 'SN'.$n,
        'source_id' => $parent('sources', ['name' => 'SRC'.$n.Str::random(4)]),
        'user_id' => $owner->id,
        'unit_id' => $owner->unit_id,
        'supplier_id' => $parent('suppliers', ['suppliername' => 'Supplier '.Str::random(6), 'phonenumber' => (string) random_int(100000000, 999999999), 'email' => Str::random(8).'@example.com']),
        'maintenance_id' => $parent('maintenance_providers', ['providername' => 'Provider '.Str::random(6), 'phonenumber' => (string) random_int(100000000, 999999999), 'email' => Str::random(8).'@example.com']),
        'asset_condition_id' => $parent('asset_conditions', ['conditionname' => 'Good '.Str::random(6)]),
        'asset_location_id' => $parent('asset_locations', ['locationname' => 'Room '.Str::random(6), 'Officenumber' => '1']),
        'organization_id' => $unit->organization_id,
        'status' => 'active',
        ...$attributes,
    ]);
}

function otherUnitId(): int
{
    $organizationId = DB::table('units')->value('organization_id');

    return DB::table('units')->insertGetId([
        'name' => 'Other Unit',
        'unit_abbreviation' => 'OU',
        'email' => 'other-unit@example.com',
        'phonenumber' => '1111111111',
        'locationname' => 'Branch',
        'location_abbreviation' => 'BR',
        'officenumber' => '2',
        'organization_id' => $organizationId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** A Messages API response: one web search with the given result URLs, then a JSON answer. */
function claudeResponse(array $json, array $resultUrls): array
{
    return [
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-5-5',
        'stop_reason' => 'end_turn',
        'content' => [
            ['type' => 'server_tool_use', 'id' => 'srvtoolu_1', 'name' => 'web_search', 'input' => ['query' => 'test']],
            [
                'type' => 'web_search_tool_result',
                'tool_use_id' => 'srvtoolu_1',
                'content' => array_map(fn ($url) => ['type' => 'web_search_result', 'url' => $url, 'title' => 'Result'], $resultUrls),
            ],
            ['type' => 'text', 'text' => json_encode($json)],
        ],
    ];
}

function specAnswer(array $overrides = []): array
{
    return array_replace([
        'model' => 'HP EliteBook 840 G8',
        'manufacturer' => 'HP',
        'confidence' => 'high',
        'variants' => [],
        'specifications' => [
            'processor' => 'Intel Core i5-1135G7',
            'ram_gb' => 16,
            'storage_gb' => 512,
            'ports' => ['USB-C', 'HDMI'],
        ],
        'sources' => [['url' => 'https://www.hp.com/elitebook-840-g8', 'note' => 'Datasheet']],
        'notes' => null,
    ], $overrides);
}

function priceAnswer(array $offers): array
{
    return ['confidence' => 'medium', 'offers' => $offers, 'notes' => null];
}

beforeEach(function () {
    config(['services.anthropic.key' => 'test-key']);
});

/* =========================================================
   Starting research
========================================================= */

test('a researcher can queue research for an asset in their unit', function () {
    Queue::fake();
    $user = researchUser(['assets.research']);
    $asset = researchAsset($user);

    $this->actingAs($user)
        ->postJson(route('assets.research', $asset), ['mode' => 'full'])
        ->assertAccepted()
        ->assertJson(['status' => 'queued', 'mode' => 'full']);

    Queue::assertPushed(ResearchAssetJob::class, fn ($job) => $job->assetId === $asset->id
        && $job->mode === 'full'
        && $job->requestedBy === $user->id);
});

test('users without the research permission cannot start research', function () {
    Queue::fake();
    $user = researchUser(['assets.review-suggestions']);
    $asset = researchAsset($user);

    $this->actingAs($user)
        ->postJson(route('assets.research', $asset))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('research is limited to assets in the user\'s own unit', function () {
    Queue::fake();
    $owner = researchUser([]);
    $asset = researchAsset($owner);
    $outsider = researchUser(['assets.research'], otherUnitId());

    $this->actingAs($outsider)
        ->postJson(route('assets.research', $asset))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('research runs on asset type and brand when there is no model or part number', function () {
    Queue::fake();
    $user = researchUser(['assets.research']);
    $asset = researchAsset($user, ['model_number' => null, 'part_number' => null]);

    $this->actingAs($user)
        ->postJson(route('assets.research', $asset))
        ->assertAccepted();

    Queue::assertPushed(ResearchAssetJob::class);
});

test('research needs at least a brand, model or part number', function () {
    Queue::fake();
    $user = researchUser(['assets.research']);
    $asset = researchAsset($user, ['brand' => null, 'model_number' => null, 'part_number' => null]);
    $asset->assetName->update(['brand' => '']);

    $this->actingAs($user)
        ->postJson(route('assets.research', $asset))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('brand');

    Queue::assertNothingPushed();
});

test('the asset type brand is used when the asset has no brand', function () {
    $user = researchUser([]);
    $asset = researchAsset($user, ['brand' => null]);
    $asset->assetName->update(['brand' => 'Lenovo']);

    expect(app(\App\Services\AssetResearchService::class)->brandFor($asset->fresh('assetName')))->toBe('Lenovo');
});

test('every asset type gets a spec category', function (string $type, string $category) {
    $user = researchUser([]);
    $asset = researchAsset($user, ['_type' => $type]);

    expect(app(\App\Services\AssetResearchService::class)->categoryFor($asset))->toBe($category);
})->with([
    'mapped type' => ['UPS', 'ups'],
    'furniture' => ['OFC', 'furniture'],
    'unlisted type falls back to general' => ['WDP', 'general'],
    'abbreviation case is ignored' => ['cctv', 'camera'],
]);

test('unlisted asset types can be researched', function () {
    Queue::fake();
    $user = researchUser(['assets.research']);
    $asset = researchAsset($user, ['_type' => 'WDP']);

    $this->actingAs($user)
        ->postJson(route('assets.research', $asset))
        ->assertAccepted();

    Queue::assertPushed(ResearchAssetJob::class);
});

test('unlisted asset types are refused when the default category is turned off', function () {
    Queue::fake();
    config(['asset_research.default_category' => null]);
    $user = researchUser(['assets.research']);
    $asset = researchAsset($user, ['_type' => 'WDP']);

    $this->actingAs($user)
        ->postJson(route('assets.research', $asset))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('asset');

    Queue::assertNothingPushed();
});

test('a recent pending suggestion is reused unless the request is forced', function () {
    Queue::fake();
    $user = researchUser(['assets.research']);
    $asset = researchAsset($user);
    $existing = AssetSuggestion::create([
        'asset_id' => $asset->id,
        'mode' => 'full',
        'payload' => ['category' => 'laptop'],
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->postJson(route('assets.research', $asset), ['mode' => 'full'])
        ->assertOk()
        ->assertJson(['status' => 'existing', 'suggestion_id' => $existing->id]);

    Queue::assertNothingPushed();

    $this->actingAs($user)
        ->postJson(route('assets.research', $asset), ['mode' => 'full', 'force' => true])
        ->assertAccepted();

    Queue::assertPushed(ResearchAssetJob::class);
});

/* =========================================================
   The research job
========================================================= */

test('the job stores a pending suggestion and does not change the asset', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudeResponse(specAnswer(), ['https://www.hp.com/elitebook-840-g8']))
        ->push(claudeResponse(priceAnswer([
            ['amount' => 1200, 'currency' => 'USD', 'condition' => 'new', 'seller' => 'Shop A', 'url' => 'https://shop-a.example/840-g8'],
            ['amount' => 1300, 'currency' => 'USD', 'condition' => 'new', 'seller' => 'Shop B', 'url' => 'https://shop-b.example/840-g8'],
            // Not in the search results, so it must be discarded.
            ['amount' => 5, 'currency' => 'USD', 'condition' => 'new', 'seller' => 'Made up', 'url' => 'https://invented.example/x'],
        ]), ['https://shop-a.example/840-g8', 'https://www.shop-b.example/840-g8/'])),
    ]);

    $user = researchUser(['assets.research']);
    $asset = researchAsset($user);

    ResearchAssetJob::dispatchSync($asset->id, 'full', $user->id);

    $suggestion = AssetSuggestion::sole();

    expect($suggestion->status)->toBe('pending')
        ->and($suggestion->error_message)->toBeNull()
        ->and($suggestion->requested_by)->toBe($user->id)
        ->and($suggestion->confidence)->toBe('medium')
        ->and($suggestion->payload['category'])->toBe('laptop')
        ->and($suggestion->payload['specifications']['ram_gb'])->toBe(16)
        ->and($suggestion->payload['specifications'])->toHaveKey('gpu') // every schema field, null when unknown
        ->and($suggestion->payload['price']['low'])->toEqual(1200)
        ->and($suggestion->payload['price']['high'])->toEqual(1300)
        ->and($suggestion->payload['price']['price_type'])->toBe('range')
        ->and($suggestion->payload['price']['discarded_offers'])->toBe(1);

    $asset->refresh();
    expect($asset->specifications)->toBeNull()
        ->and($asset->market_price_low)->toBeNull();

    Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'test-key')
        && $request['tools'][0]['type'] === 'web_search_20260209');
});

test('research without a model number is flagged and marked low confidence', function () {
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(claudeResponse(specAnswer(['model' => null, 'variants' => ['ProBook 450 G9', 'EliteBook 840 G8']]), ['https://www.hp.com/elitebook-840-g8']))
        ->push(claudeResponse(priceAnswer([
            ['amount' => 900, 'currency' => 'USD', 'condition' => 'new', 'seller' => 'Shop A', 'url' => 'https://shop-a.example/hp-laptop'],
        ]), ['https://shop-a.example/hp-laptop'])),
    ]);

    $user = researchUser(['assets.research']);
    $asset = researchAsset($user, ['model_number' => null, 'part_number' => null, 'serial_number' => 'N/A']);

    ResearchAssetJob::dispatchSync($asset->id, 'full', $user->id);

    $suggestion = AssetSuggestion::sole();

    expect($suggestion->error_message)->toBeNull()
        ->and($suggestion->confidence)->toBe('low')
        ->and($suggestion->payload['flags'])->toContain('no_model_number');

    Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], '"brand": "HP"')
        && str_contains($request['messages'][0]['content'], 'Laptop Computer')
        && str_contains($request['messages'][0]['content'], 'No model or part number is recorded')
        && ! str_contains($request['messages'][0]['content'], 'serial_number'));
});

test('a refusal is stored as a failed suggestion', function () {
    Http::fake(['api.anthropic.com/*' => Http::response([
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'stop_reason' => 'refusal',
        'stop_details' => ['type' => 'refusal', 'category' => 'cyber'],
        'content' => [],
    ])]);

    $user = researchUser(['assets.research']);
    $asset = researchAsset($user);

    ResearchAssetJob::dispatchSync($asset->id, 'full', $user->id);

    $suggestion = AssetSuggestion::sole();

    expect($suggestion->payload)->toBeNull()
        ->and($suggestion->error_message)->toContain('declined');
});

test('a response that does not match the schema is stored as a failure after the last attempt', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(
        claudeResponse(specAnswer(['specifications' => ['ram_gb' => '16 GB']]), []),
    )]);

    $user = researchUser(['assets.research']);
    $asset = researchAsset($user);

    $job = new ResearchAssetJob($asset->id, 'full', $user->id);
    $job->tries = 1;
    $job->handle(app(\App\Services\AssetResearchService::class));

    expect(AssetSuggestion::sole()->error_message)->toContain('expected format');
});

/* =========================================================
   Reviewing
========================================================= */

function pendingSuggestion(Asset $asset, array $payload = []): AssetSuggestion
{
    return AssetSuggestion::create([
        'asset_id' => $asset->id,
        'mode' => 'full',
        'status' => 'pending',
        'confidence' => 'high',
        'payload' => array_replace([
            'category' => 'laptop',
            'model' => 'EliteBook 840 G8 Notebook PC',
            'manufacturer' => 'HP',
            'variants' => [],
            'specifications' => app(\App\Services\AssetResearchService::class)
                ->normalizeSpecifications(['processor' => 'Intel Core i5', 'ram_gb' => 16], 'laptop'),
            'price' => [
                'amount' => 1200.0, 'low' => 1200.0, 'high' => 1200.0, 'currency' => 'USD',
                'price_type' => 'new', 'retrieved_at' => now()->toIso8601String(),
            ],
            'flags' => [],
            'notes' => [],
        ], $payload),
    ]);
}

test('approving applies specs and price to the asset', function () {
    $reviewer = researchUser(['assets.review-suggestions']);
    $asset = researchAsset($reviewer);
    $suggestion = pendingSuggestion($asset);

    $this->actingAs($reviewer)
        ->postJson(route('suggestions.approve', $suggestion), [
            'payload' => ['specifications' => ['ram_gb' => 32], 'price' => ['amount' => 1150]],
        ])
        ->assertOk()
        ->assertJsonPath('current.specifications.ram_gb', 32);

    $asset->refresh();

    expect($asset->specifications['ram_gb'])->toBe(32)
        ->and($asset->specifications['processor'])->toBe('Intel Core i5')
        ->and((float) $asset->market_price_amount)->toBe(1150.0)
        ->and($asset->market_price_currency)->toBe('USD')
        // The asset already had a model number, so it is kept.
        ->and($asset->model_number)->toBe('EliteBook 840 G8')
        ->and($suggestion->fresh()->status)->toBe('approved')
        ->and($suggestion->fresh()->reviewed_by)->toBe($reviewer->id);
});

test('reviewer edits are validated against the spec schema', function () {
    $reviewer = researchUser(['assets.review-suggestions']);
    $asset = researchAsset($reviewer);
    $suggestion = pendingSuggestion($asset);

    $this->actingAs($reviewer)
        ->postJson(route('suggestions.approve', $suggestion), [
            'payload' => ['specifications' => ['ram_gb' => 'lots']],
        ])
        ->assertUnprocessable();

    expect($suggestion->fresh()->status)->toBe('pending');
});

test('a suggestion can only be reviewed once', function () {
    $reviewer = researchUser(['assets.review-suggestions']);
    $asset = researchAsset($reviewer);
    $suggestion = pendingSuggestion($asset);

    $this->actingAs($reviewer)->postJson(route('suggestions.reject', $suggestion))->assertOk();
    $this->actingAs($reviewer)->postJson(route('suggestions.approve', $suggestion))->assertStatus(409);

    expect($suggestion->fresh()->status)->toBe('rejected');
});

test('a failed research result cannot be approved', function () {
    $reviewer = researchUser(['assets.review-suggestions']);
    $asset = researchAsset($reviewer);
    $failed = AssetSuggestion::create([
        'asset_id' => $asset->id,
        'mode' => 'full',
        'status' => 'pending',
        'error_message' => 'Boom',
    ]);

    $this->actingAs($reviewer)
        ->postJson(route('suggestions.approve', $failed))
        ->assertStatus(409);
});

test('researchers cannot approve their own suggestions', function () {
    $researcher = researchUser(['assets.research']);
    $asset = researchAsset($researcher);
    $suggestion = pendingSuggestion($asset);

    $this->actingAs($researcher)
        ->postJson(route('suggestions.approve', $suggestion))
        ->assertForbidden();
});

test('reviewers cannot approve suggestions for another unit\'s asset', function () {
    $owner = researchUser([]);
    $asset = researchAsset($owner);
    $suggestion = pendingSuggestion($asset);
    $outsider = researchUser(['assets.review-suggestions'], otherUnitId());

    $this->actingAs($outsider)
        ->postJson(route('suggestions.approve', $suggestion))
        ->assertForbidden();
});

/* =========================================================
   Listing and identifiers
========================================================= */

test('the suggestions endpoint returns the schema, current values and history', function () {
    $user = researchUser(['assets.research']);
    $asset = researchAsset($user);
    pendingSuggestion($asset);

    $this->actingAs($user)
        ->getJson(route('assets.suggestions.index', $asset))
        ->assertOk()
        ->assertJsonPath('category', 'laptop')
        ->assertJsonPath('can_research', true)
        ->assertJsonPath('can_review', false)
        ->assertJsonPath('current.model_number', 'EliteBook 840 G8')
        ->assertJsonCount(1, 'suggestions')
        ->assertJsonStructure(['schema' => ['processor', 'ram_gb']]);
});

test('the AI research page lists assets of every type', function () {
    $user = researchUser(['assets.research']);
    $laptop = researchAsset($user);
    $ups = researchAsset($user, ['_type' => 'UPS']);
    $dispenser = researchAsset($user, ['_type' => 'WDP']);

    $this->actingAs($user)
        ->get(route('asset-research.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AssetResearch/Index')
            ->where('assets.total', 3)
            ->where('otherTypeMatches', null));
});

test('researchers can set the model and part number only', function () {
    $user = researchUser(['assets.research']);
    $asset = researchAsset($user, ['model_number' => null]);

    $this->actingAs($user)
        ->patchJson(route('asset-research.identifiers', $asset), [
            'model_number' => '  ProBook 450 G9 ',
            'part_number' => '',
            'brand' => 'Hacked',
        ])
        ->assertOk();

    $asset->refresh();

    expect($asset->model_number)->toBe('ProBook 450 G9')
        ->and($asset->part_number)->toBeNull()
        ->and($asset->brand)->toBe('HP');
});
