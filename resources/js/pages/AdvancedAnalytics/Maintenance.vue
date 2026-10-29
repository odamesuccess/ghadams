<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type AssetOption = {
    id: number;
    asset_code: string;
    name: string;
    serial_number: string | null;
    unit: string;
    location: string;
    status: string;
};

type MaintenanceRow = {
    id: number;
    request_date: string | null;
    scheduled_date: string | null;
    completion_date: string | null;
    display_date: string | null;
    status: string;
    priority: string;
    fault_description: string;
    provider: string;
    estimated_cost: number | null;
    estimated_cost_label: string;
    actual_cost: number | null;
    actual_cost_label: string;
};

type AssetDetail = {
    asset: {
        id: number;
        asset_code: string;
        name: string;
        brand: string;
        serial_number: string;
        unit: string;
        location: string;
        status: string;
        purchase_date: string | null;
        warranty_end_date: string | null;
    };
    metrics: {
        maintenance_count: number;
        completed_maintenance_count: number;
        total_actual_maintenance_cost: number;
        total_actual_maintenance_cost_label: string;
        total_estimated_maintenance_cost: number;
        total_estimated_maintenance_cost_label: string;
        purchase_cost: number | null;
        purchase_cost_label: string;
        book_value: number | null;
        book_value_label: string;
        accumulated_depreciation: number | null;
        accumulated_depreciation_label: string;
        residual_value: number | null;
        residual_value_label: string;
        useful_life: number;
        asset_age: number | null;
        end_of_use_date: string | null;
    };
    maintenance: MaintenanceRow[];
};

const props = defineProps<{
    summary: any[];
    maintenanceSummary: any[];
    status: any[];
    priority: any[];
    topAssets: any[];
    monthlyCost: any[];
}>();

const visibleMaintenanceSummary = computed(() =>
    props.maintenanceSummary.filter(
        (card: any) =>
            !['Estimated Cost', 'Cost Variance'].includes(
                String(card?.label ?? '').trim(),
            ),
    ),
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Advanced Analytics', href: '/advanced-analytics' },
    { title: 'Maintenance Analytics', href: '/advanced-analytics/maintenance' },
];

const maxTotal = (items: any[]) =>
    Math.max(...items.map((item) => Number(item.total ?? 0)), 1);
const width = (value: number, max: number) =>
    `${Math.max(4, Math.round((Number(value) / max) * 100))}%`;

const statusBarClass = (label: string) => {
    const value = String(label ?? '').trim().toLowerCase();

    switch (value) {
        case 'in progress':
        case 'in_progress':
            return 'bg-violet-500';
        case 'completed':
            return 'bg-emerald-500';
        case 'approved':
            return 'bg-cyan-500';
        case 'pending':
            return 'bg-amber-400';
        case 'cancelled':
        case 'canceled':
            return 'bg-rose-500';
        default:
            return 'bg-slate-400';
    }
};

const statusTextClass = (label: string) => {
    const value = String(label ?? '').trim().toLowerCase();

    switch (value) {
        case 'in progress':
        case 'in_progress':
            return 'text-violet-300';
        case 'completed':
            return 'text-emerald-300';
        case 'approved':
            return 'text-cyan-300';
        case 'pending':
            return 'text-amber-300';
        case 'cancelled':
        case 'canceled':
            return 'text-rose-300';
        default:
            return 'text-slate-300';
    }
};

const priorityBarClass = (label: string) => {
    const value = String(label ?? '').trim().toLowerCase();

    switch (value) {
        case 'normal':
            return 'bg-blue-500';
        case 'high':
            return 'bg-orange-500';
        case 'urgent':
            return 'bg-rose-500';
        case 'low':
            return 'bg-teal-500';
        case 'critical':
            return 'bg-fuchsia-500';
        default:
            return 'bg-slate-400';
    }
};

const priorityTextClass = (label: string) => {
    const value = String(label ?? '').trim().toLowerCase();

    switch (value) {
        case 'normal':
            return 'text-blue-300';
        case 'high':
            return 'text-orange-300';
        case 'urgent':
            return 'text-rose-300';
        case 'low':
            return 'text-teal-300';
        case 'critical':
            return 'text-fuchsia-300';
        default:
            return 'text-slate-300';
    }
};

