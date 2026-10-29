<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { computed, reactive } from 'vue'

const props = defineProps<{
  filters: any
  maintenanceRequests: any
  providers: any[]
  summary: {
    total_requests: number
    pending_requests: number
    approved_requests: number
    completed_requests: number
    total_estimated_cost: number
    total_actual_cost: number
  }
}>()

type PaymentDraft = {
  payment_status: string
  payment_date: string
  original_payment_status: string
  original_payment_date: string
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Maintenance Payments', href: '#' },
]

const filters = useForm({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? '',
  priority: props.filters?.priority ?? '',
  provider_id: props.filters?.provider_id ?? '',
})

const paymentForm = useForm({
  payment_status: '',
  payment_date: '',
})

const paymentDrafts = reactive<Record<number, PaymentDraft>>({})

const formatDateTimeLocal = (value?: string | null) => {
  if (!value) return ''

  try {
    const date = new Date(value)

    if (Number.isNaN(date.getTime())) return ''

    const pad = (number: number) => String(number).padStart(2, '0')

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
  } catch {
    return ''
  }
}

const nowDateTimeLocal = () => {
  const date = new Date()
  const pad = (number: number) => String(number).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const ensureDraft = (request: any) => {
  const id = Number(request.id)

  if (!paymentDrafts[id]) {
    const status = request.payment_status ?? 'unpaid'
    const paymentDate = formatDateTimeLocal(request.payment_date)

    paymentDrafts[id] = {
      payment_status: status,
      payment_date: paymentDate,
      original_payment_status: status,
      original_payment_date: paymentDate,
    }
  }

  return paymentDrafts[id]
}

const isDirty = (requestId: number) => {
  const draft = paymentDrafts[requestId]

  if (!draft) return false

  return (
    draft.payment_status !== draft.original_payment_status ||
    draft.payment_date !== draft.original_payment_date
  )
}

const onPaymentStatusChange = (request: any) => {
  const draft = ensureDraft(request)

  if (draft.payment_status === 'paid') {
    draft.payment_date = nowDateTimeLocal()
    return
  }

  draft.payment_date = ''
}

const savePaymentStatus = (request: any) => {
  const id = Number(request.id)
  const draft = ensureDraft(request)

  paymentForm.payment_status = draft.payment_status
  paymentForm.payment_date = draft.payment_date

  paymentForm.post(`/maintenance-requests/${id}/payment-status`, {
    preserveScroll: true,
    onSuccess: () => {
      draft.original_payment_status = draft.payment_status
      draft.original_payment_date = draft.payment_date
    },
  })
}

const applyFilters = () => {
  router.get('/my-maintenance-payments', filters.data(), {
    preserveState: true,
    replace: true,
  })
}

const resetFilters = () => {
  filters.search = ''
  filters.status = ''
  filters.priority = ''
  filters.provider_id = ''

  router.get('/my-maintenance-payments', {}, {
    preserveState: true,
    replace: true,
  })
}

const fmt = (v?: string) => {
  if (!v) return 'â€”'

  try {
    return new Date(v).toLocaleString('en-GB', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return v
  }
}

const fmtDraftDate = (value?: string) => {
  if (!value) return 'â€”'

  try {
    return new Date(value).toLocaleString('en-GB', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return value
  }
}

const money = (value?: string | number | null) => {
  if (value === null || value === undefined || value === '') return 'â€”'

  const amount = Number(value)

  if (Number.isNaN(amount)) return value

  return amount.toLocaleString('en-GH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const statusClass = (status?: string) => {
  return {
    pending: 'bg-amber-500/10 text-amber-300 ring-amber-500/20',
    approved: 'bg-blue-500/10 text-blue-300 ring-blue-500/20',
    in_progress: 'bg-purple-500/10 text-purple-300 ring-purple-500/20',
    completed: 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20',
    rejected: 'bg-red-500/10 text-red-300 ring-red-500/20',
  }[status ?? ''] ?? 'bg-slate-500/10 text-slate-300 ring-slate-500/20'
}

const priorityClass = (priority?: string) => {
  return {
    low: 'bg-slate-500/10 text-slate-300 ring-slate-500/20',
    normal: 'bg-blue-500/10 text-blue-300 ring-blue-500/20',
    high: 'bg-orange-500/10 text-orange-300 ring-orange-500/20',
    urgent: 'bg-red-500/10 text-red-300 ring-red-500/20',
  }[priority ?? ''] ?? 'bg-slate-500/10 text-slate-300 ring-slate-500/20'
}

const paymentStatusClass = (status?: string) => {
  return {
    paid: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
    unpaid: 'border-amber-500/30 bg-amber-500/10 text-amber-300',
    pending: 'border-blue-500/30 bg-blue-500/10 text-blue-300',
  }[status ?? ''] ?? 'border-slate-700 bg-slate-900 text-slate-300'
}

const paymentDateDisplayClass = (status?: string) => {
  return status === 'paid'
    ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
    : 'border-slate-700 bg-slate-900/80 text-slate-500'
}

const totalPayable = computed(() => {
  return (props.maintenanceRequests?.data ?? []).reduce((total: number, item: any) => {
    const amount = Number(item.actual_cost ?? item.estimated_cost ?? 0)

    return total + (Number.isNaN(amount) ? 0 : amount)
  }, 0)
})
</script>

<template>
  <Head title="Maintenance Payments" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-slate-950 px-4 py-4 text-slate-100 sm:px-6">
      <div class="space-y-4 rounded-2xl border border-slate-800 bg-slate-950/90 p-4 shadow-xl sm:p-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="text-xl font-bold tracking-tight text-white">
                Maintenance Payments
              </h1>

              <span class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1 text-xs font-semibold text-slate-300">
                {{ maintenanceRequests?.total ?? maintenanceRequests?.data?.length ?? 0 }} records
              </span>
            </div>

            <p class="mt-1 text-sm text-slate-400">
              Review payment status, payable maintenance costs, providers, and payment dates.
            </p>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-900/70 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Current Page Payable
            </p>

            <p class="mt-1 text-lg font-bold text-white">
              {{ money(totalPayable) }}
            </p>
          </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
          <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Total Requests
            </p>
            <p class="mt-2 text-lg font-bold text-white">
              {{ summary?.total_requests ?? 0 }}
            </p>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Pending
            </p>
            <p class="mt-2 text-lg font-bold text-amber-300">
              {{ summary?.pending_requests ?? 0 }}
            </p>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Approved
            </p>
            <p class="mt-2 text-lg font-bold text-blue-300">
              {{ summary?.approved_requests ?? 0 }}
            </p>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Completed
            </p>
            <p class="mt-2 text-lg font-bold text-emerald-300">
              {{ summary?.completed_requests ?? 0 }}
            </p>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Estimated Cost
            </p>
            <p class="mt-2 text-lg font-bold text-white">
              {{ money(summary?.total_estimated_cost) }}
            </p>
          </div>

          <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Actual Cost
            </p>
            <p class="mt-2 text-lg font-bold text-white">
              {{ money(summary?.total_actual_cost) }}
            </p>
          </div>
        </div>

        <!-- Filters -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="grid gap-3 xl:grid-cols-6">
            <input
              v-model="filters.search"
              @keyup.enter="applyFilters"
              class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 xl:col-span-2"
              placeholder="Search asset, provider, requester, fault..."
            />

            <select
              v-model="filters.status"
              class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            >
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
              <option value="rejected">Rejected</option>
            </select>

            <select
              v-model="filters.priority"
              class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            >
              <option value="">All Priorities</option>
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>

            <select
              v-model="filters.provider_id"
              class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            >
              <option value="">All Providers</option>

              <option
                v-for="provider in providers"
                :key="provider.id"
                :value="provider.id"
              >
                {{ provider.providername }}
              </option>
            </select>

            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                @click="applyFilters"
                class="h-10 rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white transition hover:bg-blue-500"
              >
                Search
              </button>

              <button
                type="button"
                @click="resetFilters"
                class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm font-semibold text-slate-300 transition hover:bg-slate-800 hover:text-white"
              >
                Reset
              </button>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-2xl border border-slate-800">
          <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-800 text-sm">
              <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th class="px-4 py-3 text-left">Date</th>
                  <th class="px-4 py-3 text-left">Asset</th>
                  <th class="px-4 py-3 text-left">Fault</th>
                  <th class="px-4 py-3 text-left">Priority</th>
                  <th class="px-4 py-3 text-left">Status</th>
                  <th class="px-4 py-3 text-left">Provider</th>
                  <th class="px-4 py-3 text-left">Payment Status</th>
                  <th class="px-4 py-3 text-left">Payment Date</th>
                  <th class="px-4 py-3 text-left">Payable</th>
                  <th class="px-4 py-3 text-right">Save</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-800 bg-slate-950">
                <tr
                  v-for="r in maintenanceRequests?.data ?? []"
                  :key="r.id"
                  class="transition hover:bg-slate-900/70"
                >
                  <td class="whitespace-nowrap px-4 py-3 text-slate-300">
                    {{ fmt(r.created_at) }}
                  </td>

                  <td class="whitespace-nowrap px-4 py-3 font-medium text-white">
                    {{ r.asset?.asset_code ?? 'â€”' }}
                  </td>

                  <td class="max-w-md truncate px-4 py-3 text-slate-300">
                    {{ r.fault_description }}
                  </td>

                  <td class="px-4 py-3">
                    <span
                      class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1"
                      :class="priorityClass(r.priority)"
                    >
                      {{ r.priority }}
                    </span>
                  </td>

                  <td class="px-4 py-3">
                    <span
                      class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1"
                      :class="statusClass(r.status)"
                    >
                      {{ String(r.status).replaceAll('_', ' ') }}
                    </span>
                  </td>

                  <td class="whitespace-nowrap px-4 py-3 text-slate-300">
                    {{ r.maintenance_provider?.providername ?? 'â€”' }}
                  </td>

                  <td class="whitespace-nowrap px-4 py-3">
                    <select
                      v-model="ensureDraft(r).payment_status"
                      @change="onPaymentStatusChange(r)"
                      class="h-9 rounded-lg border px-3 text-xs font-semibold outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                      :class="paymentStatusClass(ensureDraft(r).payment_status)"
                    >
                      <option value="unpaid">Unpaid</option>
                      <option value="pending">Pending</option>
                      <option value="paid">Paid</option>
                    </select>
                  </td>

                  <td class="whitespace-nowrap px-4 py-3">
                    <div
                      class="inline-flex h-9 min-w-[155px] items-center rounded-lg border px-3 text-xs font-semibold transition"
                      :class="paymentDateDisplayClass(ensureDraft(r).payment_status)"
                    >
                      {{ ensureDraft(r).payment_status === 'paid'
                        ? fmtDraftDate(ensureDraft(r).payment_date)
                        : 'Not paid yet'
                      }}
                    </div>
                  </td>

                  <td class="whitespace-nowrap px-4 py-3 font-semibold text-white">
                    {{ money(r.actual_cost ?? r.estimated_cost) }}
                  </td>

                  <td class="whitespace-nowrap px-4 py-3 text-right">
                    <button
                      type="button"
                      :disabled="!isDirty(Number(r.id)) || paymentForm.processing"
                      @click="savePaymentStatus(r)"
                      class="h-9 rounded-lg px-3 text-xs font-semibold transition"
                      :class="isDirty(Number(r.id))
                        ? 'bg-blue-600 text-white hover:bg-blue-500'
                        : 'cursor-not-allowed border border-slate-700 bg-slate-900 text-slate-500'"
                    >
                      {{ paymentForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                  </td>
                </tr>

                <tr v-if="!(maintenanceRequests?.data ?? []).length">
                  <td
                    colspan="10"
                    class="px-4 py-10 text-center text-slate-400"
                  >
                    No maintenance payment records found.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pagination Summary -->
        <div
          v-if="maintenanceRequests?.links?.length"
          class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 p-3 text-sm text-slate-400 lg:flex-row lg:items-center lg:justify-between"
        >
          <div>
            Showing
            <span class="font-semibold text-slate-200">{{ maintenanceRequests.from ?? 0 }}</span>
            to
            <span class="font-semibold text-slate-200">{{ maintenanceRequests.to ?? 0 }}</span>
            of
            <span class="font-semibold text-slate-200">{{ maintenanceRequests.total ?? 0 }}</span>
            records
          </div>

          <div class="flex flex-wrap gap-1">
            <button
              v-for="link in maintenanceRequests.links"
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
  </AppLayout>
</template>

