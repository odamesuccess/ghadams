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
interface Organization {
  id: number
  organizations_name: string
  organizations_email?: string | null
  organizations_phone?: string | null
  organizations_address?: string | null
  updated_at: string
}

/* =======================
   Props (from Controller)
======================= */
const props = defineProps<{
  organizations: {
    data: Organization[]
  }
  filters: {
    search?: string
  }
}>()

/* =======================
   Search
======================= */
const search = ref(props.filters.search ?? '')

watch(search, value => {
  router.get(
    '/organizations',
    { search: value },
    { preserveState: true, replace: true }
  )
})

/* =======================
   Helpers
======================= */
const formatDate = (value: string) =>
  new Date(value).toLocaleString()

/* =======================
   Table Columns
======================= */
const columns: ColumnDef<Organization>[] = [
  { header: 'Name', accessorKey: 'organizations_name' },
  { header: 'Email', accessorKey: 'organizations_email' },
  { header: 'Phone', accessorKey: 'organizations_phone' },
  { header: 'Address', accessorKey: 'organizations_address' },

  {
    header: 'Updated',
    accessorKey: 'updated_at',
    cell: ({ getValue }) => formatDate(getValue() as string),
  },

  /* =======================
     Actions
  ======================= */
  {
    header: 'Actions',
    id: 'actions',
    cell: ({ row }) => {
      const organization = row.original
      const actions = []

      /* VIEW */
      if (can('organizations.view')) {
        actions.push(
          h(
            Link,
            {
              href: `/organizations/${organization.id}`,
              class:
                'rounded bg-slate-700 px-2 py-1 text-xs text-white hover:bg-slate-600',
            },
            'View'
          )
        )
      }

      /* EDIT */
      if (can('organizations.edit')) {
        actions.push(
          h(
            Link,
            {
              href: `/organizations/${organization.id}/edit`,
              class:
                'rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700',
            },
            'Edit'
          )
        )
      }

      /* DELETE */
      if (can('organizations.delete')) {
        actions.push(
          h(
            'button',
            {
              class:
                'rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700',
              onClick: async () => {
                const confirmed = await confirmDelete({
                  title: 'Delete Organization',
                  text: 'Are you sure you want to delete this organization?',
                })
                if (confirmed) {
                  router.delete(`/organizations/${organization.id}`)
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
  { title: 'Organizations', href: '/organizations' },
]
</script>

<template>
  <Head title="Organizations" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Action bar -->
    <div class="mt-2 flex items-center justify-between px-6">
      <input
        v-model="search"
        type="text"
        placeholder="Search organizations..."
        class="w-full max-w-sm rounded-md border bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:ring-2 focus:ring-blue-600"
      />

      <Link
        v-if="can('organizations.create')"
        href="/organizations/create"
        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
      >
        Create Organization
      </Link>
    </div>

    <!-- Table -->
    <div class="mx-6 mt-4 overflow-x-auto rounded-lg border">
      <DataTable
        :data="organizations.data"
        :columns="columns"
      />
    </div>
  </AppLayout>
</template>