const summaryCardClass = (label: string) => {
    const value = String(label ?? '').trim().toLowerCase();

    switch (value) {
        case 'maintenance requests':
            return 'border-indigo-500/30 bg-indigo-500/[0.035]';
        case 'completed requests':
            return 'border-emerald-500/30 bg-emerald-500/[0.035]';
        case 'open maintenance':
            return 'border-blue-500/30 bg-blue-500/[0.035]';
        case 'actual cost':
            return 'border-violet-500/30 bg-violet-500/[0.035]';
        default:
            return 'border-slate-800 bg-slate-950';
    }
};

const summaryValueClass = (label: string) => {
    const value = String(label ?? '').trim().toLowerCase();

    switch (value) {
        case 'maintenance requests':
            return 'text-indigo-300';
        case 'completed requests':
            return 'text-emerald-300';
        case 'open maintenance':
            return 'text-blue-300';
        case 'actual cost':
            return 'text-violet-300';
        default:
            return 'text-white';
    }
};

const searchQuery = ref('');
const searchResults = ref<AssetOption[]>([]);
const searchLoading = ref(false);
const detailLoading = ref(false);
const searchError = ref('');
const selectedAsset = ref<AssetDetail | null>(null);
const selectedCode = ref('');
const dropdownOpen = ref(false);
const highlightedIndex = ref(-1);

let searchTimer: ReturnType<typeof setTimeout> | null = null;
let searchController: AbortController | null = null;
let detailController: AbortController | null = null;

const hasResults = computed(
    () => dropdownOpen.value && searchResults.value.length > 0,
);
const noResults = computed(
    () =>
        dropdownOpen.value &&
        !searchLoading.value &&
        searchQuery.value.trim() !== '' &&
        searchResults.value.length === 0,
);

const clearSearchTimer = () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }
};

const resetSearchResults = () => {
    searchResults.value = [];
    dropdownOpen.value = false;
    highlightedIndex.value = -1;
};

const performSearch = async () => {
    const query = searchQuery.value.trim();

    if (!query) {
        resetSearchResults();
        searchLoading.value = false;
        return;
    }

    searchController?.abort();
    searchController = new AbortController();
    searchLoading.value = true;
    searchError.value = '';

    try {
        const response = await fetch(
            `/advanced-analytics/maintenance/assets/search?q=${encodeURIComponent(query)}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: searchController.signal,
            },
        );

        if (!response.ok) {
            throw new Error('Unable to search the asset register.');
        }

        searchResults.value = (await response.json()) as AssetOption[];
        dropdownOpen.value = true;
        highlightedIndex.value = searchResults.value.length > 0 ? 0 : -1;
    } catch (error) {
        if ((error as Error).name !== 'AbortError') {
            searchResults.value = [];
            dropdownOpen.value = true;
            searchError.value =
                'Asset search could not be completed. Please try again.';
        }
    } finally {
        searchLoading.value = false;
    }
};

const scheduleSearch = () => {
    clearSearchTimer();
    searchTimer = setTimeout(performSearch, 220);
};

const handleSearchInput = (event: Event) => {
    const input = event.target as HTMLInputElement;
    searchQuery.value = input.value;

    if (selectedCode.value !== searchQuery.value) {
        selectedCode.value = '';
        selectedAsset.value = null;
    }

    searchError.value = '';
    scheduleSearch();
};

const loadAsset = async (option: AssetOption) => {
    detailController?.abort();
    detailController = new AbortController();
    detailLoading.value = true;
    searchError.value = '';
    selectedCode.value = option.asset_code;
    searchQuery.value = option.asset_code;
    resetSearchResults();

    try {
        const response = await fetch(
            `/advanced-analytics/maintenance/assets/${option.id}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: detailController.signal,
            },
        );

        if (!response.ok) {
            throw new Error('Unable to load the selected asset.');
        }

        selectedAsset.value = (await response.json()) as AssetDetail;
    } catch (error) {
        if ((error as Error).name !== 'AbortError') {
            selectedAsset.value = null;
            searchError.value =
                'The selected asset could not be loaded. Please try again.';
        }
    } finally {
        detailLoading.value = false;
    }
};

