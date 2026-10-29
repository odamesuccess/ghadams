<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

/* =======================
   Props
======================= */
const props = defineProps<{
  assetName: {
    id: number
    assetname: string
     assetname_abbreviation: string
    usefullife: number | null
    brand: string | null
    created_at: string | null
    updated_at: string | null
  }
}>()

/* =======================
   Helpers
======================= */
const formatDate = (value?: string | null) => {
  if (!value) return '—'
  const date = new Date(value)
  return isNaN(date.getTime()) ? '—' : date.toLocaleString()
}

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Asset Names', href: '/assetnames' },
  { title: 'Details', href: '#' },
]
</script>

<template>
  <Head title="Asset Name Details" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-6 mt-6 rounded-2xl border border-slate-800 bg-gradient-to-br from-[#020617] via-[#020617] to-[#0f172a] p-8 shadow-xl">

      <!-- Header -->
      <div class="mb-10 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Asset Name Details</h1>
          <p class="text-sm text-slate-400">Full information about this asset name</p>
        </div>

        <div class="flex gap-3">
          <Link
            href="/assetnames"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800"
          >
            ← Back
          </Link>

          <Link
            :href="`/assetnames/${assetName.id}/edit`"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
          >
            Edit Asset Name
          </Link>
        </div>
      </div>

      <!-- Uniform Info Grid -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

        <!-- Asset Name -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Asset Name</p>
          <p class="text-sm font-medium text-white">{{ assetName.assetname }}</p>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Asset Abbreviation</p>
          <p class="text-sm font-medium text-white">{{ assetName.assetname_abbreviation }}</p>
        </div>

        <!-- Brand -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Brand</p>
          <p class="text-sm font-medium text-white">{{ assetName.brand ?? '—' }}</p>
        </div>

        <!-- Useful Life -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Useful Life (Years)</p>
          <p class="text-sm font-medium text-white">{{ assetName.usefullife ?? '—' }}</p>
        </div>

        <!-- Created -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Created</p>
          <p class="text-sm font-medium text-white">{{ formatDate(assetName.created_at) }}</p>
        </div>

        <!-- Updated -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4 ">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Last Updated</p>
          <p class="text-sm font-medium text-white">{{ formatDate(assetName.updated_at) }}</p>
        </div>

      </div>

    </div>
  </AppLayout>
</template>
