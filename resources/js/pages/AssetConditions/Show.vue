<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

/* =======================
   Props (NO destructure – SAFE)
======================= */
const props = defineProps<{
  assetCondition: {
    id: number
    conditionname: string
    created_at: string | null
    updated_at: string | null
  }
}>()

/* =======================
   Helpers
======================= */
const formatDate = (value: string | null) => {
  if (!value) return '—'
  const date = new Date(value)
  return isNaN(date.getTime()) ? '—' : date.toLocaleString()
}

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Asset Conditions', href: '/assetconditions' },
  { title: 'Details', href: '#' },
]
</script>

<template>
  <Head title="Asset Condition Details" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div
      class="mx-6 mt-6 rounded-xl border border-slate-800
             bg-gradient-to-br from-[#0b1437] via-[#0f1c4d] to-[#0b1437]
             p-8 shadow-lg"
    >
      <h2 class="mb-6 text-lg font-semibold text-white">
        Asset Condition Details
      </h2>

      <!-- DETAILS GRID -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-slate-200">

        <!-- Condition Name -->
        <div>
          <p class="text-sm text-slate-400">Condition Name</p>
          <p class="font-medium">
            {{ props.assetCondition.conditionname }}
          </p>
        </div>

        <!-- Created -->
        <div>
          <p class="text-sm text-slate-400">Created At</p>
          <p class="font-medium">
            {{ formatDate(props.assetCondition.created_at) }}
          </p>
        </div>

        <!-- Updated -->
        <div>
          <p class="text-sm text-slate-400">Last Updated</p>
          <p class="font-medium">
            {{ formatDate(props.assetCondition.updated_at) }}
          </p>
        </div>

      </div>

      <!-- FOOTER -->
      <div class="mt-8 flex justify-end gap-3 border-t border-slate-700 pt-6">
        <Link
          :href="`/assetconditions/${props.assetCondition.id}/edit`"
          class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold
                 text-white hover:bg-blue-700"
        >
          Edit
        </Link>

        <Link
          href="/assetconditions"
          class="rounded bg-slate-700 px-4 py-2 text-sm
                 text-white hover:bg-slate-600"
        >
          Back
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
