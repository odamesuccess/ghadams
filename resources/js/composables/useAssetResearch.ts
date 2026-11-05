import { computed, onBeforeUnmount, ref } from 'vue';

import * as assetRoutes from '@/routes/assets';
import * as assetSuggestionRoutes from '@/routes/assets/suggestions';
import * as suggestionRoutes from '@/routes/suggestions';

/* ======================= Types ======================= */

export type ResearchMode = 'full' | 'price_only';
export type Confidence = 'low' | 'medium' | 'high';

export interface SpecField {
    type: 'string' | 'number' | 'boolean' | 'enum' | 'list';
    label?: string;
    unit?: string;
    options?: string[];
}

export type SpecValue = string | number | boolean | string[] | null;
export type Specifications = Record<string, SpecValue>;

export interface PriceOffer {
    amount: number;
    currency: string;
    condition: string | null;
    seller: string | null;
    url: string;
    /** The listed amount in Ghana cedis (null when no exchange rate was available). */
    amount_ghs?: number | null;
}

export interface ExchangeRateUsed {
    currency: string;
    ghs_per_unit: number;
    url: string | null;
    date: string | null;
    source: 'web' | 'configured' | 'same';
}

export interface PriceSummary {
    amount: number | null;
    low: number | null;
    high: number | null;
    currency: string | null;
    price_type: string | null;
    condition?: string | null;
    sources_count?: number;
    discarded_offers?: number;
    retrieved_at?: string | null;
    offers?: PriceOffer[];
    /** Currencies converted into cedis for this price, with the rates used. */
    converted_from?: ExchangeRateUsed[];
    edited_by_reviewer?: boolean;
}

export interface SuggestionPayload {
    category: string;
    model: string | null;
    manufacturer: string | null;
    variants: string[];
    specifications: Specifications | null;
    specifications_reused_from_asset_id: number | null;
    price: PriceSummary | null;
    flags: string[];
    notes: string[];
}

export interface SuggestionSource {
    url: string;
    note: string | null;
    verified?: boolean;
}

export interface AssetSuggestion {
    id: number;
    mode: ResearchMode;
    status: 'pending' | 'approved' | 'rejected';
    confidence: Confidence | null;
    payload: SuggestionPayload | null;
    sources: SuggestionSource[];
    error_message: string | null;
    retrieved_at: string | null;
    created_at: string | null;
    reviewed_at: string | null;
    reviewer: { id: number; name: string } | null;
    requester: { id: number; name: string } | null;
}

export interface CurrentValues {
    brand: string | null;
    model_number: string | null;
    part_number: string | null;
    serial_number: string | null;
    specifications: Specifications | null;
    price: {
        amount: number | string | null;
        low: number | string | null;
        high: number | string | null;
        currency: string | null;
        price_type: string | null;
        retrieved_at: string | null;
    };
}

export interface SuggestionsResponse {
    category: string | null;
    schema: Record<string, SpecField>;
    current: CurrentValues;
    can_research: boolean;
    can_review: boolean;
    suggestions: AssetSuggestion[];
}

export interface SuggestionEdits {
    model?: string | null;
    specifications?: Specifications;
    price?: Partial<
        Pick<
            PriceSummary,
            'amount' | 'low' | 'high' | 'currency' | 'price_type'
        >
    >;
}

class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
    ) {
        super(message);
    }
}

/* ======================= HTTP ======================= */

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

async function request<T>(
    url: string,
    method: 'GET' | 'POST' | 'PATCH' = 'GET',
    body?: unknown,
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        // Research results change in the background; never reuse a cached answer.
        cache: 'no-store',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(method !== 'GET'
                ? {
                      'Content-Type': 'application/json',
                      'X-XSRF-TOKEN': xsrfToken(),
                  }
                : {}),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    const data = await response.json().catch(() => null);

    if (!response.ok) {
        throw new ApiError(
            errorMessage(response.status, data),
            response.status,
        );
    }

    return data as T;
}

function errorMessage(status: number, data: any): string {
    if (status === 429)
        return 'Too many research requests. Please wait a minute and try again.';
    if (status === 403)
        return 'You do not have permission to do this for this asset.';
    if (status === 419)
        return 'Your session has expired. Reload the page and try again.';

    const firstFieldError = data?.errors
        ? Object.values(data.errors).flat()[0]
        : null;

    return (
        (firstFieldError as string) ||
        data?.message ||
        `Request failed (HTTP ${status}).`
    );
}

/**
 * Save the model / part number used for research (AI Research page).
 */
export function saveAssetIdentifiers(
    url: string,
    identifiers: { model_number: string | null; part_number: string | null },
): Promise<{ model_number: string | null; part_number: string | null }> {
    return request(url, 'PATCH', identifiers);
}

/* ======================= Composable ======================= */

const POLL_INTERVAL_MS = 4000;
/** After this, show the "taking longer" notice and poll less often. */
const SLOW_AFTER_MS = 4 * 60 * 1000;
const SLOW_POLL_INTERVAL_MS = 15000;
/** Stop polling entirely after this (the job's retries can run ~10 minutes). */
const POLL_TIMEOUT_MS = 15 * 60 * 1000;

