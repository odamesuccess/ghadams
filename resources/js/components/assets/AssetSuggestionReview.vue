<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type {
    AssetSuggestion,
    CurrentValues,
    SpecField,
    SpecValue,
    Specifications,
    SuggestionEdits,
} from '@/composables/useAssetResearch';

/* ======================= Props / Emits ======================= */
const props = defineProps<{
    suggestion: AssetSuggestion;
    schema: Record<string, SpecField>;
    current: CurrentValues;
    canReview: boolean;
    busy: boolean;
}>();

const emit = defineEmits<{
    approve: [edits: SuggestionEdits];
    reject: [];
}>();

const payload = computed(() => props.suggestion.payload);
const isPriceOnly = computed(() => props.suggestion.mode === 'price_only');
const suggestedSpecs = computed<Specifications>(
    () => payload.value?.specifications ?? {},
);
const price = computed(() => payload.value?.price ?? null);
const isPending = computed(() => props.suggestion.status === 'pending');

/* ======================= Editable state ======================= */
// Inputs hold strings; they are converted back to typed values on approve.
const model = ref('');
const specInputs = reactive<Record<string, string>>({});
const priceInputs = reactive({
    amount: '',
    low: '',
    high: '',
    currency: '',
    price_type: '',
});
const formError = ref<string | null>(null);

const toInput = (value: SpecValue | undefined, field?: SpecField): string => {
    if (value === null || value === undefined) return '';
    if (Array.isArray(value)) return value.join(', ');
    if (field?.type === 'boolean') return value ? 'true' : 'false';
    return String(value);
};

function resetInputs(): void {
    model.value = payload.value?.model ?? '';

    for (const [key, field] of Object.entries(props.schema)) {
        specInputs[key] = toInput(suggestedSpecs.value[key], field);
    }

    const p = price.value;
    priceInputs.amount = p?.amount != null ? String(p.amount) : '';
    priceInputs.low = p?.low != null ? String(p.low) : '';
    priceInputs.high = p?.high != null ? String(p.high) : '';
    priceInputs.currency = p?.currency ?? '';
    priceInputs.price_type = p?.price_type ?? '';
    formError.value = null;
}

watch(() => props.suggestion.id, resetInputs, { immediate: true });

/* ======================= Conversion ======================= */
function parseNumber(raw: string, label: string): number | null {
    const trimmed = raw.trim();
    if (trimmed === '') return null;
    const value = Number(trimmed);
    if (!Number.isFinite(value) || value < 0)
        throw new Error(`${label} must be a positive number.`);
    return value;
}

function fromInput(raw: string, field: SpecField, key: string): SpecValue {
    const trimmed = raw.trim();
    const label = field.label ?? key;

    switch (field.type) {
        case 'number':
            return parseNumber(trimmed, label);
        case 'boolean':
            return trimmed === '' ? null : trimmed === 'true';
        case 'list': {
            const items = trimmed
                .split(',')
                .map((s) => s.trim())
                .filter(Boolean);
            return items.length ? items : null;
        }
        default:
            return trimmed === '' ? null : trimmed;
    }
}

function buildEdits(): SuggestionEdits {
    const edits: SuggestionEdits = { model: model.value.trim() || null };

    if (!isPriceOnly.value && payload.value?.specifications) {
        edits.specifications = Object.fromEntries(
            Object.entries(props.schema).map(([key, field]) => [
                key,
                fromInput(specInputs[key] ?? '', field, key),
            ]),
        );
    }

    if (price.value) {
        const currency = priceInputs.currency.trim().toUpperCase();
        if (currency && !/^[A-Z]{3}$/.test(currency))
            throw new Error(
                'Currency must be a 3-letter code such as GHS or USD.',
            );

        edits.price = {
            amount: parseNumber(priceInputs.amount, 'Price'),
            low: parseNumber(priceInputs.low, 'Lowest price'),
            high: parseNumber(priceInputs.high, 'Highest price'),
            currency: currency || null,
            price_type: priceInputs.price_type || null,
        };
    }

    return edits;
}

