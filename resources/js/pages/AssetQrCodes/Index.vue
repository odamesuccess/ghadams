<script setup lang="ts">
import { computed, h, onMounted, ref, watch } from 'vue'
import type { ColumnDef } from '@tanstack/vue-table'
import { Head, router } from '@inertiajs/vue3'
import { Check, Copy, Download, Eye, Printer, Trash2 } from 'lucide-vue-next'

import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'
import { can } from '@/utils/permissions'

import { confirmDelete, showError } from '@/utils/sweetalert'

import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'

/* ================= MODAL STATE ================= */
const showQrModal = ref(false)
const selectedQr = ref<string | null>(null)

/* ================= COPY LINK STATE ================= */
const copiedAssetId = ref<number | null>(null)

/* ================= BULK DOWNLOAD STATE ================= */
interface BulkDownloadTarget {
    id: number
    name: string
    abbreviation?: string | null
    location?: string | null
    qr_count: number
}

const bulkDownloadLoading = ref(false)
const bulkDownloadScope = ref<'unit' | 'organization'>('unit')
const bulkDownloadTargetId = ref('')
const bulkDownloadOptions = ref<{
    units: BulkDownloadTarget[]
    organizations: BulkDownloadTarget[]
    can_download_organization: boolean
}>({
    units: [],
    organizations: [],
    can_download_organization: false,
})

const bulkDownloadTargets = computed(() =>
    bulkDownloadScope.value === 'organization'
        ? bulkDownloadOptions.value.organizations
        : bulkDownloadOptions.value.units
)

const selectedBulkTarget = computed(() =>
    bulkDownloadTargets.value.find(
        (item) => String(item.id) === bulkDownloadTargetId.value
    )
)

/* ================= TYPES ================= */
interface AssetName {
    id: number
    name: string
}

interface Unit {
    id: number
    name: string
    locationname?: string | null
}

interface Asset {
    id: number
    asset_code: string
    scan_token?: string | null
    uuid?: string | null
    qr_code?: string | null
    asset_image?: string | null
    asset_name?: AssetName | null
    unit?: Unit | null
    purchase_cost?: number | null
    status: 'active' | 'under_maintenance' | 'obsolete' | 'disposed'
    updated_at: string
}

/* ================= PROPS ================= */
const props = defineProps<{
    assets?: {
        data?: Asset[]
        links?: any[]
        from?: number | null
        to?: number | null
        total?: number | null
    }
    filters?: {
        search?: string
    }
}>()

/* ================= TOP SEARCH ================= */
const searchQuery = ref(props.filters?.search ?? '')
let searchTimer: ReturnType<typeof setTimeout> | null = null

const requestAssetQrSearch = () => {
    if (searchTimer) {
        window.clearTimeout(searchTimer)
        searchTimer = null
    }

    const search = searchQuery.value.trim()

    router.get(
        '/assetqrcodes',
        search ? { search } : {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['assets', 'filters'],
        }
    )
}

watch(searchQuery, () => {
    if (searchTimer) {
        window.clearTimeout(searchTimer)
    }

    searchTimer = window.setTimeout(() => {
        requestAssetQrSearch()
    }, 350)
})

const submitAssetQrSearch = () => {
    requestAssetQrSearch()
}

const clearAssetQrSearch = () => {
    searchQuery.value = ''
}

/* ================= HELPERS ================= */
const formatDate = (value: string) => {
    if (!value) return '—'

    return new Date(value).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const statusBadge = (status: Asset['status']) =>
    ({
        active: 'bg-emerald-600 text-white',
        under_maintenance: 'bg-amber-500 text-white',
        obsolete: 'bg-orange-600 text-white',
        disposed: 'bg-red-700 text-white',
    })[status] ?? 'bg-slate-600 text-white'

const scanLink = (asset: Asset) => {
    const token = asset.scan_token ?? asset.uuid

    if (!token) return null

    return `${window.location.origin}/scan/${token}`
}

const fallbackCopy = (value: string) => {
    const textarea = document.createElement('textarea')

    textarea.value = value
    textarea.setAttribute('readonly', '')
    textarea.style.position = 'fixed'
    textarea.style.opacity = '0'
    textarea.style.pointerEvents = 'none'

    document.body.appendChild(textarea)
    textarea.select()

    const copied = document.execCommand('copy')

    document.body.removeChild(textarea)

    if (!copied) {
        throw new Error('Copy command failed.')
    }
}

const copyScanLink = async (asset: Asset) => {
    const link = scanLink(asset)

    if (!link) {
        alert('This asset has no QR scan token.')
        return
    }

    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(link)
        } else {
            fallbackCopy(link)
        }

        copiedAssetId.value = asset.id

        window.setTimeout(() => {
            if (copiedAssetId.value === asset.id) {
                copiedAssetId.value = null
            }
        }, 1800)
    } catch (error) {
        console.error('Could not copy asset QR scan link:', error)
        alert('The scan link could not be copied. Please try again.')
    }
}
const selectFirstBulkTarget = () => {
    const first = bulkDownloadTargets.value[0]
    bulkDownloadTargetId.value = first ? String(first.id) : ''
}

