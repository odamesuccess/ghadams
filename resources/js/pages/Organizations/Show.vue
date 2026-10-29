<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { can } from '@/utils/permissions'
import { Mail, Phone, MapPin, Pencil, Building2, Hash } from 'lucide-vue-next'

/* =======================
   Types
======================= */
interface Unit {
  id: number
  unitname: string
}

interface Organization {
  id: number
  organizations_name: string
  organizations_email?: string | null
  organizations_phone?: string | null
  organizations_address?: string | null
  organization_abbreviation?: string | null
  created_at: string
  updated_at: string
  units: Unit[]
}

/* =======================
   Props
======================= */
const props = defineProps<{
  organization: Organization
}>()

/* =======================
   Helpers
======================= */
const formatDate = (value: string) => new Date(value).toLocaleString()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Organizations', href: '/organizations' },
  {
    title: props.organization.organizations_name,
    href: `/organizations/${props.organization.id}`,
  },
]
</script>

<template>
  <Head :title="organization.organizations_name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-6 mt-6 rounded-2xl border border-slate-800 bg-gradient-to-br from-[#020617] via-[#020617] to-[#0f172a] p-8 shadow-xl">

      <!-- Header -->
      <div class="mb-10 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Organization Details</h1>
          <p class="text-sm text-slate-400">General information and assigned units</p>
        </div>

        <div class="flex gap-3">
          <Link
            href="/organizations"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800"
          >
            ← Back
          </Link>

          <Link
            v-if="can('organizations.edit')"
            :href="`/organizations/${organization.id}/edit`"
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
          >
            <Pencil class="h-4 w-4" />
            Edit Organization
          </Link>
        </div>
      </div>

      <!-- Info Grid -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

        <!-- Name -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Organization Name</p>
          <p class="text-sm font-medium text-white">{{ organization.organizations_name }}</p>
        </div>

        <!-- Abbreviation -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Abbreviation</p>
          <div class="inline-flex items-center gap-2 text-sm text-white">
            <Hash class="h-4 w-4 text-yellow-400" />
            {{ organization.organization_abbreviation || '—' }}
          </div>
        </div>

        <!-- Email -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email</p>
          <div class="inline-flex items-center gap-2 text-sm text-white">
            <Mail class="h-4 w-4 text-blue-400" />
            {{ organization.organizations_email || '—' }}
          </div>
        </div>

        <!-- Phone -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Phone</p>
          <div class="inline-flex items-center gap-2 text-sm text-white">
            <Phone class="h-4 w-4 text-green-400" />
            {{ organization.organizations_phone || '—' }}
          </div>
        </div>

        <!-- Address -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4 md:col-span-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Address</p>
          <div class="inline-flex items-center gap-2 text-sm text-white">
            <MapPin class="h-4 w-4 text-purple-400" />
            {{ organization.organizations_address || '—' }}
          </div>
        </div>

        <!-- Created -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Created At</p>
          <p class="text-sm font-medium text-white">{{ formatDate(organization.created_at) }}</p>
        </div>

        <!-- Updated -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Last Updated</p>
          <p class="text-sm font-medium text-white">{{ formatDate(organization.updated_at) }}</p>
        </div>

      </div>

      <!-- Units Section -->
      <div class="mt-12">
        <h3 class="mb-4 text-lg font-semibold text-white">
          Assigned Units ({{ organization.units.length }})
        </h3>

        <div v-if="organization.units.length" class="flex flex-wrap gap-3">
          <Link
            v-for="unit in organization.units"
            :key="unit.id"
            :href="`/units/${unit.id}`"
            class="inline-flex items-center gap-2 rounded-md bg-slate-800 px-3 py-1.5 text-sm text-white hover:bg-slate-700"
          >
            <Building2 class="h-4 w-4 text-blue-400" />
            {{ unit.unitname }}
          </Link>
        </div>

        <p v-else class="text-sm text-slate-400">
          No units assigned to this organization.
        </p>
      </div>

    </div>
  </AppLayout>
</template>
