<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import type { ColumnDef } from '@tanstack/vue-table'
import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'

/* ================= TYPES ================= */

interface AssetDepreciation {
id:number
asset:string
location:string
asset_code:string
purchase_date:string | null
warranty_end_date:string | null
warranty_duration:number | null
useful_life:number
end_of_use_date:string | null
total_maintenance_cost:number | null
purchase_cost:number
residual_value:number
annual_depreciation:number
accumulated_depreciation:number
book_value:number
}

const props = defineProps<{
assets:AssetDepreciation[]
}>()

/* ================= FORMATTERS ================= */

const formatCurrency=(value?:number|null)=>
value !== null && value !== undefined
? new Intl.NumberFormat('en-GH',{
style:'currency',
currency:'GHS',
minimumFractionDigits:2
}).format(value)
:'—'

const formatNumber=(value?:number|null)=>
value !== null && value !== undefined
? new Intl.NumberFormat('en-GH').format(value)
:'—'

const formatDate=(date?:string|null)=>{
if(!date) return '—'
return new Date(date).toLocaleDateString('en-GH')
}

/* ================= TABLE COLUMNS ================= */

const columns:ColumnDef<AssetDepreciation>[]=[

{
header:'Asset Code',
accessorKey:'asset_code',
},

{
header:'Asset Name',
accessorKey:'asset',
},

{
header:'Purchase Date',
accessorKey:'purchase_date',
cell:({getValue})=>formatDate(getValue() as string | null)
},

{
header:'Warranty End Date',
accessorKey:'warranty_end_date',
cell:({getValue})=>formatDate(getValue() as string | null)
},

{
header:'Warranty Duration (Years)',
accessorKey:'warranty_duration',
cell:({getValue})=>formatNumber(getValue() as number | null)
},

{
header:'Useful Life (Years)',
accessorKey:'useful_life',
cell:({getValue})=>formatNumber(getValue() as number | null)
},

{
header:'End of Use Date',
accessorKey:'end_of_use_date',
cell:({getValue})=>formatDate(getValue() as string | null)
},

{
header:'Total Maintenance Cost',
accessorKey:'total_maintenance_cost',
cell:({getValue})=>formatCurrency(getValue() as number | null)
},

{
header:'Purchase Cost',
accessorKey:'purchase_cost',
cell:({getValue})=>formatCurrency(getValue() as number | null)
},

{
header:'Annual Depreciation',
accessorKey:'annual_depreciation',
cell:({getValue})=>formatCurrency(getValue() as number | null)
},

{
header:'Accumulated Depreciation',
accessorKey:'accumulated_depreciation',
cell:({getValue})=>formatCurrency(getValue() as number | null)
},

{
header:'Book Value',
accessorKey:'book_value',
cell:({getValue})=>formatCurrency(getValue() as number | null)
},

]

/* ================= BREADCRUMBS ================= */

const breadcrumbs:BreadcrumbItem[]=[
{
title:'Asset Depreciation',
href:'/reports/depreciation'
}
]
</script>

<template>

<Head title="Asset Depreciation" />

<AppLayout :breadcrumbs="breadcrumbs">

<div class="space-y-6 p-6">

<!-- HEADER -->

<div class="flex flex-col gap-4 md:flex-row md:justify-between md:items-center">

<div>
<h1 class="text-xl font-semibold text-white">
Asset Depreciation Report
</h1>

<p class="text-sm text-slate-400">
Straight-line depreciation calculation for assets
</p>
</div>

<a
href="/reports/depreciation/pdf"
target="_blank"
class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 transition"
>
Print PDF
</a>

</div>

<!-- TABLE -->

<div class="rounded-xl border border-slate-700 bg-slate-900 overflow-hidden">

<DataTable
:data="assets"
:columns="columns"
/>

</div>

</div>

</AppLayout>

</template>