watch(bulkDownloadScope, () => {
    selectFirstBulkTarget()
})

const loadBulkDownloadOptions = async () => {
    bulkDownloadLoading.value = true

    try {
        const response = await fetch('/assetqrcodes/download-options', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`)
        }

        const data = await response.json()

        bulkDownloadOptions.value = {
            units: Array.isArray(data.units) ? data.units : [],
            organizations: Array.isArray(data.organizations)
                ? data.organizations
                : [],
            can_download_organization: Boolean(
                data.can_download_organization
            ),
        }

        if (
            bulkDownloadScope.value === 'organization'
            && !bulkDownloadOptions.value.can_download_organization
        ) {
            bulkDownloadScope.value = 'unit'
        }

        selectFirstBulkTarget()
    } catch (error) {
        console.error('Could not load bulk QR download options:', error)
    } finally {
        bulkDownloadLoading.value = false
    }
}

const downloadBulkQrZip = () => {
    if (!bulkDownloadTargetId.value) {
        alert('Select a Unit or Organization first.')
        return
    }

    const params = new URLSearchParams({
        scope: bulkDownloadScope.value,
        id: bulkDownloadTargetId.value,
    })

    /*
     * Native browser download:
     * - normal GET request
     * - normal browser download manager / Save As behaviour
     * - no fetch()
     * - no POST/CSRF
     * - no custom polling/progress interaction
     */
    window.location.href = `/assetqrcodes/download-zip?${params.toString()}`
}

onMounted(() => {
    void loadBulkDownloadOptions()
})
const statusLabel = (status: string) =>
    status
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase())

const deleteQrCode = async (asset: Asset) => {
    if (!asset.qr_code) return

    const confirmed = await confirmDelete({
        title: 'Delete QR Code',
        text: `Are you sure you want to delete the QR code for ${asset.asset_code}?`,
    })

    if (!confirmed) return

    router.delete(`/assetqrcodes/${asset.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['assets'] })
        },
        onError: () => {
            showError('Delete Failed', 'Failed to delete QR code.')
        },
    })
}

/* ================= BUTTON STYLES ================= */
const actionBase =
    'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-1'

const copyLinkButton =
    'inline-flex h-8 min-w-[96px] items-center justify-center gap-1.5 rounded-md border border-cyan-500/35 bg-cyan-500/10 px-2.5 text-[11px] font-semibold text-cyan-300 shadow-sm transition hover:bg-cyan-500/20 focus:outline-none focus:ring-2 focus:ring-cyan-500/40'

const copiedLinkButton =
    'inline-flex h-8 min-w-[96px] items-center justify-center gap-1.5 rounded-md border border-emerald-500/35 bg-emerald-500/10 px-2.5 text-[11px] font-semibold text-emerald-300 shadow-sm transition'
const actionView = `${actionBase} bg-blue-700 hover:bg-blue-600 focus:ring-blue-500`
const actionPrint = `${actionBase} bg-emerald-700 hover:bg-emerald-600 focus:ring-emerald-500`
const actionSave = `${actionBase} bg-indigo-700 hover:bg-indigo-600 focus:ring-indigo-500`
const actionDelete = `${actionBase} bg-red-700 hover:bg-red-600 focus:ring-red-500`

const iconClass = 'h-4 w-4'

/* ================= TABLE COLUMNS ================= */