const moveHighlight = (direction: 1 | -1) => {
    if (!hasResults.value) return;

    const count = searchResults.value.length;
    highlightedIndex.value =
        (highlightedIndex.value + direction + count) % count;
};

const chooseHighlighted = () => {
    if (!hasResults.value || highlightedIndex.value < 0) return;
    const option = searchResults.value[highlightedIndex.value];
    if (option) void loadAsset(option);
};

const closeDropdownLater = () => {
    window.setTimeout(() => {
        dropdownOpen.value = false;
    }, 140);
};

const clearSelectedAsset = () => {
    searchController?.abort();
    detailController?.abort();
    clearSearchTimer();
    searchQuery.value = '';
    selectedCode.value = '';
    selectedAsset.value = null;
    searchError.value = '';
    searchLoading.value = false;
    detailLoading.value = false;
    resetSearchResults();
};

onBeforeUnmount(() => {
    clearSearchTimer();
    searchController?.abort();
    detailController?.abort();
});
</script>

<template>
    <Head title="Maintenance Analytics" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-3 my-2 space-y-2 text-slate-100 sm:mx-4">
            <!-- Professional compact page heading -->
            <section
                class="rounded-xl border border-slate-800 bg-slate-950 px-4 py-2.5 sm:px-4 sm:py-3"
            >
                <p
                    class="text-[9px] font-semibold tracking-[0.28em] text-amber-300 uppercase"
                >
                    Diagnostic analytics
                </p>
                <h1 class="mt-0.5 text-xl font-black text-white sm:text-[22px]">
                    Maintenance Analytics Output
                </h1>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-400 sm:text-xs">
                    Maintenance trends, cost exposure, workflow status and repeated
                    repairs used to support lifecycle replacement decisions.
                </p>
            </section>

            <!-- =========================================================
                 LIVE ASSET LOOKUP
            ========================================================== -->
            <section
                class="rounded-xl border border-slate-800 bg-slate-950 px-4 py-3"
            >
                <div
                    class="grid gap-3 lg:grid-cols-[minmax(250px,0.72fr)_minmax(420px,1.45fr)_auto] lg:items-end"
                >
                    <div class="min-w-0 self-center">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-bold text-white sm:text-base">
                                Asset Maintenance Lookup
                            </h2>
                            <span
                                class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[9px] font-semibold tracking-wider text-amber-300 uppercase"
                            >
                                Live search
                            </span>
                        </div>
                        <p class="mt-0.5 text-[11px] leading-4 text-slate-400">
                            Type an asset code and select the required asset.
                        </p>
                    </div>

                    <div class="relative min-w-0">
                        <label
                            for="maintenance-asset-search"
                            class="mb-1 block text-[9px] font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            Asset code
                        </label>

                        <div class="relative">
                            <input
                                id="maintenance-asset-search"
                                :value="searchQuery"
                                type="text"
                                autocomplete="off"
                                placeholder="Start typing asset code, e.g. GES\\AUTO\\GEN..."
                                class="h-9 w-full rounded-lg border border-slate-700 bg-black px-3 pr-28 text-xs text-white transition outline-none placeholder:text-slate-500 focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20 sm:text-sm"
                                role="combobox"
                                aria-autocomplete="list"
                                :aria-expanded="dropdownOpen"
                                aria-controls="maintenance-asset-results"
                                @input="handleSearchInput"
                                @focus="
                                    searchResults.length && (dropdownOpen = true)
                                "
                                @blur="closeDropdownLater"
                                @keydown.down.prevent="moveHighlight(1)"
                                @keydown.up.prevent="moveHighlight(-1)"
                                @keydown.enter.prevent="chooseHighlighted"
                                @keydown.esc.prevent="dropdownOpen = false"
                            />

                            <div
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[9px] font-semibold tracking-wider text-slate-400 uppercase"
                            >
                                <span v-if="searchLoading">Searching...</span>
                                <span v-else-if="detailLoading">Loading...</span>
                            </div>
                        </div>

                        <div
                            v-if="dropdownOpen"
                            id="maintenance-asset-results"
                            class="absolute z-50 mt-1.5 max-h-72 w-full overflow-y-auto rounded-lg border border-slate-700 bg-slate-900 shadow-2xl"
                            role="listbox"
                        >
                            <button
                                v-for="(option, index) in searchResults"
                                :key="option.id"
                                type="button"
                                role="option"
                                :aria-selected="index === highlightedIndex"
                                class="flex w-full flex-col gap-0.5 border-b border-slate-800 px-3 py-2 text-left transition last:border-b-0 sm:flex-row sm:items-center sm:justify-between"
                                :class="
                                    index === highlightedIndex
                                        ? 'bg-amber-500/10 text-white'
                                        : 'text-slate-200 hover:bg-slate-800'
                                "
                                @mouseenter="highlightedIndex = index"
                                @mousedown.prevent="loadAsset(option)"
                            >
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold break-all text-white">
                                        {{ option.asset_code }}
                                    </span>
                                    <span class="block text-[11px] text-slate-400">
                                        {{ option.name }} · {{ option.unit }}
                                    </span>
                                </span>
                                <span
                                    class="mt-0.5 shrink-0 text-[11px] text-slate-500 sm:mt-0"
                                >
                                    {{ option.location }} · {{ option.status }}
                                </span>
                            </button>

                            <div
                                v-if="noResults"
                                class="px-3 py-3 text-center text-xs text-slate-400"
                            >
                                No matching asset was found.
                            </div>
                        </div>
                    </div>

                    <button
                        v-if="selectedAsset || searchQuery"
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-700 bg-slate-900 px-3 text-[11px] font-semibold text-slate-200 transition hover:bg-slate-800 focus:ring-2 focus:ring-amber-400/70 focus:outline-none"
                        @click="clearSelectedAsset"
                    >
                        Clear asset
                    </button>

                    <div
                        v-else
                        class="hidden h-9 min-w-[90px] lg:block"
                        aria-hidden="true"
                    ></div>
                </div>

                <p
                    v-if="searchError"
                    class="mt-1.5 text-[11px] font-medium text-rose-300"
                    role="alert"
                >
                    {{ searchError }}
                </p>
            </section>

            <!-- =========================================================
                 SELECTED ASSET
            ========================================================== -->
            <section
                v-if="detailLoading"
                class="rounded-2xl border border-slate-800 bg-slate-950 p-4"
            >
                <div class="animate-pulse space-y-3">
                    <div class="h-4 w-56 rounded bg-slate-800"></div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="n in 4"
                            :key="n"
                            class="h-16 rounded-lg bg-slate-900"
                        ></div>
                    </div>
                </div>
            </section>

            <template v-else-if="selectedAsset">
                <!-- Compact selected asset banner -->
                <section
                    class="rounded-2xl border border-amber-500/25 bg-slate-950 px-4 py-3"
                >
                    <div
                        class="grid gap-3 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)] xl:items-center"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span
                                    class="text-[10px] font-semibold tracking-[0.22em] text-amber-300 uppercase"
                                >
                                    Selected asset
                                </span>
                                <h2
                                    class="text-lg font-black break-all text-white"
                                >
                                    {{ selectedAsset.asset.asset_code }}
                                </h2>
                                <span class="text-sm font-semibold text-slate-200">
                                    {{ selectedAsset.asset.name }}
                                </span>
                            </div>

                            <div
                                class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-400"
                            >
                                <span>
                                    <strong class="text-slate-300">Unit:</strong>
                                    {{ selectedAsset.asset.unit }}
                                </span>
                                <span>
                                    <strong class="text-slate-300">Location:</strong>
                                    {{ selectedAsset.asset.location }}
                                </span>
                                <span>
                                    <strong class="text-slate-300">Status:</strong>
                                    {{ selectedAsset.asset.status }}
                                </span>
                                <span>
                                    <strong class="text-slate-300">Serial:</strong>
                                    {{ selectedAsset.asset.serial_number }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-3 gap-2 rounded-lg border border-slate-800 bg-slate-900/70 px-3 py-2 text-[11px] text-slate-300"
                        >
                            <div>
                                <span class="block text-slate-500">Purchase date</span>
                                <strong class="text-white">
                                    {{
                                        selectedAsset.asset.purchase_date ??
                                        'Not recorded'
                                    }}
                                </strong>
                            </div>
                            <div>
                                <span class="block text-slate-500">Useful life</span>
                                <strong class="text-white">
                                    {{ selectedAsset.metrics.useful_life }} year(s)
                                </strong>
                            </div>
                            <div>
                                <span class="block text-slate-500">End of use</span>
                                <strong class="text-white">
                                    {{
                                        selectedAsset.metrics.end_of_use_date ??
                                        'Not available'
                                    }}
                                </strong>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- All selected-asset metrics in one compact responsive row -->
                <section
                    class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-7"
                >
                    <article
                        class="rounded-xl border border-slate-800 bg-slate-950 px-3 py-3"
                    >
                        <p class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">
                            Purchase Cost
                        </p>
                        <p class="mt-1 text-lg font-black text-white">
                            {{ selectedAsset.metrics.purchase_cost_label }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Historical cost
                        </p>
                    </article>

                    <article
                        class="rounded-xl border border-slate-800 bg-slate-950 px-3 py-3"
                    >
                        <p class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">
                            Current Book Value
                        </p>
                        <p class="mt-1 text-lg font-black text-emerald-300">
                            {{ selectedAsset.metrics.book_value_label }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Depreciated value
                        </p>
                    </article>

                    <article
                        class="rounded-xl border border-slate-800 bg-slate-950 px-3 py-3"
                    >
                        <p class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">
                            Maintenance Cost
                        </p>
                        <p class="mt-1 text-lg font-black text-amber-300">
                            {{
                                selectedAsset.metrics
                                    .total_actual_maintenance_cost_label
                            }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Actual maintenance
                        </p>
                    </article>

                    <article
                        class="rounded-xl border border-slate-800 bg-slate-950 px-3 py-3"
                    >
                        <p class="text-[10px] font-semibold tracking-wider text-slate-400 uppercase">
                            Maintenance Records
                        </p>
                        <p class="mt-1 text-lg font-black text-white">
                            {{ selectedAsset.metrics.maintenance_count }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            {{
                                selectedAsset.metrics
                                    .completed_maintenance_count
                            }}
                            completed
                        </p>
                    </article>

                    <article
                        class="rounded-xl border border-slate-800 bg-slate-950 px-3 py-3"
                    >
                        <p class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                            Estimated Exposure
                        </p>
                        <p class="mt-1 text-base font-bold text-slate-200">
                            {{
                                selectedAsset.metrics
                                    .total_estimated_maintenance_cost_label
                            }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Estimated maintenance
                        </p>
                    </article>

                    <article
                        class="rounded-xl border border-slate-800 bg-slate-950 px-3 py-3"
                    >
                        <p class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                            Accum. Depreciation
                        </p>
                        <p class="mt-1 text-base font-bold text-slate-200">
                            {{
                                selectedAsset.metrics
                                    .accumulated_depreciation_label
                            }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Value consumed
                        </p>
                    </article>

                    <article
                        class="rounded-xl border border-slate-800 bg-slate-950 px-3 py-3"
                    >
                        <p class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                            Residual Value
                        </p>
                        <p class="mt-1 text-base font-bold text-slate-200">
                            {{ selectedAsset.metrics.residual_value_label }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Value floor
                        </p>
                    </article>
                </section>

                <!-- Compact history -->
                <section
                    class="rounded-2xl border border-slate-800 bg-slate-950 p-4"
                >
                    <div
                        class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2 class="text-sm font-bold text-white sm:text-base">
                                Maintenance History
                            </h2>
                            <p class="text-xs text-slate-400">
                                Dates, faults and costs recorded for the selected asset.
                            </p>
                        </div>
                        <span class="text-xs font-semibold text-slate-300">
                            {{ selectedAsset.metrics.maintenance_count }} record(s)
                        </span>
                    </div>

                    <div
                        v-if="selectedAsset.maintenance.length"
                        class="mt-2 overflow-x-auto"
                    >
                        <table class="min-w-full text-xs">
                            <thead
                                class="border-b border-slate-800 text-left text-[10px] tracking-wider text-slate-400 uppercase"
                            >
                                <tr>
                                    <th class="px-2 py-2 whitespace-nowrap">Date</th>
                                    <th class="px-2 py-2 whitespace-nowrap">Status</th>
                                    <th class="px-2 py-2 whitespace-nowrap">Priority</th>
                                    <th class="min-w-56 px-2 py-2">Fault / Reason</th>
                                    <th class="px-2 py-2 whitespace-nowrap">Provider</th>
                                    <th class="px-2 py-2 text-right whitespace-nowrap">
                                        Estimated
                                    </th>
                                    <th class="px-2 py-2 text-right whitespace-nowrap">
                                        Actual
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <tr
                                    v-for="row in selectedAsset.maintenance"
                                    :key="row.id"
                                    class="hover:bg-slate-900/50"
                                >
                                    <td class="px-2 py-2 font-semibold whitespace-nowrap text-slate-200">
                                        {{ row.display_date ?? 'Not recorded' }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-slate-300">
                                        {{ row.status }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-slate-300">
                                        {{ row.priority }}
                                    </td>
                                    <td class="px-2 py-2 leading-5 text-slate-300">
                                        {{ row.fault_description }}
                                    </td>
                                    <td class="px-2 py-2 whitespace-nowrap text-slate-400">
                                        {{ row.provider }}
                                    </td>
                                    <td class="px-2 py-2 text-right whitespace-nowrap text-slate-400">
                                        {{ row.estimated_cost_label }}
                                    </td>
                                    <td class="px-2 py-2 text-right font-bold whitespace-nowrap text-white">
                                        {{ row.actual_cost_label }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-else
                        class="mt-2 rounded-lg border border-dashed border-slate-700 bg-slate-900/40 px-4 py-3 text-center text-xs text-slate-400"
                    >
                        No maintenance records have been recorded for this asset.
                    </div>

                    <p class="mt-2 text-[10px] leading-4 text-slate-500">
                        Book value uses the existing GhanaDAMS straight-line
                        depreciation rule: 10% residual value and the useful life
                        assigned to the asset category.
                    </p>
                </section>
            </template>

            <!-- =========================================================
                 OVERALL MAINTENANCE ANALYTICS
            ========================================================== -->
            <div class="flex items-center gap-2">
                <h2
                    class="text-xs font-bold tracking-wider text-slate-300 uppercase"
                >
                    Overall Maintenance Analytics
                </h2>
                <div class="h-px flex-1 bg-slate-800"></div>
            </div>

            <!-- All overall summary cards share one row on wide screens -->
            <section
                class="grid grid-cols-2 gap-2 xl:grid-cols-4"
            >
                <article
                    v-for="card in visibleMaintenanceSummary"
                    :key="card.label"
                    class="rounded-xl border px-3 py-2.5 transition-colors"
                    :class="summaryCardClass(card.label)"
                >
                    <p class="text-[9px] font-semibold tracking-wider text-slate-400 uppercase">
                        {{ card.label }}
                    </p>
                    <p
                        class="mt-0.5 text-lg font-black"
                        :class="summaryValueClass(card.label)"
                    >
                        {{ card.value }}
                    </p>
                    <p class="mt-0.5 text-[10px] leading-4 text-slate-500">
                        {{ card.subtext }}
                    </p>
                </article>
            </section>

            <section class="grid gap-2 xl:grid-cols-2">
                <div
                    class="rounded-xl border border-slate-800 bg-slate-950 p-3.5"
                >
                    <h2 class="text-sm font-bold text-white sm:text-base">
                        Maintenance by Status
                    </h2>
                    <div class="mt-2 space-y-1.5">
                        <div
                            v-for="item in props.status"
                            :key="item.label"
                            class="rounded-lg bg-slate-900/70 px-3 py-1.5"
                        >
                            <div class="flex justify-between text-xs">
                                <span
                                    class="font-semibold"
                                    :class="statusTextClass(item.label)"
                                >
                                    {{ item.label }}
                                </span>
                                <strong>{{ item.total }}</strong>
                            </div>
                            <div class="mt-1.5 h-1.5 rounded-full bg-slate-800">
                                <div
                                    class="h-1.5 rounded-full transition-all duration-300"
                                    :class="statusBarClass(item.label)"
                                    :style="{
                                        width: width(
                                            item.total,
                                            maxTotal(props.status),
                                        ),
                                    }"
                                ></div>
                            </div>
                            <p class="mt-1 text-[10px] text-slate-500">
                                {{ item.costLabel }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-slate-800 bg-slate-950 p-3.5"
                >
                    <h2 class="text-sm font-bold text-white sm:text-base">
                        Maintenance by Priority
                    </h2>
                    <div class="mt-2 space-y-1.5">
                        <div
                            v-for="item in props.priority"
                            :key="item.label"
                            class="rounded-lg bg-slate-900/70 px-3 py-1.5"
                        >
                            <div class="flex justify-between text-xs">
                                <span
                                    class="font-semibold"
                                    :class="priorityTextClass(item.label)"
                                >
                                    {{ item.label }}
                                </span>
                                <strong>{{ item.total }}</strong>
                            </div>
                            <div class="mt-1.5 h-1.5 rounded-full bg-slate-800">
                                <div
                                    class="h-1.5 rounded-full transition-all duration-300"
                                    :class="priorityBarClass(item.label)"
                                    :style="{
                                        width: width(
                                            item.total,
                                            maxTotal(props.priority),
                                        ),
                                    }"
                                ></div>
                            </div>
                            <p class="mt-1 text-[10px] text-slate-500">
                                {{ item.costLabel }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-2 xl:grid-cols-2">
                <div
                    class="rounded-xl border border-slate-800 bg-slate-950 p-3.5"
                >
                    <h2 class="text-sm font-bold text-white sm:text-base">
                        Repeated Maintenance Assets
                    </h2>
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="text-left text-[10px] text-slate-400 uppercase">
                                <tr>
                                    <th class="py-1.5">Asset</th>
                                    <th class="py-1.5">Name</th>
                                    <th class="py-1.5 text-right">Repairs</th>
                                    <th class="py-1.5 text-right">Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <tr
                                    v-for="row in props.topAssets"
                                    :key="row.asset_code"
                                >
                                    <td class="py-1.5 font-semibold">
                                        {{ row.asset_code }}
                                    </td>
                                    <td class="py-1.5 text-slate-300">
                                        {{ row.name }}
                                    </td>
                                    <td class="py-1.5 text-right font-bold">
                                        {{ row.total }}
                                    </td>
                                    <td class="py-1.5 text-right text-slate-400">
                                        {{ row.costLabel }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    class="rounded-xl border border-slate-800 bg-slate-950 p-3.5"
                >
                    <h2 class="text-base font-bold text-white">
                        Monthly Maintenance Cost
                    </h2>
                    <div class="mt-2 space-y-2">
                        <div
                            v-for="(item, index) in props.monthlyCost"
                            :key="item.label"
                            class="rounded-lg border border-slate-800/70 bg-slate-900/70 px-3 py-2"
                        >
                            <div class="flex justify-between text-xs">
                                <span>{{ item.label }}</span>
                                <strong
                                    :class="
                                        index % 2 === 0
                                            ? 'text-blue-300'
                                            : 'text-emerald-300'
                                    "
                                >
                                    {{ item.costLabel }}
                                </strong>
                            </div>
                            <p class="mt-0.5 text-[10px] text-slate-500">
                                {{ item.total }} request(s)
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
