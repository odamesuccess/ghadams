<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { Pencil } from 'lucide-vue-next'

/* =======================
   Props
======================= */
const props = defineProps<{
  supplier: {
    id: number
    suppliername: string
    phonenumber: string
    email: string
    created_at: string
    updated_at: string
  }
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Suppliers', href: '/suppliers' },
  { title: props.supplier.suppliername, href: '#' },
]

/* =======================
   Helpers
======================= */
const formatDate = (value: string) => new Date(value).toLocaleString()
</script>

<template>
  <Head :title="supplier.suppliername" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-6 mt-6 rounded-2xl border border-slate-800 bg-gradient-to-br from-[#020617] via-[#020617] to-[#0f172a] p-8 shadow-xl">

      <!-- Header -->
      <div class="mb-10 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-white">Supplier Details</h1>
          <p class="text-sm text-slate-400">Full information about this supplier</p>
        </div>

        <div class="flex gap-3">
          <Link
            href="/suppliers"
            class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800"
          >
            ← Back
          </Link>

          <Link
            :href="`/suppliers/${supplier.id}/edit`"
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
          >
            <Pencil class="h-4 w-4" />
            Edit Supplier
          </Link>
        </div>
      </div>

      <!-- Uniform Info Grid -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">

        <!-- Supplier Name -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Supplier Name</p>
          <p class="text-sm font-medium text-white">{{ supplier.suppliername }}</p>
        </div>

        <!-- Phone -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Phone</p>
          <p class="text-sm font-medium text-white">{{ supplier.phonenumber }}</p>
        </div>

        <!-- Email -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4 md:col-span-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Email</p>
          <p class="text-sm font-medium text-white">{{ supplier.email }}</p>
        </div>

        <!-- Created At -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Created At</p>
          <p class="text-sm font-medium text-white">{{ formatDate(supplier.created_at) }}</p>
        </div>

        <!-- Updated At -->
        <div class="space-y-1 rounded-xl border border-slate-800 bg-slate-900/40 p-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Last Updated</p>
          <p class="text-sm font-medium text-white">{{ formatDate(supplier.updated_at) }}</p>
        </div>

      </div>

    </div>
  </AppLayout>
</template>
