<script setup lang="ts">
/* =========================================================
   IMPORTS
========================================================= */
import { computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

/* =========================================================
   TYPES
========================================================= */
type Nullable<T> = T | null | undefined

interface Summary {
  total_maintenance_cost?: number
  actual_maintenance_cost?: number
  this_year_maintenance_cost?: number
  last_year_maintenance_cost?: number
  this_year_maintenance_count?: number
  last_year_maintenance_count?: number
  this_year?: number
  last_year?: number
  total_requests?: number
  completed_requests?: number
  pending_requests?: number
  approved_requests?: number
  rejected_requests?: number
}

interface MonthlyCost {
  month: string
  total: number | string
}

interface LookupItem {
  id: number
  name: string
  locationname?: string | null
}

interface MaintenanceRequestRow {
  id: number
  asset_id?: number | null
  asset_code?: string | null
  asset_name?: string | null
  unit_name?: string | null
  locationname?: string | null
  provider_name?: string | null
  requested_by_name?: string | null
  approved_by_name?: string | null
  completed_by_name?: string | null
  status?: string | null
  priority?: string | null
  maintenance_type?: string | null
  fault_description?: string | null
  description?: string | null
  completion_note?: string | null
  estimated_cost?: number | string | null
  actual_cost?: number | string | null
  cost?: number | string | null
  scheduled_date?: string | null
  expected_completion_date?: string | null
  actual_completion_date?: string | null
  created_at?: string | null
  updated_at?: string | null
}

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

interface PaginatedRows {
  data?: MaintenanceRequestRow[]
  links?: PaginationLink[]
  from?: number | null
  to?: number | null
  total?: number | null
  current_page?: number
  last_page?: number
}

interface Filters {
  search?: string
  status?: string
  priority?: string
  asset_id?: string | number
  unit_id?: string | number
  provider_id?: string | number
  date_from?: string
  date_to?: string
  per_page?: number
}

const props = withDefaults(defineProps<{
  summary?: Summary
  monthlyCosts?: MonthlyCost[]
  recentRequests?: PaginatedRows
  assets?: LookupItem[]
  units?: LookupItem[]
  providers?: LookupItem[]
  filters?: Filters
}>(), {
  summary: () => ({}),
  monthlyCosts: () => [],
  recentRequests: () => ({ data: [], links: [], from: 0, to: 0, total: 0 }),
  assets: () => [],
  units: () => [],
  providers: () => [],
  filters: () => ({}),
})

/* =========================================================
   BREADCRUMBS
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Reports', href: '/reports/maintenance-cost' },
  { title: 'Maintenance Cost', href: '/reports/maintenance-cost' },
]

/* =========================================================
   FILTERS
========================================================= */
const form = useForm({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? '',
  priority: props.filters?.priority ?? '',
  asset_id: props.filters?.asset_id ?? '',
  unit_id: props.filters?.unit_id ?? '',
  provider_id: props.filters?.provider_id ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  per_page: props.filters?.per_page ?? 50,
})

const applyFilters = () => {
  router.get('/reports/maintenance-cost', form.data(), {
    preserveState: true,
    replace: true,
  })
}

const resetFilters = () => {
  form.search = ''
  form.status = ''
  form.priority = ''
  form.asset_id = ''
  form.unit_id = ''
  form.provider_id = ''
  form.date_from = ''
  form.date_to = ''
  form.per_page = 50

  router.get('/reports/maintenance-cost', {}, {
    preserveState: true,
    replace: true,
  })
}

const pdfUrl = computed(() => {
  const params = new URLSearchParams()

  const pdfFilters = {
    search: form.search,
    priority: form.priority,
    asset_id: form.asset_id,
    unit_id: form.unit_id,
    provider_id: form.provider_id,
    date_from: form.date_from,
    date_to: form.date_to,
  }

  Object.entries(pdfFilters).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '') {
      params.append(key, String(value))
    }
  })

  const query = params.toString()
  return `/reports/maintenance-cost/pdf${query ? `?${query}` : ''}`
})

/* =========================================================
   HELPERS
========================================================= */
const rows = computed(() => props.recentRequests?.data ?? [])
const paginationLinks = computed(() => props.recentRequests?.links ?? [])

