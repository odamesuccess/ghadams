<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { confirmAction } from '@/utils/sweetalert'

const props = defineProps<{
    filters: any
    assetTransfers: any
    assets: any[]
    units: any[]
    users: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'My Asset Transfers', href: '#' },
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
})

const submit = () => {
    form.post('/asset-transfers', {
        preserveScroll: true,
        onSuccess: () => form.reset('asset_id', 'to_unit_id', 'to_user_id', 'reason', 'remarks'),
    })
}

const applyFilters = () => {
    router.get('/my-asset-transfers', filters.data(), {
        preserveState: true,
        replace: true,
    })
}

const cancelTransfer = async (id: number) => {
    const confirmed = await confirmAction({
        title: 'Cancel Asset Transfer',
        text: 'Are you sure you want to cancel this asset transfer request?',
        confirmText: 'Cancel Transfer',
        icon: 'warning',
    })
    if (confirmed) {
        router.post(`/asset-transfers/${id}/cancel`, {}, { preserveScroll: true })
    }
}

const assetLabel = (asset: any) => {
    const name = asset.asset_name?.assetname ?? asset.assetName?.assetname ?? 'Asset'
    const code = asset.asset_code ? ` â€¢ ${asset.asset_code}` : ''
    const unit = asset.unit?.name ? ` â€¢ Current: ${asset.unit.name}` : ''
    return `${name}${code}${unit}`
}

const fmt = (value?: string) => value ? new Date(value).toLocaleString('en-GB') : 'â€”'
</script>

<template>
    <Head title="My Asset Transfers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-4 my-4 space-y-4 rounded-2xl border border-slate-800 bg-slate-950/90 p-4 text-slate-100 shadow-xl sm:mx-6 sm:p-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-white">My Asset Transfers</h1>
                    <p class="text-sm text-slate-400">Request asset movement from one office, unit, department or custodian to another.</p>
                </div>
                <div class="rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-xs text-slate-300">
                    Total Transfers: {{ assetTransfers?.total ?? assetTransfers?.data?.length ?? 0 }}
                </div>
            </div>

            <form @submit.prevent="submit" class="grid gap-3 rounded-xl border border-slate-800 bg-slate-900/50 p-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="xl:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">Asset</label>
                    <select v-model="form.asset_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" required>
                        <option value="">Select asset to transfer</option>
                        <option v-for="asset in assets" :key="asset.id" :value="asset.id">
                            {{ assetLabel(asset) }}
                        </option>
                    </select>
                    <p v-if="form.errors.asset_id" class="mt-1 text-xs text-red-400">{{ form.errors.asset_id }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">Destination Office</label>
                    <select v-model="form.to_unit_id" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" required>
                        <option value="">Select office/unit</option>
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
                        Submit Transfer
                    </button>
                </div>

                <div class="md:col-span-2 xl:col-span-3">
                    <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">Reason for Transfer</label>
                    <textarea v-model="form.reason" rows="3" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" placeholder="State why the asset should be moved..." required />
                    <p v-if="form.errors.reason" class="mt-1 text-xs text-red-400">{{ form.errors.reason }}</p>
                </div>

                <div class="md:col-span-2 xl:col-span-2">
                    <label class="mb-1 block text-xs font-semibold uppercase text-slate-400">Remarks / Handover Note</label>
                    <textarea v-model="form.remarks" rows="3" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm" placeholder="Optional condition or handover note..." />
                </div>
            </form>

            <div class="grid gap-2 md:grid-cols-3">
                <input v-model="filters.search" @keyup.enter="applyFilters" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm" placeholder="Search asset, transfer code, reason..." />
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
                            <th class="px-3 py-2 text-left">Movement</th>
                            <th class="px-3 py-2 text-left">Reason</th>
                            <th class="px-3 py-2 text-left">Status</th>
                            <th class="px-3 py-2 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <tr v-for="transfer in assetTransfers.data" :key="transfer.id" class="hover:bg-slate-900/60">
                            <td class="px-3 py-2">{{ fmt(transfer.requested_at ?? transfer.created_at) }}</td>
                            <td class="px-3 py-2">
                                <div class="font-mono text-xs text-slate-400">{{ transfer.transfer_code }}</div>
                                <div class="font-medium text-white">{{ transfer.asset?.asset_name?.assetname ?? transfer.asset?.assetName?.assetname ?? 'Asset' }}</div>
                                <div class="text-xs text-slate-500">{{ transfer.asset?.asset_code ?? 'â€”' }}</div>
                            </td>
                            <td class="px-3 py-2">
                                <div>{{ transfer.from_unit?.name ?? transfer.fromUnit?.name ?? 'Unassigned' }}</div>
                                <div class="text-xs text-slate-500">to</div>
                                <div class="font-semibold text-white">{{ transfer.to_unit?.name ?? transfer.toUnit?.name ?? 'â€”' }}</div>
                            </td>
                            <td class="max-w-sm px-3 py-2 text-slate-300">{{ transfer.reason }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded-full bg-blue-500/10 px-2 py-1 text-xs capitalize text-blue-300">{{ transfer.status }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button v-if="transfer.status === 'pending'" @click="cancelTransfer(transfer.id)" class="rounded-lg bg-orange-600 px-2 py-1 text-xs font-semibold text-white hover:bg-orange-500">Cancel</button>
                            </td>
                        </tr>
                        <tr v-if="!assetTransfers.data?.length">
                            <td colspan="6" class="px-3 py-8 text-center text-slate-400">No asset transfer records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

