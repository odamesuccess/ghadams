<script setup lang="ts">
import { computed, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

interface Lookup { id:number; name?:string; locationname?:string|null; providername?:string }
interface Job {
  id:number
  asset_code?:string|null
  asset_name?:string|null
  unit_name?:string|null
  locationname?:string|null
  provider_name?:string|null
  status?:string|null
  priority?:string|null
  fault_description?:string|null
  scheduled_date?:string|null
  actual_completion_date?:string|null
  payment_status?:string|null
  final_cost?:number|string|null
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
  total_jobs:number
  completed_jobs:number
  open_jobs:number
  completed_cost:number
  paid_amount:number
  outstanding_amount:number
}

const props = defineProps<{
  summary:Summary
  jobs:Paginated<Job>
  monthlyTrend:Array<{period:string;total_jobs:number;completed_jobs:number;completed_cost:number|string}>
  units:Lookup[]
  providers:Lookup[]
  years:number[]
  filters:Record<string,string|number|null|undefined>
  periodLabel:string
}>()

const breadcrumbs:BreadcrumbItem[] = [
  { title:'Management Reports', href:'/management-reports/maintenance' },
  { title:'Maintenance', href:'/management-reports/maintenance' },
]

const filters = reactive({
  period:String(props.filters.period ?? 'monthly'),
  year:Number(props.filters.year ?? new Date().getFullYear()),
  month:Number(props.filters.month ?? (new Date().getMonth()+1)),
  quarter:Number(props.filters.quarter ?? Math.ceil((new Date().getMonth()+1)/3)),
  unit_id:String(props.filters.unit_id ?? ''),
  provider_id:String(props.filters.provider_id ?? ''),
  status:String(props.filters.status ?? ''),
  priority:String(props.filters.priority ?? ''),
  search:String(props.filters.search ?? ''),
  per_page:Number(props.filters.per_page ?? 25),
})

const months = [
  'January','February','March','April','May','June',
  'July','August','September','October','November','December',
]

const reportParams=()=>({
  period:filters.period,
  year:filters.year,
  month:filters.period==='monthly'?filters.month:undefined,
  quarter:filters.period==='quarterly'?filters.quarter:undefined,
  unit_id:filters.unit_id||undefined,
  provider_id:filters.provider_id||undefined,
  status:filters.status||undefined,
  priority:filters.priority||undefined,
  search:filters.search||undefined,
  per_page:filters.per_page,
})

const apply=()=>router.get('/management-reports/maintenance',reportParams(),{
  preserveState:true,preserveScroll:true,replace:true,
})

const reset=()=>{
  filters.period='monthly'
  filters.year=new Date().getFullYear()
  filters.month=new Date().getMonth()+1
  filters.quarter=Math.ceil((new Date().getMonth()+1)/3)
  filters.unit_id=''
  filters.provider_id=''
  filters.status=''
  filters.priority=''
  filters.search=''
  filters.per_page=25
  apply()
}

const goPage=(page:number)=>{
  if(page<1||page>props.jobs.last_page)return
  router.get('/management-reports/maintenance',{...reportParams(),page},{
    preserveState:true,preserveScroll:true,
  })
}

const pdfUrl=computed(()=>{
  const q=new URLSearchParams()
  Object.entries(reportParams()).forEach(([k,v])=>{
    if(v!==undefined&&v!==null&&v!=='')q.set(k,String(v))
  })
  return `/management-reports/maintenance/pdf?${q.toString()}`
})

const money=(v:number|string|null|undefined)=>
  new Intl.NumberFormat('en-GH',{style:'currency',currency:'GHS',minimumFractionDigits:2})
    .format(Number(v??0))

const date=(v:string|null|undefined)=>
  v?new Date(`${v}T00:00:00`).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}):'â€”'

const statusClass=(v?:string|null)=>{
  const s=(v??'').toLowerCase()
  if(s==='completed')return 'gh-badge gh-badge-success'
  if(s==='in_progress')return 'gh-badge gh-badge-info'
  if(s==='approved')return 'gh-badge gh-badge-blue'
  if(s==='pending')return 'gh-badge gh-badge-warning'
  return 'gh-badge gh-badge-muted'
}

const priorityClass=(v?:string|null)=>{
  const s=(v??'').toLowerCase()
  if(s==='urgent')return 'gh-badge gh-badge-danger'
  if(s==='high')return 'gh-badge gh-badge-warning'
  if(s==='normal')return 'gh-badge gh-badge-blue'
  return 'gh-badge gh-badge-muted'
}

const maxTrend=computed(()=>Math.max(1,...props.monthlyTrend.map(r=>Number(r.completed_cost??0))))
</script>