/* =========================================================
   CLICK QR / ASSET IMAGE LARGE PREVIEW
   Uses event delegation so it works with the current table
   cell structure without rewriting existing QR/Image columns.
========================================================= */
type MediaPreviewKind = 'qr' | 'image'

const mediaPreviewOpen = ref(false)
const mediaPreviewUrl = ref('')
const mediaPreviewKind = ref<MediaPreviewKind>('image')
const mediaPreviewTitle = ref('Asset Image')

const closeMediaPreview = () => {
    mediaPreviewOpen.value = false
    mediaPreviewUrl.value = ''
}

const handleMediaTableClick = (event: MouseEvent) => {
    const target = event.target

    if (!(target instanceof Element)) {
        return
    }

    const img = target.closest('img') as HTMLImageElement | null

    if (!img) {
        return
    }

    const src = img.currentSrc || img.src || ''

    /*
     * Only table images served from GhanaDAMS public storage participate.
     * This prevents unrelated icons from opening the preview.
     */
    if (!src.includes('/storage/')) {
        return
    }

    const alt = (img.alt || '').toLowerCase()
    const lowerSrc = src.toLowerCase()

    const isQr =
        alt.includes('qr')
        || lowerSrc.includes('/qrcodes/')
        || lowerSrc.includes('/qr-codes/')

    mediaPreviewKind.value = isQr ? 'qr' : 'image'
    mediaPreviewTitle.value = isQr ? 'Asset QR Code' : 'Asset Image'
    mediaPreviewUrl.value = src
    mediaPreviewOpen.value = true
}
const columns: ColumnDef<Asset>[] = [
    {
        header: 'QR Code',
        accessorKey: 'qr_code',
        cell: ({ getValue }) => {
            const qr = getValue() as string | null

            return qr
                ? h('div', { class: 'flex items-center' }, [
                      h('img', {
                          src: `/storage/${qr}`,
                          class:
                              'h-11 w-11 rounded-lg border border-slate-700 bg-white p-1 object-contain shadow-sm',
                          alt: 'Asset QR Code',
                      }),
                  ])
                : h(
                      'div',
                      {
                          class:
                              'flex h-11 w-11 items-center justify-center rounded-lg border border-slate-700 bg-slate-800 text-xs font-semibold text-slate-400',
                      },
                      'N/A'
                  )
        },
    },


    {
        header: 'Image',
        accessorKey: 'asset_image',
        cell: ({ getValue, row }) => {
            const image = getValue() as string | null
            const asset = row.original

            return image
                ? h('img', {
                      src: `/storage/${image}`,
                      class:
                          'h-10 w-10 rounded border border-slate-700 object-cover shadow-sm',
                      alt: `Asset image for ${asset.asset_code}`,
                  })
                : h(
                      'div',
                      {
                          class:
                              'flex h-10 w-10 items-center justify-center rounded border border-slate-700 bg-slate-800 text-[10px] font-semibold text-slate-400',
                      },
                      'N/A'
                  )
        },
    },
    {
        header: 'Copy Link',
        id: 'copy_link',
        cell: ({ row }) => {
            const asset = row.original
            const link = scanLink(asset)
            const isCopied = copiedAssetId.value === asset.id

            if (!link) {
                return h(
                    'span',
                    {
                        class:
                            'inline-flex h-8 min-w-[96px] items-center justify-center rounded-md border border-slate-700 bg-slate-900 px-2.5 text-[11px] font-semibold text-slate-500',
                        title: 'No QR scan link is available for this asset.',
                    },
                    'No Link'
                )
            }

            return h(
                'button',
                {
                    type: 'button',
                    class: isCopied ? copiedLinkButton : copyLinkButton,
                    title: isCopied
                        ? 'Scan link copied'
                        : 'Copy QR scan link',
                    'aria-label': isCopied
                        ? 'Scan link copied'
                        : `Copy QR scan link for ${asset.asset_code}`,
                    onClick: () => copyScanLink(asset),
                },
                [
                    h(isCopied ? Check : Copy, {
                        class: 'h-3.5 w-3.5',
                    }),
                    h(
                        'span',
                        { class: 'whitespace-nowrap' },
                        isCopied ? 'Copied' : 'Copy Link'
                    ),
                ]
            )
        },
    },
    {
        header: 'Asset Code',
        accessorKey: 'asset_code',
        cell: ({ getValue }) =>
            h(
                'span',
                {
                    class: 'font-semibold tracking-wide text-slate-100',
                },
                getValue() as string
            ),
    },

    {
        header: 'Asset Name',
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-slate-200' },
                row.original.asset_name?.name ?? '—'
            ),
    },

    {
        header: 'Unit',
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-slate-300' },
                row.original.unit?.name ?? '—'
            ),
    },

    {
        header: 'Location',
        cell: ({ row }) =>
            h(
                'span',
                { class: 'text-slate-300' },
                row.original.unit?.locationname ?? '—'
            ),
    },

    {
        header: 'Status',
        accessorKey: 'status',
        cell: ({ getValue }) =>
            h(
                'span',
                {
                    class: [
                        'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                        statusBadge(getValue() as Asset['status']),
                    ],
                },
                statusLabel(getValue() as string)
            ),
    },

    {
        header: 'Updated',
        accessorKey: 'updated_at',
        cell: ({ getValue }) =>
            h(
                'span',
                { class: 'whitespace-nowrap text-slate-300' },
                formatDate(getValue() as string)
            ),
    },

    {
        header: 'Actions',
        id: 'actions',
        cell: ({ row }) => {
            const asset = row.original
            const actions = []

            if ((can?.('assetqrcodes.view') ?? false) && asset.qr_code) {
                actions.push(
                    h(
                        'button',
                        {
                            type: 'button',
                            class: actionView,
                            title: 'View QR Code',
                            'aria-label': 'View QR Code',
                            onClick: () => {
                                selectedQr.value = asset.qr_code ?? null
                                showQrModal.value = true
                            },
                        },
                        [h(Eye, { class: iconClass })]
                    )
                )
            }

            if ((can?.('assetqrcodes.print') ?? false) && asset.qr_code) {
                actions.push(
                    h(
                        'a',
                        {
                            href: `/assetqrcodes/${asset.id}/print`,
                            target: '_blank',
                            rel: 'noopener noreferrer',
                            class: actionPrint,
                            title: 'Print QR Code',
                            'aria-label': 'Print QR Code',
                        },
                        [h(Printer, { class: iconClass })]
                    )
                )
            }

            if ((can?.('assetqrcodes.save') ?? false) && asset.qr_code) {
                actions.push(
                    h(
                        'a',
                        {
                            href: `/storage/${asset.qr_code}`,
                            download: `asset-${asset.asset_code}-qr.png`,
                            class: actionSave,
                            title: 'Download QR Code',
                            'aria-label': 'Download QR Code',
                        },
                        [h(Download, { class: iconClass })]
                    )
                )
            }

            if ((can?.('assetqrcodes.delete') ?? false) && asset.qr_code) {
                actions.push(
                    h(
                        'button',
                        {
                            type: 'button',
                            class: actionDelete,
                            title: 'Delete QR Code',
                            'aria-label': 'Delete QR Code',
                            onClick: () => deleteQrCode(asset),
                        },
                        [h(Trash2, { class: iconClass })]
                    )
                )
            }

            return h(
                'div',
                {
                    class:
                        'flex flex-nowrap items-center justify-end gap-1.5 whitespace-nowrap',
                },
                actions
            )
        },
    },
]

