<script setup lang="ts">
import { computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { confirmDelete, promptText } from '@/utils/sweetalert'

const props = defineProps<{
    filters: any
    assetTransfers: any
    assets: any[]
    units: any[]
    users: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Asset Transfer & Movement Tracking', href: '#' },
]

const form = useForm({
    asset_id: '',
    to_unit_id: '',
    to_user_id: '',
    reason: '',
    remarks: '',
})

const filters = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
    from_unit_id: props.filters?.from_unit_id ?? '',
    to_unit_id: props.filters?.to_unit_id ?? '',
})

const submit = () => {
    form.post('/asset-transfers', {
        preserveScroll: true,
        onSuccess: () => form.reset('asset_id', 'to_unit_id', 'to_user_id', 'reason', 'remarks'),
    })
}

const applyFilters = () => {
    router.get('/asset-transfers', filters.data(), {
        preserveState: true,
        replace: true,
    })
}

const approveTransfer = async (id: number) => {
    const remarks = await promptText({
        title: 'Approve Asset Transfer',
        text: 'Add an approval remark if required.',
        placeholder: 'Approval remark (optional)',
        confirmText: 'Approve',
    })
    if (remarks === null) return
    router.post(`/asset-transfers/${id}/approve`, { remarks }, { preserveScroll: true })
}

const rejectTransfer = async (id: number) => {
    const remarks = await promptText({
        title: 'Reject Asset Transfer',
        text: 'Enter the reason for rejecting this transfer.',
        placeholder: 'Reason for rejection',
        confirmText: 'Reject',
        required: true,
    })
    if (remarks === null) return
    if (!remarks.trim()) return
    router.post(`/asset-transfers/${id}/reject`, { remarks }, { preserveScroll: true })
}

const completeTransfer = async (id: number) => {
    const remarks = await promptText({
        title: 'Complete Asset Transfer',
        text: 'Add a completion or handover note if required.',
        placeholder: 'Completion / handover note (optional)',
        confirmText: 'Complete Transfer',
    })
    if (remarks === null) return
    router.post(`/asset-transfers/${id}/complete`, { remarks }, { preserveScroll: true })
}

const destroyRecord = async (id: number) => {
    const confirmed = await confirmDelete({
        title: 'Delete Asset Transfer',
        text: 'Are you sure you want to delete this asset transfer record?',
    })
    if (confirmed) {
        router.delete(`/asset-transfers/${id}`, { preserveScroll: true })
    }
}

const assetLabel = (asset: any) => {
    const name = asset.asset_name?.assetname ?? asset.assetName?.assetname ?? 'Asset'
    const code = asset.asset_code ? ` - ${asset.asset_code}` : ''
    const unit = asset.unit?.name ? ` - Current: ${asset.unit.name}` : ''
    return `${name}${code}${unit}`
}

const fmt = (value?: string) => value ? new Date(value).toLocaleString('en-GB') : 'Not recorded'

const rows = computed(() => props.assetTransfers?.data ?? [])
const summary = computed(() => ({
    pending: rows.value.filter((transfer: any) => transfer.status === 'pending').length,
    approved: rows.value.filter((transfer: any) => transfer.status === 'approved').length,
    completed: rows.value.filter((transfer: any) => transfer.status === 'completed').length,
    rejected: rows.value.filter((transfer: any) => transfer.status === 'rejected').length,
}))
</script>

