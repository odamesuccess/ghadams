<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Search, Sparkles } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

import AiResearchPanel from '@/components/assets/AiResearchPanel.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { saveAssetIdentifiers } from '@/composables/useAssetResearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { identifiers, index } from '@/routes/asset-research';
import type { BreadcrumbItem } from '@/types';
import { toast } from '@/utils/sweetalert';

/* ======================= Types ======================= */
type StatusFilter = 'pending' | 'failed' | 'none' | 'approved';

interface ResearchRow {
    id: number;
    asset_code: string;
    type: string | null;
    brand: string | null;
    model_number: string | null;
    part_number: string | null;
    unit: string | null;
    has_pending_review: boolean;
    latest: {
        id: number;
        mode: 'full' | 'price_only';
        status: 'pending' | 'approved' | 'rejected';
        confidence: 'low' | 'medium' | 'high' | null;
        failed: boolean;
        created_at: string | null;
    } | null;
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    assets: {
        data: ResearchRow[];
        links: PageLink[];
        total: number;
        from: number | null;
        to: number | null;
        current_page: number;
        last_page: number;
        per_page: number;
    };
    counts: Record<StatusFilter, number>;
    filters: { search?: string; status?: StatusFilter };
    canEditIdentifiers: boolean;
    otherTypeMatches: {
        total: number;
        types: string[];
        example: string | null;
    } | null;
    supportedTypes: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'AI Research', href: index().url },
];

/* ======================= Filters ======================= */
const search = ref(props.filters.search ?? '');
const status = ref<StatusFilter | ''>(props.filters.status ?? '');

const cards: { key: StatusFilter; label: string; tone: string }[] = [
    {
        key: 'pending',
        label: 'Waiting for review',
        tone: 'border-amber-400/20 bg-amber-500/10 text-amber-200',
    },
    {
        key: 'failed',
        label: 'Failed',
        tone: 'border-red-400/20 bg-red-500/10 text-red-200',
    },
    {
        key: 'none',
        label: 'Not researched yet',
        tone: 'border-slate-400/20 bg-slate-500/10 text-slate-200',
    },
    {
        key: 'approved',
        label: 'Approved',
        tone: 'border-emerald-400/20 bg-emerald-500/10 text-emerald-200',
    },
];

function visit(params: { page?: number; per_page?: number } = {}): void {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            status: status.value || undefined,
            per_page: props.assets.per_page,
            ...params,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

/** A new search or filter starts again at page 1. */
function applyFilters(): void {
    visit();
}

/* ======================= Page size (like the shared DataTable) ======================= */
const PAGE_SIZES = [5, 10, 20, 50, 100] as const;
const PAGE_SIZE_KEY = 'ghadams.ai-research.page-size';

function readPageSizeChoice(): 'auto' | number {
    try {
        const saved = Number(localStorage.getItem(PAGE_SIZE_KEY));
        return (PAGE_SIZES as readonly number[]).includes(saved)
            ? saved
            : 'auto';
    } catch {
        return 'auto';
    }
}

const pageSizeChoice = ref<'auto' | number>(readPageSizeChoice());
const tableRoot = ref<HTMLElement | null>(null);

function changePageSize(): void {
    try {
        localStorage.setItem(PAGE_SIZE_KEY, String(pageSizeChoice.value));
    } catch {
        // Storage can be blocked; the choice still applies to this visit.
    }

    if (pageSizeChoice.value === 'auto') {
        queueAutoPageSize();
    } else {
        visit({ per_page: pageSizeChoice.value, page: 1 });
    }
}

/** Rows that fit the space left on screen, as the Asset Management table does. */
function autoPageSize(): number | null {
    const root = tableRoot.value;
    if (!root) return null;

    const height = (selector: string, fallback: number) =>
        root.querySelector(selector)?.getBoundingClientRect().height ??
        fallback;

    const body =
        root.clientHeight -
        height('[data-controls]', 0) -
        height('[data-pagination]', 0) -
        height('thead', 40) -
        4;
    const rowHeight = Math.max(36, height('tbody tr[data-row]', 41));

    return Math.max(5, Math.min(100, Math.floor(body / rowHeight)));
}

let autoTimer: ReturnType<typeof setTimeout> | null = null;

function queueAutoPageSize(): void {
    if (autoTimer) clearTimeout(autoTimer);

    autoTimer = setTimeout(() => {
        // Leave the page alone while a row is open for review.
        if (pageSizeChoice.value !== 'auto' || openId.value !== null) return;

        const size = autoPageSize();
        if (size && size !== props.assets.per_page) {
            visit({ per_page: size, page: 1 });
        }
    }, 200);
}

let resizeObserver: ResizeObserver | null = null;

onMounted(() => {
    if (pageSizeChoice.value !== 'auto') {
        if (pageSizeChoice.value !== props.assets.per_page) {
            visit({ per_page: pageSizeChoice.value });
        }
    } else {
        queueAutoPageSize();
    }

    if (tableRoot.value && typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(queueAutoPageSize);
        resizeObserver.observe(tableRoot.value);
    }
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    if (autoTimer) clearTimeout(autoTimer);
});

function goToPage(page: number): void {
    if (page < 1 || page > props.assets.last_page) return;
    openId.value = null;
    visit({ page });
}

function toggleStatus(key: StatusFilter): void {
    status.value = status.value === key ? '' : key;
    applyFilters();
}

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 400);
});

