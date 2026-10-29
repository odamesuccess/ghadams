<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
interface Schedule {id:number;report_type:string;frequency:string;role_ids:number[];warning_days:number;enabled:boolean;next_run_at:string|null;recipients:{id:number;name:string;email:string}[]}
const props=defineProps<{schedules:Schedule[];roles:{id:number;name:string}[];types:Record<string,string>;frequencies:Record<string,string>;deliveries:any[]}>()
const page=usePage()
const form=useForm({report_type:'maintenance',frequency:'monthly',role_ids:[] as number[],warning_days:90,enabled:false})
const selected=computed(()=>props.schedules.find(s=>s.report_type===form.report_type&&s.frequency===form.frequency))
function load(){const s=selected.value;form.role_ids=s?[...s.role_ids]:[];form.warning_days=s?.warning_days??90;form.enabled=s?.enabled??false;form.clearErrors()}
function send(s:Schedule){if(window.confirm(`Send ${props.types[s.report_type]} to ${s.recipients.length} eligible recipients? This uses the previous completed period. Previously attempted recipients will be skipped.`))router.post(`/scheduled-reports/${s.id}/send`,{}, {preserveScroll:true})}

const testForm=useForm({role_ids:[] as number[],warning_days:90})
function previewTest(type:string,frequency:string){return '/scheduled-reports/preview?'+new URLSearchParams({report_type:type,frequency,warning_days:String(form.warning_days)}).toString()}
const testRecipients=ref<{id:number;name:string;email:string}[]>([])
const recipientsLoading=ref(false)
const recipientError=ref('')
let recipientRequest:AbortController|undefined
watch(()=>[...form.role_ids],async(ids,_,onCleanup)=>{
 recipientRequest?.abort();const request=new AbortController();recipientRequest=request;onCleanup(()=>request.abort())
 testRecipients.value=[];recipientError.value=''
 if(!ids.length){recipientsLoading.value=false;return}
 recipientsLoading.value=true
 const query=new URLSearchParams();ids.forEach(id=>query.append('role_ids[]',String(id)))
 try{const response=await fetch('/scheduled-reports/recipients?'+query,{headers:{Accept:'application/json'},credentials:'same-origin',signal:request.signal});if(!response.ok)throw new Error('Could not load recipients. Check your selected roles or reload the page.');const data=await response.json();if(!request.signal.aborted)testRecipients.value=data.recipients}
 catch(error){if(!request.signal.aborted)recipientError.value=error instanceof Error?error.message:'Could not load recipients.'}
 finally{if(!request.signal.aborted)recipientsLoading.value=false}
},{immediate:true})
function testReport(){if(!testRecipients.value.length||recipientsLoading.value)return;if(!window.confirm(`Send ALL NINE test reports to ${testRecipients.value.length} eligible recipients? Each receives one email containing nine PDF attachments.`))return;Object.assign(testForm,{role_ids:[...form.role_ids],warning_days:form.warning_days});testForm.post('/scheduled-reports/test',{preserveScroll:true})}
load()
</script>
<template><Head title="Scheduled Reports"/><AppLayout :breadcrumbs="[{title:'Scheduled Reports',href:'/scheduled-reports'}]">
<div class="m-4 space-y-4 text-sm">
<header class="flex flex-wrap items-baseline justify-between gap-2"><h1 class="text-xl font-semibold">Scheduled Reports</h1><p class="text-muted-foreground">Monthly, half-year and annual summaries delivered to selected roles.</p></header>
<p v-if="(page.props.flash as any)?.success" class="rounded border p-3">{{ (page.props.flash as any).success }}</p>
<p v-for="(error,key) in form.errors" :key="key" class="text-red-500">{{ error }}</p><p v-if="(page.props.errors as any)?.delivery" class="text-red-500">{{ (page.props.errors as any).delivery }}</p>
<form class="space-y-3 rounded-lg border p-4" @submit.prevent="form.post('/scheduled-reports',{preserveScroll:true})">
<div class="flex flex-wrap items-center gap-x-6 gap-y-3">
<fieldset class="flex flex-wrap items-center gap-3"><legend class="sr-only">Schedule report</legend><span class="text-xs font-semibold text-muted-foreground">Report:</span><label v-for="(label,key) in types" :key="key" class="inline-flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm"><input v-model="form.report_type" type="radio" name="schedule-report" :value="key" @change="load" class="h-4 w-4 accent-blue-600"/>{{ label }}</label></fieldset>
<fieldset class="flex flex-wrap items-center gap-3"><legend class="sr-only">Schedule frequency</legend><span class="text-xs font-semibold text-muted-foreground">Frequency:</span><label v-for="(label,key) in frequencies" :key="key" class="inline-flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm"><input v-model="form.frequency" type="radio" name="schedule-frequency" :value="key" @change="load" class="h-4 w-4 accent-blue-600"/>{{ label }}</label></fieldset>
<label class="inline-flex items-center gap-2 whitespace-nowrap"><span class="text-xs font-semibold text-muted-foreground">Warranty:</span><select v-model.number="form.warning_days" class="rounded border bg-background px-2 py-1 text-sm"><option v-for="days in [30,60,90]" :key="days" :value="days">{{ days }} days</option></select></label>
<label class="inline-flex cursor-pointer items-center gap-2 whitespace-nowrap"><input v-model="form.enabled" type="checkbox" class="h-4 w-4 accent-blue-600"/>Automatic delivery</label>
</div>
<fieldset class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t pt-3"><legend class="sr-only">Recipient roles</legend><span class="text-xs font-semibold text-muted-foreground">Recipient roles:</span><label v-for="role in roles" :key="role.id" class="inline-flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm"><input v-model="form.role_ids" type="checkbox" :value="role.id" class="h-4 w-4 accent-blue-600"/>{{ role.name }}</label></fieldset>

