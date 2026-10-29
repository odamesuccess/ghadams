<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { confirmDelete } from '@/utils/sweetalert'

const props = defineProps<{
  filters:any
  histories:any
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Asset Lifecycle History', href: '#' },
]

const searchForm = useForm({
  search: (props.filters?.search ?? '') as string,
  module: (props.filters?.module ?? '') as string,
  action: (props.filters?.action ?? '') as string,
  type: (props.filters?.type ?? '') as string,
  status: (props.filters?.status ?? '') as string,
  priority: (props.filters?.priority ?? '') as string,
  event: (props.filters?.event ?? '') as string,
  read: (props.filters?.read ?? '') as string,
})

const applyFilters = () => {
  router.get('/asset-lifecycle-histories', searchForm.data(), { preserveState: true, replace: true })
}

const destroyRecord = async (url: string) => {
  const confirmed = await confirmDelete({
    title: 'Delete Lifecycle Record',
    text: 'Are you sure you want to delete this lifecycle history record?',
  })
  if (confirmed) {
    router.delete(url, { preserveScroll: true })
  }
}

const postTo = (url: string, data: Record<string, any> = {}) => {
  router.post(url, data, { preserveScroll: true })
}

const fmt = (value: string | null | undefined) => value ? new Date(value).toLocaleString('en-GB') : 'â€”'
</script>

<template>
  <Head title="Asset Lifecycle History" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-4 my-4 space-y-4 rounded-2xl border border-slate-800 bg-slate-950/90 p-4 text-slate-100 shadow-xl sm:mx-6 sm:p-6">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 class="text-xl font-bold text-white">Asset Lifecycle History</h1>
          <p class="text-sm text-slate-400">SUBAsset Lifecycle History</p>
        </div>
        <div></div>
      </div>

      <div class="grid gap-2 md:grid-cols-3"><input v-model="searchForm.search" @keyup.enter="applyFilters" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm" placeholder="Search asset code or reason..." /><select v-model="searchForm.status" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm"><option value="">All Statuses</option><option value="registered">Registered</option><option value="assigned">Assigned</option><option value="active">In Use / Active</option><option value="under_maintenance">Under Maintenance</option><option value="transferred">Transferred</option><option value="disposed">Disposed</option><option value="obsolete">Obsolete</option><option value="archived">Archived</option></select><button @click="applyFilters" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white">Search</button></div>

      <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="overflow-x-auto rounded-xl border border-slate-800"><table class="min-w-full divide-y divide-slate-800 text-sm"><thead class="bg-slate-900 text-xs uppercase text-slate-400"><tr><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Asset</th><th class="px-3 py-2 text-left">Old</th><th class="px-3 py-2 text-left">New</th><th class="px-3 py-2 text-left">Changed By</th><th class="px-3 py-2 text-left">Reason</th></tr></thead><tbody class="divide-y divide-slate-800"><tr v-for="h in histories.data" :key="h.id"><td class="px-3 py-2">{{ fmt(h.changed_at) }}</td><td class="px-3 py-2">{{ h.asset?.asset_code ?? 'â€”' }}</td><td class="px-3 py-2">{{ h.old_status ?? 'â€”' }}</td><td class="px-3 py-2"><span class="rounded-full bg-indigo-500/10 px-2 py-1 text-xs text-indigo-300">{{ h.new_status }}</span></td><td class="px-3 py-2">{{ h.changed_by?.name ?? 'System' }}</td><td class="px-3 py-2">{{ h.reason ?? 'â€”' }}</td></tr><tr v-if="!histories.data?.length"><td colspan="6" class="px-3 py-8 text-center text-slate-400">No lifecycle history found.</td></tr></tbody></table></div>
    </div>
  </AppLayout>
</template>

