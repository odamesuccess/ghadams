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
interface Supplier {
  id: number
  suppliername: string
  phonenumber: string
  email: string
  updated_at: string
}

/* =======================
   Props
======================= */
const props = defineProps<{
  suppliers: {
    data: Supplier[]
  }
  filters: {
    search?: string
  }
}>()

/* =======================
   Search (debounced)
======================= */
const search = ref(props.filters?.search ?? '')
let timer: number | undefined

watch(search, value => {
  clearTimeout(timer)
  timer = window.setTimeout(() => {
    router.get(
      '/suppliers',
      { search: value },
      { preserveState: true, replace: true }
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
const columns: ColumnDef<Supplier>[] = [
  {
    header: 'Supplier Name',
    accessorKey: 'suppliername',
  },
  {
    header: 'Phone',
    accessorKey: 'phonenumber',
  },
  {
    header: 'Email',
    accessorKey: 'email',
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
      const supplier = row.original
      const actions = []

      /* ✅ VIEW */
      if (can('suppliers.view')) {
        actions.push(
          h(
            Link,
            {
              href: `/suppliers/${supplier.id}`,
              class:
                'rounded bg-slate-700 px-2 py-1 text-xs text-white hover:bg-slate-600',
            },
            'Show'
          )
        )
      }

      /* ✅ EDIT */
      if (can('suppliers.edit')) {
        actions.push(
          h(
            Link,
            {
              href: `/suppliers/${supplier.id}/edit`,
              class:
                'rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700',
            },
            'Edit'
          )
        )
      }

      /* ✅ DELETE */
      if (can('suppliers.delete')) {
        actions.push(
          h(
            'button',
            {
              class:
                'rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700',
              onClick: async () => {
                const confirmed = await confirmDelete({
                  title: 'Delete Supplier',
                  text: 'Are you sure you want to delete this supplier?',
                })
                if (confirmed) {
                  router.delete(`/suppliers/${supplier.id}`)
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
  { title: 'Suppliers', href: '/suppliers' },
]
</script>

<template>
  <Head title="Suppliers" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Action Bar -->
    <div class="mt-2 flex items-center justify-between px-6 gap-4">
      <input
        v-model="search"
        type="text"
        placeholder="Search suppliers..."
        class="w-full max-w-sm rounded-md border border-slate-700 bg-slate-800
               px-3 py-2 text-sm text-slate-200
               focus:outline-none focus:ring-2 focus:ring-blue-600"
      />

      <Link
        v-if="can('suppliers.create')"
        href="/suppliers/create"
        class="rounded-md bg-blue-600 px-4 py-2
               text-sm font-semibold text-white hover:bg-blue-700"
      >
        Create Supplier
      </Link>
    </div>

    <!-- Table -->
    <div class="mx-6 mt-4 rounded-lg border border-slate-700 overflow-x-auto">
      <DataTable :data="suppliers.data" :columns="columns" />
    </div>
  </AppLayout>
</template>
