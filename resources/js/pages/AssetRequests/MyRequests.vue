<script setup lang="ts">
import { ref } from 'vue'
import { can } from '@/utils/permissions'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { confirmAction } from '@/utils/sweetalert'
const props = defineProps<{
    minimumNeededDate?: string
    filters: any
    assetRequests: any
    units: any[]
    assets: any[]
}>()
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Asset Requests', href: '#' },
]
const form = useForm({
    unit_id: '',
    asset_id: '',
    asset_category: '',
    quantity: 1,
    preferred_specification: '',
    justification: '',
    priority: 'medium',
    needed_date: '',
})
const filters = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
    priority: props.filters?.priority ?? '',
})
const assetLabel = (asset: any) => {
    return asset.label
        ?? `${asset.asset_name ?? asset.assetName?.assetname ?? 'Unnamed Asset'}${asset.asset_code ? ` - ${asset.asset_code}` : ''}`
}
const editingId = ref<number | null>(null)
const startEdit = (request: any) => {
    editingId.value = request.id
    form.clearErrors()
    form.unit_id = String(request.unit_id ?? '')
    form.asset_id = String(request.asset_id ?? '')
    form.asset_category = request.asset_category ?? ''
    form.quantity = request.quantity ?? 1
    form.preferred_specification = request.preferred_specification ?? ''
    form.justification = request.justification ?? ''
    form.priority = request.priority ?? 'medium'
    form.needed_date = request.needed_date?.slice(0, 10) ?? ''
    window.scrollTo({ top: 0, behavior: 'smooth' })
}
const cancelEdit = () => { editingId.value = null; form.reset(); form.clearErrors() }
const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => cancelEdit() }
    if (editingId.value !== null) form.patch(`/asset-requests/${editingId.value}`, options)
    else form.post('/asset-requests', options)
}

const applyFilters = () => {
    router.get('/my-asset-requests', filters.data(), {
        preserveState: true,
        replace: true,
    })
}
const cancelRequest = async (id: number) => {
    const confirmed = await confirmAction({
        title: 'Cancel Asset Request',
        text: 'Are you sure you want to cancel this asset request?',
        confirmText: 'Cancel Request',
        icon: 'warning',
    })
    if (confirmed) {
        router.post(`/asset-requests/${id}/cancel`, {}, { preserveScroll: true })
    }
}
const fmt = (value?: string) => value ? new Date(value).toLocaleString('en-GB') : 'Not recorded'
const fmtDate = (value?: string) => value ? new Date(value).toLocaleDateString('en-GB') : 'Not recorded'
const statusClass = (status: string) => {
    if (status === 'pending') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-300'
    }
    if (status === 'approved') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
    }
    if (status === 'supplied') {
        return 'border-blue-500/30 bg-blue-500/10 text-blue-300'
    }
    if (status === 'rejected') {
        return 'border-red-500/30 bg-red-500/10 text-red-300'
    }
    return 'border-slate-600 bg-slate-800 text-slate-300'
}
const priorityClass = (priority: string) => {
    if (priority === 'urgent') {
        return 'border-red-500/30 bg-red-500/10 text-red-300'
    }
    if (priority === 'high') {
        return 'border-orange-500/30 bg-orange-500/10 text-orange-300'
    }
    if (priority === 'medium') {
        return 'border-blue-500/30 bg-blue-500/10 text-blue-300'
    }
    return 'border-slate-600 bg-slate-800 text-slate-300'
}
</script>
<template>
    <Head title="My Asset Requests" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-4 my-4 space-y-4 rounded-2xl border border-slate-800 bg-slate-950/90 p-4 text-slate-100 shadow-xl sm:mx-6 sm:p-6">
            <div class="flex items-center justify-between gap-4 overflow-x-auto">
                <div class="flex shrink-0 items-center gap-4 whitespace-nowrap">
                    <h1 class="text-xl font-bold text-white">
                        Asset Requests
                    </h1>
                    <p class="text-sm text-slate-400">
                        Submit and track asset requests from your office, unit or department.
                    </p>
                </div>
                <div class="shrink-0 whitespace-nowrap rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-xs text-slate-300">
                    Total Requests: {{ assetRequests?.total ?? assetRequests?.data?.length ?? 0 }}
                </div>
            </div>
            <form
                @submit.prevent="submit"
                class="space-y-4 rounded-xl border border-slate-800 bg-slate-900/50 p-4"
            >
                <div class="flex flex-col gap-1 border-b border-slate-800 pb-3">
                    <h2 class="text-sm font-semibold text-white">
                        New Asset Request
                    </h2>
                    <p class="text-xs text-slate-400">
                        Complete the form below to request an asset for your office or unit.
                    </p>
                </div>
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Requesting Office / Unit
                            <span class="text-red-400">*</span>
                        </label>
                        <select
                            v-model="form.unit_id"
                            required
                            class="h-10 w-full rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                        >
                            <option value="">Select office/unit</option>
                            <option
                                v-for="unit in units"
                                :key="unit.id"
                                :value="unit.id"
                            >
                                {{ unit.name }}{{ unit.unit_abbreviation ? ` (${unit.unit_abbreviation})` : '' }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.unit_id"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.unit_id }}
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Requested Asset
                            <span class="text-red-400">*</span>
                        </label>
                        <select
                            v-model="form.asset_id"
                            required
                            class="h-10 w-full rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                        >
                            <option value="">Select requested asset</option>
                            <option
                                v-for="asset in assets"
                                :key="asset.id"
                                :value="asset.id"
                            >
                                {{ assetLabel(asset) }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.asset_id"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.asset_id }}
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Asset Category
                        </label>
                        <input
                            v-model="form.asset_category"
                            class="h-10 w-full rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                            placeholder="e.g. ICT Equipment"
                        />
                        <p
                            v-if="form.errors.asset_category"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.asset_category }}
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Quantity
                            <span class="text-red-400">*</span>
                        </label>
                        <input
                            v-model.number="form.quantity"
                            required
                            type="number"
                            min="1"
                            class="h-10 w-full rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                            placeholder="Enter quantity"
                        />
                        <p
                            v-if="form.errors.quantity"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.quantity }}
                        </p>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Priority Level
                            <span class="text-red-400">*</span>
                        </label>
                        <select
                            v-model="form.priority"
                            required
                            class="h-10 w-full rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                        >
                            <option value="low">Low Priority</option>
                            <option value="medium">Medium Priority</option>
                            <option value="high">High Priority</option>
                            <option value="urgent">Urgent Priority</option>
                        </select>
                        <p
                            v-if="form.errors.priority"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.priority }}
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Date Needed
                        </label>
                        <input
                            v-model="form.needed_date" :min="props.minimumNeededDate"
                            type="date"
                            class="h-10 w-full rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                        />
                        <p
                            v-if="form.errors.needed_date"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.needed_date }}
                        </p>
                    </div>
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Preferred Specification
                        </label>
                        <input
                            v-model="form.preferred_specification"
                            class="h-10 w-full rounded-lg border border-slate-700 bg-black px-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                            placeholder="e.g. Core i5, 8GB RAM, 512GB SSD"
                        />
                        <p
                            v-if="form.errors.preferred_specification"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.preferred_specification }}
                        </p>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Justification / Reason for Request
                        <span class="text-red-400">*</span>
                    </label>
                    <textarea
                        v-model="form.justification"
                        required
                        rows="3"
                        class="w-full rounded-lg border border-slate-700 bg-black px-3 py-2 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                        placeholder="Explain why this asset is needed by your office/unit"
                    ></textarea>
                    <p
                        v-if="form.errors.justification"
                        class="text-xs text-red-400"
                    >
                        {{ form.errors.justification }}
                    </p>
                </div>
                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ form.processing ? 'Saving...' : editingId !== null ? 'Save Changes' : 'Submit Asset Request' }}
                    </button>
                </div>
            <button v-if="editingId !== null" type="button" @click="cancelEdit" class="text-sm text-slate-300">Cancel Edit</button>
