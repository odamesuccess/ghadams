<script setup lang="ts">
/* =========================================================
IMPORTS
========================================================= */
import { h, ref, watch } from 'vue'
import type { ColumnDef } from '@tanstack/vue-table'
import { Head, router } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'

import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'

/* =========================================================
TYPES
========================================================= */
interface AssetName {
  id:number
  name:string
}

interface Unit {
  id:number
  name:string
  locationname?:string|null
}

interface Supplier {
  id:number
  suppliername:string
}

interface Asset {
  id:number
  asset_code:string
  asset_name?:AssetName|null
  unit?:Unit|null
  supplier?:Supplier|null   // ✅ ADD THIS
  purchase_cost?:number|null
  purchase_date?:string|null
  warranty_duration?:number|null
  warranty_end_date?:string|null
  status:'active'|'under_maintenance'|'obsolete'|'disposed'
  updated_at:string
}

/* =========================================================
PROPS
========================================================= */
const props = defineProps<{

  assets:{
    data:Asset[]
    total:number
    per_page:number
    current_page:number
    last_page:number
  }

  years:number[]

  grandTotal:{
    total_value:number
    total_assets:number
  }

  filters:{
    search?:string
    unit_id?:number|string
    year?:number|string
  }

}>()

/* =========================================================
STATE
========================================================= */
const search = ref(props.filters.search ?? '')
const unitId = ref(props.filters.unit_id ?? '')
const year = ref(props.filters.year ?? '')

/* =========================================================
FILTER
========================================================= */
const applyFilter = () => {

  router.get(
    '/reports/yearlypurchased-assets',
    {
      search: search.value || undefined,
      unit_id: unitId.value || undefined,
      year: year.value || undefined
    },
    {
      preserveState:true,
      replace:true,
    }
  )

}

/* =========================================================
WATCHERS
========================================================= */
watch(search, debounce(applyFilter,400))
watch(unitId,applyFilter)
watch(year,applyFilter)

/* =========================================================
HELPERS
========================================================= */
const formatDate=(value?:string|null)=>
  value ? new Date(value).toLocaleDateString() : '—'

const formatCurrency=(value?:number|null)=>
  value
    ? new Intl.NumberFormat(
        'en-GH',
        {
          style:'currency',
          currency:'GHS'
        }
      ).format(value)
    : '—'

const isExpired=(date?:string|null)=>
  date ? new Date(date)<new Date() : false

const statusBadge=(status:Asset['status'])=>({

  active:'bg-green-700',
  under_maintenance:'bg-yellow-600',
  obsolete:'bg-orange-600',
  disposed:'bg-red-700',

}[status] ?? 'bg-slate-600')

/* =========================================================
TABLE COLUMNS
========================================================= */
const columns:ColumnDef<Asset>[]=[

{
header:'Asset Code',
accessorKey:'asset_code',
},

{
header:'Supplier',
id:'supplier',
cell:({row}) =>
row.original.supplier?.suppliername ?? '—',
},
{
header:'Asset Name',
id:'asset_name',
cell:({row}) =>
row.original.asset_name?.name ?? '—',
},

{
header:'Unit / Division',
id:'unit',
cell:({row}) =>
row.original.unit?.name ?? '—',
},

{
header:'Location',
id:'location',
cell:({row}) =>
row.original.unit?.locationname ?? '—',
},

{
header:'Purchase Year',
id:'purchase_year',
cell:({row}) => {

const date = row.original.purchase_date

return date
? new Date(date).getFullYear()
: '—'

}
},

{
header:'Purchase Cost',
accessorKey:'purchase_cost',
cell:({getValue}) =>
formatCurrency(getValue() as number|null)
},

{
header:'Warranty Duration',
accessorKey:'warranty_duration',
cell:({getValue}) => {

const value = getValue() as number|null

return value
? `${value} months`
: '—'

}
},

{
header:'Warranty End',
accessorKey:'warranty_end_date',
cell:({getValue}) => {

const date = getValue() as string|null

if(!date) return '—'

return h(
'span',
{
class:
isExpired(date)
? 'text-red-500 font-semibold'
: ''
},
formatDate(date)
)

}
},

{
header:'Status',
accessorKey:'status',
cell:({getValue}) =>
h(
'span',
{
class:[
'rounded px-2 py-1 text-xs font-semibold text-white',
statusBadge(getValue() as Asset['status'])
],
},
(getValue() as string).replaceAll('_',' ')
)
},

]

/* =========================================================
BREADCRUMB
========================================================= */
const breadcrumbs:BreadcrumbItem[]=[
{
title:'Yearly Purchased Assets',
href:'/reports/yearlypurchased-assets',
}
]

</script>


<template>

<Head title="Yearly Purchased Assets" />

<AppLayout :breadcrumbs="breadcrumbs">

<!-- FILTER BAR -->
<div class="mt-2 flex flex-wrap items-center gap-4 px-6">

<input
v-model="search"
placeholder="Search assets..."
class="w-full max-w-sm rounded-md border bg-slate-800 px-3 py-2 text-sm text-slate-200"
/>


<select
v-model="year"
class="rounded-md border bg-slate-800 px-3 py-2 text-sm text-slate-200"
>

<option value="">
All Years Purchased
</option>

<option
v-for="y in years"
:key="y"
:value="y"
>
{{ y }}
</option>

</select>


<div class="font-semibold">

Total Records:
{{ assets.total }}

</div>


<a
:href="`/reports/yearlypurchased-assets/pdf?year=${year}&search=${search}`"
target="_blank"
class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700"
>

Print PDF

</a>

</div>


<!-- TABLE -->
<div class="mx-6 mt-4 overflow-x-auto rounded-lg border">

<DataTable
:data="assets.data"
:columns="columns"
/>

</div>


<!-- SUMMARY -->
<div class="mx-6 mt-4 text-right text-lg font-bold text-green-400">

Total Quantity of Assets Purchased:
{{ assets.total }}

&nbsp; | &nbsp;

Grand Total Purchase Cost:
{{ formatCurrency(grandTotal.total_value) }}

</div>


</AppLayout>

</template>
