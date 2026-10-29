<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { can } from '@/utils/permissions'

const props = defineProps<{
  maintenanceProvider: {
    id: number
    providername: string
    phonenumber: string
    email: string
    created_at: string
    updated_at: string
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Maintenance Providers', href: '/maintenanceproviders' },
  { title: 'Details', href: '#' },
]

const formatDate = (value: string) => new Date(value).toLocaleString()
</script>

<template>
  <Head title="Maintenance Provider Details" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-6 mt-6 rounded-2xl border border-slate-800 bg-gradient-to-br from-[#020617] via-[#020617] to-[#0f172a] p-8 shadow-xl">

      <!-- Header -->
      <div class="mb-10 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Maintenance Provider Details</h1>
          <p class="text-sm text-slate-400">Full information about this maintenance provider</p>
        </div>

        <div class="flex gap-3">
          <Link
            href="/maintenanceproviders"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800"
          >
            ← Back
          </Link>

          <Link
            v-if="can('maintenanceproviders.edit')"
            :href="`/maintenanceproviders/${maintenanceProvider.id}/edit`"
            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
          >
            Edit Provider
          </Link>
        </div>
      </div>

      <!-- Uniform Info Grid -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Provider Name</p>
          <p class="text-sm font-medium text-white">{{ maintenanceProvider.providername }}</p>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Phone</p>
          <p class="text-sm font-medium text-white">{{ maintenanceProvider.phonenumber }}</p>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4 md:col-span-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email</p>
          <p class="text-sm font-medium text-white">{{ maintenanceProvider.email }}</p>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Created At</p>
          <p class="text-sm font-medium text-white">{{ formatDate(maintenanceProvider.created_at) }}</p>
        </div>

        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Last Updated</p>
          <p class="text-sm font-medium text-white">{{ formatDate(maintenanceProvider.updated_at) }}</p>
        </div>

      </div>

    </div>
  </AppLayout>
</template>
