<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Search, Sparkles } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

import AiResearchPanel from '@/components/assets/AiResearchPanel.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { saveAssetIdentifiers } from '@/composables/useAssetResearch';
import AppLayout from '@/layouts/AppLayout.vue';
import { identifiers, index } from '@/routes/asset-research';
import type { BreadcrumbItem } from '@/types';
import { toast } from '@/utils/sweetalert';

/* ======================= Types ======================= */
type StatusFilter = 'pending' | 'failed' | 'none' | 'approved' | 'missing_ids';

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
    {
        key: 'missing_ids',
        label: 'Missing model / part no.',
        tone: 'border-indigo-400/20 bg-indigo-500/10 text-indigo-200',
    },
];

function applyFilters(): void {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
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
const pageLabel = (label: string) =>
    label.replace('&laquo;', '«').replace('&raquo;', '»');

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
            <div class="flex flex-col gap-1">
                <h1
                    class="flex items-center gap-2 text-2xl font-semibold text-white"
                >
                    <Sparkles class="size-5 text-indigo-300" /> AI Research
                </h1>
                <p class="text-sm text-slate-400">
                    Look up specifications and current market prices for your
                    assets. AI results are suggestions: an asset only changes
                    when a reviewer approves.
                </p>
            </div>

            <!-- Status cards (click to filter) -->
            <div class="grid grid-cols-2 gap-2 md:grid-cols-5">
                <button
                    v-for="card in cards"
                    :key="card.key"
                    type="button"
                    class="rounded-xl border px-3 py-2 text-left transition hover:brightness-125"
                    :class="[
                        card.tone,
                        status === card.key ? 'ring-2 ring-indigo-400' : '',
                    ]"
                    @click="toggleStatus(card.key)"
                >
                    <p class="text-[11px] font-semibold">{{ card.label }}</p>
                    <p class="text-lg font-bold text-white">
                        {{ counts[card.key] }}
                    </p>
                </button>
            </div>

            <!-- Search -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-md">
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
                <span class="ml-auto text-xs text-slate-500">
                    <template v-if="assets.total"
                        >Showing {{ assets.from }}–{{ assets.to }} of
                        {{ assets.total }}</template
                    >
                </span>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table class="w-full min-w-[760px] text-sm">
                    <thead
                        class="bg-slate-900/80 text-left text-xs tracking-wide text-slate-400 uppercase"
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
                                        match “{{ search }}”, but AI research
                                        doesn't cover
                                        {{
                                            otherTypeMatches.total === 1
                                                ? 'its type'
                                                : 'their types'
                                        }}:
                                        {{ otherTypeMatches.types.join(', ') }}.
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
                                class="cursor-pointer border-t border-slate-800 hover:bg-slate-900/60"
                                :class="
                                    openId === row.id ? 'bg-slate-900/60' : ''
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
                                    <span
                                        v-if="
                                            !row.model_number &&
                                            !row.part_number
                                        "
                                        class="ml-2 rounded-full border border-indigo-700 bg-indigo-900/30 px-2 py-0.5 text-[11px] text-indigo-200"
                                        >Needs model no.</span
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
                                        {{ formatDate(row.latest.created_at) }}
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
                                        <label class="text-xs text-slate-400">
                                            Model number
                                            <input
                                                v-model="idForm.model_number"
                                                placeholder="e.g. EliteBook 840 G8"
                                                class="mt-1 block w-60 rounded-md border border-slate-700 bg-slate-900 px-2 py-1.5 text-sm text-white"
                                            />
                                        </label>
                                        <label class="text-xs text-slate-400">
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
                                            The AI searches using the saved
                                            model and part number. Save them
                                            before running research.
                                        </p>
                                    </form>

                                    <AiResearchPanel
                                        :key="row.id"
                                        :asset-id="row.id"
                                        @applied="refresh"
                                    />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav v-if="assets.links.length > 3" class="flex flex-wrap gap-1">
                <component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, i) in assets.links"
                    :key="i"
                    :href="link.url ?? undefined"
                    preserve-scroll
                    class="rounded-md border px-3 py-1 text-xs"
                    :class="
                        link.active
                            ? 'border-indigo-500 bg-indigo-600 text-white'
                            : link.url
                              ? 'border-slate-700 text-slate-300 hover:bg-slate-800'
                              : 'border-slate-800 text-slate-600'
                    "
                    >{{ pageLabel(link.label) }}</component
                >
            </nav>
        </div>
    </AppLayout>
</template>
