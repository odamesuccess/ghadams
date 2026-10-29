<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

interface Organization {
  id: number
  organizations_name: string
  organization_abbreviation: string
}

interface Unit {
  id: number
  organization_id: number | null
  name: string
  unit_abbreviation: string | null
  locationname: string | null
  location_abbreviation: string | null
  officenumber: string | null
  email: string
  phonenumber: string
  created_at: string
  updated_at: string
  organization?: Organization | null
}

interface Props {
  unit: Unit
}

const props = defineProps<Props>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Units', href: '/units' },
  { title: 'Details', href: '#' },
]
</script>

<template>
  <Head title="Unit Details" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="rounded-2xl border border-slate-800 bg-gradient-to-br from-[#020617] via-[#020617] to-[#0f172a] p-8 shadow-xl">

      <!-- Header -->
      <div class="mb-10 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Unit Details</h1>
          <p class="text-sm text-slate-400">Full information about this unit</p>
        </div>

        <div class="flex gap-3">
          <Link
            href="/units"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800"
          >
            ← Back
          </Link>

          <Link
            :href="`/units/${props.unit.id}/edit`"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
          >
            Edit Unit
          </Link>
        </div>
      </div>

      <!-- Professional Uniform Grid -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

        <!-- Field Card -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Unit Name</p>
          <p class="text-base font-semibold text-white">{{ props.unit.name }}</p>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Unit Abbreviation</p>
          <span class="inline-flex rounded-full bg-indigo-600/20 px-3 py-1 text-xs font-semibold text-indigo-300">
            # {{ props.unit.unit_abbreviation || '—' }}
          </span>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Organization</p>
          <span class="inline-flex rounded-full bg-slate-800 px-3 py-1 text-xs text-slate-200">
            {{ props.unit.organization?.organizations_name ?? '—' }}
          </span>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Location Abbreviation</p>
          <span class="inline-flex rounded-full bg-slate-800 px-3 py-1 text-xs text-slate-200">
            {{ props.unit.location_abbreviation || '—' }}
          </span>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Location Name</p>
          <p class="text-sm text-white">{{ props.unit.locationname || '—' }}</p>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Office Number</p>
          <p class="text-sm text-white">{{ props.unit.officenumber || '—' }}</p>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email</p>
          <span class="inline-flex rounded-full bg-slate-800 px-3 py-1 text-xs text-slate-200">
            {{ props.unit.email || '—' }}
          </span>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Phone</p>
          <span class="inline-flex rounded-full bg-slate-800 px-3 py-1 text-xs text-slate-200">
            {{ props.unit.phonenumber || '—' }}
          </span>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Created At</p>
          <p class="text-sm text-white">{{ props.unit.created_at }}</p>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Last Updated</p>
          <p class="text-sm text-white">{{ props.unit.updated_at }}</p>
        </div>

      </div>
    </div>
  </AppLayout>
</template>