/* ======================= Expanded rows ======================= */
const openId = ref<number | null>(null);
const idForm = reactive({ model_number: '', part_number: '' });
const savingIds = ref(false);

function toggleRow(row: ResearchRow): void {
    if (openId.value === row.id) {
        openId.value = null;
        refresh();
        return;
    }

    openId.value = row.id;
    idForm.model_number = row.model_number ?? '';
    idForm.part_number = row.part_number ?? '';
}

async function saveIds(row: ResearchRow): Promise<void> {
    savingIds.value = true;

    try {
        await saveAssetIdentifiers(identifiers(row.id).url, {
            model_number: idForm.model_number.trim() || null,
            part_number: idForm.part_number.trim() || null,
        });
        refresh();
        toast.fire({ icon: 'success', title: 'Model and part number saved' });
    } catch (error) {
        toast.fire({
            icon: 'error',
            title: error instanceof Error ? error.message : 'Could not save',
        });
    } finally {
        savingIds.value = false;
    }
}

function refresh(): void {
    router.reload({ only: ['assets', 'counts'] });
}

/* ======================= Display ======================= */
function statusBadge(row: ResearchRow): { text: string; tone: string } {
    if (row.has_pending_review)
        return {
            text: 'Waiting for review',
            tone: 'border-amber-700 bg-amber-900/40 text-amber-200',
        };
    if (!row.latest)
        return {
            text: 'Not researched',
            tone: 'border-slate-700 bg-slate-800/60 text-slate-300',
        };
    if (row.latest.failed)
        return {
            text: 'Failed',
            tone: 'border-red-700 bg-red-900/40 text-red-200',
        };
    if (row.latest.status === 'approved')
        return {
            text: 'Approved',
            tone: 'border-emerald-700 bg-emerald-900/40 text-emerald-200',
        };
    return {
        text: 'Rejected',
        tone: 'border-slate-700 bg-slate-800/60 text-slate-400',
    };
}

// Laravel's pagination labels contain HTML entities; render them as plain text.

const formatDate = (value: string | null) =>
    value
        ? new Date(value).toLocaleDateString('en-GB', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          })
        : '';
</script>

