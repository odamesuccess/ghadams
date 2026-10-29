<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

const props = defineProps<{ reports: Array<{ title: string; description: string; csv: string }> }>()
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }, { title: 'Advanced Reports', href: '#' }]
</script>

<template>
  <Head title="Advanced Reports" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-4 my-4 space-y-4 rounded-2xl border border-slate-800 bg-slate-950/90 p-4 text-slate-100 shadow-xl sm:mx-6 sm:p-6">
      <div>
        <h1 class="text-xl font-bold text-white">Advanced Reports & Excel Exports</h1>
        <p class="text-sm text-slate-400">Asset register, warranty, end-of-life, replacement recommendation, maintenance history and QR scan reports.</p>
      </div>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div v-for="report in props.reports" :key="report.title" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
          <h2 class="font-semibold text-white">{{ report.title }}</h2>
          <p class="mt-2 min-h-12 text-sm text-slate-400">{{ report.description }}</p>
          <a :href="report.csv" class="mt-4 inline-flex rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Export Excel CSV</a>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
