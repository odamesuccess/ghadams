<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

const props = defineProps<{
  summary: any[]
  status: any[]
  locations: any[]
  categories: any[]
  sources: any[]
  costBands: any[]
  ageBands: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Advanced Analytics', href: '/advanced-analytics' },
  { title: 'Distribution Count', href: '/advanced-analytics/distribution-count' },
]

const maxTotal = (items: any[]) => Math.max(...items.map((item) => Number(item.total ?? 0)), 1)
const width = (value: number, max: number) => `${Math.max(4, Math.round((Number(value) / max) * 100))}%`
</script>

<template>
  <Head title="Asset Distribution Count" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-4 my-4 space-y-5 text-slate-100 sm:mx-6">
      <section class="rounded-3xl border border-slate-800 bg-slate-950 p-5 shadow-2xl sm:p-7">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-300">Descriptive analytics</p>
        <h1 class="mt-2 text-2xl font-black text-white sm:text-3xl">Asset Distribution Count Analysis</h1>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-300">This page provides count-based evidence for Chapter Four by showing the distribution of assets by lifecycle status, office/location, category, source, cost band and asset age band.</p>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article v-for="card in props.summary" :key="card.label" class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ card.label }}</p>
          <p class="mt-2 text-2xl font-black text-white">{{ card.value }}</p>
          <p class="mt-2 text-sm text-slate-400">{{ card.subtext }}</p>
        </article>
      </section>

      <section class="grid gap-5 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <h2 class="text-lg font-bold text-white">Count by Lifecycle Status</h2>
          <div class="mt-4 space-y-3">
            <div v-for="item in props.status" :key="item.label" class="rounded-xl bg-slate-900/70 p-3">
              <div class="flex justify-between text-sm"><span>{{ item.label }}</span><strong>{{ item.total }}</strong></div>
              <div class="mt-2 h-2 rounded-full bg-slate-800"><div class="h-2 rounded-full bg-cyan-400" :style="{ width: width(item.total, maxTotal(props.status)) }"></div></div>
              <p class="mt-1 text-xs text-slate-500">{{ item.valueLabel }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <h2 class="text-lg font-bold text-white">Count by Asset Category</h2>
          <div class="mt-4 space-y-3">
            <div v-for="item in props.categories" :key="item.label" class="rounded-xl bg-slate-900/70 p-3">
              <div class="flex justify-between text-sm"><span class="truncate pr-3">{{ item.label }}</span><strong>{{ item.total }}</strong></div>
              <div class="mt-2 h-2 rounded-full bg-slate-800"><div class="h-2 rounded-full bg-violet-400" :style="{ width: width(item.total, maxTotal(props.categories)) }"></div></div>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-5 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <h2 class="text-lg font-bold text-white">Count by Office / Location</h2>
          <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="text-left text-xs uppercase text-slate-400"><tr><th class="py-2">Location</th><th class="py-2 text-right">Count</th><th class="py-2 text-right">Value</th></tr></thead>
              <tbody class="divide-y divide-slate-800">
                <tr v-for="item in props.locations" :key="item.label"><td class="py-2 text-slate-200">{{ item.label }}</td><td class="py-2 text-right font-bold text-white">{{ item.total }}</td><td class="py-2 text-right text-slate-400">{{ item.valueLabel }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <h2 class="text-lg font-bold text-white">Count by Source, Cost and Age</h2>
          <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div>
              <h3 class="mb-2 text-sm font-bold text-cyan-300">Source</h3>
              <p v-for="item in props.sources" :key="item.label" class="flex justify-between border-b border-slate-800 py-2 text-xs"><span>{{ item.label }}</span><strong>{{ item.total }}</strong></p>
            </div>
            <div>
              <h3 class="mb-2 text-sm font-bold text-cyan-300">Cost band</h3>
              <p v-for="item in props.costBands" :key="item.label" class="flex justify-between border-b border-slate-800 py-2 text-xs"><span>{{ item.label }}</span><strong>{{ item.total }}</strong></p>
            </div>
            <div>
              <h3 class="mb-2 text-sm font-bold text-cyan-300">Age band</h3>
              <p v-for="item in props.ageBands" :key="item.label" class="flex justify-between border-b border-slate-800 py-2 text-xs"><span>{{ item.label }}</span><strong>{{ item.total }}</strong></p>
            </div>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
