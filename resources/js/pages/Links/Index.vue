<script setup lang="ts">
import { h, ref } from 'vue'
import type { ColumnDef } from '@tanstack/vue-table'
import { Head, Link } from '@inertiajs/vue3'

import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'

import type { BreadcrumbItem } from '@/types'
import { create } from '@/routes/links'
import { can } from '@/utils/permissions'

/* ================= TYPES ================= */
interface LinkItem {
    id: number
    name: string
    url: string
    logo?: string | null
    logo_url?: string | null
    qr_code?: string | null
    qr_code_url?: string | null
    expires_at?: string | null
    status: 'active' | 'expired' | 'disabled'
    updated_at: string
}

interface Paginated<T> {
    data: T[]
}

/* ================= PROPS ================= */
const props = defineProps<{
    qrs: Paginated<LinkItem>
}>()

/* ================= MODAL STATE ================= */
const showQrModal = ref(false)
const selectedQr = ref<string | null>(null)

const openQrModal = (qr?: string | null) => {
    if (!qr) return

    selectedQr.value = normalizeAssetUrl(qr)
    showQrModal.value = true
}

const closeQrModal = () => {
    showQrModal.value = false
    selectedQr.value = null
}

/* ================= HELPERS ================= */
const statusColor = (status: LinkItem['status']) =>
    ({
        active: 'bg-green-600',
        expired: 'bg-yellow-600',
        disabled: 'bg-red-600',
    })[status] ?? 'bg-gray-600'

const normalizeAssetUrl = (path?: string | null) => {
    if (!path) return null

    const value = String(path).trim()

    if (!value) return null

    if (
        value.startsWith('http://') ||
        value.startsWith('https://') ||
        value.startsWith('data:')
    ) {
        return value
    }

    if (value.startsWith('/storage/')) {
        return value
    }

    if (value.startsWith('storage/')) {
        return `/${value}`
    }

    if (value.startsWith('app-qrs/')) {
        return `/storage/${value}`
    }

    if (value.startsWith('logos/')) {
        return `/storage/app-qrs/${value}`
    }

    if (value.startsWith('images/')) {
        return `/storage/app-qrs/${value}`
    }

    /*
      Fallback for old records where database stores only:
      cnn.png, youtube.png, gr-18.png, qr-10.png, etc.
    */
    if (
        value.startsWith('gr-') ||
        value.startsWith('qr-') ||
        value.includes('qr') ||
        value.includes('qrcode')
    ) {
        return `/storage/app-qrs/images/${value}`
    }

    return `/storage/app-qrs/logos/${value}`
}

const getLogoUrl = (link: LinkItem) => {
    return normalizeAssetUrl(link.logo_url ?? link.logo)
}

const getQrUrl = (link: LinkItem) => {
    return normalizeAssetUrl(link.qr_code_url ?? link.qr_code)
}

/* ================= BUTTON STYLE ================= */
const actionBtn = 'rounded px-2 py-1 text-xs text-white transition'
const actionPreview = `${actionBtn} bg-slate-700 hover:bg-slate-600`
const actionAnalytics = `${actionBtn} bg-emerald-600 hover:bg-emerald-700`
const actionView = `${actionBtn} bg-blue-600 hover:bg-blue-700`

