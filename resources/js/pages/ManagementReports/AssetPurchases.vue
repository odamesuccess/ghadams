<script setup lang="ts">
import { computed, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

interface Lookup { id:number; name?:string; locationname?:string|null; suppliername?:string }
interface AssetName { id:number; assetname:string }
interface AssetRow {
  id:number
  asset_code:string
  purchase_cost:number|string|null
  purchase_date:string|null
  status:string
  asset_name?:AssetName|null
  unit?:Lookup|null
  supplier?:Lookup|null
}
interface Paginated<T> {
  data:T[]
  current_page:number
  last_page:number
  per_page:number
  total:number
  from?:number|null
  to?:number|null
}
interface Summary {
  total_assets:number
  total_cost:number
  average_cost:number
  units:number
  suppliers:number
}

const props = defineProps<{
  summary:Summary
  assets:Paginated<AssetRow>
  monthlyTrend:Array<{period:string;total_assets:number;total_cost:number|string}>
  units:Lookup[]
  suppliers:Lookup[]
  years:number[]
  filters:Record<string, string|number|null|undefined>
  periodLabel:string
}>()

const breadcrumbs:BreadcrumbItem[] = [
  { title:'Management Reports', href:'/management-reports/asset-purchases' },
  { title:'Asset Purchases', href:'/management-reports/asset-purchases' },
]

const filters = reactive({
  period:String(props.filters.period ?? 'monthly'),
  year:Number(props.filters.year ?? new Date().getFullYear()),
  month:Number(props.filters.month ?? (new Date().getMonth()+1)),
  quarter:Number(props.filters.quarter ?? Math.ceil((new Date().getMonth()+1)/3)),
  unit_id:String(props.filters.unit_id ?? ''),
  supplier_id:String(props.filters.supplier_id ?? ''),
  search:String(props.filters.search ?? ''),
  per_page:Number(props.filters.per_page ?? 25),
})

const months = [
  'January','February','March','April','May','June',
  'July','August','September','October','November','December',
]

const reportParams = () => ({
  period:filters.period,
  year:filters.year,
  month:filters.period === 'monthly' ? filters.month : undefined,
  quarter:filters.period === 'quarterly' ? filters.quarter : undefined,
  unit_id:filters.unit_id || undefined,
  supplier_id:filters.supplier_id || undefined,
  search:filters.search || undefined,
  per_page:filters.per_page,
})

const apply = () => router.get('/management-reports/asset-purchases', reportParams(), {
  preserveState:true,
  preserveScroll:true,
  replace:true,
})

const reset = () => {
  filters.period='monthly'
  filters.year=new Date().getFullYear()
  filters.month=new Date().getMonth()+1
  filters.quarter=Math.ceil((new Date().getMonth()+1)/3)
  filters.unit_id=''
  filters.supplier_id=''
  filters.search=''
  filters.per_page=25
  apply()
}

const goPage = (page:number) => {
  if (page < 1 || page > props.assets.last_page) return
  router.get('/management-reports/asset-purchases', {...reportParams(), page}, {
    preserveState:true,
    preserveScroll:true,
  })
}

const pdfUrl = computed(() => {
  const q = new URLSearchParams()
  Object.entries(reportParams()).forEach(([key,value]) => {
    if (value !== undefined && value !== null && value !== '') q.set(key,String(value))
  })
  return `/management-reports/asset-purchases/pdf?${q.toString()}`
})

const money=(v:number|string|null|undefined)=>
  new Intl.NumberFormat('en-GH',{style:'currency',currency:'GHS',minimumFractionDigits:2})
    .format(Number(v ?? 0))

const date=(v:string|null|undefined)=>
  v ? new Date(`${v}T00:00:00`).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : 'â€”'

const statusClass=(status:string)=>({
  active:'gh-badge gh-badge-success',
  under_maintenance:'gh-badge gh-badge-warning',
  obsolete:'gh-badge gh-badge-danger',
  disposed:'gh-badge gh-badge-muted',
}[status] ?? 'gh-badge gh-badge-info')

const maxTrend = computed(() =>
  Math.max(1,...props.monthlyTrend.map(r => Number(r.total_cost ?? 0)))
)
</script>

<template>
  <Head title="Asset Purchase Management Report" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="gh-page">
      <section class="gh-page-header">
        <div>
          <p class="gh-kicker">Management Reporting</p>
          <h1>New Assets Purchased</h1>
          <p>Monthly, quarterly and yearly acquisition reporting for management review.</p>
        </div>
        <a :href="pdfUrl" target="_blank" class="gh-btn gh-btn-primary">Print PDF</a>
      </section>

      <section class="gh-filter-bar">
        <input v-model="filters.search" class="gh-field min-w-[220px] flex-1" placeholder="Search asset, unit or supplier..." @keyup.enter="apply">

        <select v-model="filters.period" class="gh-field">
          <option value="monthly">Monthly</option>
          <option value="quarterly">Quarterly</option>
          <option value="yearly">Yearly</option>
        </select>

        <select v-model="filters.year" class="gh-field">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>

        <select v-if="filters.period==='monthly'" v-model="filters.month" class="gh-field">
          <option v-for="(m,i) in months" :key="m" :value="i+1">{{ m }}</option>
        </select>

        <select v-if="filters.period==='quarterly'" v-model="filters.quarter" class="gh-field">
          <option :value="1">Q1 (Janâ€“Mar)</option>
          <option :value="2">Q2 (Aprâ€“Jun)</option>
          <option :value="3">Q3 (Julâ€“Sep)</option>
          <option :value="4">Q4 (Octâ€“Dec)</option>
        </select>

        <select v-model="filters.unit_id" class="gh-field">
          <option value="">All Units</option>
          <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>

        <select v-model="filters.supplier_id" class="gh-field">
          <option value="">All Suppliers</option>
          <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.suppliername }}</option>
        </select>

        <button class="gh-btn gh-btn-primary" @click="apply">Search</button>
        <button class="gh-btn gh-btn-secondary" @click="reset">Reset</button>
      </section>

      <div class="gh-period-line">
        Reporting period: <strong>{{ periodLabel }}</strong>
      </div>

      <section class="gh-summary-grid gh-summary-grid-5">
        <article class="gh-summary-card"><span>New Assets</span><strong>{{ summary.total_assets.toLocaleString() }}</strong><small>Purchased in period</small></article>
        <article class="gh-summary-card"><span>Total Purchase Cost</span><strong>{{ money(summary.total_cost) }}</strong><small>Acquisition value</small></article>
        <article class="gh-summary-card"><span>Average Cost</span><strong>{{ money(summary.average_cost) }}</strong><small>Per asset</small></article>
        <article class="gh-summary-card"><span>Units</span><strong>{{ summary.units }}</strong><small>Receiving units</small></article>
        <article class="gh-summary-card"><span>Suppliers</span><strong>{{ summary.suppliers }}</strong><small>Suppliers represented</small></article>
      </section>

      <section v-if="monthlyTrend.length" class="gh-panel">
        <div class="gh-panel-heading">
          <div><h2>Acquisition Trend</h2><p>Monthly value within the selected reporting period.</p></div>
        </div>
        <div class="space-y-2">
          <div v-for="row in monthlyTrend" :key="row.period" class="gh-trend-row">
            <div class="gh-trend-label">{{ row.period }} <span>{{ row.total_assets }} asset(s)</span></div>
            <div class="gh-trend-track"><div class="gh-trend-fill" :style="{width:`${Math.max(2,(Number(row.total_cost)/maxTrend)*100)}%`}"></div></div>
            <strong>{{ money(row.total_cost) }}</strong>
          </div>
        </div>
      </section>

      <section class="gh-panel">
        <div class="gh-panel-heading">
          <div><h2>Purchased Asset Register</h2><p>{{ assets.total.toLocaleString() }} record(s) for {{ periodLabel }}.</p></div>
          <select v-model="filters.per_page" class="gh-field" @change="apply">
            <option :value="10">10 rows</option>
            <option :value="25">25 rows</option>
            <option :value="50">50 rows</option>
            <option :value="100">100 rows</option>
          </select>
        </div>

        <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="gh-table-wrap"><table class="gh-table">
            <thead>
              <tr>
                <th>Asset Code</th><th>Asset</th><th>Unit / Location</th><th>Supplier</th>
                <th>Purchase Date</th><th>Purchase Cost</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="asset in assets.data" :key="asset.id">
                <td class="font-semibold">{{ asset.asset_code }}</td>
                <td>{{ asset.asset_name?.assetname ?? 'â€”' }}</td>
                <td><strong>{{ asset.unit?.name ?? 'â€”' }}</strong><small>{{ asset.unit?.locationname ?? '' }}</small></td>
                <td>{{ asset.supplier?.suppliername ?? 'â€”' }}</td>
                <td>{{ date(asset.purchase_date) }}</td>
                <td class="font-semibold">{{ money(asset.purchase_cost) }}</td>
                <td><span :class="statusClass(asset.status)">{{ asset.status.replaceAll('_',' ') }}</span></td>
              </tr>
              <tr v-if="!assets.data.length"><td colspan="7" class="gh-empty">No purchased assets found for this period.</td></tr>
            </tbody>
          </table>
        </div>

        <div class="gh-pagination">
          <span>Showing {{ assets.from ?? 0 }}â€“{{ assets.to ?? 0 }} of {{ assets.total }}</span>
          <div>
            <button class="gh-btn gh-btn-secondary" :disabled="assets.current_page<=1" @click="goPage(assets.current_page-1)">Previous</button>
            <span class="gh-page-number">{{ assets.current_page }} / {{ assets.last_page }}</span>
            <button class="gh-btn gh-btn-secondary" :disabled="assets.current_page>=assets.last_page" @click="goPage(assets.current_page+1)">Next</button>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

