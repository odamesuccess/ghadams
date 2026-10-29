<script setup lang="ts">
/* =========================================================
   IMPORTS
========================================================= */
import { h, ref, watch } from 'vue'
import type { ColumnDef } from '@tanstack/vue-table'
import { Head, Link, router } from '@inertiajs/vue3'
import { debounce } from 'lodash-es'

import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'
import { create } from '@/routes/assets'
import { can } from '@/utils/permissions'
import { confirmDelete } from '@/utils/sweetalert'

/* =========================================================
   TYPES
========================================================= */
interface AssetName {
  id: number
  name: string
}

interface Unit {
  id: number
  name: string
  locationname?: string | null
}

type StatusType =
  | 'active'
  | 'under_maintenance'
  | 'obsolete'
  | 'disposed'

interface Asset {
  id: number
  asset_code: string
  asset_name?: AssetName | null
  unit?: Unit | null
  purchase_cost?: number | null
  status: StatusType
  asset_image?: string | null
  updated_at: string
  purchase_date?: string | null
}

/* =========================================================
   PROPS
========================================================= */
const props = defineProps<{
  assets: {
    data: Asset[]
  }
  units: Unit[]
  filters: {
    search?: string
    status?: StatusType | ''
    unit_id?: number | string | ''
  }
}>()

/* =========================================================
   SEARCH + STATUS FILTER STATE
========================================================= */
const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const unitId = ref(String(props.filters.unit_id ?? ''))
const backfillingAssetMedia = ref(false)

/* =========================================================
   SERVER FILTERING
========================================================= */
const applyFilters = () => {
  router.get(
    '/assets',
    {
      search: search.value,
      status: status.value,
      unit_id: unitId.value,
    },
    {
      preserveState: true,
      replace: true,
    }
  )
}

/* Debounced search */
watch(
  search,
  debounce(() => {
    applyFilters()
  }, 400)
)

/* Immediate status change */
watch(status, () => {
  applyFilters()
})

/* Immediate unit change */
watch(unitId, () => {
  applyFilters()
})

const backfillExistingAssetMedia = () => {
  if (backfillingAssetMedia.value) return

  const confirmed = window.confirm(
    'Fix all existing asset media now? GhanaDAMS will repair missing QR codes, replace old generated/type placeholders with real representative photographs by Asset Type, and preserve actual uploaded asset photographs.'
  )

  if (!confirmed) return

  backfillingAssetMedia.value = true

  router.post(
    '/assetqrcodes/backfill-existing',
    { force_qr: false },
    {
      preserveScroll: true,
      onFinish: () => {
        backfillingAssetMedia.value = false
      },
    }
  )
}

/* =========================================================
   HELPERS
========================================================= */
const formatDate = (value: string) =>
  new Date(value).toLocaleString()

const statusBadge = (status: StatusType) =>
  ({
    active: 'bg-green-700',
    under_maintenance: 'bg-yellow-600',
    obsolete: 'bg-orange-600',
    disposed: 'bg-red-700',
  }[status] ?? 'bg-slate-600')

/* =========================================================
   TABLE COLUMNS
========================================================= */

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
    cell: ({ getValue }) => {
      const image = getValue() as string | null
      return image
        ? h('img', {
            src: `/storage/${image}`,
            class: 'h-10 w-10 rounded object-cover border',
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
    header: 'Asset Code',
    accessorKey: 'asset_code',
  },
  {
    header: 'Asset Name',
    id: 'asset_name',
    cell: ({ row }) =>
      row.original.asset_name?.name ?? '—',
  },
  {
    header: 'Unit',
    id: 'unit',
    cell: ({ row }) =>
      row.original.unit?.name ?? '—',
  },
  {
    header: 'Location',
    id: 'location',
    cell: ({ row }) =>
      row.original.unit?.locationname ?? '—',
  },
  {
    header: 'Status',
    accessorKey: 'status',
    cell: ({ getValue }) =>
      h(
        'span',
        {
          class: [
            'rounded px-2 py-1 text-xs font-semibold text-white',
            statusBadge(getValue() as StatusType),
          ],
        },
        (getValue() as string).replaceAll('_', ' ')
      ),
  },
  {
    header: 'Purchase Date',
    accessorKey: 'purchase_date',
    cell: ({ getValue }) =>
      formatDate(getValue() as string),
  },
  {
    header: 'Updated',
    accessorKey: 'updated_at',
    cell: ({ getValue }) =>
      formatDate(getValue() as string),
  },
  {
    header: 'Actions',
    id: 'actions',
    cell: ({ row }) => {
      const asset = row.original
      const actions = []

      if (can('assets.view')) {
        actions.push(
          h(
            Link,
            {
              href: `/assets/${asset.id}`,
              class:
                'rounded bg-slate-700 px-2 py-1 text-xs text-white hover:bg-slate-600',
            },
            'View'
          )
        )
      }

      if (can('assets.edit')) {
        actions.push(
          h(
            Link,
            {
              href: `/assets/${asset.id}/edit`,
              class:
                'rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700',
            },
            'Edit'
          )
        )
      }

      if (can('assets.delete')) {
        actions.push(
          h(
            'button',
            {
              class:
                'rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700',
              onClick: async () => {
                const confirmed = await confirmDelete({
                  title: 'Delete Asset',
                  text: `Are you sure you want to delete ${asset.asset_code}? The asset will be moved to trash where applicable.`,
                })
                if (confirmed) {
                  router.delete(`/assets/${asset.id}`)
                }
              },
            },
            'Delete'
          )
        )
      }

      return h(
        'div',
        { class: 'flex gap-2 justify-end' },
        actions
      )
    },
  },
]

/* =========================================================
   BREADCRUMBS
========================================================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Assets Management', href: '/assets' },
]
</script>

<template>

    <Head title="Assets Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- ACTION BAR -->
       <div class="mt-2 flex items-center gap-4 px-6 flex-wrap">
  <input
    v-model="search"
    type="text"
    placeholder="Search assets..."
    class="w-full max-w-sm rounded-md border bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:ring-2 focus:ring-blue-600"
  />

  <select
    v-model="status"
    class="rounded-md border bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:ring-2 focus:ring-blue-600"
  >
    <option value="">All Statuses</option>
    <option value="active">Active</option>
    <option value="under_maintenance">Under Maintenance</option>
    <option value="obsolete">Obsolete</option>
    <option value="disposed">Disposed</option>
  </select>

  <select
    v-model="unitId"
    class="min-w-56 rounded-md border bg-slate-800 px-3 py-2 text-sm text-slate-200 focus:ring-2 focus:ring-blue-600"
  >
    <option value="">All Units</option>
    <option
      v-for="unit in units"
      :key="unit.id"
      :value="String(unit.id)"
    >
      {{ unit.name }}{{ unit.locationname ? ` — ${unit.locationname}` : '' }}
    </option>
  </select>

  <div>
    Total: {{ assets.data.length }}
    {{ assets.data.length === 1 ? 'asset' : 'assets' }}
  </div>
  

  <Link
    v-if="can('assets.create')"
    :href="create().url"
    class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
  >
    Create New Asset
  </Link>
</div>

        <!-- TABLE -->
        <div class="mx-6 mt-4 overflow-x-auto rounded-lg border ghadams-media-preview-table" @click="handleMediaTableClick">
            <DataTable :data="assets.data" :columns="columns" />
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