export function useAssetResearch(
    assetId: number,
    options: { onResult?: (suggestion: AssetSuggestion) => void } = {},
) {
    const data = ref<SuggestionsResponse | null>(null);
    const loading = ref(false);
    const loadError = ref<string | null>(null);

    const researching = ref<ResearchMode | null>(null);
    const researchError = ref<string | null>(null);
    const notice = ref<string | null>(null);
    const timedOut = ref(false);
    const reviewing = ref(false);

    let pollTimer: ReturnType<typeof setTimeout> | null = null;

    const suggestions = computed(() => data.value?.suggestions ?? []);

    /** The newest pending suggestion with a result: what the reviewer acts on. */
    const reviewable = computed(
        () =>
            suggestions.value.find(
                (s) => s.status === 'pending' && !s.error_message,
            ) ?? null,
    );

    /** The newest attempt, when it failed and nobody has dismissed it yet. */
    const latestFailure = computed(() => {
        const newest = suggestions.value[0];
        return newest && newest.status === 'pending' && newest.error_message
            ? newest
            : null;
    });

    const history = computed(() =>
        suggestions.value.filter(
            (s) =>
                s.id !== reviewable.value?.id &&
                s.id !== latestFailure.value?.id,
        ),
    );

    async function load(): Promise<void> {
        loading.value = data.value === null;
        loadError.value = null;

        try {
            data.value = await request<SuggestionsResponse>(
                assetSuggestionRoutes.index(assetId).url,
            );
        } catch (error) {
            loadError.value =
                error instanceof Error
                    ? error.message
                    : 'Could not load AI suggestions.';
        } finally {
            loading.value = false;
        }
    }

    function stopPolling(): void {
        if (pollTimer) clearTimeout(pollTimer);
        pollTimer = null;
    }

    function poll(
        mode: ResearchMode,
        requestedAt: number,
        startedAt: number,
    ): void {
        const slow = Date.now() - startedAt > SLOW_AFTER_MS;

        pollTimer = setTimeout(
            async () => {
                await load();

                const arrived = suggestions.value.find(
                    (s) =>
                        s.mode === mode &&
                        s.created_at !== null &&
                        Date.parse(s.created_at) >= requestedAt,
                );

                if (arrived) {
                    researching.value = null;
                    timedOut.value = false;
                    options.onResult?.(arrived);
                    return;
                }

                const elapsed = Date.now() - startedAt;

                if (elapsed > POLL_TIMEOUT_MS) {
                    researching.value = null;
                    timedOut.value = true;
                    return;
                }

                // Long runs: free the buttons and show the notice, but keep checking.
                if (elapsed > SLOW_AFTER_MS) {
                    researching.value = null;
                    timedOut.value = true;
                }

                poll(mode, requestedAt, startedAt);
            },
            slow ? SLOW_POLL_INTERVAL_MS : POLL_INTERVAL_MS,
        );
    }

    async function startResearch(
        mode: ResearchMode,
        force = false,
    ): Promise<void> {
        stopPolling();
        researchError.value = null;
        notice.value = null;
        timedOut.value = false;

        try {
            const response = await request<{
                status: string;
                message?: string;
                requested_at?: string;
            }>(assetRoutes.research(assetId).url, 'POST', { mode, force });

            if (response.status === 'existing') {
                notice.value =
                    response.message ??
                    'A recent suggestion is already waiting for review.';
                await load();
                return;
            }

            researching.value = mode;
            // Server timestamps have second precision; allow for that when matching the new row.
            const requestedAt =
                Date.parse(response.requested_at ?? '') - 1000 ||
                Date.now() - 1000;
            poll(mode, requestedAt, Date.now());
        } catch (error) {
            researchError.value =
                error instanceof Error
                    ? error.message
                    : 'Could not start AI research.';
        }
    }

    async function approve(
        suggestion: AssetSuggestion,
        edits: SuggestionEdits,
    ): Promise<CurrentValues | null> {
        reviewing.value = true;

        try {
            const response = await request<{
                current: CurrentValues;
                message: string;
            }>(suggestionRoutes.approve(suggestion.id).url, 'POST', {
                payload: edits,
            });
            await load();
            return response.current;
        } finally {
            reviewing.value = false;
        }
    }

    async function reject(suggestion: AssetSuggestion): Promise<void> {
        reviewing.value = true;

        try {
            await request(suggestionRoutes.reject(suggestion.id).url, 'POST');
            await load();
        } finally {
            reviewing.value = false;
        }
    }

    onBeforeUnmount(stopPolling);

    return {
        data,
        loading,
        loadError,
        researching,
        researchError,
        notice,
        timedOut,
        reviewing,
        suggestions,
        reviewable,
        latestFailure,
        history,
        load,
        startResearch,
        approve,
        reject,
    };
}