function onApprove(): void {
    formError.value = null;

    try {
        emit('approve', buildEdits());
    } catch (error) {
        formError.value =
            error instanceof Error
                ? error.message
                : 'Please check the edited values.';
    }
}

/* ======================= Display helpers ======================= */
const fieldLabel = (key: string, field: SpecField) =>
    `${field.label ?? key.replaceAll('_', ' ')}${field.unit ? ` (${field.unit})` : ''}`;

function formatValue(value: SpecValue | undefined, field?: SpecField): string {
    if (value === null || value === undefined || value === '') return '—';
    if (Array.isArray(value)) return value.length ? value.join(', ') : '—';
    if (typeof value === 'boolean' || field?.type === 'boolean')
        return value ? 'Yes' : 'No';
    return `${value}${field?.unit ? ` ${field.unit}` : ''}`;
}

const changed = (key: string, field: SpecField) =>
    formatValue(props.current.specifications?.[key], field) !==
    formatValue(suggestedSpecs.value[key], field);

function formatMoney(
    value: number | string | null | undefined,
    currency: string | null | undefined,
): string {
    if (value === null || value === undefined || value === '') return '—';
    const amount = Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    return currency ? `${currency} ${amount}` : amount;
}

const currentPrice = computed(() => {
    const p = props.current.price;
    if (p.amount != null) return formatMoney(p.amount, p.currency);
    if (p.low != null)
        return `${formatMoney(p.low, p.currency)} – ${formatMoney(p.high, null)}`;
    return '—';
});

