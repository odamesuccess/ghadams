<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { can } from '@/utils/permissions'

/* =======================
   Props
======================= */
const props = defineProps<{
  source: {
    id: number
    name: string
    description: string | null
    created_at: string
    updated_at: string
  }
}>()

/* =======================
   Helpers
======================= */
const formatDate = (value: string) => new Date(value).toLocaleString()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Sources', href: '/sources' },
  { title: 'Details', href: '#' },
]
</script>

<template>
  <Head title="Source Details" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-6 mt-6 rounded-2xl border border-slate-800 bg-gradient-to-br from-[#020617] via-[#020617] to-[#0f172a] p-8 shadow-xl">

      <!-- Header -->
      <div class="mb-10 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Source Details</h1>
          <p class="text-sm text-slate-400">Full information about this funding source</p>
        </div>

        <div class="flex gap-3">
          <Link
            href="/sources"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800"
          >
            ← Back
          </Link>

          <Link
            v-if="can('sources.edit')"
            :href="`/sources/${source.id}/edit`"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
          >
            Edit Source
          </Link>
        </div>
      </div>

      <!-- Uniform Info Grid -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

        <!-- Source Name -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Source Name</p>
          <p class="text-sm font-medium text-white">{{ source.name }}</p>
        </div>

        <!-- Description -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4 md:col-span-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Description</p>
          <p class="text-sm font-medium text-white">{{ source.description ?? '—' }}</p>
        </div>

        <!-- Created At -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Created At</p>
          <p class="text-sm font-medium text-white">{{ formatDate(source.created_at) }}</p>
        </div>

        <!-- Updated At -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Last Updated</p>
          <p class="text-sm font-medium text-white">{{ formatDate(source.updated_at) }}</p>
        </div>

      </div>

    </div>
  </AppLayout>
</template>
