<script setup lang="ts">
import { h, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import type { ColumnDef } from '@tanstack/vue-table'
import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'
import { can } from '@/utils/permissions'
import { confirmDelete } from '@/utils/sweetalert'

interface UserSummary {
  id: number
  name: string
  email: string
}

interface ActivityTracker {
  id: number
  user?: UserSummary | null
  module: string
  action: string
  title?: string | null
  description?: string | null
  ip_address?: string | null
  created_at: string
}

const props = defineProps<{
  activityTrackers: { data: ActivityTracker[] }
  modules: string[]
  actions: string[]
  filters: { search?: string; module?: string; action?: string }
}>()

const search = ref(props.filters.search ?? '')
const module = ref(props.filters.module ?? '')
const action = ref(props.filters.action ?? '')

const reload = () => {
  router.get('/activity-trackers', { search: search.value, module: module.value, action: action.value }, { preserveState: true, replace: true })
}

watch(search, reload)
watch(module, reload)
watch(action, reload)

const formatDate = (value?: string | null) => value ? new Date(value).toLocaleString() : '—'

const actionClass = (value: string) => {
  if (value === 'created') return 'bg-green-600/20 text-green-300 border-green-500/30'
  if (value === 'updated') return 'bg-blue-600/20 text-blue-300 border-blue-500/30'
  if (value === 'deleted') return 'bg-red-600/20 text-red-300 border-red-500/30'
  return 'bg-slate-600/20 text-slate-300 border-slate-500/30'
}

const columns: ColumnDef<ActivityTracker>[] = [
  { header: 'User', cell: ({ row }) => row.original.user?.name ?? 'System' },
  { header: 'Module', accessorKey: 'module' },
  {
    header: 'Action',
    accessorKey: 'action',
    cell: ({ getValue }) => h('span', { class: `rounded border px-2 py-0.5 text-xs font-semibold ${actionClass(String(getValue()))}` }, String(getValue())),
  },
  { header: 'Title', accessorKey: 'title' },
  { header: 'Description', accessorKey: 'description' },
  { header: 'IP', accessorKey: 'ip_address' },
  { header: 'Date', accessorKey: 'created_at', cell: ({ getValue }) => formatDate(getValue() as string) },
  {
    header: 'Actions',
    id: 'actions',
    cell: ({ row }) => can('activity-trackers.delete')
      ? h('button', {
          class: 'rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700',
          onClick: async () => {
            const confirmed = await confirmDelete({
              title: 'Delete Activity Record',
              text: 'Are you sure you want to delete this activity tracker record?',
            })
            if (confirmed) {
              router.delete(`/activity-trackers/${row.original.id}`)
            }
          },
        }, 'Delete')
      : '—',
  },
]

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Security', href: '/activity-trackers' },
  { title: 'Activity Tracker', href: '/activity-trackers' },
]
</script>

<template>
  <Head title="Activity Tracker" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-6 mt-4 rounded-lg border bg-slate-900 p-4">
      <div class="grid gap-3 md:grid-cols-3">
        <input
          v-model="search"
          type="text"
          placeholder="Search activity..."
          class="w-full rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100"
        />

        <select v-model="module" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
          <option value="">All modules</option>
          <option v-for="item in modules" :key="item" :value="item">{{ item }}</option>
        </select>

        <select v-model="action" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100">
          <option value="">All actions</option>
          <option v-for="item in actions" :key="item" :value="item">{{ item }}</option>
        </select>
      </div>
    </div>

    <div class="mx-6 mt-4 overflow-x-auto rounded-lg border">
      <DataTable :data="activityTrackers.data" :columns="columns" />
    </div>
  </AppLayout>
</template>