function formatDate(value: string | null | undefined): string {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/** Only http(s) links are rendered, whatever the stored value is. */
const safeUrl = (url: string | null | undefined): string | null =>
    url && /^https?:\/\//i.test(url) ? url : null;

function hostname(url: string): string {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}

/* ======================= Badges ======================= */
const confidenceClass = computed(
    () =>
        ({
            high: 'border-emerald-700 bg-emerald-900/40 text-emerald-300',
            medium: 'border-amber-700 bg-amber-900/40 text-amber-300',
            low: 'border-red-700 bg-red-900/40 text-red-300',
        })[props.suggestion.confidence ?? 'low'],
);

const FLAG_LABELS: Record<string, { text: string; tone: 'warn' | 'info' }> = {
    single_price_source: { text: 'Only one price source', tone: 'warn' },
    implausible_price: { text: 'Price looks implausible', tone: 'warn' },
    mixed_currencies: {
        text: 'Prices found in several currencies',
        tone: 'warn',
    },
    converted_to_ghs: {
        text: 'Converted to cedis from a price abroad',
        tone: 'warn',
    },
    no_exchange_rate: {
        text: 'Prices found abroad, but no exchange rate to convert them',
        tone: 'info',
    },
    ambiguous_model: {
        text: 'Model number matches several products',
        tone: 'warn',
    },
    no_model_number: {
        text: 'No model number: based on type and brand only',
        tone: 'warn',
    },
    no_price_found: { text: 'No price found', tone: 'info' },
    specs_reused: {
        text: 'Specs reused from an approved asset with the same model',
        tone: 'info',
    },
};

const badges = computed(() => {
    const list = (payload.value?.flags ?? [])
        .filter((flag) => FLAG_LABELS[flag])
        .map((flag) => FLAG_LABELS[flag]);

    if (props.suggestion.confidence === 'low')
        list.unshift({ text: 'Low confidence: check carefully', tone: 'warn' });

    return list;
});

const noData = computed(() => {
    const specsEmpty = Object.values(suggestedSpecs.value).every(
        (v) => v === null || (Array.isArray(v) && !v.length),
    );
    return specsEmpty && !price.value && !payload.value?.model;
});
</script>

<template>
    <div
        class="space-y-5 rounded-xl border border-slate-800 bg-slate-950/60 p-4"
    >
        <!-- Header -->
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-white">
                {{ isPriceOnly ? 'Price refresh' : 'AI suggestion' }} #{{
                    suggestion.id
                }}
            </span>
            <span
                class="rounded-full border px-2 py-0.5 text-xs font-medium capitalize"
                :class="confidenceClass"
            >
                {{ suggestion.confidence ?? 'unknown' }} confidence
            </span>
            <span
                v-for="badge in badges"
                :key="badge.text"
                class="rounded-full border px-2 py-0.5 text-xs"
                :class="
                    badge.tone === 'warn'
                        ? 'border-amber-700 bg-amber-900/30 text-amber-200'
                        : 'border-slate-700 bg-slate-800/60 text-slate-300'
                "
            >
                {{ badge.tone === 'warn' ? '⚠ ' : '' }}{{ badge.text }}
            </span>
            <span class="ml-auto text-xs text-slate-400">
                Retrieved {{ formatDate(suggestion.retrieved_at) }}
                <template v-if="price?.currency">
                    · Currency {{ price.currency }}</template
                >
            </span>
        </div>

        <!-- Ambiguous model variants -->
        <div
            v-if="payload?.variants?.length"
            class="rounded-lg border border-amber-800 bg-amber-950/30 p-3 text-sm text-amber-100"
        >
            <p class="font-medium">This model number could be any of:</p>
            <ul class="mt-1 list-disc pl-5 text-amber-200/90">
                <li v-for="variant in payload.variants" :key="variant">
                    {{ variant }}
                </li>
            </ul>
            <p class="mt-1 text-xs text-amber-300/80">
                Only specs shared by all of them were filled in. Add a part
                number for an exact match.
            </p>
        </div>

        <!-- Empty result -->
        <div
            v-if="noData"
            class="rounded-lg border border-slate-800 bg-slate-900/40 p-4 text-sm text-slate-300"
        >
            <p class="font-medium text-white">No reliable data found.</p>
            <p class="mt-1 text-slate-400">
                The AI could not confirm this product's specifications or price
                from trustworthy sources. Check the model or part number and try
                again.
            </p>
        </div>

        <template v-else>
            <!-- Comparison table -->
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-800 text-left text-xs tracking-wide text-slate-400 uppercase"
                        >
                            <th class="w-1/4 py-2 pr-3 font-semibold">Field</th>
                            <th class="w-1/3 py-2 pr-3 font-semibold">
                                Current
                            </th>
                            <th class="py-2 font-semibold">
                                Suggested
                                <span
                                    v-if="isPending && canReview"
                                    class="text-slate-500 normal-case"
                                    >(editable)</span
                                >
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <!-- Identity -->
                        <template v-if="!isPriceOnly">
                            <tr>
                                <td
                                    colspan="3"
                                    class="pt-4 pb-1 text-xs font-semibold tracking-wide text-indigo-300 uppercase"
                                >
                                    Product
                                </td>
                            </tr>
                            <tr class="border-b border-slate-900">
                                <td class="py-2 pr-3 text-slate-300">
                                    Model number
                                </td>
                                <td class="py-2 pr-3 text-slate-400">
                                    {{ current.model_number || '—' }}
                                </td>
                                <td class="py-2">
                                    <input
                                        v-if="isPending && canReview"
                                        v-model="model"
                                        class="w-full rounded-md border border-slate-700 bg-slate-900 px-2 py-1 text-white"
                                    />
                                    <span v-else class="text-white">{{
                                        payload?.model || '—'
                                    }}</span>
                                    <p
                                        v-if="current.model_number"
                                        class="mt-1 text-xs text-slate-500"
                                    >
                                        Saved only if the asset has no model
                                        number.
                                    </p>
                                </td>
                            </tr>
                            <tr class="border-b border-slate-900">
                                <td class="py-2 pr-3 text-slate-300">
                                    Manufacturer
                                </td>
                                <td class="py-2 pr-3 text-slate-400">
                                    {{ current.brand || '—' }}
                                </td>
                                <td class="py-2 text-white">
                                    {{ payload?.manufacturer || '—' }}
                                    <span class="ml-1 text-xs text-slate-500"
                                        >(for reference, not saved)</span
                                    >
                                </td>
                            </tr>

                            <!-- Specifications -->
                            <tr>
                                <td
                                    colspan="3"
                                    class="pt-4 pb-1 text-xs font-semibold tracking-wide text-indigo-300 uppercase"
                                >
                                    Specifications
                                </td>
                            </tr>
                            <tr
                                v-for="(field, key) in schema"
                                :key="key"
                                class="border-b border-slate-900"
                            >
                                <td class="py-2 pr-3 text-slate-300 capitalize">
                                    {{ fieldLabel(String(key), field) }}
                                </td>
                                <td class="py-2 pr-3 text-slate-400">
                                    {{
                                        formatValue(
                                            current.specifications?.[key],
                                            field,
                                        )
                                    }}
                                </td>
                                <td class="py-2">
                                    <template v-if="isPending && canReview">
                                        <select
                                            v-if="field.type === 'boolean'"
                                            v-model="specInputs[key]"
                                            class="w-full rounded-md border border-slate-700 bg-slate-900 px-2 py-1 text-white"
                                        >
                                            <option value="">Unknown</option>
                                            <option value="true">Yes</option>
                                            <option value="false">No</option>
                                        </select>
                                        <select
                                            v-else-if="field.type === 'enum'"
                                            v-model="specInputs[key]"
                                            class="w-full rounded-md border border-slate-700 bg-slate-900 px-2 py-1 text-white capitalize"
                                        >
                                            <option value="">Unknown</option>
                                            <option
                                                v-for="option in field.options"
                                                :key="option"
                                                :value="option"
                                            >
                                                {{ option }}
                                            </option>
                                        </select>
                                        <div
                                            v-else
                                            class="flex items-center gap-2"
                                        >
                                            <input
                                                v-model="specInputs[key]"
                                                :type="
                                                    field.type === 'number'
                                                        ? 'number'
                                                        : 'text'
                                                "
                                                :step="
                                                    field.type === 'number'
                                                        ? 'any'
                                                        : undefined
                                                "
                                                min="0"
                                                :placeholder="
                                                    field.type === 'list'
                                                        ? 'Comma separated'
                                                        : 'Unknown'
                                                "
                                                class="w-full rounded-md border px-2 py-1 text-white"
                                                :class="
                                                    changed(String(key), field)
                                                        ? 'border-indigo-600 bg-indigo-950/40'
                                                        : 'border-slate-700 bg-slate-900'
                                                "
                                            />
                                            <span
                                                v-if="field.unit"
                                                class="shrink-0 text-xs text-slate-500"
                                                >{{ field.unit }}</span
                                            >
                                        </div>
                                    </template>
                                    <span v-else class="text-white">{{
                                        formatValue(suggestedSpecs[key], field)
                                    }}</span>
                                </td>
                            </tr>
                        </template>

                        <!-- Price -->
                        <tr>
                            <td
                                colspan="3"
                                class="pt-4 pb-1 text-xs font-semibold tracking-wide text-indigo-300 uppercase"
                            >
                                Market price
                            </td>
                        </tr>
                        <tr v-if="!price" class="border-b border-slate-900">
                            <td class="py-2 pr-3 text-slate-300">Price</td>
                            <td class="py-2 pr-3 text-slate-400">
                                {{ currentPrice }}
                            </td>
                            <td class="py-2 text-slate-400">
                                No verified price found. The current price will
                                not change.
                            </td>
                        </tr>
                        <template v-else>
                            <tr class="border-b border-slate-900">
                                <td class="py-2 pr-3 text-slate-300">Price</td>
                                <td class="py-2 pr-3 text-slate-400">
                                    {{ currentPrice }}
                                    <span
                                        v-if="current.price.retrieved_at"
                                        class="block text-xs text-slate-500"
                                        >as of
                                        {{
                                            formatDate(
                                                current.price.retrieved_at,
                                            )
                                        }}</span
                                    >
                                </td>
                                <td class="py-2">
                                    <div
                                        v-if="isPending && canReview"
                                        class="grid grid-cols-2 gap-2 sm:grid-cols-3"
                                    >
                                        <label class="text-xs text-slate-400"
                                            >Single price
                                            <input
                                                v-model="priceInputs.amount"
                                                type="number"
                                                step="any"
                                                min="0"
                                                class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900 px-2 py-1 text-sm text-white"
                                            />
                                        </label>
                                        <label class="text-xs text-slate-400"
                                            >Lowest
                                            <input
                                                v-model="priceInputs.low"
                                                type="number"
                                                step="any"
                                                min="0"
                                                class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900 px-2 py-1 text-sm text-white"
                                            />
                                        </label>
                                        <label class="text-xs text-slate-400"
                                            >Highest
                                            <input
                                                v-model="priceInputs.high"
                                                type="number"
                                                step="any"
                                                min="0"
                                                class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900 px-2 py-1 text-sm text-white"
                                            />
                                        </label>
                                        <label class="text-xs text-slate-400"
                                            >Currency (as found)
                                            <input
                                                v-model="priceInputs.currency"
                                                maxlength="3"
                                                class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900 px-2 py-1 text-sm text-white uppercase"
                                            />
                                        </label>
                                        <label class="text-xs text-slate-400"
                                            >Price type
                                            <select
                                                v-model="priceInputs.price_type"
                                                class="mt-1 w-full rounded-md border border-slate-700 bg-slate-900 px-2 py-1 text-sm text-white"
                                            >
                                                <option value="">
                                                    Unknown
                                                </option>
                                                <option value="new">New</option>
                                                <option value="used">
                                                    Used
                                                </option>
                                                <option value="refurbished">
                                                    Refurbished
                                                </option>
                                                <option value="range">
                                                    Range
                                                </option>
                                            </select>
                                        </label>
                                    </div>
                                    <div v-else class="text-white">
                                        <template v-if="price.amount != null">{{
                                            formatMoney(
                                                price.amount,
                                                price.currency,
                                            )
                                        }}</template>
                                        <template v-else
                                            >{{
                                                formatMoney(
                                                    price.low,
                                                    price.currency,
                                                )
                                            }}
                                            –
                                            {{
                                                formatMoney(price.high, null)
                                            }}</template
                                        >
                                        <span
                                            class="ml-1 text-xs text-slate-400 capitalize"
                                            >({{
                                                price.price_type ?? 'unknown'
                                            }})</span
                                        >
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Based on
                                        {{ price.sources_count ?? 0 }} listing{{
                                            price.sources_count === 1
                                                ? ''
                                                : 's'
                                        }}<template
                                            v-if="price.discarded_offers"
                                            >;
                                            {{ price.discarded_offers }}
                                            unverified listing{{
                                                price.discarded_offers === 1
                                                    ? ' was'
                                                    : 's were'
                                            }}
                                            ignored</template
                                        >.
                                    </p>
                                    <p
                                        v-for="rate in price.converted_from ??
                                        []"
                                        :key="rate.currency"
                                        class="mt-1 text-xs text-amber-300/90"
                                    >
                                        Converted from {{ rate.currency }} at 1
                                        {{ rate.currency }} =
                                        {{ rate.ghs_per_unit }} GHS
                                        <template
                                            v-if="rate.source === 'configured'"
                                            >(fixed rate set by the
                                            administrator)</template
                                        >
                                        <template v-else>
                                            <template v-if="rate.date"
                                                >({{ rate.date }})</template
                                            >
                                            ·
                                            <a
                                                v-if="
                                                    rate.url &&
                                                    safeUrl(rate.url)
                                                "
                                                :href="safeUrl(rate.url)!"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-indigo-300 underline-offset-2 hover:underline"
                                                >{{ hostname(rate.url) }}</a
                                            >
                                        </template>
                                    </p>
                                </td>
                            </tr>
                            <tr v-if="price.offers?.length">
                                <td class="py-2 pr-3 align-top text-slate-300">
                                    Listings
                                </td>
                                <td colspan="2" class="py-2">
                                    <ul class="space-y-1 text-xs">
                                        <li
                                            v-for="offer in price.offers"
                                            :key="offer.url"
                                            class="text-slate-300"
                                        >
                                            {{
                                                formatMoney(
                                                    offer.amount,
                                                    offer.currency,
                                                )
                                            }}
                                            <span
                                                v-if="
                                                    offer.amount_ghs != null &&
                                                    offer.currency !== 'GHS'
                                                "
                                                class="text-slate-400"
                                                >(≈
                                                {{
                                                    formatMoney(
                                                        offer.amount_ghs,
                                                        'GHS',
                                                    )
                                                }})</span
                                            >
                                            <span
                                                v-if="offer.condition"
                                                class="text-slate-500 capitalize"
                                                >· {{ offer.condition }}</span
                                            >
                                            ·
                                            <a
                                                v-if="safeUrl(offer.url)"
                                                :href="safeUrl(offer.url)!"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-indigo-300 underline-offset-2 hover:underline"
                                                >{{
                                                    offer.seller ||
                                                    hostname(offer.url)
                                                }}</a
                                            >
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- Notes -->
        <div
            v-if="payload?.notes?.length"
            class="rounded-lg bg-slate-900/60 p-3 text-xs text-slate-300"
        >
            <p class="font-semibold tracking-wide text-slate-400 uppercase">
                AI notes
            </p>
            <p
                v-for="(note, i) in payload.notes"
                :key="i"
                class="mt-1 whitespace-pre-line"
            >
                {{ note }}
            </p>
        </div>

        <!-- Sources -->
        <div v-if="suggestion.sources.length">
            <p
                class="text-xs font-semibold tracking-wide text-slate-400 uppercase"
            >
                Sources
            </p>
            <ul class="mt-2 space-y-1 text-sm">
                <li
                    v-for="source in suggestion.sources"
                    :key="source.url"
                    class="flex flex-wrap items-baseline gap-x-2"
                >
                    <a
                        v-if="safeUrl(source.url)"
                        :href="safeUrl(source.url)!"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-indigo-300 underline-offset-2 hover:underline"
                        >{{ hostname(source.url) }} ↗</a
                    >
                    <span v-if="source.note" class="text-xs text-slate-400">{{
                        source.note
                    }}</span>
                    <span
                        v-if="source.verified === false"
                        class="text-xs text-amber-300"
                        >(not seen in search results)</span
                    >
                </li>
            </ul>
        </div>

        <!-- Actions / review status -->
        <p v-if="formError" class="text-sm text-red-400">{{ formError }}</p>

        <div
            v-if="isPending && canReview"
            class="flex flex-wrap justify-end gap-3 border-t border-slate-800 pt-4"
        >
            <Button variant="outline" :disabled="busy" @click="emit('reject')"
                >Reject</Button
            >
            <Button v-if="!noData" :disabled="busy" @click="onApprove">
                <Spinner v-if="busy" />
                Approve and apply
            </Button>
        </div>
        <p
            v-else-if="isPending"
            class="border-t border-slate-800 pt-3 text-sm text-slate-400"
        >
            Waiting for a reviewer with permission to approve AI suggestions.
        </p>
        <p v-else class="border-t border-slate-800 pt-3 text-sm text-slate-400">
            <span class="capitalize">{{ suggestion.status }}</span>
            <template v-if="suggestion.reviewer">
                by {{ suggestion.reviewer.name }}</template
            >
            <template v-if="suggestion.reviewed_at">
                on {{ formatDate(suggestion.reviewed_at) }}</template
            >.
        </p>
    </div>
</template>
