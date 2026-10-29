<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { onBeforeUnmount, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

const props = defineProps<{
  filters: any
  maintenanceRequests: any
  providers: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'My Maintenance Jobs', href: '/my-maintenance-requests' },
]

const form = useForm({
  asset_id: '',
  maintenance_provider_id: '',
  priority: 'normal',
  fault_description: '',
  estimated_cost: '',
  scheduled_date: '',
  expected_completion_date: '',
})

type AssetSearchResult = {
  id: number
  asset_code: string
  asset_name?: string | null
  unit_name?: string | null
  location_name?: string | null
  status?: string | null
}

const assetSearchTerm = ref('')
const assetSearchResults = ref<AssetSearchResult[]>([])
const assetSearchOpen = ref(false)
const assetSearchLoading = ref(false)
const selectedAsset = ref<AssetSearchResult | null>(null)
const highlightedAssetIndex = ref(-1)

let assetSearchTimer: ReturnType<typeof setTimeout> | null = null
let assetSearchController: AbortController | null = null

const assetLabel = (asset: AssetSearchResult) =>
  `${asset.asset_code} - ${asset.asset_name ?? 'Unnamed Asset'}`

const clearAssetSearchRequest = () => {
  if (assetSearchTimer) {
    clearTimeout(assetSearchTimer)
    assetSearchTimer = null
  }

  if (assetSearchController) {
    assetSearchController.abort()
    assetSearchController = null
  }
}

const fetchAssetSuggestions = async (query: string) => {
  const search = query.trim()

  if (!search) {
    assetSearchResults.value = []
    assetSearchOpen.value = false
    assetSearchLoading.value = false
    highlightedAssetIndex.value = -1
    return
  }

  assetSearchController?.abort()
  assetSearchController = new AbortController()
  assetSearchLoading.value = true

  try {
    const response = await fetch(
      `/maintenance-requests/asset-search?q=${encodeURIComponent(search)}`,
      {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        signal: assetSearchController.signal,
      },
    )

    if (!response.ok) {
      throw new Error(`Asset lookup failed with HTTP ${response.status}`)
    }

    const payload = await response.json()

    assetSearchResults.value = Array.isArray(payload?.data)
      ? payload.data
      : []

    assetSearchOpen.value = true
    highlightedAssetIndex.value = assetSearchResults.value.length ? 0 : -1
  } catch (error: any) {
    if (error?.name !== 'AbortError') {
      console.error('Asset live search failed:', error)
      assetSearchResults.value = []
      assetSearchOpen.value = true
      highlightedAssetIndex.value = -1
    }
  } finally {
    assetSearchLoading.value = false
  }
}

watch(assetSearchTerm, (value) => {
  /*
   * When a selected asset is displayed in the input, do not search again.
   * If the officer edits the text, clear the old asset_id immediately so a
   * different result must be selected before the job can be created.
   */
  if (selectedAsset.value && value === assetLabel(selectedAsset.value)) {
    return
  }

  if (selectedAsset.value) {
    selectedAsset.value = null
    form.asset_id = ''
  }

  assetSelectionError.value = false
  clearAssetSearchRequest()

  if (!value.trim()) {
    assetSearchResults.value = []
    assetSearchOpen.value = false
    highlightedAssetIndex.value = -1
    return
  }

  assetSearchTimer = setTimeout(() => {
    fetchAssetSuggestions(value)
  }, 180)
})

const selectAsset = (asset: AssetSearchResult) => {
  selectedAsset.value = asset
  form.asset_id = String(asset.id)
  assetSearchTerm.value = assetLabel(asset)
  assetSearchResults.value = []
  assetSearchOpen.value = false
  highlightedAssetIndex.value = -1
  assetSelectionError.value = false
}

const moveAssetHighlight = (direction: 1 | -1) => {
  if (!assetSearchResults.value.length) return

  assetSearchOpen.value = true

  const maxIndex = assetSearchResults.value.length - 1

  if (highlightedAssetIndex.value < 0) {
    highlightedAssetIndex.value = direction === 1 ? 0 : maxIndex
    return
  }

  highlightedAssetIndex.value =
    (highlightedAssetIndex.value + direction + assetSearchResults.value.length)
    % assetSearchResults.value.length
}

const selectHighlightedAsset = () => {
  if (!assetSearchOpen.value) return

  const asset = assetSearchResults.value[highlightedAssetIndex.value]

  if (asset) {
    selectAsset(asset)
  }
}