/* ================= TABLE COLUMNS ================= */
const columns: ColumnDef<LinkItem>[] = [
    {
        header: 'Logo',
        accessorKey: 'logo',
        cell: ({ row }) => {
            const logo = getLogoUrl(row.original)

            return logo
                ? h('img', {
                      src: logo,
                      class: 'h-10 w-10 rounded object-cover border',
                      alt: row.original.name,
                      loading: 'lazy',
                  })
                : h(
                      'div',
                      {
                          class:
                              'h-10 w-10 rounded bg-slate-700 flex items-center justify-center text-xs text-slate-300',
                      },
                      'N/A'
                  )
        },
    },

    {
        header: 'App Name',
        accessorKey: 'name',
    },

    {
        header: 'URL',
        accessorKey: 'url',
        cell: ({ getValue }) =>
            h(
                'a',
                {
                    href: getValue() as string,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    class: 'text-blue-400 underline',
                },
                'Open'
            ),
    },

    {
        header: 'QR',
        accessorKey: 'qr_code',
        cell: ({ row }) => {
            const qr = getQrUrl(row.original)

            if (!qr) return '—'

            return h('img', {
                src: qr,
                class:
                    'h-10 w-10 object-contain cursor-pointer hover:scale-110 transition',
                alt: `${row.original.name} QR Code`,
                loading: 'lazy',
                onClick: (e: MouseEvent) => {
                    e.stopPropagation()
                    openQrModal(qr)
                },
            })
        },
    },

    {
        header: 'Status',
        accessorKey: 'status',
        cell: ({ getValue }) =>
            h(
                'span',
                {
                    class: [
                        'px-2 py-1 text-xs rounded text-white capitalize',
                        statusColor(getValue() as LinkItem['status']),
                    ],
                },
                getValue() as string
            ),
    },

    {
        header: 'Expires',
        accessorKey: 'expires_at',
        cell: ({ getValue }) => getValue() ?? '—',
    },

    {
        header: 'Updated',
        accessorKey: 'updated_at',
    },

    {
        header: 'Actions',
        id: 'actions',
        cell: ({ row }) => {
            const link = row.original
            const qr = getQrUrl(link)
            const actions: any[] = []

            if (can('links.view') && qr) {
                actions.push(
                    h(
                        'button',
                        {
                            type: 'button',
                            class: actionPreview,
                            onClick: (e: MouseEvent) => {
                                e.stopPropagation()
                                openQrModal(qr)
                            },
                        },
                        'QR Preview'
                    )
                )
            }

            if (can('links.qranalytic')) {
                actions.push(
                    h(
                        Link,
                        {
                            href: `/links/${link.id}/qranalytic`,
                            class: actionAnalytics,
                        },
                        () => 'Analytics'
                    )
                )
            }

            if (can('links.show')) {
                actions.push(
                    h(
                        Link,
                        {
                            href: `/links/${link.id}`,
                            class: actionView,
                        },
                        () => 'View'
                    )
                )
            }

            return h('div', { class: 'flex gap-2 justify-end' }, actions)
        },
    },
]

/* ================= BREADCRUMBS ================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Links', href: '/links' },
    { title: 'All Links', href: '/links' },
]
</script>

<template>
    <Head title="Links" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-screen bg-slate-950 px-4 py-4 text-slate-100 sm:px-6">
            <!-- Top Bar -->
            <div class="mb-4 flex items-center justify-between">
                <Link
                    v-if="can('links.view')"
                    href="/links/analytics"
                    class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                >
                    View Analytics
                </Link>

                <Link
                    v-if="can('links.create')"
                    :href="create().url"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    Create Link
                </Link>
            </div>

            <!-- Old Interface Style Table -->
            <div class="overflow-x-auto rounded-lg border border-slate-300 bg-slate-900">
                <DataTable
                    :data="props.qrs.data"
                    :columns="columns"
                    searchable
                />

                <div
                    v-if="!qrs?.data?.length"
                    class="p-6 text-center text-slate-400"
                >
                    No links found.
                </div>
            </div>

            <!-- QR MODAL -->
            <div
                v-if="showQrModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            >
                <div class="w-full max-w-lg rounded-xl bg-slate-900 p-6 text-center shadow-xl">
                    <img
                        v-if="selectedQr"
                        :src="selectedQr"
                        class="mx-auto max-h-[70vh] object-contain"
                        alt="QR Code"
                    />

                    <div class="mt-6 flex justify-center gap-3">
                        <a
                            :href="selectedQr ?? '#'"
                            download
                            class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
                        >
                            Download QR
                        </a>

                        <button
                            type="button"
                            @click="closeQrModal"
                            class="rounded bg-slate-700 px-4 py-2 text-sm text-white hover:bg-slate-600"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
