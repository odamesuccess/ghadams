<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { confirmDelete } from '@/utils/sweetalert'
import { can } from '@/utils/permissions'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps<{
  filters: any
  maintenanceRequests: any
  providers: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Maintenance Requests', href: '#' },
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

const editingId = ref<number | null>(null)

type EditAssetSearchResult = {
  id: number
  asset_code: string
  asset_name?: string | null
  unit_name?: string | null
  location_name?: string | null
  status?: string | null
}

const editAssetSearchTerm = ref('')
const editAssetSearchResults = ref<EditAssetSearchResult[]>([])
const editAssetSearchOpen = ref(false)
const editAssetSearchLoading = ref(false)
const editSelectedAsset = ref<EditAssetSearchResult | null>(null)
const editAssetHighlightedIndex = ref(-1)
const editAssetSelectionError = ref(false)

let editAssetSearchTimer: ReturnType<typeof setTimeout> | null = null
let editAssetSearchController: AbortController | null = null

const editAssetLabel = (asset: EditAssetSearchResult) =>
  `${asset.asset_code} - ${asset.asset_name ?? 'Unnamed Asset'}`

const clearEditAssetSearchRequest = () => {
  if (editAssetSearchTimer) {
    clearTimeout(editAssetSearchTimer)
    editAssetSearchTimer = null
  }

  if (editAssetSearchController) {
    editAssetSearchController.abort()
    editAssetSearchController = null
  }
}

const fetchEditAssetSuggestions = async (query: string) => {
  const search = query.trim()

  if (!search) {
    editAssetSearchResults.value = []
    editAssetSearchOpen.value = false
    editAssetSearchLoading.value = false
    editAssetHighlightedIndex.value = -1
    return
  }

  editAssetSearchController?.abort()
  editAssetSearchController = new AbortController()
  editAssetSearchLoading.value = true

  try {
    const response = await fetch(
      `/maintenance-requests/asset-search?q=${encodeURIComponent(search)}`,
      {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        signal: editAssetSearchController.signal,
      },
    )

    if (!response.ok) {
      throw new Error(`Asset lookup failed with HTTP ${response.status}`)
    }

    const payload = await response.json()

    editAssetSearchResults.value = Array.isArray(payload?.data)
      ? payload.data
      : []

    editAssetSearchOpen.value = true
    editAssetHighlightedIndex.value =
      editAssetSearchResults.value.length ? 0 : -1
  } catch (error: any) {
    if (error?.name !== 'AbortError') {
      console.error('Admin maintenance asset search failed:', error)
      editAssetSearchResults.value = []
      editAssetSearchOpen.value = true
      editAssetHighlightedIndex.value = -1
    }
  } finally {
    editAssetSearchLoading.value = false
  }
}

watch(editAssetSearchTerm, (value) => {
  if (
    editSelectedAsset.value
    && value === editAssetLabel(editSelectedAsset.value)
  ) {
    return
  }

  if (editSelectedAsset.value) {
    editSelectedAsset.value = null
    form.asset_id = ''
  }

  editAssetSelectionError.value = false
  clearEditAssetSearchRequest()

  if (!value.trim()) {
    editAssetSearchResults.value = []
    editAssetSearchOpen.value = false
    editAssetHighlightedIndex.value = -1
    return
  }

  editAssetSearchTimer = setTimeout(() => {
    fetchEditAssetSuggestions(value)
  }, 180)
})

const selectEditAsset = (asset: EditAssetSearchResult) => {
  editSelectedAsset.value = asset
  form.asset_id = String(asset.id)
  editAssetSearchTerm.value = editAssetLabel(asset)
  editAssetSearchResults.value = []
  editAssetSearchOpen.value = false
  editAssetHighlightedIndex.value = -1
  editAssetSelectionError.value = false
}

const moveEditAssetHighlight = (direction: 1 | -1) => {
  if (!editAssetSearchResults.value.length) return

  editAssetSearchOpen.value = true

  const total = editAssetSearchResults.value.length

  if (editAssetHighlightedIndex.value < 0) {
    editAssetHighlightedIndex.value =
      direction === 1 ? 0 : total - 1
    return
  }

  editAssetHighlightedIndex.value =
    (editAssetHighlightedIndex.value + direction + total) % total
}

const selectHighlightedEditAsset = () => {
  if (!editAssetSearchOpen.value) return

  const asset =
    editAssetSearchResults.value[editAssetHighlightedIndex.value]

  if (asset) {
    selectEditAsset(asset)
  }
}

const closeEditAssetSuggestions = () => {
  window.setTimeout(() => {
    editAssetSearchOpen.value = false
  }, 140)
}

onBeforeUnmount(() => {
  clearEditAssetSearchRequest()
})
const isEditing = computed(() => editingId.value !== null)

const filters = useForm({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? '',
  priority: props.filters?.priority ?? '',
  payment_status: props.filters?.payment_status ?? '',
})

const canEditJob = (request: any) =>
  String(request?.payment_status ?? 'unpaid').toLowerCase() !== 'paid'

const canDeleteJob = (request: any) =>
  ['pending', 'rejected'].includes(String(request?.status ?? ''))

const canCompleteJobs = can('maintenance-requests.complete')

const statusUpdatingId = ref<number | null>(null)

const changeJobStatus = (
  request: any,
  event: Event,
) => {
  const select = event.target as HTMLSelectElement
  const currentStatus = String(request?.status ?? 'in_progress')
  const nextStatus = String(select.value)

  if (nextStatus === currentStatus) return

  if (!canCompleteJobs || !canEditJob(request)) {
    select.value = currentStatus
    return
  }

  /*
   * Completed uses the existing Head Completion modal, rather than a simple
   * status-only request, so actual cost/date and the audit trail remain valid.
   */
  if (nextStatus === 'completed') {
    select.value = currentStatus

    if (!request?.invoice_pdf_path) {
      return
    }

    openCompletion(request)
    return
  }

  statusUpdatingId.value = Number(request.id)

  router.patch(
    `/maintenance-requests/${Number(request.id)}/job-status`,
    { status: nextStatus },
    {
      preserveScroll: true,
      onError: () => {
        select.value = currentStatus
      },
      onFinish: () => {
        statusUpdatingId.value = null
      },
    },
  )
}

const invoiceUrl = (request: any) =>
  `/maintenance-requests/${Number(request.id)}/invoice`

const todayDate = () => {
  const date = new Date()
  const offset = date.getTimezoneOffset()
  return new Date(date.getTime() - offset * 60_000).toISOString().slice(0, 10)
}

const completionTarget = ref<any | null>(null)

const completionForm = useForm({
  actual_cost: '',
  actual_completion_date: todayDate(),
  completion_note: '',
})

const openCompletion = (request: any) => {
  if (!canCompleteJobs) return
  if (!['pending', 'approved', 'in_progress'].includes(String(request?.status ?? ''))) return

  completionTarget.value = request
  completionForm.actual_cost = String(request.actual_cost ?? request.estimated_cost ?? '')
  completionForm.actual_completion_date = todayDate()
  completionForm.completion_note = request.completion_note ?? ''
  completionForm.clearErrors()
}

const closeCompletion = () => {
  completionTarget.value = null
  completionForm.reset()
  completionForm.actual_completion_date = todayDate()
  completionForm.clearErrors()
}

const submitCompletion = () => {
  if (!completionTarget.value) return

  completionForm.post(`/maintenance-requests/${completionTarget.value.id}/complete`, {
    preserveScroll: true,
    onSuccess: () => closeCompletion(),
  })
}

const toDateInput = (value?: string | null) => {
  if (!value) return ''
  return String(value).slice(0, 10)
}

const cancelEdit = () => {
  editingId.value = null
  form.reset()
  form.clearErrors()

  editAssetSearchTerm.value = ''
  editAssetSearchResults.value = []
  editAssetSearchOpen.value = false
  editAssetSearchLoading.value = false
  editSelectedAsset.value = null
  editAssetHighlightedIndex.value = -1
  editAssetSelectionError.value = false

  clearEditAssetSearchRequest()
}

const startEdit = (request: any) => {
  if (!canEditJob(request)) return

  editingId.value = Number(request.id)

  const currentAssetId =
    Number(request.asset_id ?? request.asset?.id ?? 0)

  const currentAsset: EditAssetSearchResult | null =
    currentAssetId
      ? {
          id: currentAssetId,
          asset_code:
            String(request.asset?.asset_code ?? '').trim(),
          asset_name:
            request.asset?.asset_name?.assetname
            ?? request.asset?.assetName?.assetname
            ?? request.asset?.asset_name
            ?? null,
          unit_name:
            request.asset?.unit?.name
            ?? null,
          location_name:
            request.asset?.unit?.locationname
            ?? null,
          status:
            request.asset?.status
            ?? null,
        }
      : null

  editSelectedAsset.value = currentAsset
  form.asset_id = currentAssetId
    ? String(currentAssetId)
    : ''

  editAssetSearchTerm.value = currentAsset
    ? editAssetLabel(currentAsset)
    : ''

  editAssetSearchResults.value = []
  editAssetSearchOpen.value = false
  editAssetHighlightedIndex.value = -1
  editAssetSelectionError.value = false
  form.maintenance_provider_id = String(
    request.maintenance_provider_id ?? request.maintenance_provider?.id ?? '',
  )
  form.priority = request.priority ?? 'normal'
  form.fault_description = request.fault_description ?? ''
  form.estimated_cost = request.estimated_cost ?? ''
  form.scheduled_date = toDateInput(request.scheduled_date)
  form.expected_completion_date = toDateInput(request.expected_completion_date)

  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const submit = () => {
  if (!form.asset_id) {
    editAssetSelectionError.value = true
    return
  }

  if (isEditing.value && editingId.value) {
    form.put(`/maintenance-requests/${editingId.value}`, {
      preserveScroll: true,
      onSuccess: () => cancelEdit(),
    })
    return
  }

  form.post('/maintenance-requests', {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  })
}

const applyFilters = () => {
  router.get('/maintenance-requests', filters.data(), {
    preserveState: true,
    replace: true,
  })
}

const resetFilters = () => {
  filters.search = ''
  filters.status = ''
  filters.priority = ''
  filters.payment_status = ''

  router.get('/maintenance-requests', {}, {
    preserveState: true,
    replace: true,
  })
}

const postTo = (url: string, data: Record<string, any> = {}) => {
  router.post(url, data, {
    preserveScroll: true,
  })
}

const destroyRecord = async (request: any) => {
  if (!canDeleteJob(request)) return

  const confirmed = await confirmDelete({
    title: 'Delete Maintenance Job',
    text: `Move ${request.asset?.asset_code ?? 'this maintenance job'} to trash? Only Pending/Rejected jobs can be deleted.`,
    confirmText: 'Move to Trash',
  })

  if (!confirmed) return

  router.delete(`/maintenance-requests/${request.id}`, {
    preserveScroll: true,
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
  <Head title="Admin Maintenance Requests" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-slate-950 px-4 py-4 text-slate-100 sm:px-6">
      <div class="space-y-4 rounded-2xl border border-slate-800 bg-slate-950/90 p-4 shadow-xl sm:p-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="text-xl font-bold tracking-tight text-white">
                Admin Maintenance Request Module
              </h1>
              <span class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1 text-xs font-semibold text-slate-300">
                {{ maintenanceRequests?.total ?? maintenanceRequests?.data?.length ?? 0 }} records
              </span>
            </div>
            <p class="mt-1 text-sm text-slate-400">
              Create maintenance jobs, monitor Job Status, and allow the authorised head to mark completed work.
            </p>
          </div>

          <a
            href="/accounts/maintenance-payments"
            class="inline-flex h-10 items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-500/20"
          >
            Accounts Payment
          </a>
        </div>

        <div class="grid gap-2 sm:grid-cols-2">
          <div class="rounded-xl border border-purple-500/20 bg-purple-500/5 px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-purple-300">Step 1 Â· Job Created</p>
            <p class="mt-1 text-sm font-semibold text-white">Job Status: In Progress</p>
            <p class="mt-1 text-xs text-slate-400">The asset is automatically marked Under Maintenance.</p>
          </div>
          <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3">
            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">Step 2 Â· Work Done</p>
            <p class="mt-1 text-sm font-semibold text-white">Head marks the job Completed</p>
            <p class="mt-1 text-xs text-slate-400">Only then does it enter Accounts Payment and Maintenance Cost History.</p>
          </div>
        </div>

        <!-- Create / Edit Form -->
        <form
          @submit.prevent="submit"
          class="rounded-2xl border p-4"
          :class="isEditing ? 'border-amber-500/40 bg-amber-500/5' : 'border-slate-800 bg-slate-900/60'"
        >
          <div v-if="isEditing" class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-amber-300">Editing maintenance job</p>
              <p class="text-xs text-slate-400">Maintenance jobs can be edited until Accounts marks the payment as Paid.</p>
            </div>
            <button
              type="button"
              @click="cancelEdit"
              class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800"
            >
              Cancel Edit
            </button>
          </div>

          <div class="grid gap-3 lg:grid-cols-4">
            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                Asset Code
              </label>

              <div class="relative">
                <input
                  v-model="editAssetSearchTerm"
                  type="text"
                  autocomplete="off"
                  spellcheck="false"
                  placeholder="Start typing asset code..."
                  class="h-10 w-full rounded-lg border bg-black px-3 pr-24 text-sm text-white outline-none placeholder:text-slate-500 focus:ring-2"
                  :class="editAssetSelectionError
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20'
                    : 'border-slate-700 focus:border-blue-500 focus:ring-blue-500/20'"
                  @focus="
                    editAssetSearchOpen =
                      Boolean(editAssetSearchTerm.trim())
                  "
                  @blur="closeEditAssetSuggestions"
                  @keydown.down.prevent="moveEditAssetHighlight(1)"
                  @keydown.up.prevent="moveEditAssetHighlight(-1)"
                  @keydown.enter.prevent="
                    editAssetSearchOpen
                      ? selectHighlightedEditAsset()
                      : submit()
                  "
                  @keydown.esc.prevent="editAssetSearchOpen = false"
                />

                <div
                  class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[10px] font-semibold"
                  :class="
                    editAssetSearchLoading
                      ? 'text-blue-300'
                      : 'text-slate-500'
                  "
                >
                  {{
                    editAssetSearchLoading
                      ? 'Searching...'
                      : editSelectedAsset
                        ? 'Selected'
                        : 'Type to search'
                  }}
                </div>

                <div
                  v-if="editAssetSearchOpen"
                  class="absolute left-0 right-0 top-[44px] z-50 overflow-hidden rounded-xl border border-slate-700 bg-slate-950 shadow-2xl"
                >
                  <div
                    v-if="
                      editAssetSearchLoading
                      && !editAssetSearchResults.length
                    "
                    class="px-3 py-3 text-sm text-slate-400"
                  >
                    Searching assets...
                  </div>

                  <div
                    v-else-if="editAssetSearchResults.length"
                    class="max-h-72 overflow-y-auto py-1"
                  >
                    <button
                      v-for="(asset, index) in editAssetSearchResults"
                      :key="asset.id"
                      type="button"
                      class="flex w-full items-start justify-between gap-3 px-3 py-2.5 text-left transition"
                      :class="
                        index === editAssetHighlightedIndex
                          ? 'bg-blue-600/20'
                          : 'hover:bg-slate-900'
                      "
                      @mousedown.prevent="selectEditAsset(asset)"
                      @mouseenter="
                        editAssetHighlightedIndex = index
                      "
                    >
                      <div class="min-w-0">
                        <div class="truncate text-sm font-bold text-white">
                          {{ asset.asset_code }}
                        </div>

                        <div class="mt-0.5 truncate text-xs text-slate-300">
                          {{
                            asset.asset_name
                            ?? 'Unnamed Asset'
                          }}
                        </div>

                        <div
                          v-if="
                            asset.unit_name
                            || asset.location_name
                          "
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
                        {{
                          String(asset.status)
                            .replaceAll('_', ' ')
                        }}
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
                v-if="editAssetSelectionError"
                class="text-[11px] font-semibold text-red-400"
              >
                Type the asset code and select the correct asset.
              </p>
            </div>

            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Provider</label>
              <select
                v-model="form.maintenance_provider_id"
                class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              >
                <option value="">Select Provider</option>
                <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.providername }}</option>
              </select>
            </div>

            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Priority</label>
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
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Estimated Cost</label>
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
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Scheduled Date</label>
              <input
                v-model="form.scheduled_date"
                type="date"
                class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </div>

            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Expected Completion Date</label>
              <input
                v-model="form.expected_completion_date"
                type="date"
                class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </div>

            <div class="grid gap-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Fault Description</label>
              <input
                v-model="form.fault_description"
                required
                class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                placeholder="Describe the fault"
              />
            </div>

            <div class="grid gap-1.5">
              <label class="invisible text-xs font-semibold uppercase tracking-wide">Submit</label>
              <button
                type="submit"
                :disabled="form.processing"
                class="h-10 rounded-lg px-3 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60"
                :class="isEditing ? 'bg-amber-600 hover:bg-amber-500' : 'bg-blue-600 hover:bg-blue-500'"
              >
                {{ form.processing ? 'Saving...' : isEditing ? 'Update Job' : 'Create Job' }}
              </button>
            </div>
          </div>
        </form>

        <!-- Filters -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4">
          <div class="grid gap-3 xl:grid-cols-6">
            <input
              v-model="filters.search"
              @keyup.enter="applyFilters"
              class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 xl:col-span-2"
              placeholder="Search maintenance requests..."
            />

            <select v-model="filters.status" class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500">
              <option value="">All Job Statuses</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
              <option value="pending">Pending (Legacy)</option>
              <option value="approved">Approved (Legacy)</option>
              <option value="rejected">Rejected</option>
            </select>

            <select v-model="filters.priority" class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500">
              <option value="">All Priorities</option>
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>

            <select v-model="filters.payment_status" class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-blue-500">
              <option value="">All Payments</option>
              <option value="unpaid">Unpaid</option>
              <option value="pending">Payment Pending</option>
              <option value="paid">Paid</option>
            </select>

            <div class="grid grid-cols-2 gap-2">
              <button type="button" @click="applyFilters" class="h-10 rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white hover:bg-blue-500">Search</button>
              <button type="button" @click="resetFilters" class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm font-semibold text-slate-300 hover:bg-slate-800">Reset</button>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="flex items-center justify-between gap-3 px-1 text-[11px] text-slate-500">
          <span>Use the Job Status dropdown to select Pending, Approved, In Progress or Completed. Completed opens the Head Completion form.</span>
          <span class="hidden font-semibold text-slate-400 lg:inline">Actions stay visible at the right edge.</span>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-800">
          <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="overflow-x-auto"><table class="min-w-[1450px] w-full divide-y divide-slate-800 text-sm">
              <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th class="px-3 py-2.5 text-left">Date</th>
                  <th class="px-3 py-2.5 text-left">Asset</th>
                  <th class="px-3 py-2.5 text-left">Fault</th>
                  <th class="px-3 py-2.5 text-left">Priority</th>
                  <th class="px-3 py-2.5 text-left">Job Status</th>
                  <th class="px-3 py-2.5 text-left">Provider</th>
                  <th class="px-3 py-2.5 text-left">Payment</th>
                  <th class="px-3 py-2.5 text-left">Cost</th>
                  <th class="sticky right-0 z-30 min-w-[440px] border-l border-slate-700 bg-slate-900 px-3 py-2.5 text-right shadow-[-10px_0_18px_rgba(2,6,23,0.50)]">Action</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-slate-800 bg-slate-950">
                <tr v-for="r in maintenanceRequests?.data ?? []" :key="r.id" class="transition hover:bg-slate-900/70">
                  <td class="whitespace-nowrap px-3 py-2.5 text-slate-300">{{ fmt(r.created_at) }}</td>
                  <td class="whitespace-nowrap px-3 py-2.5 font-medium text-white">{{ r.asset?.asset_code ?? 'â€”' }}</td>
                  <td class="max-w-[320px] truncate px-3 py-2.5 text-slate-300">{{ r.fault_description }}</td>
                  <td class="px-3 py-2.5">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1" :class="priorityClass(r.priority)">{{ r.priority }}</span>
                  </td>
                  <td class="px-3 py-2.5">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1" :class="statusClass(r.status)">{{ String(r.status).replaceAll('_', ' ') }}</span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-2.5 text-slate-300">{{ r.maintenance_provider?.providername ?? 'â€”' }}</td>
                  <td class="whitespace-nowrap px-4 py-3">
                    <span
                      v-if="r.status === 'completed'"
                      class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize ring-1"
                      :class="paymentClass(r.payment_status)"
                    >{{ r.payment_status ?? 'unpaid' }}</span>
                    <span
                      v-else
                      class="rounded-full bg-slate-500/10 px-2.5 py-1 text-xs font-semibold text-slate-400 ring-1 ring-slate-500/20"
                    >Not Ready</span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-2.5 font-semibold text-white">{{ money(r.actual_cost ?? r.estimated_cost) }}</td>

                  <td
                    class="sticky right-0 z-20 min-w-[440px] whitespace-nowrap border-l border-slate-800 bg-slate-950 px-3 py-2.5 text-right shadow-[-10px_0_18px_rgba(2,6,23,0.50)]"
                  >
                    <div class="flex items-center justify-end gap-1.5">
                      <a
                        v-if="r.invoice_pdf_path"
                        :href="invoiceUrl(r)"
                        target="_blank"
                        rel="noopener"
                        class="rounded-md border border-emerald-500/40 bg-emerald-500/10 px-2 py-1.5 text-[11px] font-bold text-emerald-300 hover:bg-emerald-500/20"
                        title="View or print the attached maintenance job invoice"
                      >Invoice PDF</a>

                      <span
                        v-else
                        class="rounded-md border border-red-500/30 bg-red-500/10 px-2 py-1.5 text-[11px] font-semibold text-red-300"
                        title="The requester must attach a PDF invoice before the Head can complete this job."
                      >Invoice Required</span>
<select
  :value="r.status"
  title="Change Job Status"
  class="h-8 min-w-[132px] rounded-md border border-violet-500/35 bg-slate-950 px-2 text-[11px] font-bold capitalize text-violet-200 outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-500/20 disabled:cursor-not-allowed disabled:border-slate-700 disabled:text-slate-500"
  :disabled="
    !canCompleteJobs
    || !canEditJob(r)
    || statusUpdatingId === Number(r.id)
  "
  @change="changeJobStatus(r, $event)"
>
  <option value="pending">Pending</option>
  <option value="approved">Approved</option>
  <option value="in_progress">In Progress</option>
  <option
    value="completed"
    :disabled="!r.invoice_pdf_path"
  >
    {{
      r.invoice_pdf_path
        ? 'Completed'
        : 'Completed â€” Invoice Required'
    }}
  </option>
</select>
<button
                        v-if="canEditJob(r)"
                        type="button"
                        @click="startEdit(r)"
                        class="rounded-md border border-amber-500/50 bg-amber-500/10 px-2.5 py-1.5 text-[11px] font-bold text-amber-300 hover:bg-amber-500/20"
                      >Edit</button>

                      <button
                        v-if="canDeleteJob(r)"
                        type="button"
                        @click="destroyRecord(r)"
                        class="rounded-md border border-red-500/50 bg-red-500/10 px-2.5 py-1.5 text-[11px] font-bold text-red-300 hover:bg-red-500/20"
                      >Delete</button>

                      <span
                        v-if="!canEditJob(r)"
                        class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-1.5 text-[11px] font-semibold text-emerald-300"
                        title="Payment has been completed. This maintenance job is locked against further editing."
                      >Paid Â· Locked</span>
                    </div>
                  </td>
                </tr>

                <tr v-if="!(maintenanceRequests?.data ?? []).length">
                  <td colspan="9" class="px-4 py-10 text-center text-slate-400">No maintenance requests found.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div
          v-if="maintenanceRequests?.links?.length"
          class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 p-3 text-sm text-slate-400 lg:flex-row lg:items-center lg:justify-between"
        >
          <div>
            Showing <span class="font-semibold text-slate-200">{{ maintenanceRequests.from ?? 0 }}</span>
            to <span class="font-semibold text-slate-200">{{ maintenanceRequests.to ?? 0 }}</span>
            of <span class="font-semibold text-slate-200">{{ maintenanceRequests.total ?? 0 }}</span> requests
          </div>

          <div class="flex flex-wrap gap-1">
            <button
              v-for="link in maintenanceRequests.links"
              :key="link.label"
              :disabled="!link.url"
              v-html="link.label"
              @click="link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })"
              class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40"
              :class="link.active ? 'border-blue-500 bg-blue-600 text-white' : 'border-slate-700 bg-slate-950 text-slate-300 hover:bg-slate-800'"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- =========================================================
         HEAD COMPLETION MODAL
    ========================================================== -->
    <div
      v-if="completionTarget"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-black/75 p-4"
      @click.self="closeCompletion"
    >
      <form
        class="w-full max-w-xl rounded-2xl border border-slate-700 bg-slate-900 p-5 shadow-2xl"
        @submit.prevent="submitCompletion"
      >
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-emerald-300">
              Head Completion Control
            </p>
            <h2 class="mt-1 text-xl font-black text-white">
              Mark Maintenance Job Completed
            </h2>
            <p class="mt-1 text-xs leading-5 text-slate-400">
              {{ completionTarget?.asset?.asset_code ?? 'Asset' }} Â·
              {{ completionTarget?.fault_description ?? 'Maintenance job' }}
            </p>
          </div>

          <button
            type="button"
            class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800"
            @click="closeCompletion"
          >
            Close
          </button>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <label class="block">
            <span class="mb-1 block text-xs font-semibold text-slate-300">
              Actual Maintenance Cost (GHâ‚µ)
            </span>
            <input
              v-model="completionForm.actual_cost"
              type="number"
              min="0"
              step="0.01"
              required
              class="h-11 w-full rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-emerald-500"
            />
            <span v-if="completionForm.errors.actual_cost" class="mt-1 block text-xs text-red-300">
              {{ completionForm.errors.actual_cost }}
            </span>
          </label>

          <label class="block">
            <span class="mb-1 block text-xs font-semibold text-slate-300">
              Completion Date
            </span>
            <input
              v-model="completionForm.actual_completion_date"
              type="date"
              required
              class="h-11 w-full rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none focus:border-emerald-500"
            />
            <span v-if="completionForm.errors.actual_completion_date" class="mt-1 block text-xs text-red-300">
              {{ completionForm.errors.actual_completion_date }}
            </span>
          </label>
        </div>

        <label class="mt-4 block">
          <span class="mb-1 block text-xs font-semibold text-slate-300">
            Completion Note
          </span>
          <textarea
            v-model="completionForm.completion_note"
            rows="3"
            placeholder="Optional note about the completed work..."
            class="w-full rounded-lg border border-slate-700 bg-black px-3 py-2 text-sm text-white outline-none placeholder:text-slate-500 focus:border-emerald-500"
          ></textarea>
        </label>

        <div class="mt-5 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3 text-xs leading-5 text-slate-300">
          The attached <strong class="text-white">Job Invoice PDF</strong> remains available to the Head and Accounts.
          After completion, the asset returns to <strong class="text-white">Active</strong>,
          and the maintenance job becomes visible on
          <strong class="text-emerald-300">Accounts Payment</strong> and
          <strong class="text-emerald-300">Maintenance Cost History</strong>.
        </div>

        <div class="mt-5 flex justify-end gap-3">
          <button
            type="button"
            class="min-w-28 rounded-lg border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700"
            @click="closeCompletion"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="completionForm.processing"
            class="min-w-36 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-500 disabled:opacity-50"
          >
            {{ completionForm.processing ? 'Completing...' : 'Mark Completed' }}
          </button>
        </div>
      </form>
    </div>

  </AppLayout>
</template>