/* ================= BREADCRUMBS ================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Asset QR Codes', href: '/assetqrcodes' },
    { title: 'QR Code Register', href: '/assetqrcodes' },
]
</script>

<template>
    <Head title="Asset QR Codes" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 px-4 py-4 sm:px-6">
            <!-- Unified QR Header + Bulk Download: same row on desktop -->
            <div
                class="rounded-2xl border border-slate-800 bg-linear-to-r from-slate-950 via-slate-950 to-slate-900 px-4 py-3 text-slate-100 shadow-sm"
            >
                <div
                    class="flex flex-col gap-3 2xl:flex-row 2xl:items-center 2xl:justify-between"
                >
                    <!-- Left: title / badge / description -->
                    <div
                        class="flex min-w-0 flex-1 flex-col gap-2 xl:flex-row xl:items-center xl:gap-3"
                    >
                        <div class="flex shrink-0 items-center gap-2">
                            <h1
                                class="whitespace-nowrap text-base font-bold tracking-tight text-white"
                            >
                                Asset QR Codes
                            </h1>

                            <span
                                class="whitespace-nowrap rounded-full border border-blue-500/30 bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-300"
                            >
                                QR Inventory
                            </span>
                        </div>

                        <p
                            class="min-w-0 text-sm text-slate-400 xl:truncate 2xl:max-w-[390px]"
                        >
                            Manage generated asset QR codes for viewing, printing,
                            downloading and controlled deletion.
                        </p>
                    </div>

                    <!-- Right: top search + bulk download + total -->
                    <div
                        class="flex w-full shrink-0 flex-col gap-2 md:flex-row md:items-end 2xl:w-auto 2xl:items-center"
                    >
                        <!-- System top search: DataTable's internal search stays disabled -->
                        <label class="block w-full min-w-0 md:w-[280px] 2xl:w-[320px]">
                            <span
                                class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500"
                            >
                                Search
                            </span>

                            <div class="relative">
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    autocomplete="off"
                                    placeholder="Search code, asset, unit, location..."
                                    class="h-10 w-full rounded-lg border border-slate-700 bg-black px-3 pr-9 text-sm font-semibold text-white outline-none placeholder:font-normal placeholder:text-slate-500 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20"
                                    @keydown.enter.prevent="submitAssetQrSearch"
                                />

                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    class="absolute right-2 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md text-base font-bold text-slate-400 transition hover:bg-slate-800 hover:text-white"
                                    title="Clear search"
                                    aria-label="Clear search"
                                    @click="clearAssetQrSearch"
                                >
                                    ×
                                </button>
                            </div>
                        </label>

                        <div
                            v-if="can?.('assetqrcodes.save') ?? false"
                            class="grid min-w-0 flex-1 gap-2 sm:grid-cols-[155px_minmax(260px,1fr)_auto] 2xl:flex-none 2xl:grid-cols-[150px_330px_auto]"
                        >
                            <label class="block min-w-0">
                                <span
                                    class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500"
                                >
                                    Download By
                                </span>

                                <select
                                    v-model="bulkDownloadScope"
                                    :disabled="bulkDownloadLoading"
                                    class="h-10 w-full rounded-lg border border-slate-700 bg-black px-3 text-sm font-semibold text-white outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 disabled:opacity-50"
                                >
                                    <option value="unit">Unit</option>
                                    <option
                                        v-if="
                                            bulkDownloadOptions.can_download_organization
                                        "
                                        value="organization"
                                    >
                                        Organization
                                    </option>
                                </select>
                            </label>

                            <label class="block min-w-0">
                                <span
                                    class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500"
                                >
                                    {{
                                        bulkDownloadScope === 'organization'
                                            ? 'Organization'
                                            : 'Unit'
                                    }}
                                </span>

                                <select
                                    v-model="bulkDownloadTargetId"
                                    :disabled="
                                        bulkDownloadLoading ||
                                        bulkDownloadTargets.length === 0
                                    "
                                    class="h-10 w-full min-w-0 rounded-lg border border-slate-700 bg-black px-3 text-sm font-semibold text-white outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 disabled:opacity-50"
                                >
                                    <option value="" disabled>
                                        {{
                                            bulkDownloadLoading
                                                ? 'Loading...'
                                                : 'Select target'
                                        }}
                                    </option>

                                    <option
                                        v-for="item in bulkDownloadTargets"
                                        :key="`${bulkDownloadScope}-${item.id}`"
                                        :value="String(item.id)"
                                    >
                                        {{ item.name }} ({{ item.qr_count }} QR)
                                    </option>
                                </select>
                            </label>

                            <button
                                type="button"
                                :disabled="
                                    bulkDownloadLoading ||
                                    !bulkDownloadTargetId ||
                                    !selectedBulkTarget?.qr_count
                                "
                                @click="downloadBulkQrZip"
                                class="inline-flex h-10 items-center justify-center gap-2 self-end whitespace-nowrap rounded-lg bg-sky-700 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-sky-600 disabled:cursor-not-allowed disabled:opacity-50"
                                title="Download all matching QR codes as one ZIP archive"
                            >
                                <Download class="h-4 w-4" />
                            Download QR ZIP
                            </button>
                        </div>

                        <div
                            class="flex h-10 shrink-0 items-center gap-2 self-end rounded-xl border border-slate-800 bg-slate-900/80 px-4 text-sm md:self-auto"
                        >
                            <span
                                class="whitespace-nowrap text-xs font-medium text-slate-500"
                            >
                                Total QR Codes
                            </span>

                            <span
                                class="text-lg font-bold leading-none text-white"
                            >
                                {{
                                    props.assets?.total ??
                                    props.assets?.data?.length ??
                                    0
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QR Preview Modal -->
            <Dialog v-model:open="showQrModal">
                <DialogContent class="w-full max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Asset QR Code</DialogTitle>
                    </DialogHeader>

                    <div class="flex justify-center py-4">
                        <img
                            v-if="selectedQr"
                            :src="`/storage/${selectedQr}`"
                            class="max-h-[85vh] rounded-xl bg-white p-3 shadow"
                            alt="Asset QR Code Preview"
                        />
                    </div>
                </DialogContent>
            </Dialog>

            <!-- Table Card -->
            <div
                class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-950 shadow-sm ghadams-media-preview-table"
             @click="handleMediaTableClick">
                <DataTable :data="props.assets?.data ?? []" :columns="columns" />
            </div>

            <!-- Summary Footer -->
            <div
                v-if="props.assets?.total"
                class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-400"
            >
                Showing
                <span class="font-semibold text-slate-200">
                    {{ props.assets?.from ?? 0 }}
                </span>
                to
                <span class="font-semibold text-slate-200">
                    {{ props.assets?.to ?? 0 }}
                </span>
                of
                <span class="font-semibold text-slate-200">
                    {{ props.assets?.total ?? 0 }}
                </span>
                QR code records
            </div>
        </div>
    
        <!-- GhanaDAMS Large Media Preview -->
        <Teleport to="body">
            <div
                v-if="mediaPreviewOpen"
                class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/85 p-3 backdrop-blur-sm sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-label="mediaPreviewTitle"
                @click.self="closeMediaPreview"
            >
                <div
                    class="relative flex max-h-[96vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-700 bg-slate-950 shadow-2xl"
                >
                    <div
                        class="flex shrink-0 items-center justify-between border-b border-slate-800 px-4 py-3"
                    >
                        <div>
                            <h2 class="text-base font-bold text-white">
                                {{ mediaPreviewTitle }}
                            </h2>
                            <p
                                v-if="mediaPreviewKind === 'qr'"
                                class="mt-0.5 text-xs font-medium text-cyan-300"
                            >
                                Large scannable QR preview
                            </p>
                            <p
                                v-else
                                class="mt-0.5 text-xs text-slate-400"
                            >
                                Full asset image preview
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-700 bg-slate-900 text-xl font-bold text-slate-300 transition hover:border-red-500 hover:bg-red-500/10 hover:text-red-300"
                            title="Close preview"
                            aria-label="Close preview"
                            @click="closeMediaPreview"
                        >
                            ×
                        </button>
                    </div>

                    <div
                        class="flex min-h-0 flex-1 items-center justify-center overflow-auto bg-slate-900/60 p-3 sm:p-5"
                    >
                        <img
                            v-if="mediaPreviewUrl"
                            :src="mediaPreviewUrl"
                            :class="
                                mediaPreviewKind === 'qr'
                                    ? 'max-h-[78vh] w-[min(82vw,720px)] rounded-2xl bg-white p-5 object-contain shadow-2xl'
                                    : 'max-h-[82vh] max-w-[94vw] rounded-xl object-contain shadow-2xl'
                            "
                            :alt="mediaPreviewTitle"
                        />
                    </div>

                    <div
                        v-if="mediaPreviewKind === 'qr'"
                        class="shrink-0 border-t border-slate-800 px-4 py-2 text-center text-xs font-semibold text-cyan-300"
                    >
                        Scan this enlarged QR code directly with another phone or QR scanner.
                    </div>
                </div>
            </div>
        </Teleport>
</AppLayout>
</template>

<style scoped>
/* GhanaDAMS clickable table media */
:deep(.ghadams-media-preview-table img[src*="/storage/"]) {
    cursor: zoom-in;
    transition:
        transform 150ms ease,
        box-shadow 150ms ease,
        border-color 150ms ease;
}

:deep(.ghadams-media-preview-table img[src*="/storage/"]:hover) {
    transform: scale(1.08);
    box-shadow: 0 0 0 2px rgb(34 211 238 / 0.35);
}
</style>
