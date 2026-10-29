<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

const props = defineProps<{
  filters: any
  auditLogs: any
  modules: string[]
  actions: string[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Audit Logs', href: '#' },
]

const searchForm = useForm({
  search: (props.filters?.search ?? '') as string,
  module: (props.filters?.module ?? '') as string,
  action: (props.filters?.action ?? '') as string,
})

const applyFilters = () => {
  router.get('/audit-logs', searchForm.data(), {
    preserveState: true,
    replace: true,
  })
}

const resetFilters = () => {
  searchForm.search = ''
  searchForm.module = ''
  searchForm.action = ''

  router.get('/audit-logs', {}, {
    preserveState: true,
    replace: true,
  })
}

const fmt = (value: string | null | undefined) => {
  if (!value) return 'Not recorded'

  try {
    return new Date(value).toLocaleString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return value
  }
}

const titleCase = (value: string | null | undefined) => {
  if (!value) return 'Not recorded'

  return String(value)
    .replace(/[_-]/g, ' ')
    .replace(/\./g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

const formatModule = (value: string | null | undefined) => {
  if (!value) return 'System'

  const shortName = String(value).split('\\').pop() ?? value

  return titleCase(shortName)
}

const formatValue = (value: any) => {
  if (value === null || value === undefined || value === '') return 'Not recorded'

  if (typeof value === 'boolean') return value ? 'Yes' : 'No'

  if (Array.isArray(value)) {
    return value.length ? value.join(', ') : 'Not recorded'
  }

  if (typeof value === 'object') {
    return 'Saved details'
  }

  return String(value)
}

const shortValue = (value: any, limit = 38) => {
  const text = formatValue(value)

  if (text.length <= limit) return text

  return `${text.slice(0, limit)}...`
}

const actionTone = (action: string | null | undefined) => {
  const value = String(action ?? '').toLowerCase()

  if (value.includes('created') || value.includes('create') || value.includes('login')) {
    return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
  }

  if (value.includes('updated') || value.includes('update') || value.includes('restored')) {
    return 'border-blue-500/30 bg-blue-500/10 text-blue-300'
  }

  if (value.includes('deleted') || value.includes('delete') || value.includes('logout')) {
    return 'border-red-500/30 bg-red-500/10 text-red-300'
  }

  if (value.includes('password') || value.includes('security')) {
    return 'border-amber-500/30 bg-amber-500/10 text-amber-300'
  }

  return 'border-slate-600 bg-slate-800 text-slate-300'
}

const actionLabel = (action: string | null | undefined) => {
  if (!action) return 'System Action'

  return titleCase(action)
}

const hiddenAuditKeys = [
  'id',
  'uuid',
  'scan_token',
  'user_id',
  'organization_id',
  'updated_at',
  'created_at',
  'deleted_at',
  'remember_token',
  'password',
  'email_verified_at',
]

const getChangedKeys = (oldValues: any, newValues: any) => {
  const oldObj = oldValues && typeof oldValues === 'object' ? oldValues : {}
  const newObj = newValues && typeof newValues === 'object' ? newValues : {}

  return Array.from(new Set([...Object.keys(oldObj), ...Object.keys(newObj)]))
    .filter((key) => !hiddenAuditKeys.includes(key))
}

const previewChangedKeys = (oldValues: any, newValues: any) => {
  return getChangedKeys(oldValues, newValues).slice(0, 4)
}

const remainingChangedCount = (oldValues: any, newValues: any) => {
  const total = getChangedKeys(oldValues, newValues).length

  return Math.max(total - 4, 0)
}

const changeType = (oldValues: any, newValues: any) => {
  if (!oldValues && newValues) return 'created'
  if (oldValues && !newValues) return 'deleted'
  if (oldValues && newValues) return 'updated'

  return 'none'
}

const changeTypeLabel = (oldValues: any, newValues: any) => {
  const type = changeType(oldValues, newValues)

  if (type === 'created') return 'Record created'
  if (type === 'updated') return 'Record updated'
  if (type === 'deleted') return 'Record deleted'

  return 'No field changes'
}

const changeTypeClass = (oldValues: any, newValues: any) => {
  const type = changeType(oldValues, newValues)

  if (type === 'created') return 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20'
  if (type === 'updated') return 'bg-blue-500/10 text-blue-300 ring-blue-500/20'
  if (type === 'deleted') return 'bg-red-500/10 text-red-300 ring-red-500/20'

  return 'bg-slate-800 text-slate-400 ring-slate-700'
}

const hasChanges = (log: any) => {
  return !!(log.old_values || log.new_values)
}

const summaryStats = computed(() => {
  return {
    total: props.auditLogs?.total ?? props.auditLogs?.data?.length ?? 0,
    current: props.auditLogs?.data?.length ?? 0,
  }
})
</script>

<template>
  <Head title="Audit Logs" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-4 rounded-2xl border border-slate-800 bg-slate-950/95 text-slate-100 shadow-2xl mx-0 my-0 p-0">
      <div class="flex items-center gap-3 overflow-x-auto py-1">
        <div class="flex shrink-0 items-center gap-2 whitespace-nowrap">
          <h1 class="text-xl font-bold tracking-tight text-white">Audit Logs</h1>
          <span class="rounded-full border border-slate-700 bg-slate-900 px-2 py-1 text-[11px] font-semibold text-slate-300">
            {{ summaryStats.total }} records
          </span>
          <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 text-[11px] font-semibold text-emerald-300">
            Immutable Records
          </span>
        </div>
        <p class="shrink-0 whitespace-nowrap text-xs text-slate-400">
          Monitor user actions, security events, record changes, IP addresses, and accountability trails without exposing internal system data.
        </p>
        <div class="ml-auto flex shrink-0 items-center gap-2 whitespace-nowrap">
          <a href="/audit-logs/export-csv" class="inline-flex items-center justify-center rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-300 transition hover:bg-emerald-500/20">Export CSV</a>
          <a href="/audit-logs/export-pdf" class="inline-flex items-center justify-center rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs font-semibold text-red-300 transition hover:bg-red-500/20">Export PDF</a>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-3">
        <div class="grid gap-2 md:grid-cols-12">
          <input
            v-model="searchForm.search"
            @keyup.enter="applyFilters"
            class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 md:col-span-5"
            placeholder="Search user, action, IP, country, city..."
          />

          <select
            v-model="searchForm.module"
            class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 md:col-span-3"
          >
            <option value="">All Modules</option>
            <option v-for="m in modules" :key="m" :value="m">
              {{ formatModule(m) }}
            </option>
          </select>

          <select
            v-model="searchForm.action"
            class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 md:col-span-2"
          >
            <option value="">All Actions</option>
            <option v-for="a in actions" :key="a" :value="a">
              {{ actionLabel(a) }}
            </option>
          </select>

          <div class="grid grid-cols-2 gap-2 md:col-span-2">
            <button
              type="button"
              @click="applyFilters"
              class="rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500"
            >
              Search
            </button>

            <button
              type="button"
              @click="resetFilters"
              class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-slate-800"
            >
              Reset
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-800">
        <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/90 text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-3 text-left">Event</th>
                <th class="px-4 py-3 text-left">User</th>
                <th class="px-4 py-3 text-left">Module</th>
                <th class="px-4 py-3 text-left">Description</th>
                <th class="px-4 py-3 text-left">IP / Location</th>
                <th class="px-4 py-3 text-left">Changes</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-800 bg-slate-950">
              <tr
                v-for="log in auditLogs.data"
                :key="log.id"
                class="align-top transition hover:bg-slate-900/70"
              >
                <td class="whitespace-nowrap px-4 py-4">
                  <div class="space-y-2">
                    <span
                      class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                      :class="actionTone(log.action)"
                    >
                      {{ actionLabel(log.action) }}
                    </span>

                    <div class="text-xs text-slate-500">
                      {{ fmt(log.created_at) }}
                    </div>
                  </div>
                </td>

                <td class="px-4 py-4">
                  <div class="font-semibold text-white">
                    {{ log.user?.name ?? 'System' }}
                  </div>
                  <div class="mt-0.5 text-xs text-slate-500">
                    {{ log.user?.email ?? 'System generated event' }}
                  </div>
                </td>

                <td class="px-4 py-4">
                  <div class="font-medium text-slate-200">
                    {{ formatModule(log.module) }}
                  </div>
                  <div v-if="log.auditable_id" class="mt-0.5 text-xs text-slate-500">
                    Ref: #{{ log.auditable_id }}
                  </div>
                </td>

                <td class="max-w-md px-4 py-4">
                  <div class="line-clamp-2 leading-6 text-slate-200">
                    {{ log.description ?? log.title ?? 'System activity was recorded.' }}
                  </div>
                </td>

                <td class="whitespace-nowrap px-4 py-4">
                  <div class="font-medium text-slate-200">
                    {{ log.ip_address ?? 'Not recorded' }}
                  </div>

                  <div class="mt-0.5 text-xs text-slate-500">
                    <span v-if="log.ip_city || log.ip_country">
                      {{ log.ip_city ?? 'Unknown city' }}<span v-if="log.ip_country">, {{ log.ip_country }}</span>
                    </span>
                    <span v-else>Unknown location</span>
                  </div>
                </td>

                <td class="min-w-80 max-w-md px-4 py-4">
                  <div v-if="hasChanges(log)" class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                      <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1"
                        :class="changeTypeClass(log.old_values, log.new_values)"
                      >
                        {{ changeTypeLabel(log.old_values, log.new_values) }}
                      </span>

                      <span class="text-xs text-slate-500">
                        {{ getChangedKeys(log.old_values, log.new_values).length }} visible fields
                      </span>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                      <span
                        v-for="key in previewChangedKeys(log.old_values, log.new_values)"
                        :key="key"
                        class="inline-flex max-w-42.5 items-center rounded-lg border border-slate-700 bg-slate-900 px-2 py-1 text-[11px] text-slate-300"
                        :title="`${titleCase(key)}: ${formatValue(log.new_values?.[key])}`"
                      >
                        <span class="mr-1 font-semibold text-slate-400">
                          {{ titleCase(key) }}:
                        </span>
                        <span class="truncate">
                          {{ shortValue(log.new_values?.[key] ?? log.old_values?.[key]) }}
                        </span>
                      </span>

                      <span
                        v-if="remainingChangedCount(log.old_values, log.new_values)"
                        class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-2 py-1 text-[11px] font-semibold text-slate-400"
                      >
                        +{{ remainingChangedCount(log.old_values, log.new_values) }} more
                      </span>
                    </div>

                    <details class="group rounded-xl border border-slate-800 bg-slate-900/70 p-2 text-xs">
                      <summary class="cursor-pointer select-none font-semibold text-slate-300 group-open:text-blue-300">
                        View safe field changes
                      </summary>

                      <div class="mt-3 max-h-90 space-y-2 overflow-y-auto pr-1">
                        <div
                          v-for="key in getChangedKeys(log.old_values, log.new_values)"
                          :key="key"
                          class="rounded-lg border border-slate-800 bg-slate-950 p-3"
                        >
                          <div class="mb-2 font-semibold text-slate-300">
                            {{ titleCase(key) }}
                          </div>

                          <div class="grid gap-2 md:grid-cols-2">
                            <div class="rounded-lg bg-red-500/5 p-2 ring-1 ring-red-500/10">
                              <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-red-300">
                                Old
                              </div>
                              <div class="wrap-break-word text-slate-300">
                                {{ formatValue(log.old_values?.[key]) }}
                              </div>
                            </div>

                            <div class="rounded-lg bg-emerald-500/5 p-2 ring-1 ring-emerald-500/10">
                              <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-emerald-300">
                                New
                              </div>
                              <div class="wrap-break-word text-slate-300">
                                {{ formatValue(log.new_values?.[key]) }}
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </details>
                  </div>

                  <span v-else class="text-slate-500">
                    No field changes
                  </span>
                </td>
              </tr>

              <tr v-if="!auditLogs.data?.length">
                <td colspan="6" class="px-4 py-12 text-center">
                  <div class="text-base font-semibold text-slate-300">
                    No audit logs found
                  </div>
                  <div class="mt-1 text-sm text-slate-500">
                    Try adjusting the search term, module, or action filter.
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div
        v-if="auditLogs.links?.length"
        class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 p-3 text-sm text-slate-400 lg:flex-row lg:items-center lg:justify-between"
      >
        <div>
          Showing
          <span class="font-semibold text-slate-200">{{ auditLogs.from ?? 0 }}</span>
          to
          <span class="font-semibold text-slate-200">{{ auditLogs.to ?? 0 }}</span>
          of
          <span class="font-semibold text-slate-200">{{ auditLogs.total ?? 0 }}</span>
          audit logs
        </div>

        <div class="flex flex-wrap gap-1">
          <button
            v-for="link in auditLogs.links"
            :key="link.label"
            :disabled="!link.url"
            v-html="link.label"
            @click="link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })"
            class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40"
            :class="link.active
              ? 'border-blue-500 bg-blue-600 text-white'
              : 'border-slate-700 bg-slate-950 text-slate-300 hover:bg-slate-800'"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