</form>
            <div class="grid gap-2 md:grid-cols-4">
                <input
                    v-model="filters.search"
                    @keyup.enter="applyFilters"
                    class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-slate-100 outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                    placeholder="Search my requests..."
                />
                <select
                    v-model="filters.status"
                    class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-slate-100 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                >
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="supplied">Supplied</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select
                    v-model="filters.priority"
                    class="h-10 rounded-lg border border-slate-700 bg-black px-3 text-sm text-slate-100 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30"
                >
                    <option value="">All Priorities</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
                <button
                    type="button"
                    @click="applyFilters"
                    class="h-10 rounded-lg bg-blue-600 px-3 text-sm font-semibold text-white transition hover:bg-blue-500"
                >
                    Search
                </button>
            </div>
            <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="overflow-x-auto rounded-xl border border-slate-800"><table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900 text-xs uppercase text-slate-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Code</th>
                            <th class="px-3 py-2 text-left">Asset</th>
                            <th class="px-3 py-2 text-left">Office</th>
                            <th class="px-3 py-2 text-left">Qty</th>
                            <th class="px-3 py-2 text-left">Priority</th>
                            <th class="px-3 py-2 text-left">Needed</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <tr
                            v-for="request in assetRequests.data"
                            :key="request.id"
                            class="hover:bg-slate-900/60"
                        >
                            <td class="whitespace-nowrap px-3 py-2">
                                {{ fmt(request.created_at) }}
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-slate-300">
                                {{ request.request_code }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-white">
                                    {{ request.asset_name }}
                                </div>
                                <div
                                    v-if="request.asset?.asset_code"
                                    class="text-xs text-blue-300"
                                >
                                    {{ request.asset?.assetName?.assetname ?? request.asset_name }}
                                    - {{ request.asset.asset_code }}
                                </div>
                                <div class="text-xs text-slate-400">
                                    {{ request.preferred_specification ?? request.asset_category ?? 'Not recorded' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div>{{ request.unit?.name ?? 'Not recorded' }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ request.unit?.unit_abbreviation ?? '' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                {{ request.quantity }}
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    :class="[
                                        'rounded-full border px-2 py-1 text-xs font-medium capitalize',
                                        priorityClass(request.priority),
                                    ]"
                                >
                                    {{ request.priority }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                {{ fmtDate(request.needed_date) }}
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    :class="[
                                        'rounded-full border px-2 py-1 text-xs font-medium capitalize',
                                        statusClass(request.status),
                                    ]"
                                >
                                    {{ request.status }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right"><button v-if="request.status === 'pending' && can('asset-requests.create')" type="button" @click="startEdit(request)" class="mr-2 rounded-lg border border-blue-500/40 px-2 py-1 text-xs font-semibold text-blue-300 hover:bg-blue-500/10">Edit</button>
                                <button
                                    v-if="request.status === 'pending'"
                                    type="button"
                                    @click="cancelRequest(request.id)"
                                    class="rounded-lg border border-orange-500/40 px-2 py-1 text-xs font-semibold text-orange-300 transition hover:bg-orange-500/10"
                                >
                                    Cancel
                                </button>
                                <span
                                    v-else
                                    class="text-slate-500"
                                >
                                    Restricted
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!assetRequests.data?.length">
                            <td
                                colspan="9"
                                class="px-3 py-8 text-center text-slate-400"
                            >
                                No asset requests found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
