<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import AiResearchPanel from '@/components/assets/AiResearchPanel.vue'
import type { BreadcrumbItem } from '@/types'
import { index, edit } from '@/routes/assets'
import { can } from '@/utils/permissions'

/* =======================
   Props
======================= */
const props = defineProps<{
    asset: any
}>()

/* =======================
   AI research / specs / market price
======================= */
const canUseAi = can('assets.research') || can('assets.review-suggestions')

const specEntries = computed(() =>
    Object.entries((props.asset.specifications ?? {}) as Record<string, unknown>)
        .filter(([, value]) => value !== null && value !== '' && !(Array.isArray(value) && !value.length)),
)

const formatSpec = (value: unknown) =>
    Array.isArray(value) ? value.join(', ') : typeof value === 'boolean' ? (value ? 'Yes' : 'No') : String(value)

const formatMoney = (value: unknown) =>
    Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const marketPrice = computed(() => {
    const a = props.asset
    if (a.market_price_amount != null) return `${a.market_price_currency ?? ''} ${formatMoney(a.market_price_amount)}`.trim()
    if (a.market_price_low != null) {
        return `${a.market_price_currency ?? ''} ${formatMoney(a.market_price_low)} – ${formatMoney(a.market_price_high)}`.trim()
    }
    return null
})

const reloadAsset = () => router.reload({ only: ['asset'] })


const formatDate = (value: string | null) => {
    if (!value) return '—'
    return new Date(value).toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    })
}


/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Assets', href: index().url },
    { title: 'View Asset', href: '#' },
]
</script>