<template>
    <Head title="AI Research" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-4 lg:p-6">
            <!-- Header -->
            <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <h1
                    class="flex shrink-0 items-center gap-2 text-2xl font-semibold text-white"
                >
                    <Sparkles class="size-5 text-indigo-300" /> AI Research
                </h1>
                <p class="text-sm text-slate-400">
                    Look up specifications and current market prices for your
                    assets. AI results are suggestions: an asset only changes
                    when a reviewer approves.
                </p>
            </div>

            <!-- Status cards (click to filter) and search, on one row -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="card in cards"
                    :key="card.key"
                    type="button"
                    class="flex items-baseline gap-2 rounded-xl border px-3 py-2 text-left whitespace-nowrap transition hover:brightness-125"
                    :class="[
                        card.tone,
                        status === card.key ? 'ring-2 ring-indigo-400' : '',
                    ]"
                    @click="toggleStatus(card.key)"
                >
                    <span class="text-[11px] font-semibold">{{
                        card.label
                    }}</span>
                    <span class="text-lg font-bold text-white">
                        {{ counts[card.key] }}
                    </span>
                </button>

                <div class="relative min-w-[220px] flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500"
                    />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search code, type, brand, model, serial or unit"
                        class="w-full rounded-lg border border-slate-700 bg-slate-900 py-2 pr-3 pl-9 text-sm text-white placeholder:text-slate-500"
                    />
                </div>
                <button
                    v-if="status"
                    type="button"
                    class="text-xs text-slate-400 underline"
                    @click="toggleStatus(status)"
                >
                    Clear status filter
                </button>
                <span class="text-xs whitespace-nowrap text-slate-500">
                    <template v-if="assets.total"
                        >Showing {{ assets.from }}–{{ assets.to }} of
                        {{ assets.total }}</template
                    >
                </span>
            </div>

            <!-- Table: fills the screen and pages automatically, like Asset Management -->
            <div
                ref="tableRoot"
                v-fill-viewport-table="{
                    enabled: true,
                    bottomGap: 16,
                    minHeight: 320,
                    mode: 'fill',
                }"
                class="flex min-h-0 flex-col overflow-hidden rounded-xl border border-slate-800 bg-slate-900"
            >
                <div
                    data-controls
                    class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-4 py-3 text-sm text-slate-300"
                >
                    <label class="flex items-center gap-2">
                        <span>Show</span>
                        <select
                            v-model="pageSizeChoice"
                            class="h-9 rounded-md border border-slate-700 bg-black px-3 text-sm text-white transition outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            @change="changePageSize"
                        >
                            <option value="auto">Auto</option>
                            <option
                                v-for="size in PAGE_SIZES"
                                :key="size"
                                :value="size"
                            >
                                {{ size }}
                            </option>
                        </select>
                        <span>entries</span>
                    </label>
                    <span class="text-xs font-medium text-slate-300">
                        Page {{ assets.current_page }} of
                        {{ assets.last_page || 1 }}
                    </span>
                </div>

                <div class="relative min-h-0 w-full flex-1 overflow-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead
                            class="sticky top-0 z-20 bg-slate-800 text-left text-xs tracking-wide text-slate-300 uppercase"
                        >
                            <tr>
                                <th class="w-8 px-3 py-2"></th>
                                <th class="px-3 py-2">Asset code</th>
                                <th class="px-3 py-2">Type</th>
                                <th class="px-3 py-2">Brand / model</th>
                                <th class="px-3 py-2">Unit</th>
                                <th class="px-3 py-2">AI status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!assets.data.length">
                                <td
                                    colspan="6"
                                    class="px-3 py-8 text-center text-slate-400"
                                >
                                    <template v-if="otherTypeMatches">
                                        <p class="font-medium text-slate-200">
                                            {{ otherTypeMatches.total }} asset{{
                                                otherTypeMatches.total === 1
                                                    ? ''
                                                    : 's'
                                            }}
                                            match “{{ search }}”, but AI
                                            research doesn't cover
                                            {{
                                                otherTypeMatches.total === 1
                                                    ? 'its type'
                                                    : 'their types'
                                            }}:
                                            {{
                                                otherTypeMatches.types.join(
                                                    ', ',
                                                )
                                            }}.
                                        </p>
                                        <p
                                            v-if="otherTypeMatches.example"
                                            class="mt-1 font-mono text-xs text-slate-500"
                                        >
                                            e.g. {{ otherTypeMatches.example }}
                                        </p>
                                        <p class="mt-2 text-xs">
                                            AI research supports:
                                            {{ supportedTypes.join(', ') }}.
                                        </p>
                                    </template>
                                    <template v-else-if="search || status">
                                        No assets match this search or filter.
                                    </template>
                                    <template v-else>
                                        No assets to research yet.
                                    </template>
                                </td>
                            </tr>

                            <template v-for="row in assets.data" :key="row.id">
                                <tr
                                    data-row
                                    class="cursor-pointer border-t border-slate-800 hover:bg-slate-900/60"
                                    :class="
                                        openId === row.id
                                            ? 'bg-slate-900/60'
                                            : ''
                                    "
                                    @click="toggleRow(row)"
                                >
                                    <td class="px-3 py-2 text-slate-400">
                                        <ChevronDown
                                            v-if="openId === row.id"
                                            class="size-4"
                                        />
                                        <ChevronRight v-else class="size-4" />
                                    </td>
                                    <td
                                        class="px-3 py-2 font-mono text-xs text-white"
                                    >
                                        {{ row.asset_code }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-300">
                                        {{ row.type ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="text-white">{{
                                            row.brand ?? '—'
                                        }}</span>
                                        <span
                                            v-if="row.model_number"
                                            class="text-slate-300"
                                        >
                                            · {{ row.model_number }}</span
                                        >
                                        <span
                                            v-if="row.part_number"
                                            class="text-slate-500"
                                        >
                                            · {{ row.part_number }}</span
                                        >
                                    </td>
                                    <td class="px-3 py-2 text-slate-300">
                                        {{ row.unit ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="rounded-full border px-2 py-0.5 text-xs"
                                            :class="statusBadge(row).tone"
                                        >
                                            {{ statusBadge(row).text }}
                                        </span>
                                        <span
                                            v-if="row.latest?.created_at"
                                            class="ml-2 text-xs text-slate-500"
                                        >
                                            {{
                                                formatDate(
                                                    row.latest.created_at,
                                                )
                                            }}
                                        </span>
                                    </td>
                                </tr>

                                <!-- Expanded: identifiers + AI panel -->
                                <tr
                                    v-if="openId === row.id"
                                    class="border-t border-slate-800 bg-slate-950/60"
                                >
                                    <td colspan="6" class="space-y-4 p-4">
                                        <form
                                            v-if="canEditIdentifiers"
                                            class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-800 bg-slate-900/40 p-4"
                                            @submit.prevent="saveIds(row)"
                                        >
                                            <label
                                                class="text-xs text-slate-400"
                                            >
                                                Model number
                                                <input
                                                    v-model="
                                                        idForm.model_number
                                                    "
                                                    placeholder="e.g. EliteBook 840 G8"
                                                    class="mt-1 block w-60 rounded-md border border-slate-700 bg-slate-900 px-2 py-1.5 text-sm text-white"
                                                />
                                            </label>
                                            <label
                                                class="text-xs text-slate-400"
                                            >
                                                Part number
                                                <input
                                                    v-model="idForm.part_number"
                                                    placeholder="e.g. 3G2A3EA"
                                                    class="mt-1 block w-48 rounded-md border border-slate-700 bg-slate-900 px-2 py-1.5 text-sm text-white"
                                                />
                                            </label>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant="outline"
                                                :disabled="savingIds"
                                            >
                                                <Spinner v-if="savingIds" />
                                                Save
                                            </Button>
                                            <p
                                                class="basis-full text-xs text-slate-500"
                                            >
                                                The AI searches using the asset
                                                type, brand and these numbers. A
                                                model or part number is
                                                required; clearing both removes
                                                the asset from this list.
                                            </p>
                                        </form>

                                        <AiResearchPanel
                                            :key="row.id"
                                            :asset-id="row.id"
                                            @applied="refresh"
                                            @researched="refresh"
                                        />
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    data-pagination
                    class="flex shrink-0 justify-end gap-2 border-t border-slate-800 bg-slate-900 px-4 py-3"
                >
                    <button
                        type="button"
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5 text-sm text-slate-200 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="assets.current_page <= 1"
                        @click="goToPage(assets.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5 text-sm text-slate-200 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="assets.current_page >= assets.last_page"
                        @click="goToPage(assets.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
