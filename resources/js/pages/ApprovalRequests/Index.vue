<script setup lang="ts">
import { ref } from 'vue'
import { can } from '@/utils/permissions'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { confirmDelete } from '@/utils/sweetalert'

const props = defineProps<{ filters:any; approvalRequests:any; assets:any[] }>()
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }, { title: 'Approval Requests', href: '#' }]

const form = useForm({ asset_id: '', type: 'asset_transfer', title: '', reason: '' })
const filters = useForm({ search: props.filters?.search ?? '', type: props.filters?.type ?? '', status: props.filters?.status ?? '' })
const editingId = ref<number | null>(null)
const startEdit = (record: any) => {
  editingId.value = record.id
  form.clearErrors()
  form.asset_id = record.asset_id == null ? '' : String(record.asset_id)
  form.type = record.type
  form.title = record.title ?? ''
  form.reason = record.reason ?? ''
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
const cancelEdit = () => { editingId.value = null; form.reset(); form.clearErrors() }
const submit = () => {
  const options = { preserveScroll: true, onSuccess: () => cancelEdit() }
  if (editingId.value !== null) form.patch(`/approval-requests/${editingId.value}`, options)
  else form.post('/approval-requests', options)
}
const applyFilters = () => router.get('/approval-requests', filters.data(), { preserveState: true, replace: true })
const postTo = (url: string) => router.post(url, {}, { preserveScroll: true })
const destroyRecord = async (url: string) => {
  const confirmed = await confirmDelete({
    title: 'Delete Approval Request',
    text: 'Are you sure you want to delete this approval request?',
  })
  if (confirmed) router.delete(url, { preserveScroll: true })
}
const fmt = (v?: string) => v ? new Date(v).toLocaleString('en-GB') : 'Not recorded'
</script>

<template>
  <Head title="Approval Requests" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-4 my-4 space-y-4 rounded-2xl border border-slate-800 bg-slate-950/90 p-4 text-slate-100 shadow-xl sm:mx-6 sm:p-6">
      <div><h1 class="text-xl font-bold text-white">Approval Workflow</h1><p class="text-sm text-slate-400">Transfer, disposal, assignment and maintenance approvals.</p></div>
      <p v-if="editingId !== null" class="text-sm text-blue-300">Editing pending request #{{ editingId }}</p><div v-if="Object.keys(form.errors).length" role="alert" class="text-sm text-red-300"><p v-for="(message, field) in form.errors" :key="field">{{ message }}</p></div><form @submit.prevent="submit" class="grid gap-2 rounded-xl border border-slate-800 bg-slate-900/50 p-4 md:grid-cols-5">
        <select v-model="form.asset_id" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm"><option value="">No Asset</option><option v-for="a in assets" :key="a.id" :value="a.id">{{ a.asset_code }} - {{ a.asset_name?.assetname }}</option></select>
        <select v-model="form.type" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm"><option value="asset_transfer">Asset Transfer</option><option value="asset_disposal">Asset Disposal</option><option value="maintenance_request">Maintenance Request</option><option value="asset_assignment">Asset Assignment</option></select>
        <input v-model="form.title" required class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm" placeholder="Approval title" />
        <input v-model="form.reason" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm" placeholder="Reason / comment" />
        <div class="flex items-center gap-2"><button :disabled="form.processing" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ editingId !== null ? 'Save Changes' : 'Submit Request' }}</button><button v-if="editingId !== null" type="button" @click="cancelEdit" class="text-sm text-slate-300">Cancel</button></div>
      </form>
      <div class="grid gap-2 md:grid-cols-4"><input v-model="filters.search" @keyup.enter="applyFilters" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm" placeholder="Search approvals..." /><select v-model="filters.type" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm"><option value="">All Types</option><option value="asset_transfer">Transfer</option><option value="asset_disposal">Disposal</option><option value="maintenance_request">Maintenance</option><option value="asset_assignment">Assignment</option></select><select v-model="filters.status" class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm"><option value="">All Statuses</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select><button @click="applyFilters" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white">Search</button></div>
      <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="overflow-x-auto rounded-xl border border-slate-800"><table class="min-w-full divide-y divide-slate-800 text-sm"><thead class="bg-slate-900 text-xs uppercase text-slate-400"><tr><th class="px-3 py-2 text-left">Date</th><th class="px-3 py-2 text-left">Title</th><th class="px-3 py-2 text-left">Asset</th><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Requested By</th><th class="px-3 py-2 text-right">Action</th></tr></thead><tbody class="divide-y divide-slate-800"><tr v-for="r in approvalRequests.data" :key="r.id"><td class="px-3 py-2">{{ fmt(r.created_at) }}</td><td class="px-3 py-2">{{ r.title }}</td><td class="px-3 py-2">{{ r.asset?.asset_code ?? 'Not recorded' }}</td><td class="px-3 py-2">{{ r.type }}</td><td class="px-3 py-2"><span class="rounded-full bg-blue-500/10 px-2 py-1 text-xs text-blue-300">{{ r.status }}</span></td><td class="px-3 py-2">{{ r.requester?.name ?? 'Not recorded' }}</td><td class="space-x-2 px-3 py-2 text-right"><button v-if="r.status === 'pending' && can('approval-requests.create')" @click="startEdit(r)" class="text-blue-300 hover:underline">Edit</button><button v-if="r.status === 'pending'" @click="postTo(`/approval-requests/${r.id}/approve`)" class="text-emerald-300 hover:underline">Approve</button><button v-if="r.status === 'pending'" @click="postTo(`/approval-requests/${r.id}/reject`)" class="text-orange-300 hover:underline">Reject</button><button @click="destroyRecord(`/approval-requests/${r.id}`)" class="text-red-300 hover:underline">Delete</button></td></tr><tr v-if="!approvalRequests.data?.length"><td colspan="7" class="px-3 py-8 text-center text-slate-400">No approval requests found.</td></tr></tbody></table></div>
    </div>
  </AppLayout>
</template>

