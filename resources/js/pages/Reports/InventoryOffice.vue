<script setup lang="ts">
/* =========================================================
IMPORTS
========================================================= */
import { h, ref, watch, computed } from 'vue'
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
  id: number
  name: string
}

interface Unit {
  id: number
  name: string
  locationname?: string | null
}

interface Asset {
  id: number
  asset_code: string
  asset_name?: AssetName | null
  unit?: Unit | null
  purchase_cost?: number | null
  warranty_duration?: number | null
  warranty_end_date?: string | null
  status: 'active' | 'under_maintenance' | 'obsolete' | 'disposed'
  asset_image?: string | null
  updated_at: string
}


/* =========================================================
PROPS
========================================================= */
const props = defineProps<{
  assets: {
    data: Asset[]
  }
  units: Unit[]
  filters: {
    search?: string
    unit_id?: number | string
    locationname?: string
  }
}>()


/* =========================================================
FILTER STATE
========================================================= */
const search = ref(props.filters.search ?? '')
const locationName = ref(props.filters.locationname ?? '')


/*
Create UNIQUE locations list
*/
const locations = computed(() => {

  const set = new Set<string>()

  props.units.forEach(u => {
    if (u.locationname) {
      set.add(u.locationname)
    }
  })

  return Array.from(set).sort()

})


/* =========================================================
APPLY FILTER
========================================================= */
const applyFilter = () => {

  router.get(
    '/reports/inventory-office',
    {
      search: search.value,
      locationname: locationName.value,
    },
    {
      preserveState: true,
      replace: true,
    }
  )

}


/* =========================================================
WATCHERS
========================================================= */
watch(
  search,
  debounce(() => applyFilter(), 400)
)

watch(locationName, () => applyFilter())


/* =========================================================
HELPERS
========================================================= */
const formatDate = (value?: string | null) =>
  value ? new Date(value).toLocaleDateString() : '—'


const isExpired = (date?: string | null) => {

  if (!date) return false

  return new Date(date) < new Date()

}


const statusBadge = (status: Asset['status']) =>
({
  active: 'bg-green-700',
  under_maintenance: 'bg-yellow-600',
  obsolete: 'bg-orange-600',
  disposed: 'bg-red-700',
}[status] ?? 'bg-slate-600')


/* =========================================================
TABLE COLUMNS
========================================================= */
const columns: ColumnDef<Asset>[] = [

{
  header: 'Asset Code',
  accessorKey: 'asset_code',
},

{
  header: 'Asset Name',
  id: 'asset_name',
  cell: ({ row }) =>
    row.original.asset_name?.name ?? '—',
},

{
  header: 'Unit / Division',
  id: 'unit',
  cell: ({ row }) =>
    row.original.unit?.name ?? '—',
},

{
  header: 'Location',
  id: 'location',
  cell: ({ row }) =>
    row.original.unit?.locationname ?? '—',
},

{
  header: 'Warranty End',
  accessorKey: 'warranty_end_date',
  cell: ({ getValue }) => {

    const date = getValue() as string | null

    if (!date) return '—'

    return h(
      'span',
      {
        class: isExpired(date)
          ? 'text-red-500 font-semibold'
          : '',
      },
      formatDate(date)
    )

  },
},

{
  header: 'Status',
  accessorKey: 'status',
  cell: ({ getValue }) =>
    h(
      'span',
      {
        class: [
          'rounded px-2 py-1 text-xs font-semibold text-white',
          statusBadge(getValue() as Asset['status']),
        ],
      },
      (getValue() as string).replaceAll('_', ' ')
    ),
},

]


/* =========================================================
BREADCRUMBS
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
{
  title: 'Inventory by Office',
  href: '/reports/inventory-office',
},
]

</script>


<template>

<Head title="Inventory by Office" />

<AppLayout :breadcrumbs="breadcrumbs">


<!-- FILTER BAR -->
<div class="mt-2 flex flex-wrap items-center gap-4 px-6">


<!-- SEARCH -->
<input
  v-model="search"
  type="text"
  placeholder="Search assets..."
  class="w-full max-w-sm rounded-md border bg-slate-800 px-3 py-2 text-sm text-slate-200"
/>


<!-- LOCATION FILTER -->
<select
  v-model="locationName"
  class="rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
>
  <option value="">All Locations</option>

  <option
    v-for="location in locations"
    :key="location"
    :value="location"
  >
    {{ location }}
  </option>

</select>


<div>
Total: {{ assets.data.length }}
{{ assets.data.length === 1 ? 'asset' : 'assets' }}
</div>


<!-- PDF -->
<a
:href="`/reports/inventory-office/pdf?locationname=${locationName}&search=${search}`"
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


</AppLayout>

</template>
