<script setup lang="ts">
/* =======================
   Imports
======================= */
import { h, ref, watch } from 'vue'
import type { ColumnDef } from '@tanstack/vue-table'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'
import { can } from '@/utils/permissions'
import { confirmDelete } from '@/utils/sweetalert'

/* =======================
   Types
======================= */
interface Location {
  id: number
  locationname: string
  officenumber: string | null
  updated_at: string
}

/* =======================
   Props
======================= */
const props = defineProps<{
  locations: {
    data: Location[]
  }
  filters: {
    search?: string
  }
}>()

/* =======================
   Search (Debounced)
======================= */
const search = ref(props.filters?.search ?? '')
let searchTimeout: number | undefined

watch(search, value => {
  clearTimeout(searchTimeout)
  searchTimeout = window.setTimeout(() => {
    router.get(
      '/locations',
      { search: value },
      {
        preserveState: true,
        replace: true,
      }
    )
  }, 400)
})

/* =======================
   Helpers
======================= */
const formatDate = (value: string) =>
  new Date(value).toLocaleString()

/* =======================
   Table Columns
======================= */
const columns: ColumnDef<Location>[] = [
  {
    header: 'Locationname',
    accessorKey: 'locationname',
  },
  {
    header: 'Door Tag',
    accessorKey: 'officenumber',
    cell: ({ getValue }) => getValue() ?? '—',
  },
  {
    header: 'Updated',
    accessorKey: 'updated_at',
    cell: ({ getValue }) => formatDate(getValue() as string),
  },
  {
    header: 'Actions',
    id: 'actions',
    cell: ({ row }) => {
      const location = row.original
      const actions = []

      if (can('locations.view')) {
        actions.push(
          h(
            Link,
            {
              href: `/locations/${location.id}`,
              class:
                'rounded bg-slate-700 px-2 py-1 text-xs text-white hover:bg-slate-600',
            },
            'View'
          )
        )
      }

      if (can('locations.edit')) {
        actions.push(
          h(
            Link,
            {
              href: `/locations/${location.id}/edit`,
              class:
                'rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700',
            },
            'Edit'
          )
        )
      }

      if (can('locations.delete')) {
        actions.push(
          h(
            'button',
            {
              class:
                'rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700',
              onClick: async () => {
                const confirmed = await confirmDelete({
                  title: 'Delete Location',
                  text: 'Are you sure you want to delete this location?',
                })
                if (confirmed) {
                  router.delete(`/locations/${location.id}`, {
                    preserveScroll: true,
                  })
                }
              },
            },
            'Delete'
          )
        )
      }

      return h(
        'div',
        { class: 'flex justify-end gap-2' },
        actions.length ? actions : '—'
      )
    },
  },
]

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Locations', href: '/locations' },
]
</script>

<template>
  <Head title="Locations" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Action bar -->
    <div class="mt-2 flex items-center justify-between gap-4 px-6">
      <input
        v-model="search"
        type="text"
        placeholder="Search locations..."
        class="w-full max-w-sm rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600"
      />

      <Link
        v-if="can('locations.create')"
        href="/locations/create"
        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
      >
        Create Location
      </Link>
    </div>

    <!-- Table -->
    <div class="mx-6 mt-4 overflow-x-auto rounded-lg border border-slate-700">
      <DataTable
        :data="locations.data"
        :columns="columns"
      />
    </div>
  </AppLayout>
</template>


