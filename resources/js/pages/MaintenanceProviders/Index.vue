<script setup lang="ts">
import { h, ref, watch } from 'vue'
import type { ColumnDef } from '@tanstack/vue-table'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'
import { can } from '@/utils/permissions'
import { confirmDelete } from '@/utils/sweetalert'

interface MaintenanceProvider {
  id: number
  providername: string
  phonenumber: string
  email: string
  updated_at: string
}

const props = defineProps<{
  maintenanceProviders: {
    data: MaintenanceProvider[]
  }
  filters: {
    search?: string
  }
}>()

const search = ref(props.filters?.search ?? '')
let timer: number | undefined

watch(search, value => {
  clearTimeout(timer)
  timer = window.setTimeout(() => {
    router.get(
      '/maintenanceproviders',
      { search: value },
      { preserveState: true, replace: true }
    )
  }, 400)
})

const formatDate = (value: string) =>
  new Date(value).toLocaleString()

const columns: ColumnDef<MaintenanceProvider>[] = [
  { header: 'Provider Name', accessorKey: 'providername' },
  { header: 'Phone', accessorKey: 'phonenumber' },
  { header: 'Email', accessorKey: 'email' },
  {
    header: 'Updated',
    accessorKey: 'updated_at',
    cell: ({ getValue }) => formatDate(getValue() as string),
  },
  {
    header: 'Actions',
    id: 'actions',
    cell: ({ row }) => {
      const provider = row.original
      const actions = []

      if (can('maintenanceproviders.view')) {
        actions.push(
          h(Link, {
            href: `/maintenanceproviders/${provider.id}`,
            class: 'rounded bg-slate-700 px-2 py-1 text-xs text-white hover:bg-slate-600',
          }, 'Show')
        )
      }

      if (can('maintenanceproviders.edit')) {
        actions.push(
          h(Link, {
            href: `/maintenanceproviders/${provider.id}/edit`,
            class: 'rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700',
          }, 'Edit')
        )
      }

      if (can('maintenanceproviders.delete')) {
        actions.push(
          h('button', {
            class: 'rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700',
            onClick: async () => {
              const confirmed = await confirmDelete({
                title: 'Delete Maintenance Provider',
                text: 'Are you sure you want to delete this maintenance provider?',
              })
              if (confirmed) {
                router.delete(`/maintenanceproviders/${provider.id}`)
              }
            },
          }, 'Delete')
        )
      }

      return h('div', { class: 'flex gap-2 justify-end' }, actions.length ? actions : '—')
    },
  },
]

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Maintenance Providers', href: '/maintenanceproviders' },
]
</script>

<template>
  <Head title="Maintenance Providers" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mt-2 flex items-center justify-between px-6 gap-4">
      <input
        v-model="search"
        type="text"
        placeholder="Search maintenance providers..."
        class="w-full max-w-sm rounded-md border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:ring-2 focus:ring-blue-600"
      />

      <Link
        v-if="can('maintenanceproviders.create')"
        href="/maintenanceproviders/create"
        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
      >
        Create Provider
      </Link>
    </div>

    <div class="mx-6 mt-4 rounded-lg border border-slate-700 overflow-x-auto">
      <DataTable :data="maintenanceProviders.data" :columns="columns" />
    </div>
  </AppLayout>
</template>
