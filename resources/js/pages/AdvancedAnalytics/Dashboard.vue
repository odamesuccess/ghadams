<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

const props = defineProps<{
  summary: any[]
  assetStatus: any[]
  assetYears: any[]
  topLocations: any[]
  topCategories: any[]
  workflow: any[]
  quality: any
  risks: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Advanced Analytics', href: '/advanced-analytics' },
]

const maxTotal = (items: any[]) => Math.max(...items.map((item) => Number(item.total ?? item.value ?? 0)), 1)
const width = (value: number, max: number) => `${Math.max(4, Math.round((Number(value) / max) * 100))}%`
</script>

<template>
  <Head title="Advanced Analytics" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-4 my-4 space-y-5 text-slate-100 sm:mx-6">
      <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-950 shadow-2xl">
        <div class="bg-gradient-to-br from-slate-900 via-slate-950 to-slate-900 p-5 sm:p-7">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-300">Data-driven asset intelligence</p>
              <h1 class="mt-2 text-2xl font-black text-white sm:text-3xl">Advanced Analytics Dashboard</h1>
              <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                Executive analytics for asset visibility, lifecycle risk, data quality, maintenance pressure and audit readiness.
              </p>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs sm:grid-cols-3">
              <Link href="/advanced-analytics/distribution-count" class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-center font-semibold hover:bg-slate-800">Distribution</Link>
              <Link href="/advanced-analytics/data-quality" class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-center font-semibold hover:bg-slate-800">Data Quality</Link>
              <Link href="/advanced-analytics/lifecycle-risk" class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-center font-semibold hover:bg-slate-800">Risk</Link>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article v-for="card in props.summary" :key="card.label" class="rounded-2xl border border-slate-800 bg-slate-950 p-5 shadow-xl">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ card.label }}</p>
              <p class="mt-2 text-2xl font-black text-white">{{ card.value }}</p>
            </div>
            <span class="rounded-full bg-cyan-500/10 px-3 py-1 text-xs font-bold text-cyan-300">KPI</span>
          </div>
          <p class="mt-3 text-sm text-slate-400">{{ card.subtext }}</p>
        </article>
      </section>

      <section class="grid gap-5 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-5 xl:col-span-2">
          <div class="mb-4 flex items-center justify-between">
            <div>
              <h2 class="text-lg font-bold text-white">Asset Status Distribution</h2>
              <p class="text-sm text-slate-400">Lifecycle status summary used for risk and replacement planning.</p>
            </div>
            <Link href="/advanced-analytics/distribution-count" class="text-sm font-semibold text-cyan-300 hover:text-cyan-200">View details</Link>
          </div>
          <div class="space-y-3">
            <div v-for="item in props.assetStatus" :key="item.status" class="rounded-xl border border-slate-800 bg-slate-900/60 p-3">
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="font-semibold text-slate-100">{{ item.label }}</span>
                <span class="font-bold text-white">{{ item.total }}</span>
              </div>
              <div class="mt-2 h-2 rounded-full bg-slate-800">
                <div class="h-2 rounded-full bg-cyan-400" :style="{ width: width(item.total, maxTotal(props.assetStatus)) }"></div>
              </div>
              <p class="mt-2 text-xs text-slate-500">{{ item.valueLabel }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <h2 class="text-lg font-bold text-white">Data Quality Index</h2>
          <p class="text-sm text-slate-400">Critical completeness score for analysis-ready records.</p>
          <div class="mt-5 rounded-3xl border border-emerald-500/30 bg-emerald-500/10 p-6 text-center">
            <p class="text-5xl font-black text-emerald-300">{{ props.quality.overallScore }}%</p>
            <p class="mt-2 text-sm font-semibold text-emerald-100">{{ props.quality.label }}</p>
          </div>
          <Link href="/advanced-analytics/data-quality" class="mt-4 block rounded-xl bg-emerald-600 px-4 py-3 text-center text-sm font-bold text-white hover:bg-emerald-500">Open data quality page</Link>
        </div>
      </section>

      <section class="grid gap-5 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <h2 class="text-lg font-bold text-white">Top Asset Locations</h2>
          <div class="mt-4 space-y-3">
            <div v-for="item in props.topLocations" :key="item.label" class="rounded-xl bg-slate-900/60 p-3">
              <div class="flex justify-between gap-4 text-sm"><span class="text-slate-200">{{ item.label }}</span><strong>{{ item.total }}</strong></div>
              <div class="mt-2 h-2 rounded-full bg-slate-800"><div class="h-2 rounded-full bg-violet-400" :style="{ width: width(item.total, maxTotal(props.topLocations)) }"></div></div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <h2 class="text-lg font-bold text-white">Lifecycle Risk Watch</h2>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <div v-for="risk in props.risks" :key="risk.label" class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4">
              <p class="text-xs font-bold uppercase tracking-wider text-amber-300">{{ risk.label }}</p>
              <p class="mt-2 text-2xl font-black text-white">{{ risk.value }}</p>
              <p class="mt-1 text-xs text-slate-400">{{ risk.subtext }}</p>
            </div>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