const closeAssetSuggestions = () => {
  window.setTimeout(() => {
    assetSearchOpen.value = false
  }, 140)
}

onBeforeUnmount(() => {
  clearAssetSearchRequest()
})

const filters = useForm({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? '',
  priority: props.filters?.priority ?? '',
})

const assetSelectionError = ref(false)

const submit = () => {
  if (!form.asset_id) {
    assetSelectionError.value = true
    return
  }

  form.post('/maintenance-requests', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      assetSearchTerm.value = ''
      selectedAsset.value = null
      assetSearchResults.value = []
      assetSearchOpen.value = false
      assetSelectionError.value = false
    },
  })
}

const applyFilters = () => {
  router.get('/my-maintenance-requests', filters.data(), {
    preserveState: true,
    replace: true,
  })
}

const resetFilters = () => {
  filters.search = ''
  filters.status = ''
  filters.priority = ''

  router.get('/my-maintenance-requests', {}, {
    preserveState: true,
    replace: true,
  })
}

const uploadingInvoiceId = ref<number | null>(null)

const invoiceUrl = (request: any) =>
  `/maintenance-requests/${Number(request.id)}/invoice`

const invoiceLocked = (request: any) =>
  request?.payment_status === 'paid' && Boolean(request?.invoice_pdf_path)

const uploadInvoice = (event: Event, request: any) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) return

  const data = new FormData()
  data.append('invoice_pdf', file)

  uploadingInvoiceId.value = Number(request.id)

  router.post(
    `/maintenance-requests/${Number(request.id)}/invoice`,
    data,
    {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => {
        uploadingInvoiceId.value = null
        input.value = ''
      },
    },
  )
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

const paymentClass = (status?: string | null) => {
  return {
    paid: 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/20',
    pending: 'bg-blue-500/10 text-blue-300 ring-blue-500/20',
    unpaid: 'bg-amber-500/10 text-amber-300 ring-amber-500/20',
  }[status ?? 'unpaid'] ?? 'bg-slate-500/10 text-slate-300 ring-slate-500/20'
}
</script>

