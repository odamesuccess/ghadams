<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import type { ColumnDef } from '@tanstack/vue-table'
import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'

interface YearSummary {
    year:number
    total_assets:number
    total_value:number
}

const props = defineProps<{
    data:YearSummary[]
    grandTotal:{
        total_assets:number
        total_value:number
    }
}>()

const formatCurrency = (value?: number | null) =>
    value !== null && value !== undefined
        ? new Intl.NumberFormat('en-GH',{
            style:'currency',
            currency:'GHS'
        }).format(value)
        : '—'

const columns:ColumnDef<YearSummary>[]=[
{
    header:'Year',
    accessorKey:'year',
},
{
    header:'Total Assets',
    accessorKey:'total_assets',
},
{
    header:'Total Purchase Value',
    accessorKey:'total_value',
    cell:({getValue}) =>
        formatCurrency(getValue() as number)
},
]

const breadcrumbs:BreadcrumbItem[]=[
{
title:'Asset Count by Year',
href:'/reports/assetcount-by-year',
}
]
</script>

<template>

<Head title="Asset Count by Year"/>

<AppLayout :breadcrumbs="breadcrumbs">

<!-- ACTION BAR -->
<div class="mx-6 mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

<div class="text-lg font-semibold">
Asset Count by Year Report
</div>

<a
href="/reports/asset-count-by-year/pdf"
target="_blank"
class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition"
>
Print PDF
</a>

</div>

<!-- TABLE -->
<div class="mx-6 mt-4 overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900">

<DataTable
:data="data"
:columns="columns"
/>

</div>

<!-- GRAND TOTAL -->
<div class="mx-6 mt-4 text-right text-lg font-bold text-green-500">

Total Assets:
{{ grandTotal?.total_assets ?? 0 }}

&nbsp; | &nbsp;

Total Value:
{{ formatCurrency(grandTotal?.total_value ?? 0) }}

</div>

</AppLayout>

</template>
