<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { confirmDelete, promptText } from '@/utils/sweetalert'

const props = defineProps<{
    filters: any
    assetRequests: any
    units: any[]
    assets: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Asset Request Approval', href: '#' },
]

const filters = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
    priority: props.filters?.priority ?? '',
    unit_id: props.filters?.unit_id ?? '',
})

const reviewForm = useForm({
    review_comment: '',
    asset_id: '',
})

const applyFilters = () => {
    router.get('/asset-requests', filters.data(), {
        preserveState: true,
        replace: true,
    })
}

const approveRequest = async (id: number) => {
    const comment = await promptText({
        title: 'Approve Asset Request',
        text: 'Add an approval comment if required.',
        placeholder: 'Approval comment (optional)',
        confirmText: 'Approve',
    })
    if (comment === null) return
    router.post(`/asset-requests/${id}/approve`, { review_comment: comment }, { preserveScroll: true })
}

const rejectRequest = async (id: number) => {
    const comment = await promptText({
        title: 'Reject Asset Request',
        text: 'Enter a reason for rejection if required.',
        placeholder: 'Reason for rejection (optional)',
        confirmText: 'Reject',
    })
    if (comment === null) return
    router.post(`/asset-requests/${id}/reject`, { review_comment: comment }, { preserveScroll: true })
}

const supplyRequest = async (id: number) => {
    const assetId = await promptText({
        title: 'Link Supplied Asset',
        text: 'Enter the registered asset ID to link, if available.',
        placeholder: 'Registered asset ID (optional)',
        confirmText: 'Continue',
    })
    if (assetId === null) return

    const comment = await promptText({
        title: 'Supply Note',
        text: 'Add a supply or handover note if required.',
        placeholder: 'Supply note (optional)',
        confirmText: 'Mark Supplied',
    })
    if (comment === null) return

    reviewForm.asset_id = assetId
    reviewForm.review_comment = comment
    reviewForm.post(`/asset-requests/${id}/supply`, {
        preserveScroll: true,
        onSuccess: () => reviewForm.reset(),
    })
}

const destroyRecord = async (id: number) => {
    const confirmed = await confirmDelete({
        title: 'Delete Asset Request',
        text: 'Are you sure you want to delete this asset request?',
    })
    if (confirmed) {
        router.delete(`/asset-requests/${id}`, { preserveScroll: true })
    }
}

const fmt = (value?: string) => value ? new Date(value).toLocaleString('en-GB') : 'Not recorded'
const fmtDate = (value?: string) => value ? new Date(value).toLocaleDateString('en-GB') : 'Not recorded'

const rows = computed(() => props.assetRequests?.data ?? [])
const summary = computed(() => ({
    pending: rows.value.filter((request: any) => request.status === 'pending').length,
    approved: rows.value.filter((request: any) => request.status === 'approved').length,
    supplied: rows.value.filter((request: any) => request.status === 'supplied').length,
    rejected: rows.value.filter((request: any) => request.status === 'rejected').length,
}))
</script>

<template>
    <Head title="Asset Request Approval" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-4 my-4 space-y-4 rounded-2xl border border-slate-800 bg-slate-950/90 p-4 text-slate-100 shadow-xl sm:mx-6 sm:p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-white">Asset Request Approval</h1>
                    <p class="text-sm text-slate-400">Review, approve, reject and mark office asset requests as supplied.</p>
                </div>
                <div class="rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-xs text-slate-300">
                    Total Requests: {{ assetRequests?.total ?? assetRequests?.data?.length ?? 0 }}
                </div>
            </div>

            <div class="grid gap-2 md:grid-cols-5">
                <input v-model="filters.search" @keyup.enter="applyFilters" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm" placeholder="Search requests, office, requester..." />
                <select v-model="filters.unit_id" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
                    <option value="">All Offices/Units</option>
                    <option v-for="unit in units" :key="unit.id" :value="unit.id">
                        {{ unit.name }}{{ unit.unit_abbreviation ? ` (${unit.unit_abbreviation})` : '' }}
                    </option>
                </select>
                <select v-model="filters.status" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="supplied">Supplied</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <select v-model="filters.priority" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
                    <option value="">All Priorities</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
                <button @click="applyFilters" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white">Search</button>
            </div>

            <div class="grid gap-3 md:grid-cols-4">
                <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                    <p class="text-xs uppercase text-slate-500">Pending</p>
                    <p class="text-2xl font-bold text-white">{{ summary.pending }}</p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                    <p class="text-xs uppercase text-slate-500">Approved</p>
                    <p class="text-2xl font-bold text-white">{{ summary.approved }}</p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                    <p class="text-xs uppercase text-slate-500">Supplied</p>
                    <p class="text-2xl font-bold text-white">{{ summary.supplied }}</p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                    <p class="text-xs uppercase text-slate-500">Rejected</p>
                    <p class="text-2xl font-bold text-white">{{ summary.rejected }}</p>
                </div>
            </div>

            <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="overflow-x-auto rounded-xl border border-slate-800"><table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900 text-xs uppercase text-slate-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Request</th>
                            <th class="px-3 py-2 text-left">Office</th>
                            <th class="px-3 py-2 text-left">Requested By</th>
                            <th class="px-3 py-2 text-left">Qty</th>
                            <th class="px-3 py-2 text-left">Priority</th>
                            <th class="px-3 py-2 text-left">Needed</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Reviewer</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <tr v-for="request in assetRequests.data" :key="request.id" class="hover:bg-slate-900/60">
                            <td class="px-3 py-2">{{ fmt(request.created_at) }}</td>
                            <td class="px-3 py-2">
                                <div class="font-mono text-xs text-slate-400">{{ request.request_code }}</div>
                                <div class="font-medium text-white">{{ request.asset_name }}</div>
                                <div class="max-w-md text-xs text-slate-400">{{ request.preferred_specification ?? request.asset_category ?? 'Not recorded' }}</div>
                            </td>
                            <td class="px-3 py-2">{{ request.unit?.name ?? 'Not recorded' }}</td>
                            <td class="px-3 py-2">
                                <div>{{ request.requester?.name ?? 'Not recorded' }}</div>
                                <div class="text-xs text-slate-500">{{ request.requester?.email ?? '' }}</div>
                            </td>
                            <td class="px-3 py-2">{{ request.quantity }}</td>
                            <td class="px-3 py-2 capitalize">{{ request.priority }}</td>
                            <td class="px-3 py-2">{{ fmtDate(request.needed_date) }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded-full bg-blue-500/10 px-2 py-1 text-xs capitalize text-blue-300">{{ request.status }}</span>
                            </td>
                            <td class="px-3 py-2">{{ request.reviewer?.name ?? 'Not reviewed' }}</td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button v-if="request.status === 'pending'" @click="approveRequest(request.id)" class="rounded-lg bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-500">Approve</button>
                                    <button v-if="request.status === 'pending'" @click="rejectRequest(request.id)" class="rounded-lg bg-orange-600 px-2 py-1 text-xs font-semibold text-white hover:bg-orange-500">Reject</button>
                                    <button v-if="request.status === 'approved'" @click="supplyRequest(request.id)" class="rounded-lg bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-500">Supplied</button>
                                    <button @click="destroyRecord(request.id)" class="rounded-lg bg-red-600 px-2 py-1 text-xs font-semibold text-white hover:bg-red-500">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!assetRequests.data?.length">
                            <td colspan="10" class="px-3 py-8 text-center text-slate-400">No asset requests found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

