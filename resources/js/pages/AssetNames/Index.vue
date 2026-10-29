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
interface AssetName {
  id: number
  assetname: string
  usefullife: number | null
  brand: string | null
  updated_at: string
}

/* =======================
   Props
======================= */
const props = defineProps<{
  assetNames: {
    data: AssetName[]
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
      '/assetnames',
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
const columns: ColumnDef<AssetName>[] = [

  {
    header: 'Asset Name',
    accessorKey: 'assetname',
  },
  {
    header: 'Asset Abbreviation',
    accessorKey: 'assetname_abbreviation',
  },
  {
    header: 'Useful Life (Years)',
    accessorKey: 'usefullife',
    cell: ({ getValue }) => getValue() ?? '—',
  },
  {
    header: 'Brand',
    accessorKey: 'brand',
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
      const asset = row.original
      const actions = []

      if (can('assetnames.view')) {
        actions.push(
          h(
            Link,
            {
              href: `/assetnames/${asset.id}`,
              class: 'rounded bg-slate-700 px-2 py-1 text-xs text-white hover:bg-slate-600',
            },
            'View'
          )
        )
      }

      if (can('assetnames.edit')) {
        actions.push(
          h(
            Link,
            {
              href: `/assetnames/${asset.id}/edit`,
              class: 'rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700',
            },
            'Edit'
          )
        )
      }

      if (can('assetnames.delete')) {
        actions.push(
          h(
            'button',
            {
              class: 'rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700',
              onClick: async () => {
                const confirmed = await confirmDelete({
                  title: 'Delete Asset Name',
                  text: 'Are you sure you want to delete this asset name?',
                })
                if (confirmed) {
                  router.delete(`/assetnames/${asset.id}`)
                }
              },
            },
            'Delete'
          )
        )
      }

      return h(
        'div',
        { class: 'flex gap-2 justify-end' },
        actions.length ? actions : '—'
      )
    },
  },
]

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Asset Names', href: '/assetnames' },
]
</script>

<template>
  <Head title="Asset Names" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Action bar -->
    <div class="mt-2 flex items-center justify-between px-6 gap-4">
      <input
        v-model="search"
        type="text"
        placeholder="Search Asset Names..."
        class="w-full max-w-sm rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600"
      />

      <Link
        v-if="can('assetnames.create')"
        href="/assetnames/create"
        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
      >
        Create Asset Name
      </Link>
    </div>

    <!-- Table -->
    <div class="mx-6 mt-4 overflow-x-auto rounded-lg border border-slate-700">
      <DataTable
        :data="assetNames.data"
        :columns="columns"
      />
    </div>
  </AppLayout>
</template>
