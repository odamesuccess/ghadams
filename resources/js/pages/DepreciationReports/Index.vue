<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import type { ColumnDef } from '@tanstack/vue-table'

import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'

import depreciationreports from '@/routes/depreciationreports'

/* ================= TYPES ================= */

interface AssetDepreciation {
    id: number
    asset_code: string | null
    asset_name: string | null
    brand: string | null
    serial_number: string | null
    location: string | null
    unit: string | null
    status: string | null

    purchase_date: string | null
    warranty_end_date: string | null
    useful_life: number
    asset_age: number
    years_used: number
    end_of_use_date: string | null

    purchase_cost: number
    residual_value: number
    annual_depreciation: number
    accumulated_depreciation: number
    book_value: number
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

interface PaginatedAssets {
    data: AssetDepreciation[]
    links?: PaginationLink[]
    current_page?: number
    last_page?: number
    from?: number | null
    to?: number | null
    total?: number
    per_page?: number
}

interface Filters {
    search?: string
    status?: string
    from_date?: string
    to_date?: string
    per_page?: number
}

interface Summary {
    total_assets: number
    total_purchase_cost: number
    total_residual_value: number
    total_annual_depreciation: number
    total_accumulated_depreciation: number
    total_book_value: number
}

const props = defineProps<{
    assets: PaginatedAssets
    filters: Filters
    summary: Summary
}>()

/* ================= STATE ================= */

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')
const fromDate = ref(props.filters?.from_date ?? '')
const toDate = ref(props.filters?.to_date ?? '')

/* ================= COMPUTED ================= */

const rows = computed(() => props.assets?.data ?? [])

const pdfUrl = computed(() => {
    const params = new URLSearchParams()

    if (search.value) params.append('search', search.value)
    if (status.value) params.append('status', status.value)
    if (fromDate.value) params.append('from_date', fromDate.value)
    if (toDate.value) params.append('to_date', toDate.value)

    const query = params.toString()

    return query
        ? `${depreciationreports.pdf().url}?${query}`
        : depreciationreports.pdf().url
})

/* ================= FORMATTERS ================= */

const formatCurrency = (value?: number | null) =>
    value !== null && value !== undefined
        ? new Intl.NumberFormat('en-GH', {
              style: 'currency',
              currency: 'GHS',
              minimumFractionDigits: 2,
          }).format(value)
        : '—'

const formatNumber = (value?: number | null) =>
    value !== null && value !== undefined
        ? new Intl.NumberFormat('en-GH').format(value)
        : '—'

const formatDate = (date?: string | null) => {
    if (!date) return '—'

    const parsedDate = new Date(date)

    if (Number.isNaN(parsedDate.getTime())) {
        return '—'
    }

    return parsedDate.toLocaleDateString('en-GH')
}

const formatStatus = (value?: string | null) => {
    if (!value) return 'N/A'

    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase())
}

/* ================= TABLE COLUMNS ================= */

const columns: ColumnDef<AssetDepreciation>[] = [
    {
        header: 'Asset Code',
        accessorKey: 'asset_code',
        cell: ({ getValue }) => getValue() || '—',
    },
    {
        header: 'Asset Name',
        accessorKey: 'asset_name',
        cell: ({ getValue }) => getValue() || '—',
    },
    {
        header: 'Status',
        accessorKey: 'status',
        cell: ({ getValue }) => formatStatus(getValue() as string | null),
    },
    {
        header: 'Purchase Date',
        accessorKey: 'purchase_date',
        cell: ({ getValue }) => formatDate(getValue() as string | null),
    },
    {
        header: 'Warranty End',
        accessorKey: 'warranty_end_date',
        cell: ({ getValue }) => formatDate(getValue() as string | null),
    },
    {
        header: 'Useful Life',
        accessorKey: 'useful_life',
        cell: ({ getValue }) => `${formatNumber(getValue() as number | null)} yrs`,
    },
   
    {
        header: 'Years Used',
        accessorKey: 'years_used',
        cell: ({ getValue }) => `${formatNumber(getValue() as number | null)} yrs`,
    },
    {
        header: 'End of Use',
        accessorKey: 'end_of_use_date',
        cell: ({ getValue }) => formatDate(getValue() as string | null),
    },
    {
        header: 'Purchase Cost',
        accessorKey: 'purchase_cost',
        cell: ({ getValue }) => formatCurrency(getValue() as number | null),
    },
    {
        header: 'Residual Value',
        accessorKey: 'residual_value',
        cell: ({ getValue }) => formatCurrency(getValue() as number | null),
    },
    {
        header: 'Annual Depreciation',
        accessorKey: 'annual_depreciation',
        cell: ({ getValue }) => formatCurrency(getValue() as number | null),
    },
    {
        header: 'Accumulated Depreciation',
        accessorKey: 'accumulated_depreciation',
        cell: ({ getValue }) => formatCurrency(getValue() as number | null),
    },
    {
        header: 'Book Value',
        accessorKey: 'book_value',
        cell: ({ getValue }) => formatCurrency(getValue() as number | null),
    },
]

/* ================= BREADCRUMBS ================= */

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Depreciation Report',
        href: depreciationreports.index().url,
    },
]
</script>

<template>
    <Head title="Depreciation Report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-3 p-3 sm:p-4">
            <!-- HEADER -->
            <div
                class="flex flex-col gap-3 rounded-xl border border-slate-700 bg-slate-900/70 p-4 shadow-sm md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-xl font-semibold text-white">
                        Asset Depreciation Report
                    </h1>

                    <p class="text-sm text-slate-400">
                        Straight-line depreciation calculation for fixed assets.
                    </p>
                </div>

                <a
                    :href="pdfUrl"
                    target="_blank"
                    class="inline-flex items-center justify-center rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-green-700"
                >
                    Print PDF
                </a>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                <div class="rounded-xl border border-slate-700 bg-slate-900 p-3">
                    <p class="text-xs text-slate-400">Total Active Assets</p>
                    <p class="mt-1 text-base font-semibold text-white">
                        {{ formatNumber(summary.total_assets) }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-700 bg-slate-900 p-3">
                    <p class="text-xs text-slate-400">Purchase Cost</p>
                    <p class="mt-1 text-base font-semibold text-white">
                        {{ formatCurrency(summary.total_purchase_cost) }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-700 bg-slate-900 p-3">
                    <p class="text-xs text-slate-400">Residual Value</p>
                    <p class="mt-1 text-base font-semibold text-white">
                        {{ formatCurrency(summary.total_residual_value) }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-700 bg-slate-900 p-3">
                    <p class="text-xs text-slate-400">Annual Depreciation</p>
                    <p class="mt-1 text-base font-semibold text-white">
                        {{ formatCurrency(summary.total_annual_depreciation) }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-700 bg-slate-900 p-3">
                    <p class="text-xs text-slate-400">Accumulated Depreciation</p>
                    <p class="mt-1 text-base font-semibold text-white">
                        {{ formatCurrency(summary.total_accumulated_depreciation) }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-700 bg-slate-900 p-3">
                    <p class="text-xs text-slate-400">Book Value</p>
                    <p class="mt-1 text-base font-semibold text-white">
                        {{ formatCurrency(summary.total_book_value) }}
                    </p>
                </div>
            </div>

            <!-- TABLE LIKE ROLES TABLE -->
            <div class="overflow-hidden rounded-xl border border-slate-700 bg-slate-900">
                <DataTable
    :data="rows"
    :columns="columns"
    :searchable="true"
/>
            </div>
        </div>
    </AppLayout>
</template>
