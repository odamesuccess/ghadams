<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

const props = defineProps<{
  summary: any[]; quality: any; checks: any[]
  duplicateAssetCodes: any[]; missingCriticalFields: any[]
}>()
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Advanced Analytics', href: '/advanced-analytics' },
  { title: 'Data Quality', href: '/advanced-analytics/data-quality' },
]
const root = ref<HTMLElement | null>(null)
const availableHeight = ref(700)
const checkPage = ref(1)
const duplicatePage = ref(1)
const missingPage = ref(1)
const checksPerPage = computed(() => availableHeight.value < 620 ? 4 : 6)
const rowsPerPage = computed(() => availableHeight.value < 620 ? 2 : 4)
const checkPages = computed(() => Math.max(1, Math.ceil(props.checks.length / checksPerPage.value)))
const duplicatePages = computed(() => Math.max(1, Math.ceil(props.duplicateAssetCodes.length / rowsPerPage.value)))
const missingPages = computed(() => Math.max(1, Math.ceil(props.missingCriticalFields.length / rowsPerPage.value)))
const shownChecks = computed(() => props.checks.slice((checkPage.value - 1) * checksPerPage.value, checkPage.value * checksPerPage.value))
const shownDuplicates = computed(() => props.duplicateAssetCodes.slice((duplicatePage.value - 1) * rowsPerPage.value, duplicatePage.value * rowsPerPage.value))
const shownMissing = computed(() => props.missingCriticalFields.slice((missingPage.value - 1) * rowsPerPage.value, missingPage.value * rowsPerPage.value))
const scoreWidth = (value: unknown) => `${Math.max(0, Math.min(100, parseFloat(String(value)) || 0))}%`
const measure = () => {
  if (root.value) availableHeight.value = Math.floor(window.innerHeight - root.value.getBoundingClientRect().top - 12)
}
watch([checkPages, duplicatePages, missingPages], () => {
  checkPage.value = Math.min(checkPage.value, checkPages.value)
  duplicatePage.value = Math.min(duplicatePage.value, duplicatePages.value)
  missingPage.value = Math.min(missingPage.value, missingPages.value)
})
let observer: ResizeObserver | undefined
onMounted(() => {
  measure()
  window.addEventListener('resize', measure)
  observer = new ResizeObserver(measure)
  if (root.value?.parentElement) observer.observe(root.value.parentElement)
})
onUnmounted(() => { window.removeEventListener('resize', measure); observer?.disconnect() })
</script>

<template>
  <Head title="Asset Data Quality Index" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div ref="root" class="dq-page text-slate-100" :style="{ '--dq-height': `${availableHeight}px` }">
      <section class="dq-panel dq-heading">
        <div><p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-300">Data governance</p>
          <h1 class="text-xl font-black text-white">Asset Data Quality Index</h1></div>
        <p class="text-xs leading-5 text-slate-400">Asset completeness for analytics, depreciation, warranty monitoring, QR verification and audit review.</p>
      </section>
      <section class="dq-main">
        <div class="dq-panel dq-score border-emerald-500/30 bg-emerald-500/10">
          <p class="text-xs font-bold uppercase tracking-wider text-emerald-300">Overall score</p>
          <p class="my-2 text-5xl font-black text-emerald-200">{{ props.quality.overallScore }}%</p>
          <p class="text-sm font-semibold text-emerald-100">{{ props.quality.label }}</p>
          <p class="mt-3 text-xs text-slate-300">QR coverage: {{ props.quality.qrCoverage }}</p>
        </div>
        <div class="dq-panel dq-check-panel">
          <div class="dq-title"><h2>Completeness Checks</h2><span>{{ props.checks.length }} checks</span></div>
          <div class="dq-check-grid">
            <div v-for="item in shownChecks" :key="item.field" class="dq-check">
              <div class="flex items-center justify-between gap-2 text-xs"><span class="font-semibold">{{ item.field }}</span><strong class="text-emerald-200">{{ item.scoreLabel }}</strong></div>
              <div class="my-2 h-1.5 rounded-full bg-slate-800"><div class="h-1.5 rounded-full bg-emerald-400" :style="{ width: scoreWidth(item.scoreLabel) }"></div></div>
              <p class="text-[11px] text-slate-300">Complete: {{ item.complete }} · Missing: {{ item.missing }}</p>
              <p class="dq-reason text-[11px] leading-4 text-slate-400" :title="item.why">{{ item.why }}</p>
            </div>
          </div>
          <div v-if="checkPages > 1" class="dq-pager"><span>Checks {{ checkPage }} / {{ checkPages }}</span><div><button :disabled="checkPage === 1" @click="checkPage--">Previous</button><button :disabled="checkPage === checkPages" @click="checkPage++">Next</button></div></div>
        </div>
      </section>
      <section class="dq-tables">
        <div class="dq-panel dq-records">
          <div class="dq-title"><h2>Duplicate Asset Codes</h2><span>{{ props.duplicateAssetCodes.length }} groups</span></div>
          <p class="dq-description">Unique codes support physical verification and audit traceability.</p>
          <div v-if="!props.duplicateAssetCodes.length" class="dq-empty text-emerald-200 bg-emerald-500/10">No duplicate asset codes were detected.</div>
          <table v-else><thead><tr><th>Asset code</th><th class="text-right">Count</th></tr></thead><tbody><tr v-for="(row, index) in shownDuplicates" :key="`${row.asset_code}-${index}`"><td :title="row.asset_code">{{ row.asset_code }}</td><td class="text-right font-bold">{{ row.total }}</td></tr></tbody></table>
          <div v-if="props.duplicateAssetCodes.length" class="dq-pager"><span>Page {{ duplicatePage }} / {{ duplicatePages }}</span><div><button :disabled="duplicatePage === 1" @click="duplicatePage--">Previous</button><button :disabled="duplicatePage === duplicatePages" @click="duplicatePage++">Next</button></div></div>
        </div>
        <div class="dq-panel dq-records">
          <div class="dq-title"><h2>Records Missing Critical Fields</h2><span>{{ props.missingCriticalFields.length }} records</span></div>
          <p class="dq-description">Correct these records before final reporting or migration.</p>
          <div v-if="!props.missingCriticalFields.length" class="dq-empty text-emerald-200 bg-emerald-500/10">No records with missing critical fields were returned.</div>
          <table v-else><thead><tr><th>Asset code</th><th>Name</th><th>Missing</th></tr></thead><tbody><tr v-for="(row, index) in shownMissing" :key="`${row.asset_code}-${index}`"><td :title="row.asset_code" class="font-semibold">{{ row.asset_code || 'No code' }}</td><td :title="row.name">{{ row.name }}</td><td :title="String(row.missing)" class="text-amber-300">{{ row.missing }}</td></tr></tbody></table>
          <div v-if="props.missingCriticalFields.length" class="dq-pager"><span>Page {{ missingPage }} / {{ missingPages }}</span><div><button :disabled="missingPage === 1" @click="missingPage--">Previous</button><button :disabled="missingPage === missingPages" @click="missingPage++">Next</button></div></div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