<template>
    <Head title="Asset Transfer & Movement Tracking" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-4 my-4 space-y-4 rounded-2xl border border-slate-800 bg-slate-950/90 p-4 text-slate-100 shadow-xl sm:mx-6 sm:p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-white">Asset Transfer & Movement Tracking</h1>
                    <p class="text-sm text-slate-400">Move assets between offices, units and custodians while preserving a complete movement history.</p>
                </div>
                <div class="rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-xs text-slate-300">
                    Total Transfers: {{ assetTransfers?.total ?? assetTransfers?.data?.length ?? 0 }}
                </div>
            </div>

            <form @submit.prevent="submit" class="grid gap-3 rounded-xl border border-slate-800 bg-slate-900/50 p-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="xl:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">Asset to Move</label>
                    <select v-model="form.asset_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" required>
                        <option value="">Select asset</option>
                        <option v-for="asset in assets" :key="asset.id" :value="asset.id">
                            {{ assetLabel(asset) }}
                        </option>
                    </select>
                    <p v-if="form.errors.asset_id" class="mt-1 text-xs text-red-400">{{ form.errors.asset_id }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">Destination Office/Unit</label>
                    <select v-model="form.to_unit_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" required>
                        <option value="">Select destination</option>
                        <option v-for="unit in units" :key="unit.id" :value="unit.id">
                            {{ unit.name }}{{ unit.unit_abbreviation ? ` (${unit.unit_abbreviation})` : '' }}
                        </option>
                    </select>
                    <p v-if="form.errors.to_unit_id" class="mt-1 text-xs text-red-400">{{ form.errors.to_unit_id }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">New Custodian</label>
                    <select v-model="form.to_user_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm">
                        <option value="">No specific custodian</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}{{ user.email ? ` (${user.email})` : '' }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">Submit</label>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-50">
                        Create Transfer
                    </button>
                </div>

                <div class="md:col-span-2 xl:col-span-3">
                    <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">Reason for Movement</label>
                    <textarea v-model="form.reason" rows="3" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" placeholder="Example: asset reassigned to another office due to operational need..." required />
                    <p v-if="form.errors.reason" class="mt-1 text-xs text-red-400">{{ form.errors.reason }}</p>
                </div>

                <div class="md:col-span-2 xl:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">Remarks / Handover Note</label>
                    <textarea v-model="form.remarks" rows="3" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" placeholder="Optional condition, handover, approval or movement note..." />
                </div>
            </form>

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
                    <p class="text-xs uppercase text-slate-500">Completed</p>
                    <p class="text-2xl font-bold text-white">{{ summary.completed }}</p>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
                    <p class="text-xs uppercase text-slate-500">Rejected</p>
                    <p class="text-2xl font-bold text-white">{{ summary.rejected }}</p>
                </div>
            </div>

            <div class="grid gap-2 md:grid-cols-5">
                <input v-model="filters.search" @keyup.enter="applyFilters" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm" placeholder="Search transfer code, asset, office..." />
                <select v-model="filters.from_unit_id" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
                    <option value="">All From Offices</option>
                    <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                </select>
                <select v-model="filters.to_unit_id" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
                    <option value="">All To Offices</option>
                    <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
                </select>
                <select v-model="filters.status" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button @click="applyFilters" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white">Search</button>
            </div>

            <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="overflow-x-auto rounded-xl border border-slate-800"><table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900 text-xs uppercase text-slate-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Asset</th>
                            <th class="px-3 py-2 text-left">From</th>
                            <th class="px-3 py-2 text-left">To</th>
                            <th class="px-3 py-2 text-left">Requested By</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-left">Completed</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <tr v-for="transfer in assetTransfers.data" :key="transfer.id" class="hover:bg-slate-900/60">
                            <td class="px-3 py-2">{{ fmt(transfer.requested_at ?? transfer.created_at) }}</td>
                            <td class="px-3 py-2">
                                <div class="font-mono text-xs text-slate-400">{{ transfer.transfer_code }}</div>
                                <div class="font-medium text-white">{{ transfer.asset?.asset_name?.assetname ?? transfer.asset?.assetName?.assetname ?? 'Asset' }}</div>
                                <div class="text-xs text-slate-500">{{ transfer.asset?.asset_code ?? 'Not recorded' }}</div>
                            </td>
                            <td class="px-3 py-2">
                                <div>{{ transfer.from_unit?.name ?? transfer.fromUnit?.name ?? 'Unassigned' }}</div>
                                <div class="text-xs text-slate-500">{{ transfer.from_user?.name ?? transfer.fromUser?.name ?? '' }}</div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-semibold text-white">{{ transfer.to_unit?.name ?? transfer.toUnit?.name ?? 'Not recorded' }}</div>
                                <div class="text-xs text-slate-500">{{ transfer.to_user?.name ?? transfer.toUser?.name ?? '' }}</div>
                            </td>
                            <td class="px-3 py-2">{{ transfer.requester?.name ?? 'Not recorded' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded-full bg-blue-500/10 px-2 py-1 text-xs capitalize text-blue-300">{{ transfer.status }}</span>
                            </td>
                            <td class="px-3 py-2">{{ transfer.completed_at ? fmt(transfer.completed_at) : 'Not completed' }}</td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button v-if="transfer.status === 'pending'" @click="approveTransfer(transfer.id)" class="rounded-lg bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-500">Approve</button>
                                    <button v-if="transfer.status === 'pending'" @click="rejectTransfer(transfer.id)" class="rounded-lg bg-orange-600 px-2 py-1 text-xs font-semibold text-white hover:bg-orange-500">Reject</button>
                                    <button v-if="transfer.status === 'pending' || transfer.status === 'approved'" @click="completeTransfer(transfer.id)" class="rounded-lg bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-500">Complete</button>
                                    <button @click="destroyRecord(transfer.id)" class="rounded-lg bg-red-600 px-2 py-1 text-xs font-semibold text-white hover:bg-red-500">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!assetTransfers.data?.length">
                            <td colspan="8" class="px-3 py-8 text-center text-slate-400">No asset transfers found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

