<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

type RiskCard = {
  label: string
  value: number | string
  subtext: string
  severity?: string
  tone?: 'rose' | 'amber' | 'violet' | 'sky'
}

type EndOfLifeRow = {
  asset_code: string
  name: string
  unit: string
  status: string
  purchase_date: string | null
  useful_life: number | null
  end_date: string | null
  days_remaining: number | null
  risk: 'Overdue' | 'Due Soon' | 'Watch' | string
}

type MaintenanceRow = {
  asset_code: string
  name: string
  unit: string
  status: string
  maintenance_count: number
}

type ObsoleteRow = {
  asset_code: string
  name: string
  unit: string
  status: string
  purchase_cost: string
}

type ScanAlertRow = {
  date: string | null
  asset_code: string | null
  name: string
  unit: string
  scanned_by: string
  location: string
  alert: string
}

type DataQuality = {
  legacy_end_of_life: number
  future_purchase_dates: number
  missing_useful_life: number
}

const props = defineProps<{
  summary: any[]
  risks: RiskCard[]
  endOfLife: EndOfLifeRow[]
  lifecycleDataQuality: DataQuality
  highMaintenance: MaintenanceRow[]
  obsoleteAssets: ObsoleteRow[]
  scanAlerts: ScanAlertRow[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Advanced Analytics', href: '/advanced-analytics' },
  { title: 'Lifecycle Risk', href: '/advanced-analytics/lifecycle-risk' },
]

const toneClasses: Record<string, string> = {
  rose: 'border-rose-500/30 bg-rose-500/10 text-rose-200',
  amber: 'border-amber-500/30 bg-amber-500/10 text-amber-200',
  violet: 'border-violet-500/30 bg-violet-500/10 text-violet-200',
  sky: 'border-sky-500/30 bg-sky-500/10 text-sky-200',
}

const riskBadgeClasses: Record<string, string> = {
  Overdue: 'border-rose-500/30 bg-rose-500/10 text-rose-200',
  'Due Soon': 'border-amber-500/30 bg-amber-500/10 text-amber-200',
  Watch: 'border-sky-500/30 bg-sky-500/10 text-sky-200',
}

const dataReviewTotal = computed(() =>
  Number(props.lifecycleDataQuality?.legacy_end_of_life ?? 0)
  + Number(props.lifecycleDataQuality?.future_purchase_dates ?? 0)
  + Number(props.lifecycleDataQuality?.missing_useful_life ?? 0)
)

function daysLabel(days: number | null): string {
  if (days === null || Number.isNaN(Number(days))) {
    return '—'
  }

  if (days < 0) {
    const value = Math.abs(days)
    return `${value} day${value === 1 ? '' : 's'} overdue`
  }

  if (days === 0) {
    return 'Due today'
  }

  return `${days} day${days === 1 ? '' : 's'} remaining`
}
</script>

<template>
  <Head title="Lifecycle Risk Monitoring" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-4 my-4 space-y-4 text-slate-100 sm:mx-6">
      <section class="rounded-2xl border border-slate-800 bg-slate-950 p-5 shadow-xl">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-300">
              Lifecycle analytics
            </p>
            <h1 class="mt-2 text-2xl font-black text-white sm:text-3xl">
              Lifecycle Risk Monitoring
            </h1>
            <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-300">
              Prioritises active-asset warranty exposure, near-term end-of-life,
              obsolete assets, repeated completed maintenance and recent QR scan alerts.
            </p>
          </div>

          <div class="rounded-xl border border-slate-800 bg-slate-900/60 px-4 py-3 text-xs leading-5 text-slate-300">
            <strong class="text-white">Scope:</strong>
            warranty metrics use active assets only. Other lifecycle-risk measures
            retain their own operational scope. The priority end-of-life list is
            limited to records within one year of the calculated end-of-life date.
          </div>
        </div>
      </section>

      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="risk in props.risks"
          :key="risk.label"
          class="rounded-2xl border p-4"
          :class="toneClasses[risk.tone || 'rose']"
        >
          <p class="text-xs font-semibold uppercase tracking-wider opacity-90">
            {{ risk.label }}
          </p>
          <p class="mt-2 text-2xl font-black text-white">{{ risk.value }}</p>
          <p class="mt-1 text-xs leading-5 text-slate-300">{{ risk.subtext }}</p>
        </article>
      </section>

      <section
        v-if="dataReviewTotal > 0"
        class="rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4"
      >
        <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="font-bold text-amber-100">Lifecycle data review required</h2>
            <p class="mt-1 text-sm leading-6 text-slate-300">
              Very old calculated end-of-life dates are not shown in the priority watch list.
              Review them as legacy/data-quality records before using them for management decisions.
            </p>
          </div>

          <div class="grid grid-cols-3 gap-2 text-center text-xs">
            <div class="rounded-xl border border-slate-800 bg-slate-950 px-3 py-2">
              <div class="text-lg font-black text-white">
                {{ props.lifecycleDataQuality.legacy_end_of_life }}
              </div>
              <div class="text-slate-400">Legacy EOL</div>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-950 px-3 py-2">
              <div class="text-lg font-black text-white">
                {{ props.lifecycleDataQuality.future_purchase_dates }}
              </div>
              <div class="text-slate-400">Future dates</div>
            </div>
            <div class="rounded-xl border border-slate-800 bg-slate-950 px-3 py-2">
              <div class="text-lg font-black text-white">
                {{ props.lifecycleDataQuality.missing_useful_life }}
              </div>
              <div class="text-slate-400">No useful life</div>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-4 2xl:grid-cols-[1.45fr_0.75fr]">
        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950">
          <div class="border-b border-slate-800 px-5 py-4">
            <h2 class="text-base font-bold text-white">Priority End-of-Life Watch</h2>
            <p class="mt-1 text-xs text-slate-400">
              Recently overdue assets and assets approaching calculated end-of-life within 12 months.
            </p>
          </div>

          <div v-if="props.endOfLife.length" class="max-h-[390px] overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 z-10 bg-slate-950 text-left text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                  <th class="px-4 py-3">Asset</th>
                  <th class="px-4 py-3">Name / Unit</th>
                  <th class="px-4 py-3">Purchase</th>
                  <th class="px-4 py-3">Useful Life</th>
                  <th class="px-4 py-3">End Date</th>
                  <th class="px-4 py-3">Position</th>
                  <th class="px-4 py-3">Risk</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                <tr v-for="row in props.endOfLife" :key="row.asset_code">
                  <td class="px-4 py-3 font-semibold text-white">{{ row.asset_code }}</td>
                  <td class="px-4 py-3">
                    <div class="font-medium text-slate-200">{{ row.name }}</div>
                    <div class="mt-0.5 text-xs text-slate-500">{{ row.unit }}</div>
                  </td>
                  <td class="px-4 py-3 text-slate-300">{{ row.purchase_date || '—' }}</td>
                  <td class="px-4 py-3 text-slate-300">
                    {{ row.useful_life ? `${row.useful_life} years` : '—' }}
                  </td>
                  <td class="px-4 py-3 text-slate-300">{{ row.end_date || '—' }}</td>
                  <td class="px-4 py-3 text-xs text-slate-300">
                    {{ daysLabel(row.days_remaining) }}
                  </td>
                  <td class="px-4 py-3">
                    <span
                      class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                      :class="riskBadgeClasses[row.risk] || 'border-slate-700 bg-slate-900 text-slate-300'"
                    >
                      {{ row.risk }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="px-5 py-10 text-center">
            <p class="font-semibold text-slate-200">
              No assets in the priority end-of-life window.
            </p>
            <p class="mt-1 text-sm text-slate-500">
              Historical/legacy end dates are kept out of this management watch list.
            </p>
          </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950">
          <div class="border-b border-slate-800 px-5 py-4">
            <h2 class="text-base font-bold text-white">Repeated Completed Maintenance</h2>
            <p class="mt-1 text-xs text-slate-400">
              Non-disposed assets with at least three completed maintenance jobs.
            </p>
          </div>

          <div v-if="props.highMaintenance.length" class="max-h-[390px] overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 bg-slate-950 text-left text-[11px] uppercase text-slate-400">
                <tr>
                  <th class="px-4 py-3">Asset</th>
                  <th class="px-4 py-3">Name</th>
                  <th class="px-4 py-3 text-right">Completed</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                <tr v-for="row in props.highMaintenance" :key="row.asset_code">
                  <td class="px-4 py-3 font-semibold text-white">{{ row.asset_code }}</td>
                  <td class="px-4 py-3 text-slate-300">{{ row.name }}</td>
                  <td class="px-4 py-3 text-right font-black text-violet-200">
                    {{ row.maintenance_count }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="px-5 py-10 text-center">
            <p class="font-semibold text-slate-200">No repeated-maintenance assets.</p>
            <p class="mt-1 text-sm text-slate-500">
              No non-disposed asset currently has three completed maintenance jobs.
            </p>
          </div>
        </div>
      </section>

      <section class="grid gap-4 xl:grid-cols-2">
        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950">
          <div class="border-b border-slate-800 px-5 py-4">
            <h2 class="text-base font-bold text-white">Obsolete Assets Requiring Action</h2>
            <p class="mt-1 text-xs text-slate-400">
              Obsolete assets are shown separately from already disposed historical records.
            </p>
          </div>

          <div v-if="props.obsoleteAssets.length" class="max-h-[330px] overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 bg-slate-950 text-left text-[11px] uppercase text-slate-400">
                <tr>
                  <th class="px-4 py-3">Asset</th>
                  <th class="px-4 py-3">Name / Unit</th>
                  <th class="px-4 py-3 text-right">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                <tr v-for="row in props.obsoleteAssets" :key="row.asset_code">
                  <td class="px-4 py-3 font-semibold text-white">{{ row.asset_code }}</td>
                  <td class="px-4 py-3">
                    <div class="text-slate-300">{{ row.name }}</div>
                    <div class="text-xs text-slate-500">{{ row.unit }}</div>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <span class="rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-200">
                      {{ row.status }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="px-5 py-10 text-center text-sm text-slate-500">
            No obsolete assets requiring action.
          </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950">
          <div class="border-b border-slate-800 px-5 py-4">
            <h2 class="text-base font-bold text-white">Recent QR Scan Risk Signals</h2>
            <p class="mt-1 text-xs text-slate-400">
              Unknown-location or tamper signals recorded during the last 90 days.
            </p>
          </div>

          <div v-if="props.scanAlerts.length" class="max-h-[330px] overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 bg-slate-950 text-left text-[11px] uppercase text-slate-400">
                <tr>
                  <th class="px-4 py-3">Asset / Time</th>
                  <th class="px-4 py-3">Name</th>
                  <th class="px-4 py-3 text-right">Alert</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                <tr v-for="row in props.scanAlerts" :key="`${row.asset_code}-${row.date}`">
                  <td class="px-4 py-3">
                    <div class="font-semibold text-white">
                      {{ row.asset_code || 'Unknown asset' }}
                    </div>
                    <div class="text-xs text-slate-500">{{ row.date || '—' }}</div>
                  </td>
                  <td class="px-4 py-3 text-slate-300">{{ row.name }}</td>
                  <td class="px-4 py-3 text-right">
                    <span class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-200">
                      {{ row.alert }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="px-5 py-10 text-center text-sm text-slate-500">
            No QR scan risk signals recorded during the last 90 days.
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