<style scoped>
.dq-page { margin: 8px 12px 0; display: flex; flex-direction: column; gap: 10px; }
.dq-panel { border: 1px solid #1e293b; border-radius: 14px; background-color: #020617; padding: 12px; min-width: 0; }
.dq-heading { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
.dq-heading > p { max-width: 540px; }
.dq-main { display: grid; grid-template-columns: 230px minmax(0, 1fr); gap: 10px; }
.dq-score { display: flex; flex-direction: column; align-items: center; justify-content: center; background-color: rgb(16 185 129 / .1); border-color: rgb(16 185 129 / .3); }
.dq-check-panel, .dq-records { display: flex; flex-direction: column; gap: 8px; }
.dq-title { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.dq-title h2 { font-size: 14px; font-weight: 700; color: white; }
.dq-title > span { font-size: 11px; color: #94a3b8; white-space: nowrap; }
.dq-check-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; flex: 1; }
.dq-check { border: 1px solid #1e293b; border-radius: 9px; background: #0f172a; padding: 9px; }
.dq-reason { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: 3px; }
.dq-tables { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.dq-description { font-size: 11px; line-height: 16px; color: #94a3b8; }
.dq-empty { border-radius: 8px; padding: 12px; font-size: 12px; }
table { width: 100%; table-layout: fixed; font-size: 11px; }
th { color: #94a3b8; text-align: left; padding: 6px; font-weight: 600; }
td { padding: 7px 6px; border-top: 1px solid #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dq-pager { display: flex; justify-content: space-between; align-items: center; margin-top: auto; font-size: 11px; color: #94a3b8; }
.dq-pager button { margin-left: 5px; padding: 3px 9px; border: 1px solid #334155; border-radius: 6px; color: #e2e8f0; }
.dq-pager button:disabled { opacity: .35; cursor: not-allowed; }
@media (min-width: 1024px) and (min-height: 600px) {
  .dq-page { height: var(--dq-height); }
  .dq-main { flex: 1; min-height: 0; }
  .dq-tables { flex: 0 0 auto; }
}
@media (max-width: 1023px), (max-height: 599px) {
  .dq-heading { align-items: flex-start; flex-direction: column; gap: 4px; }
  .dq-main { grid-template-columns: 1fr; }
  .dq-score { padding: 12px; }
  .dq-score p { margin-top: 3px; margin-bottom: 3px; }
  .dq-tables { grid-template-columns: 1fr; }
}
@media (max-width: 600px) { .dq-check-grid { grid-template-columns: 1fr; } }
</style>
