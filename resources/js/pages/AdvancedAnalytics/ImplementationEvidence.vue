<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

const props = defineProps<{
  summary: any[]
  evidence: any[]
  chapterOutputs: any[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Advanced Analytics', href: '/advanced-analytics' },
  { title: 'Implementation Evidence', href: '/advanced-analytics/implementation-evidence' },
]
</script>

<template>
  <Head title="Implementation Evidence" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-4 my-4 space-y-5 text-slate-100 sm:mx-6">
      <section class="rounded-3xl border border-slate-800 bg-slate-950 p-5 shadow-2xl sm:p-7">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-violet-300">Chapter evidence</p>
        <h1 class="mt-2 text-2xl font-black text-white sm:text-3xl">Implementation Evidence Centre</h1>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-300">A single page for collecting the screenshots and outputs needed to strengthen Chapter Four and Chapter Five without using design diagrams in the implementation chapter.</p>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article v-for="card in props.summary" :key="card.label" class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
          <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ card.label }}</p>
          <p class="mt-2 text-2xl font-black text-white">{{ card.value }}</p>
          <p class="mt-2 text-sm text-slate-400">{{ card.subtext }}</p>
        </article>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
        <h2 class="text-lg font-bold text-white">Recommended Figure Outputs</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="item in props.evidence" :key="item.figure" class="rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
            <p class="text-xs font-bold uppercase tracking-wider text-cyan-300">{{ item.figure }}</p>
            <h3 class="mt-2 font-bold text-white">{{ item.title }}</h3>
            <p class="mt-2 min-h-12 text-sm text-slate-400">{{ item.evidence }}</p>
            <Link :href="item.page" class="mt-4 inline-flex rounded-xl bg-cyan-600 px-3 py-2 text-xs font-bold text-white hover:bg-cyan-500">Open page</Link>
          </article>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-950 p-5">
        <h2 class="text-lg font-bold text-white">How the New Pages Strengthen the Chapters</h2>
        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="text-left text-xs uppercase text-slate-400"><tr><th class="py-2">Chapter area</th><th class="py-2">System page</th><th class="py-2">Value</th></tr></thead>
            <tbody class="divide-y divide-slate-800"><tr v-for="row in props.chapterOutputs" :key="row.chapter_area"><td class="py-2 font-semibold text-white">{{ row.chapter_area }}</td><td class="py-2 text-cyan-300">{{ row.system_page }}</td><td class="py-2 text-slate-300">{{ row.chapter_value }}</td></tr></tbody>
          </table>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