<template>
  <Head title="Maintenance Management Report" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="gh-page">
      <section class="gh-page-header">
        <div>
          <p class="gh-kicker">Management Reporting</p>
          <h1>Maintenance Report</h1>
          <p>Monthly, quarterly and yearly maintenance performance and expenditure.</p>
        </div>
        <a :href="pdfUrl" target="_blank" class="gh-btn gh-btn-primary">Print PDF</a>
      </section>

      <section class="gh-filter-bar">
        <input v-model="filters.search" class="gh-field min-w-[220px] flex-1" placeholder="Search asset, fault, unit or provider..." @keyup.enter="apply">

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

        <select v-model="filters.provider_id" class="gh-field">
          <option value="">All Providers</option>
          <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.providername }}</option>
        </select>

        <select v-model="filters.status" class="gh-field">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
        </select>

        <select v-model="filters.priority" class="gh-field">
          <option value="">All Priorities</option>
          <option value="low">Low</option>
          <option value="normal">Normal</option>
          <option value="high">High</option>
          <option value="urgent">Urgent</option>
        </select>

        <button class="gh-btn gh-btn-primary" @click="apply">Search</button>
        <button class="gh-btn gh-btn-secondary" @click="reset">Reset</button>
      </section>

      <div class="gh-period-line">Reporting period: <strong>{{ periodLabel }}</strong></div>

      <section class="gh-summary-grid gh-summary-grid-6">
        <article class="gh-summary-card"><span>Maintenance Jobs</span><strong>{{ summary.total_jobs }}</strong><small>Total jobs in period</small></article>
        <article class="gh-summary-card"><span>Completed</span><strong>{{ summary.completed_jobs }}</strong><small>Finished jobs</small></article>
        <article class="gh-summary-card"><span>Open</span><strong>{{ summary.open_jobs }}</strong><small>Not completed</small></article>
        <article class="gh-summary-card"><span>Completed Cost</span><strong>{{ money(summary.completed_cost) }}</strong><small>Final completed cost</small></article>
        <article class="gh-summary-card"><span>Paid Amount</span><strong>{{ money(summary.paid_amount) }}</strong><small>Paid maintenance</small></article>
        <article class="gh-summary-card"><span>Outstanding</span><strong>{{ money(summary.outstanding_amount) }}</strong><small>Completed but not paid</small></article>
      </section>

      <section v-if="monthlyTrend.length" class="gh-panel">
        <div class="gh-panel-heading">
          <div><h2>Maintenance Cost Trend</h2><p>Completed maintenance cost by month.</p></div>
        </div>
        <div class="space-y-2">
          <div v-for="row in monthlyTrend" :key="row.period" class="gh-trend-row">
            <div class="gh-trend-label">{{ row.period }} <span>{{ row.completed_jobs }}/{{ row.total_jobs }} completed</span></div>
            <div class="gh-trend-track"><div class="gh-trend-fill gh-trend-fill-green" :style="{width:`${Math.max(2,(Number(row.completed_cost)/maxTrend)*100)}%`}"></div></div>
            <strong>{{ money(row.completed_cost) }}</strong>
          </div>
        </div>
      </section>

      <section class="gh-panel">
        <div class="gh-panel-heading">
          <div><h2>Maintenance Jobs</h2><p>{{ jobs.total }} record(s) for {{ periodLabel }}.</p></div>
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
                <th>Asset</th><th>Unit / Location</th><th>Provider</th><th>Fault</th>
                <th>Priority</th><th>Status</th><th>Date</th><th>Payment</th><th>Cost</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="job in jobs.data" :key="job.id">
                <td><strong>{{ job.asset_code ?? 'â€”' }}</strong><small>{{ job.asset_name ?? '' }}</small></td>
                <td><strong>{{ job.unit_name ?? 'â€”' }}</strong><small>{{ job.locationname ?? '' }}</small></td>
                <td>{{ job.provider_name ?? 'â€”' }}</td>
                <td>{{ job.fault_description ?? 'â€”' }}</td>
                <td><span :class="priorityClass(job.priority)">{{ job.priority ?? 'â€”' }}</span></td>
                <td><span :class="statusClass(job.status)">{{ (job.status ?? 'â€”').replaceAll('_',' ') }}</span></td>
                <td>{{ date(job.actual_completion_date ?? job.scheduled_date) }}</td>
                <td><span class="gh-badge" :class="job.payment_status==='paid'?'gh-badge-success':'gh-badge-warning'">{{ job.payment_status ?? 'Not Ready' }}</span></td>
                <td class="font-semibold">{{ money(job.final_cost) }}</td>
              </tr>
              <tr v-if="!jobs.data.length"><td colspan="9" class="gh-empty">No maintenance jobs found for this period.</td></tr>
            </tbody>
          </table>
        </div>

        <div class="gh-pagination">
          <span>Showing {{ jobs.from ?? 0 }}â€“{{ jobs.to ?? 0 }} of {{ jobs.total }}</span>
          <div>
            <button class="gh-btn gh-btn-secondary" :disabled="jobs.current_page<=1" @click="goPage(jobs.current_page-1)">Previous</button>
            <span class="gh-page-number">{{ jobs.current_page }} / {{ jobs.last_page }}</span>
            <button class="gh-btn gh-btn-secondary" :disabled="jobs.current_page>=jobs.last_page" @click="goPage(jobs.current_page+1)">Next</button>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

