<script setup lang="ts">
import { computed, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

interface Lookup { id:number; name?:string; locationname?:string|null }
interface AssetRow {
  id:number
  asset_code:string
  asset_name:string
  serial_number:string
  unit_name:string
  location:string
  supplier_name:string
  status:string
  purchase_date:string|null
  warranty_end_date:string|null
  purchase_cost:number
  book_value:number
  useful_life:number
}
interface Paginated<T> {
  data:T[]
  current_page:number
  last_page:number
  per_page:number
  total:number
  from:number
  to:number
}
interface Summary {
  total_assets:number
  active:number
  under_maintenance:number
  obsolete:number
  disposed:number
  acquisition_cost:number
  book_value:number
  maintenance_cost:number
  warranty_expiring:number
}

const props = defineProps<{
  summary:Summary
  assets:Paginated<AssetRow>
  units:Lookup[]
  years:number[]
  filters:Record<string,string|number|null|undefined>
  valuationDate:string
}>()

const breadcrumbs:BreadcrumbItem[] = [
  { title:'Management Reports', href:'/management-reports/annual-assets' },
  { title:'Annual Asset Report', href:'/management-reports/annual-assets' },
]

const filters = reactive({
  year:Number(props.filters.year ?? new Date().getFullYear()),
  unit_id:String(props.filters.unit_id ?? ''),
  status:String(props.filters.status ?? ''),
  search:String(props.filters.search ?? ''),
  per_page:Number(props.filters.per_page ?? 25),
})

const params=()=>({
  year:filters.year,
  unit_id:filters.unit_id||undefined,
  status:filters.status||undefined,
  search:filters.search||undefined,
  per_page:filters.per_page,
})

const apply=()=>router.get('/management-reports/annual-assets',params(),{
  preserveState:true,preserveScroll:true,replace:true,
})

const reset=()=>{
  filters.year=new Date().getFullYear()
  filters.unit_id=''
  filters.status=''
  filters.search=''
  filters.per_page=25
  apply()
}

const goPage=(page:number)=>{
  if(page<1||page>props.assets.last_page)return
  router.get('/management-reports/annual-assets',{...params(),page},{
    preserveState:true,preserveScroll:true,
  })
}

const pdfUrl=computed(()=>{
  const q=new URLSearchParams()
  Object.entries(params()).forEach(([k,v])=>{
    if(v!==undefined&&v!==null&&v!=='')q.set(k,String(v))
  })
  return `/management-reports/annual-assets/pdf?${q.toString()}`
})

const money=(v:number|string|null|undefined)=>
  new Intl.NumberFormat('en-GH',{style:'currency',currency:'GHS',minimumFractionDigits:2})
    .format(Number(v??0))

const date=(v:string|null|undefined)=>
  v?new Date(`${v}T00:00:00`).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}):'—'

const statusClass=(status:string)=>({
  active:'gh-badge gh-badge-success',
  under_maintenance:'gh-badge gh-badge-warning',
  obsolete:'gh-badge gh-badge-danger',
  disposed:'gh-badge gh-badge-muted',
}[status]??'gh-badge gh-badge-info')
</script>

