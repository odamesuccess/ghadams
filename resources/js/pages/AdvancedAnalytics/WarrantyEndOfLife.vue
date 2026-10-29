<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

const props = defineProps<{
  summary: any[]
  warranty: any[]
  warrantyRows: any[]
  endOfLife: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Advanced Analytics', href: '/advanced-analytics' },
  { title: 'Warranty and End-of-Life', href: '/advanced-analytics/warranty-end-of-life' },
]
</script>

<template>
  <Head title="Warranty and End-of-Life Analytics" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-4 my-4 space-y-5 text-slate-100 sm:mx-6">
      <section class="rounded-3xl border border-slate-800 bg-slate-950 p-5 shadow-2xl sm:p-7">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-300">Lifecycle planning</p>
        <h1 class="mt-2 text-2xl font-black text-white sm:text-3xl">Warranty and End-of-Life Analytics</h1>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-300">
          Active-asset warranty exposure, missing warranty fields and useful-life risk outputs
          for preventive maintenance and replacement planning.
        </p>
        <p class="mt-2 text-xs font-semibold text-emerald-300">
          Warranty scope: Active assets only.
        </p>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article v-for="card in props.warranty" :key="card.label" class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ card.label }}</p>
          <p class="mt-2 text-2xl font-black text-white">{{ card.value }}</p>
          <p class="mt-2 text-sm text-slate-400">{{ card.subtext }}</p>
        </article>
      </section>

      <section class="grid gap-5 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <h2 class="text-lg font-bold text-white">Active Asset Warranty Watch List</h2>
          <div class="mt-4 max-h-[520px] overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 bg-slate-950 text-left text-xs uppercase text-slate-400"><tr><th class="py-2">Asset</th><th class="py-2">Name</th><th class="py-2">Warranty End</th><th class="py-2 text-right">Days</th></tr></thead>
              <tbody class="divide-y divide-slate-800"><tr v-for="row in props.warrantyRows" :key="row.asset_code"><td class="py-2 font-semibold">{{ row.asset_code }}</td><td class="py-2 text-slate-300">{{ row.name }}</td><td class="py-2 text-slate-400">{{ row.warranty_end }}</td><td class="py-2 text-right font-bold" :class="Number(row.days_remaining) < 0 ? 'text-rose-300' : 'text-emerald-300'">{{ row.days_remaining }}</td></tr></tbody>
            </table>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <h2 class="text-lg font-bold text-white">Useful-Life Watch List</h2>
          <div class="mt-4 max-h-[520px] overflow-auto">
            <table class="min-w-full text-sm">
              <thead class="sticky top-0 bg-slate-950 text-left text-xs uppercase text-slate-400"><tr><th class="py-2">Asset</th><th class="py-2">Name</th><th class="py-2">End Date</th><th class="py-2">Risk</th></tr></thead>
              <tbody class="divide-y divide-slate-800"><tr v-for="row in props.endOfLife" :key="row.asset_code"><td class="py-2 font-semibold">{{ row.asset_code }}</td><td class="py-2 text-slate-300">{{ row.name }}</td><td class="py-2 text-slate-400">{{ row.end_date }}</td><td class="py-2 text-amber-300">{{ row.risk }}</td></tr></tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
