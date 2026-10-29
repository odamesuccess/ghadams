<script setup lang="ts">
/* =========================================================
   IMPORTS
========================================================= */
import { h } from 'vue'
import type { ColumnDef } from '@tanstack/vue-table'
import { Head } from '@inertiajs/vue3'

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
  purchase_date?: string | null
  warranty_duration?: number | null
  warranty_end_date?: string | null
  serial_number?: string | null
  description?: string | null
  status: 'obsolete'
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
  filters?: {
    search?: string
    per_page?: number
  }
}>()

/* =========================================================
   HELPERS
========================================================= */
const formatDate = (value?: string | null) =>
  value ? new Date(value).toLocaleDateString() : '—'

const formatDateTime = (value: string) =>
  new Date(value).toLocaleString()

const isWarrantyExpired = (date?: string | null) => {
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
    header: 'Image',
    accessorKey: 'asset_image',
    cell: ({ getValue }) => {
      const image = getValue() as string | null

      return image
        ? h('img', {
            src: `/storage/${image}`,
            class: 'h-10 w-10 rounded object-cover border',
          })
        : h(
            'div',
            {
              class:
                'h-10 w-10 rounded bg-slate-700 flex items-center justify-center text-xs text-slate-300',
            },
            'N/A'
          )
    },
  },

  {
    header: 'Asset Code',
    accessorKey: 'asset_code',
  },

  {
    header: 'Serial No.',
    accessorKey: 'serial_number',
  },

  {
    header: 'Asset Name',
    id: 'asset_name',
    cell: ({ row }) =>
      row.original.asset_name?.name ?? '—',
  },

  {
    header: 'Unit',
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
    header: 'Purchase Date',
    accessorKey: 'purchase_date',
    cell: ({ getValue }) =>
      formatDate(getValue() as string | null),
  },
{
  header: () => h(
    'span',
    { class: 'text-slate-300 font-semibold no-underline' },
    'Warranty Duration'
  ),
  accessorKey: 'warranty_duration',
  cell: ({ getValue }) => {
    const value = getValue() as number | null
    return value ? `${value} months` : '—'
  },
},
{
  header: 'Warranty End',
  accessorKey: 'warranty_end_date',
  cell: ({ getValue }) => {
    const date = getValue() as string | null

    if (!date) return '—'

    const today = new Date()
    const warrantyDate = new Date(date)

    const expired = warrantyDate < today

    return h(
      'span',
      {
        class: expired
          ? 'text-red-500 font-semibold'
          : 'text-slate-300',
      },
      formatDate(date)
    )
  },

},

  {
    header: 'Description',
    accessorKey: 'description',
    cell: ({ getValue }) => {
      const text = getValue() as string | null
      if (!text) return '—'

      return text.length > 30
        ? text.substring(0, 30) + '...'
        : text
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
  { title: 'Reports', href: '/reports/obsolete-assets' },
  { title: 'Obsolete Assets', href: '/reports/obsolete-assets' },
]
</script>
<template>
  <Head title="Obsolete Assets Report" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-6 mt-4 flex flex-nowrap items-center gap-4 overflow-x-auto" data-report-complete-header-row>
      <div class="flex shrink-0 flex-nowrap items-baseline gap-4">
        <h1 class="text-lg font-semibold shrink-0 whitespace-nowrap">Obsolete Assets Report</h1>
        <p class="text-sm text-muted-foreground shrink-0 whitespace-nowrap">
        List of all assets marked as obsolete.
      </p>
      </div>
      <div class="ml-auto flex shrink-0 flex-nowrap items-center gap-4 whitespace-nowrap">
        <div>
        Total: {{ assets.data.length }} {{ assets.data.length === 1 ? 'asset' : 'assets' }}
      </div>
        <a
        :href="`/reports/obsolete-assets/pdf${filters?.search ? '?search=' + encodeURIComponent(filters.search) : ''}`"
        target="_blank"
        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
      >
        Print PDF
      </a>
      </div>
    </div>

    <div class="mx-6 mt-3 overflow-x-auto rounded-lg border">
      <DataTable
        :data="assets.data"
        :columns="columns"
        :filters="filters"
        routeName="/reports/obsolete-assets"
        searchable
      />
    </div>
  </AppLayout>
</template>