<template>
  <Head title="Annual Full Asset Report" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="gh-page">
      <section class="gh-page-header">
        <div>
          <p class="gh-kicker">Management Reporting</p>
          <h1>Annual Full Asset Report</h1>
          <p>Consolidated asset-register, status, value and maintenance overview for the selected year.</p>
        </div>
        <a :href="pdfUrl" target="_blank" class="gh-btn gh-btn-primary">Print PDF</a>
      </section>

      <section class="gh-filter-bar">
        <input v-model="filters.search" class="gh-field min-w-[220px] flex-1" placeholder="Search asset code, serial, name, unit or location..." @keyup.enter="apply">

        <select v-model="filters.year" class="gh-field">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>

        <select v-model="filters.unit_id" class="gh-field">
          <option value="">All Units</option>
          <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>

        <select v-model="filters.status" class="gh-field">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="under_maintenance">Under Maintenance</option>
          <option value="obsolete">Obsolete</option>
          <option value="disposed">Disposed</option>
        </select>

        <button class="gh-btn gh-btn-primary" @click="apply">Search</button>
        <button class="gh-btn gh-btn-secondary" @click="reset">Reset</button>
      </section>

      <div class="gh-period-line">
        Reporting year: <strong>{{ filters.year }}</strong>
        <span class="gh-separator">•</span>
        Book-value date: <strong>{{ valuationDate }}</strong>
      </div>

      <section class="gh-summary-grid gh-summary-grid-4">
        <article class="gh-summary-card"><span>Total Assets</span><strong>{{ summary.total_assets.toLocaleString() }}</strong><small>Register records in scope</small></article>
        <article class="gh-summary-card"><span>Active</span><strong>{{ summary.active.toLocaleString() }}</strong><small>Current stored status</small></article>
        <article class="gh-summary-card"><span>Under Maintenance</span><strong>{{ summary.under_maintenance.toLocaleString() }}</strong><small>Current stored status</small></article>
        <article class="gh-summary-card"><span>Obsolete / Disposed</span><strong>{{ (summary.obsolete + summary.disposed).toLocaleString() }}</strong><small>{{ summary.obsolete }} obsolete · {{ summary.disposed }} disposed</small></article>
        <article class="gh-summary-card"><span>Acquisition Cost</span><strong>{{ money(summary.acquisition_cost) }}</strong><small>Historical purchase cost</small></article>
        <article class="gh-summary-card"><span>Estimated Book Value</span><strong>{{ money(summary.book_value) }}</strong><small>Straight-line, 10% residual</small></article>
        <article class="gh-summary-card"><span>Maintenance Cost</span><strong>{{ money(summary.maintenance_cost) }}</strong><small>Completed jobs in {{ filters.year }}</small></article>
        <article class="gh-summary-card"><span>Warranty Expiring</span><strong>{{ summary.warranty_expiring }}</strong><small>Warranty end date in {{ filters.year }}</small></article>
      </section>

      <section class="gh-note">
        <strong>Reporting note:</strong>
        asset status reflects the current GhanaDAMS record. Book value is calculated as at {{ valuationDate }}
        using the existing straight-line depreciation rule and 10% residual value.
      </section>

      <section class="gh-panel">
        <div class="gh-panel-heading">
          <div><h2>Full Asset Register</h2><p>{{ assets.total.toLocaleString() }} asset record(s) in the selected annual scope.</p></div>
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
                <th>Asset Code</th><th>Asset / Serial</th><th>Unit / Location</th><th>Status</th>
                <th>Purchase Date</th><th>Purchase Cost</th><th>Book Value</th><th>Warranty End</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="asset in assets.data" :key="asset.id">
                <td class="font-semibold">{{ asset.asset_code }}</td>
                <td><strong>{{ asset.asset_name }}</strong><small>{{ asset.serial_number }}</small></td>
                <td><strong>{{ asset.unit_name }}</strong><small>{{ asset.location }}</small></td>
                <td><span :class="statusClass(asset.status)">{{ asset.status.replaceAll('_',' ') }}</span></td>
                <td>{{ date(asset.purchase_date) }}</td>
                <td class="font-semibold">{{ money(asset.purchase_cost) }}</td>
                <td class="font-semibold text-emerald-300">{{ money(asset.book_value) }}</td>
                <td>{{ date(asset.warranty_end_date) }}</td>
              </tr>
              <tr v-if="!assets.data.length"><td colspan="8" class="gh-empty">No asset records found for the selected annual scope.</td></tr>
            </tbody>
          </table>
        </div>

        <div class="gh-pagination">
          <span>Showing {{ assets.from ?? 0 }}–{{ assets.to ?? 0 }} of {{ assets.total }}</span>
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

