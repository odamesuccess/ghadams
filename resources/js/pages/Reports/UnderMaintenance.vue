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
   TYPES (MATCHES LARAVEL RELATIONSHIPS EXACTLY)
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

interface Supplier {
  id: number
  suppliername: string | null
}

interface MaintenanceProvider {
  id: number
  providername: string | null
}

interface Asset {
  id: number
  asset_code: string
  asset_name?: AssetName | null
  unit?: Unit | null
  supplier?: Supplier | null
  maintenance_provider?: MaintenanceProvider | null
  purchase_date?: string | null
  warranty_duration?: number | null
  warranty_end_date?: string | null
  serial_number?: string | null
  description?: string | null
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
    header: 'Warranty Duration',
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

      const expired = new Date(date) < new Date()

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
    header: 'Supplier',
    id: 'supplier',
    cell: ({ row }) =>
      row.original.supplier?.suppliername ?? '—',
  },

  {
  header: 'Maintenance Company',
  id: 'maintenance_provider',
  cell: ({ row }) =>
    row.original.maintenance_provider?.providername ?? '—',
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
  { title: 'Reports', href: '/reports/under-maintenance-assets' },
  { title: 'Under Maintenance Assets', href: '/reports/under-maintenance-assets' },
]
</script>
<template>
  <Head title="Under Maintenance Assets Report" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-6 mt-4 flex flex-nowrap items-center gap-4 overflow-x-auto" data-report-complete-header-row>
      <div class="flex shrink-0 flex-nowrap items-baseline gap-4">
        <h1 class="text-lg font-semibold shrink-0 whitespace-nowrap">Under Maintenance Assets Report</h1>
        <p class="text-sm text-muted-foreground shrink-0 whitespace-nowrap">
        Only assets with a scheduled, open maintenance job are shown. Completed jobs are removed automatically.
      </p>
      </div>
      <div class="ml-auto flex shrink-0 flex-nowrap items-center gap-4 whitespace-nowrap">
        <div>
        Total: {{ assets.data.length }} {{ assets.data.length === 1 ? 'asset' : 'assets' }}
      </div>
        <a
        :href="`/reports/under-maintenance/pdf${filters?.search ? '?search=' + encodeURIComponent(filters.search) : ''}`"
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
        routeName="/reports/under-maintenance-assets"
        searchable
      />
    </div>
  </AppLayout>
</template>