<template>
  <Head title="My Maintenance Jobs" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-slate-950 px-4 py-4 text-slate-100 sm:px-6">
      <div class="space-y-4 rounded-2xl border border-slate-800 bg-slate-950/90 p-4 shadow-xl sm:p-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="text-xl font-bold tracking-tight text-white">
                My Maintenance Jobs
              </h1>

              <span class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1 text-xs font-semibold text-slate-300">
                {{ maintenanceRequests?.total ?? maintenanceRequests?.data?.length ?? 0 }} records
              </span>
            </div>

            <p class="mt-1 text-sm text-slate-400">
              Create maintenance jobs and track each Job Status from In Progress to Completed.
            </p>
          </div>
        </div>

        <!-- Request Form -->
        <form
          @submit.prevent="submit"
          class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4"
        >
          <div class="grid gap-3 lg:grid-cols-4">
            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Asset Code
              </label>

              <div class="relative">
                <input
                  v-model="assetSearchTerm"
                  type="text"
                  autocomplete="off"
                  spellcheck="false"
                  required
                  placeholder="Start typing asset code..."
                  class="h-10 w-full rounded-lg border bg-black px-3 pr-24 text-sm text-white outline-none placeholder:text-slate-500 focus:ring-2"
                  :class="assetSelectionError
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20'
                    : 'border-slate-700 focus:border-blue-500 focus:ring-blue-500/20'"
                  @focus="assetSearchOpen = Boolean(assetSearchTerm.trim())"
                  @blur="closeAssetSuggestions"
                  @keydown.down.prevent="moveAssetHighlight(1)"
                  @keydown.up.prevent="moveAssetHighlight(-1)"
                  @keydown.enter.prevent="assetSearchOpen ? selectHighlightedAsset() : submit()"
                  @keydown.esc.prevent="assetSearchOpen = false"
                />

                <div
                  class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[11px] font-semibold"
                  :class="assetSearchLoading ? 'text-blue-300' : 'text-slate-500'"
                >
                  {{ assetSearchLoading ? 'Searchingâ€¦' : (selectedAsset ? 'Selected' : 'Type to search') }}
                </div>

                <div
                  v-if="assetSearchOpen"
                  class="absolute left-0 right-0 top-[44px] z-50 overflow-hidden rounded-xl border border-slate-700 bg-slate-950 shadow-2xl"
                >
                  <div
                    v-if="assetSearchLoading && !assetSearchResults.length"
                    class="px-3 py-3 text-sm text-slate-400"
                  >
                    Searching assetsâ€¦
                  </div>

                  <div
                    v-else-if="assetSearchResults.length"
                    class="max-h-72 overflow-y-auto py-1"
                  >
                    <button
                      v-for="(asset, index) in assetSearchResults"
                      :key="asset.id"
                      type="button"
                      class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left transition"
                      :class="index === highlightedAssetIndex
                        ? 'bg-blue-600/20'
                        : 'hover:bg-slate-900'"
                      @mousedown.prevent="selectAsset(asset)"
                      @mouseenter="highlightedAssetIndex = index"
                    >
                      <div class="min-w-0">
                        <div class="truncate text-sm font-bold text-white">
                          {{ asset.asset_code }}
                        </div>

                        <div class="mt-0.5 truncate text-xs text-slate-300">
                          {{ asset.asset_name ?? 'Unnamed Asset' }}
                        </div>

                        <div
                          v-if="asset.unit_name || asset.location_name"
                          class="mt-0.5 truncate text-[11px] text-slate-500"
                        >
                          {{ asset.unit_name ?? 'No unit' }}
                          <span v-if="asset.location_name">
                            Â· {{ asset.location_name }}
                          </span>
                        </div>
                      </div>

                      <span
                        v-if="asset.status"
                        class="shrink-0 rounded-full border border-slate-700 bg-slate-900 px-2 py-1 text-[10px] font-semibold capitalize text-slate-400"
                      >
                        {{ String(asset.status).replaceAll('_', ' ') }}
                      </span>
                    </button>
                  </div>

                  <div
                    v-else
                    class="px-3 py-3 text-sm text-slate-400"
                  >
                    No matching asset found.
                  </div>
                </div>
              </div>

              <p
                v-if="assetSelectionError"
                class="text-[11px] font-semibold text-red-400"
              >
                Type the asset code and select the correct asset from the suggestions.
              </p>

              <p
                v-else-if="selectedAsset"
                class="truncate text-[11px] text-emerald-400"
              >
                Selected: {{ selectedAsset.asset_code }} Â·
                {{ selectedAsset.asset_name ?? 'Unnamed Asset' }}
              </p>
            </div>

            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Provider
              </label>

              <select
                v-model="form.maintenance_provider_id"
                class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              >
                <option value="">Select Provider</option>

                <option
                  v-for="p in providers"
                  :key="p.id"
                  :value="p.id"
                >
                  {{ p.providername }}
                </option>
              </select>
            </div>

            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Priority
              </label>

              <select
                v-model="form.priority"
                class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              >
                <option value="low">Low Priority</option>
                <option value="normal">Normal Priority</option>
                <option value="high">High Priority</option>
                <option value="urgent">Urgent Priority</option>
              </select>
            </div>

            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Estimated Cost
              </label>

              <input
                v-model="form.estimated_cost"
                type="number"
                min="0"
                step="0.01"
                class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                placeholder="Estimated cost"
              />
            </div>

            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Scheduled Date
              </label>

              <input
                v-model="form.scheduled_date"
                type="date"
                class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </div>

            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Expected Completion Date
              </label>

              <input
                v-model="form.expected_completion_date"
                type="date"
                class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </div>

            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Fault Description
              </label>

              <input
                v-model="form.fault_description"
                required
                class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                placeholder="Describe the fault"
              />
            </div>

            <div class="grid gap-1.5">
              <label class="invisible text-xs font-semibold uppercase tracking-wide">
                Submit
              </label>

              <button
                type="submit"
                :disabled="form.processing"
                class="h-10 rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
              >
                {{ form.processing ? 'Creating...' : 'Create Job' }}
              </button>
            </div>
          </div>
        </form>

        <!-- Job Status Workflow -->
        <div class="grid gap-2 sm:grid-cols-3">
          <div class="rounded-xl border border-purple-500/20 bg-purple-500/5 px-3 py-2.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-purple-300">
              Job Created
            </p>
            <p class="mt-1 text-sm font-bold text-white">In Progress</p>
          </div>

          <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-3 py-2.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">
              Work Finished
            </p>
            <p class="mt-1 text-sm font-bold text-white">Completed by Head</p>
          </div>

          <div class="rounded-xl border border-blue-500/20 bg-blue-500/5 px-3 py-2.5">
            <p class="text-[10px] font-bold uppercase tracking-wider text-blue-300">
              After Completion
            </p>
            <p class="mt-1 text-sm font-bold text-white">Ready for Accounts</p>
          </div>
        </div>

        <!-- Filters -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="grid gap-3 lg:grid-cols-5">
            <input
              v-model="filters.search"
              @keyup.enter="applyFilters"
              class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 lg:col-span-2"
              placeholder="Search maintenance requests..."
            />

            <select
              v-model="filters.status"
              class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            >
              <option value="">All Job Statuses</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
              <option value="pending">Pending (Legacy)</option>
              <option value="approved">Approved (Legacy)</option>
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
          <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="overflow-x-auto"><table class="min-w-[1450px] w-full divide-y divide-slate-800 text-sm">
              <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th class="px-4 py-3 text-left">Date</th>
                  <th class="px-4 py-3 text-left">Asset</th>
                  <th class="px-4 py-3 text-left">Fault</th>
                  <th class="px-4 py-3 text-left">Priority</th>
                  <th class="min-w-[140px] px-4 py-3 text-left">Job Status</th>
                  <th class="px-4 py-3 text-left">Provider</th>
                  <th class="min-w-[210px] px-4 py-3 text-left">Job Invoice PDF</th>
                  <th class="min-w-[130px] px-4 py-3 text-left">Payment Status</th>
                  <th class="px-4 py-3 text-left">Cost</th>
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

                  <td class="min-w-[140px] px-4 py-3">
                    <span
                      class="rounded-full px-2.5 py-1 text-xs font-bold capitalize ring-1"
                      :class="statusClass(r.status)"
                    >
                      {{ String(r.status).replaceAll('_', ' ') }}
                    </span>
                  </td>

                  <td class="whitespace-nowrap px-4 py-3 text-slate-300">
                    {{ r.maintenance_provider?.providername ?? 'â€”' }}
                  </td>

                  <td class="min-w-[210px] whitespace-nowrap px-4 py-3">
                    <div class="flex items-center gap-2">
                      <a
                        v-if="r.invoice_pdf_path"
                        :href="invoiceUrl(r)"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-8 items-center rounded-md border border-emerald-500/35 bg-emerald-500/10 px-2.5 text-[11px] font-bold text-emerald-300 hover:bg-emerald-500/20"
                        title="Open the original job invoice PDF to view or print"
                      >
                        View / Print
                      </a>

                      <label
                        class="inline-flex h-8 items-center rounded-md border px-2.5 text-[11px] font-bold transition"
                        :class="invoiceLocked(r)
                          ? 'cursor-not-allowed border-slate-700 bg-slate-900 text-slate-500'
                          : 'cursor-pointer border-blue-500/35 bg-blue-500/10 text-blue-300 hover:bg-blue-500/20'"
                      >
                        {{
                          uploadingInvoiceId === Number(r.id)
                            ? 'Uploading...'
                            : invoiceLocked(r)
                              ? 'Locked'
                              : r.invoice_pdf_path
                                ? 'Replace PDF'
                                : 'Attach PDF'
                        }}

                        <input
                          type="file"
                          accept="application/pdf,.pdf"
                          class="hidden"
                          :disabled="invoiceLocked(r) || uploadingInvoiceId === Number(r.id)"
                          @change="uploadInvoice($event, r)"
                        />
                      </label>
                    </div>
                  </td>

                  <td class="min-w-[130px] whitespace-nowrap px-4 py-3">
                    <span
                      v-if="r.status === 'completed'"
                      class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1"
                      :class="paymentClass(r.payment_status)"
                    >
                      {{ r.payment_status ?? 'unpaid' }}
                    </span>

                    <span
                      v-else
                      class="rounded-full bg-slate-500/10 px-2.5 py-1 text-xs font-semibold text-slate-400 ring-1 ring-slate-500/20"
                    >
                      Not Ready
                    </span>
                  </td>

                  <td class="whitespace-nowrap px-4 py-3 font-semibold text-white">
                    {{ money(r.actual_cost ?? r.estimated_cost) }}
                  </td>
                </tr>

                <tr v-if="!(maintenanceRequests?.data ?? []).length">
                  <td
                    colspan="9"
                    class="px-4 py-10 text-center text-slate-400"
                  >
                    No maintenance requests found.
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
            requests
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