const money = (value: Nullable<number | string>) => {
  const amount = Number(value ?? 0)

  return new Intl.NumberFormat('en-GH', {
    style: 'currency',
    currency: 'GHS',
    minimumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0)
}

const numberFormat = (value: Nullable<number | string>) => {
  const number = Number(value ?? 0)
  return new Intl.NumberFormat('en-GB').format(Number.isFinite(number) ? number : 0)
}

const dateFormat = (value: Nullable<string>) => {
  if (!value) return 'â€”'

  try {
    return new Date(value).toLocaleDateString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    })
  } catch {
    return String(value)
  }
}

const titleCase = (value: Nullable<string>) => {
  if (!value) return 'â€”'

  return String(value)
    .replace(/[_-]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

const statusClass = (status: Nullable<string>) => {
  const value = String(status ?? '').toLowerCase()

  if (value === 'completed') return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
  if (value === 'approved') return 'border-blue-500/30 bg-blue-500/10 text-blue-300'
  if (value === 'pending') return 'border-amber-500/30 bg-amber-500/10 text-amber-300'
  if (value === 'rejected') return 'border-red-500/30 bg-red-500/10 text-red-300'

  return 'border-slate-700 bg-slate-800 text-slate-300'
}

const priorityClass = (priority: Nullable<string>) => {
  const value = String(priority ?? '').toLowerCase()

  if (value === 'high' || value === 'critical' || value === 'urgent') {
    return 'border-red-500/30 bg-red-500/10 text-red-300'
  }

  if (value === 'medium') {
    return 'border-amber-500/30 bg-amber-500/10 text-amber-300'
  }

  if (value === 'low') {
    return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
  }

  return 'border-slate-700 bg-slate-800 text-slate-300'
}

const highestMonthlyCost = computed(() => {
  const values = props.monthlyCosts.map((item) => Number(item.total ?? 0))
  return Math.max(...values, 0)
})

const monthlyBarWidth = (value: Nullable<number | string>) => {
  const amount = Number(value ?? 0)

  if (!highestMonthlyCost.value || !Number.isFinite(amount)) {
    return '0%'
  }

  return `${Math.max(4, Math.round((amount / highestMonthlyCost.value) * 100))}%`
}
</script>

<template>
  <Head title="Maintenance Cost History" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="bg-slate-950 px-3 py-2 text-slate-100 sm:px-4">
      <div class="space-y-2.5">
        <!-- Header -->
        <div class="rounded-xl border border-slate-700/70 bg-slate-900/80 p-3 shadow-lg">
          <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div>
              <h1 class="text-xl font-bold tracking-tight text-white">
                Maintenance Cost History
              </h1>
              <p class="mt-1 text-sm text-slate-400">
                Completed maintenance jobs only: review maintenance costs, providers, assets and completion-date trends.
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <a
                :href="pdfUrl"
                target="_blank"
                class="inline-flex h-9 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-500"
              >
                Print PDF
              </a>

              <button
                type="button"
                @click="resetFilters"
                class="inline-flex h-9 items-center justify-center rounded-xl bg-slate-700 px-4 text-sm font-semibold text-white transition hover:bg-slate-600"
              >
                Reset
              </button>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="rounded-xl border border-slate-700/70 bg-slate-900/80 p-3 shadow-lg">
          <div class="grid gap-3 xl:grid-cols-12">
            <input
              v-model="form.search"
              type="text"
              placeholder="Search asset, fault, provider, user, unit..."
              class="h-9 rounded-md border border-slate-800 bg-black px-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 xl:col-span-3"
              @keyup.enter="applyFilters"
            />

            <div class="flex h-9 items-center justify-center rounded-md border border-emerald-500/25 bg-emerald-500/10 px-3 text-xs font-bold text-emerald-300 xl:col-span-1">
              Completed Only
            </div>

            <select
              v-model="form.priority"
              class="h-9 rounded-md border border-slate-800 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 xl:col-span-1"
            >
              <option value="">All Priority</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
              <option value="critical">Critical</option>
            </select>

            <select
              v-model="form.asset_id"
              class="h-9 rounded-md border border-slate-800 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 xl:col-span-2"
            >
              <option value="">All Assets</option>
              <option v-for="asset in assets" :key="asset.id" :value="asset.id">
                {{ asset.name }}
              </option>
            </select>

            <select
              v-model="form.unit_id"
              class="h-9 rounded-md border border-slate-800 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 xl:col-span-1"
            >
              <option value="">All Units</option>
              <option v-for="unit in units" :key="unit.id" :value="unit.id">
                {{ unit.name }}
              </option>
            </select>

            <select
              v-model="form.provider_id"
              class="h-9 rounded-md border border-slate-800 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 xl:col-span-1"
            >
              <option value="">All Providers</option>
              <option v-for="provider in providers" :key="provider.id" :value="provider.id">
                {{ provider.name }}
              </option>
            </select>

            <input
              v-model="form.date_from"
              type="date"
              class="h-9 rounded-md border border-slate-800 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 xl:col-span-1"
            />

            <input
              v-model="form.date_to"
              type="date"
              class="h-9 rounded-md border border-slate-800 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 xl:col-span-1"
            />

            <button
              type="button"
              @click="applyFilters"
              class="h-10 rounded-xl bg-white px-4 text-sm font-semibold text-slate-950 transition hover:bg-slate-200 xl:col-span-1"
            >
              Search
            </button>
          </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-2.5 md:grid-cols-2 xl:grid-cols-5">
          <div class="rounded-xl border border-slate-700/70 bg-slate-900/80 p-3 shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Total Cost
            </p>
            <p class="mt-2 text-xl font-bold text-white">
              {{ money(summary.total_maintenance_cost) }}
            </p>
            <p class="mt-1 text-xs text-slate-400">
              Completed jobs in current report
            </p>
          </div>

          <div class="rounded-xl border border-slate-700/70 bg-slate-900/80 p-3 shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Actual Cost
            </p>
            <p class="mt-2 text-xl font-bold text-white">
              {{ money(summary.actual_maintenance_cost) }}
            </p>
            <p class="mt-1 text-xs text-slate-400">
              Recorded actual maintenance cost
            </p>
          </div>

          <div class="rounded-xl border border-blue-500/20 bg-blue-500/5 p-3 shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-300">
              Maintenance This Year
              <span v-if="summary.this_year">({{ summary.this_year }})</span>
            </p>
            <p class="mt-2 text-xl font-bold text-blue-200">
              {{ money(summary.this_year_maintenance_cost) }}
            </p>
            <p class="mt-1 text-xs text-slate-400">
              {{ numberFormat(summary.this_year_maintenance_count) }} completed job(s)
            </p>
          </div>

          <div class="rounded-xl border border-violet-500/20 bg-violet-500/5 p-3 shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wide text-violet-300">
              Maintenance Last Year
              <span v-if="summary.last_year">({{ summary.last_year }})</span>
            </p>
            <p class="mt-2 text-xl font-bold text-violet-200">
              {{ money(summary.last_year_maintenance_cost) }}
            </p>
            <p class="mt-1 text-xs text-slate-400">
              {{ numberFormat(summary.last_year_maintenance_count) }} completed job(s)
            </p>
          </div>

          <div class="rounded-xl border border-slate-700/70 bg-slate-900/80 p-3 shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Requests
            </p>
            <p class="mt-2 text-xl font-bold text-white">
              {{ numberFormat(summary.total_requests) }}
            </p>
            <p class="mt-1 text-xs text-slate-400">
              Completed maintenance jobs only
            </p>
          </div>
        </div>
        <!-- Monthly Costs -->
        <div class="rounded-xl border border-slate-700/70 bg-slate-900/80 p-3 shadow-lg">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="text-base font-bold text-white">Monthly Cost Trend</h2>
              <p class="mt-1 text-sm text-slate-400">Maintenance cost distribution by completion month.</p>
            </div>
            <span class="rounded-full border border-slate-700 bg-slate-950 px-3 py-1 text-xs font-semibold text-slate-300">
              {{ monthlyCosts.length }} months
            </span>
          </div>

          <div v-if="monthlyCosts.length" class="mt-2 space-y-2">
            <div v-for="item in monthlyCosts" :key="item.month" class="grid gap-2 md:grid-cols-12 md:items-center">
              <div class="text-sm font-semibold text-slate-300 md:col-span-2">
                {{ item.month }}
              </div>
              <div class="h-3 overflow-hidden rounded-full bg-slate-950 md:col-span-8">
                <div
                  class="h-full rounded-full bg-blue-500"
                  :style="{ width: monthlyBarWidth(item.total) }"
                ></div>
              </div>
              <div class="text-sm font-semibold text-white md:col-span-2 md:text-right">
                {{ money(item.total) }}
              </div>
            </div>
          </div>

          <div v-else class="mt-2 rounded-lg border border-slate-800 bg-slate-950 px-3 py-4 text-center text-sm text-slate-400">
            No monthly cost data found.
          </div>
        </div>

        <!-- Recent Requests -->
        <div class="rounded-xl border border-slate-700/70 bg-slate-900/80 p-3 shadow-lg">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="text-base font-bold text-white">Maintenance Requests</h2>
              <p class="mt-1 text-sm text-slate-400">Completed maintenance-job cost history.</p>
            </div>

            <select
              v-model="form.per_page"
              @change="applyFilters"
              class="h-9 rounded-lg border border-slate-800 bg-black px-3 text-sm font-semibold text-white outline-none focus:border-blue-500"
            >
              <option :value="25">25 rows</option>
              <option :value="50">50 rows</option>
              <option :value="100">100 rows</option>
              <option :value="250">250 rows</option>
              <option :value="500">500 rows</option>
            </select>
          </div>

          <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="mt-2 overflow-x-auto rounded-lg border border-slate-800"><table class="min-w-full divide-y divide-slate-800 text-sm">
              <thead class="bg-slate-950 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th class="px-4 py-3 text-left">Asset</th>
                  <th class="px-4 py-3 text-left">Unit / Location</th>
                  <th class="px-4 py-3 text-left">Provider</th>
                  <th class="px-4 py-3 text-left">Status</th>
                  <th class="px-4 py-3 text-left">Priority</th>
                  <th class="px-4 py-3 text-left">Date</th>
                  <th class="px-4 py-3 text-right">Actual</th>
                  <th class="px-4 py-3 text-right">Cost</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-800 bg-slate-900/50">
                <tr v-for="item in rows" :key="item.id" class="align-top transition hover:bg-slate-800/60">
                  <td class="px-4 py-3">
                    <div class="font-semibold text-white">{{ item.asset_code ?? 'â€”' }}</div>
                    <div class="mt-0.5 max-w-xs truncate text-xs text-slate-400">{{ item.asset_name ?? 'Unknown asset' }}</div>
                    <div class="mt-1 max-w-xs truncate text-xs text-slate-500">{{ item.fault_description ?? item.description ?? 'No description' }}</div>
                  </td>

                  <td class="px-4 py-3">
                    <div class="text-slate-200">{{ item.unit_name ?? 'â€”' }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">{{ item.locationname ?? 'No location' }}</div>
                  </td>

                  <td class="px-4 py-3 text-slate-300">
                    {{ item.provider_name ?? 'â€”' }}
                  </td>

                  <td class="px-4 py-3">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusClass(item.status)">
                      {{ titleCase(item.status) }}
                    </span>
                  </td>

                  <td class="px-4 py-3">
                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold" :class="priorityClass(item.priority)">
                      {{ titleCase(item.priority) }}
                    </span>
                  </td>

                  <td class="px-4 py-3 whitespace-nowrap text-slate-300">
                    {{ dateFormat(item.actual_completion_date ?? item.created_at) }}
                  </td>

                  <td class="px-4 py-3 text-right text-slate-300">
                    {{ money(item.actual_cost) }}
                  </td>

                  <td class="px-4 py-3 text-right font-bold text-white">
                    {{ money(item.cost) }}
                  </td>
                </tr>

                <tr v-if="!rows.length">
                  <td colspan="9" class="px-3 py-5 text-center">
                    <p class="font-semibold text-slate-300">No maintenance cost records found</p>
                    <p class="mt-1 text-sm text-slate-500">Try adjusting your filters.</p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div
            v-if="paginationLinks.length"
            class="mt-2 flex flex-col gap-2 text-sm text-slate-400 lg:flex-row lg:items-center lg:justify-between"
          >
            <div>
              Showing
              <span class="font-semibold text-slate-200">{{ recentRequests?.from ?? 0 }}</span>
              to
              <span class="font-semibold text-slate-200">{{ recentRequests?.to ?? 0 }}</span>
              of
              <span class="font-semibold text-slate-200">{{ recentRequests?.total ?? 0 }}</span>
              requests
            </div>

            <div class="flex flex-wrap gap-1">
              <button
                v-for="link in paginationLinks"
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
      </div>
    </div>
  </AppLayout>
</template>