<template>

    <Head title="View Asset" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="mx-6 mt-6 rounded-2xl border border-slate-800 bg-linear-to-br from-[#020617] via-[#020617] to-[#0f172a] p-8 shadow-xl">

            <!-- Header -->
            <div class="mb-10 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-white">Asset Details</h1>
                    <p class="text-sm text-slate-400">Full information about this asset</p>
                </div>

                <div class="flex gap-3">
                    <Link :href="index().url"
                        class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">
                        ← Back
                    </Link>

                    <Link :href="edit(asset.id).url"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        Edit Asset
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">

                <!-- LEFT: IMAGE & CODE -->
                <div class="space-y-6 lg:col-span-4">

                    <!-- Asset Image Card -->
                    <div class="space-y-2 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Asset Image</p>

                        <div class="mt-3">
                            <img v-if="asset.asset_image" :src="`/storage/${asset.asset_image}`"
                                :alt="asset.asset_name?.assetname || 'Asset Image'"
                                class="w-full max-h-105 rounded-lg bg-black/40 object-contain p-4" />

                            <div v-else
                                class="flex h-40 items-center justify-center rounded-lg bg-slate-900 text-sm text-slate-500">
                                No Image Available
                            </div>
                        </div>
                    </div>

                    <!-- Asset Code Card -->
                    <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Asset Code</p>
                        <p class="mt-2 font-mono text-lg font-semibold text-white">{{ asset.asset_code }}</p>
                    </div>
                </div>

                <!-- RIGHT: INFO GRID -->
                <div class="lg:col-span-8">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

                        <!-- Info Cards -->
                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Asset Name</p>
                            <p class="text-sm font-medium text-white">{{ asset.asset_name?.assetname ?? '—' }}</p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Brand</p>
                            <p class="text-sm font-medium text-white">{{ asset.brand || '—' }}</p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Useful Life (Years)
                            </p>
                            <p class="text-sm font-medium text-white">{{ asset.asset_name?.usefullife ?? '—' }}</p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Serial Number</p>
                            <p class="text-sm font-medium text-white">{{ asset.serial_number || '—' }}</p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Model / Part Number</p>
                            <p class="text-sm font-medium text-white">
                                {{ asset.model_number || '—' }}<span v-if="asset.part_number" class="text-slate-400"> · {{ asset.part_number }}</span>
                            </p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Market Price</p>
                            <p class="text-sm font-medium text-white">
                                {{ marketPrice ?? '—' }}
                                <span v-if="asset.market_price_type" class="text-xs capitalize text-slate-400">({{ asset.market_price_type }})</span>
                            </p>
                            <p v-if="asset.market_price_retrieved_at" class="text-xs text-slate-500">
                                as of {{ formatDate(asset.market_price_retrieved_at) }}
                            </p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Purchase Cost</p>
                            <p class="text-sm font-medium text-white">{{ asset.purchase_cost ?? '—' }}</p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Purchase Date</p>
                            <p class="text-sm font-medium text-white">
                                {{ formatDate(asset.purchase_date) }}
                            </p>



                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Warranty Duration
                            </p>
                            <p class="text-sm font-medium text-white">{{ asset.warranty_duration ?? '—' }}</p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Warranty End Date
                            </p>
                            <p class="text-sm font-medium text-white">
                                {{ formatDate(asset.warranty_end_date) }}
                            </p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Division / Unit</p>
                            <p class="text-sm font-medium text-white">{{ asset.unit?.name ?? '—' }}</p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Location</p>
                            <p class="text-sm font-medium text-white">{{ asset.unit?.locationname ?? '—' }}</p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Source of Funding
                            </p>
                            <p class="text-sm font-medium text-white">{{ asset.source?.name ?? '—' }}</p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Supplier</p>
                            <p class="text-sm font-medium text-white">{{ asset.supplier?.suppliername ?? '—' }}</p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Maintenance Provider
                            </p>
                            <p class="text-sm font-medium text-white">{{ asset.maintenance_provider?.providername ?? '—'
                                }}</p>
                        </div>

                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status</p>
                            <p class="text-sm font-medium capitalize text-white">{{ asset.status?.replace('_', ' ') ||
                                '—' }}</p>
                        </div>

                        <!-- Full-width system info -->
                        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4 md:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Added By</p>
                            <p class="text-sm font-medium text-white">{{ asset.user?.name ?? '—' }}</p>
                        </div>

                        <!-- Specifications (from approved AI suggestions) -->
                        <div v-if="specEntries.length" class="rounded-xl border border-slate-800 bg-slate-900/40 p-4 md:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Specifications</p>
                            <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                                <div v-for="[key, value] in specEntries" :key="key" class="flex justify-between gap-3 border-b border-slate-800/70 pb-1">
                                    <dt class="capitalize text-slate-400">{{ key.replaceAll('_', ' ') }}</dt>
                                    <dd class="text-right text-white">{{ formatSpec(value) }}</dd>
                                </div>
                            </dl>
                        </div>

                    </div>
                </div>
            </div>

            <AiResearchPanel v-if="canUseAi" class="mt-8" :asset-id="asset.id" @applied="reloadAsset" />

            <div class="mt-8 grid gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4 lg:col-span-1">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Lifecycle Timeline</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="item in asset.lifecycle_histories" :key="item.id" class="border-l-2 border-blue-500/40 pl-3">
                            <p class="text-sm font-semibold text-white">{{ item.old_status ?? 'New' }} → {{ item.new_status }}</p>
                            <p class="text-xs text-slate-400">{{ formatDate(item.changed_at) }} • {{ item.changed_by?.name ?? 'System' }}</p>
                            <p class="text-xs text-slate-500">{{ item.reason ?? 'No reason provided' }}</p>
                        </div>
                        <p v-if="!asset.lifecycle_histories?.length" class="text-sm text-slate-500">No lifecycle history yet.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4 lg:col-span-1">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Maintenance History</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="item in asset.maintenance_requests" :key="item.id" class="rounded-lg bg-slate-950/60 p-3">
                            <p class="text-sm font-semibold text-white">{{ item.status }} • {{ item.priority }}</p>
                            <p class="text-xs text-slate-400">{{ item.fault_description }}</p>
                            <p class="text-xs text-slate-500">Cost: {{ item.actual_cost ?? item.estimated_cost ?? '—' }}</p>
                        </div>
                        <p v-if="!asset.maintenance_requests?.length" class="text-sm text-slate-500">No maintenance request yet.</p>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-4 lg:col-span-1">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">QR Scan Summary</h2>
                    <div class="mt-4 space-y-3">
                        <div class="rounded-lg bg-slate-950/60 p-3">
                            <p class="text-xs text-slate-400">Total Scans</p>
                            <p class="text-2xl font-bold text-white">{{ asset.scan_logs?.length ?? 0 }}</p>
                        </div>
                        <div v-for="scan in (asset.scan_logs ?? []).slice(0, 5)" :key="scan.id" class="rounded-lg bg-slate-950/60 p-3">
                            <p class="text-xs text-slate-400">{{ formatDate(scan.created_at) }} • {{ scan.device ?? 'Device' }}</p>
                            <p class="text-xs text-slate-500">{{ scan.browser ?? 'Browser unknown' }} / {{ scan.ip_address ?? 'No IP' }}</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