<div class="flex flex-wrap items-center gap-3"><button :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 font-semibold text-white">Save settings</button><a v-if="selected" :href="`/scheduled-reports/${selected.id}/pdf`" target="_blank" rel="noopener" class="rounded border px-3 py-2">Preview / Download PDF</a></div>
<div v-if="selected" class="text-xs"><strong>Saved recipient preview ({{ selected.recipients.length }}):</strong> {{ selected.recipients.map(u=>`${u.name} <${u.email}>`).join('; ') || 'No eligible recipients' }}<p>Save changes to refresh this preview.</p></div>
</form>
<section class="space-y-3 rounded-lg border p-4"><div class="flex flex-wrap items-baseline justify-between gap-2"><h2 class="font-semibold">All reports — one test</h2><p class="text-xs text-muted-foreground">Sends all three report types for all three periods to the selected roles. Dates are automatic.</p></div>
<div class="grid gap-3 md:grid-cols-3"><div v-for="(label,frequency) in frequencies" :key="frequency" class="rounded border p-3"><h3 class="font-semibold">{{ label }}</h3><p class="my-2 text-xs text-muted-foreground">{{ frequency==='monthly'?'Current month through today':frequency==='half_year'?'January 1–June 30, or today if earlier':'January 1 through today' }}</p><a v-for="(title,type) in types" :key="type" :href="previewTest(String(type),String(frequency))" target="_blank" rel="noopener" class="mb-1 block text-xs text-blue-500 underline">Preview {{ title }}</a></div></div>
<div class="rounded border p-3"><h3 class="font-semibold">Automatic test recipients ({{ testRecipients.length }})</h3><p v-if="recipientsLoading" class="mt-2 text-muted-foreground">Loading users from selected roles…</p><p v-else-if="recipientError" class="mt-2 text-red-500">{{ recipientError }}</p><p v-else-if="!form.role_ids.length" class="mt-2 text-muted-foreground">Select recipient roles above to populate this list automatically.</p><p v-else-if="!testRecipients.length" class="mt-2 text-muted-foreground">No eligible users with valid email addresses in the selected roles.</p><div v-else class="mt-2 grid max-h-40 gap-2 overflow-auto sm:grid-cols-2 lg:grid-cols-3"><div v-for="user in testRecipients" :key="user.id" class="rounded bg-muted/40 p-2"><p class="font-medium">{{ user.name }}</p><p class="break-all text-xs text-muted-foreground">{{ user.email }}</p></div></div></div>
<form class="flex flex-wrap items-center justify-between gap-3" @submit.prevent="testReport"><p class="text-xs text-muted-foreground">Recipients update automatically when roles change. Save is not required for a test.</p><button :disabled="testForm.processing||recipientsLoading||!testRecipients.length||!!recipientError" class="rounded bg-emerald-600 px-4 py-2 font-semibold text-white disabled:opacity-50">{{ testForm.processing?'Sending tests…':'Send Test Report' }}</button></form><p v-for="(error,key) in testForm.errors" :key="key" class="text-red-500">{{ error }}</p>
</section>

<div class="overflow-x-auto rounded border"><table class="w-full text-left"><thead><tr class="border-b"><th class="p-2">Report</th><th>Frequency</th><th>Status</th><th>Next due date</th><th>Recipients</th><th>Actions</th></tr></thead><tbody><tr v-for="s in schedules" :key="s.id" class="border-b"><td class="p-2">{{ types[s.report_type] }}</td><td>{{ frequencies[s.frequency] }}</td><td>{{ s.enabled?'Enabled':'Paused' }}</td><td>{{ s.next_run_at?.slice(0,10)??'Not set' }}</td><td>{{ s.recipients.length }}</td><td><div class="flex gap-3 whitespace-nowrap"><a :href="`/scheduled-reports/${s.id}/pdf`" target="_blank" rel="noopener" class="text-blue-500">PDF</a><button :disabled="!s.recipients.length" @click="send(s)" class="text-blue-500 disabled:opacity-40">Send now</button></div></td></tr><tr v-if="!schedules.length"><td colspan="6" class="p-4 text-center">Create a report schedule above. All three report types support each frequency.</td></tr></tbody></table></div>
<h2 class="font-semibold">Latest 100 delivery attempts</h2>
<div class="max-h-64 overflow-auto rounded border"><table class="w-full text-left"><thead><tr><th class="p-2">Recipient</th><th>Schedule</th><th>Period</th><th>Status</th><th>Sent at</th></tr></thead><tbody><tr v-for="d in deliveries" :key="d.id" class="border-t"><td class="p-2">{{ d.email }}</td><td>{{ d.schedule_id }}</td><td>{{ d.period }}</td><td>{{ d.status }}</td><td>{{ d.sent_at??'—' }}</td></tr></tbody></table></div>
</div></AppLayout></template>
