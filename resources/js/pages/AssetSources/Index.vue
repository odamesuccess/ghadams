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
interface AssetSource {
  id: number
  name: string
  description: string | null
  updated_at: string
}

/* =======================
   Props (MUST match controller)
======================= */
const props = defineProps<{
  assetSources: {
    data: AssetSource[]
  }
  filters: {
    search?: string
  }
}>()

/* =======================
   Search (Debounced) — SAME AS LOCATIONS
======================= */
const search = ref(props.filters?.search ?? '')
let searchTimeout: number | undefined

watch(search, value => {
  clearTimeout(searchTimeout)
  searchTimeout = window.setTimeout(() => {
    router.get(
      '/assetsources',
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
const columns: ColumnDef<AssetSource>[] = [
  {
    header: 'ID',
    accessorKey: 'id',
  },
  {
    header: 'Source Name',
    accessorKey: 'name',
  },
  {
    header: 'Description',
    accessorKey: 'description',
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
      const source = row.original
      const actions = []

      /* VIEW */
      if (can('assetsources.view')) {
        actions.push(
          h(
            Link,
            {
              href: `/assetsources/${source.id}`,
              class:
                'rounded bg-slate-700 px-2 py-1 text-xs text-white hover:bg-slate-600',
            },
            'View'
          )
        )
      }

      /* EDIT */
      if (can('assetsources.edit')) {
        actions.push(
          h(
            Link,
            {
              href: `/assetsources/${source.id}/edit`,
              class:
                'rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700',
            },
            'Edit'
          )
        )
      }

      /* DELETE — FIXED */
      if (can('assetsources.delete')) {
        actions.push(
          h(
            'button',
            {
              class:
                'rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700',
              onClick: async () => {
                const confirmed = await confirmDelete({
                  title: 'Delete Asset Source',
                  text: 'Are you sure you want to delete this asset source?',
                })
                if (confirmed) {
                  router.delete(`/assetsources/${source.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                      router.reload({ only: ['assetSources'] })
                    },
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
  { title: 'Asset Sources', href: '/assetsources' },
]
</script>

<template>
  <Head title="Asset Sources" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Action bar -->
    <div class="mt-2 flex items-center justify-between gap-4 px-6">
      <input
        v-model="search"
        type="text"
        placeholder="Search asset sources..."
        class="w-full max-w-sm rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600"
      />

      <Link
        v-if="can('assetsources.create')"
        href="/assetsources/create"
        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
      >
        Create Asset Source
      </Link>
    </div>

    <!-- Table -->
    <div class="mx-6 mt-4 overflow-x-auto rounded-lg border border-slate-700">
      <DataTable
        :data="assetSources.data"
        :columns="columns"
      />
    </div>
  </AppLayout>
</template>
