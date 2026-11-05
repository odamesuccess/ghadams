<script setup lang="ts">
/* ======================= Imports ======================= */
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import FormCard from '@/components/ui/FormCard.vue'
import type { BreadcrumbItem } from '@/types'
import { index, update } from '@/routes/assets'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import DarkSelect from '@/components/ui/DarkSelect.vue'
import AiResearchPanel from '@/components/assets/AiResearchPanel.vue'
import type { CurrentValues } from '@/composables/useAssetResearch'
import { can } from '@/utils/permissions'
import { toast } from '@/utils/sweetalert'

/* ======================= Types ======================= */
interface Asset {
  id: number
  asset_code: string
  assetname_id: number
  brand: string
  usefullife: number
  unit_id: number
  serial_number?: string
  model_number?: string | null
  part_number?: string | null
  description?: string
  purchase_cost?: number
  purchase_date?: string
  warranty_duration?: number
  warranty_end_date?: string
  source_id?: number
  supplier_id?: number
  maintenance_id?: number
  status: 'active' | 'under_maintenance' | 'obsolete' | 'disposed'
  asset_image?: string | null
}

/* ======================= Props ======================= */
const props = defineProps<{
  asset: Asset
  assetNames: { id: number; assetname: string; usefullife: number }[]
  brands: string[]
  suppliers: { id: number; suppliername: string }[]
  maintenanceProviders: { id: number; providername: string }[]
  sources: { id: number; name: string }[]
  units: { id: number; name: string; locationname: string }[]
}>()

/* ======================= Breadcrumbs ======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Assets', href: index().url },
  { title: 'Edit Asset', href: '#' },
]

/* ======================= Image Preview ======================= */
const preview = ref<string | null>(
  props.asset.asset_image ? `/storage/${props.asset.asset_image}` : null
)

const onImageChange = (e: Event) => {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  form.asset_image = file
  preview.value = URL.createObjectURL(file)
}
const toDateInput = (value?: string | null) => {
  if (!value) return ''
  return value.split('T')[0]   // converts ISO → YYYY-MM-DD
}

/* ======================= Form ======================= */
const form = useForm({
  assetname_id: props.asset.assetname_id,
  brand: props.asset.brand,
  usefullife: props.asset.usefullife,
  unit_id: props.asset.unit_id,
  serial_number: props.asset.serial_number ?? '',
  model_number: props.asset.model_number ?? '',
  part_number: props.asset.part_number ?? '',
  description: props.asset.description ?? '',
  purchase_cost: props.asset.purchase_cost ?? '',
  purchase_date: toDateInput(props.asset.purchase_date),
  warranty_duration: props.asset.warranty_duration ?? '',
  warranty_end_date: toDateInput(props.asset.warranty_end_date),
  source_id: props.asset.source_id ?? '',
  supplier_id: props.asset.supplier_id ?? '',
  maintenance_id: props.asset.maintenance_id ?? '',
  status: props.asset.status,
  asset_image: null as File | null,
})


/* ======================= Useful Life Auto ======================= */
const selectedAsset = computed(() =>
  props.assetNames.find(a => a.id === Number(form.assetname_id))
)

watch(() => form.assetname_id, () => {
  if (selectedAsset.value) {
    form.usefullife = selectedAsset.value.usefullife
  }
})

/* ======================= Warranty Auto ======================= */
watch(
  [() => form.purchase_date, () => form.warranty_duration],
  ([date, duration]) => {
    if (!date || !duration) {
      form.warranty_end_date = ''
      return
    }

    const d = new Date(date)
    d.setMonth(d.getMonth() + Number(duration))
    form.warranty_end_date = d.toISOString().split('T')[0]
  }
)

/* ======================= Selected Unit Location ======================= */
const selectedUnit = computed(() =>
  props.units.find(u => u.id === Number(form.unit_id))
)

/* ======================= AI Research ======================= */
const canUseAi = can('assets.research') || can('assets.review-suggestions')

// Keep the form in step with an approved suggestion so saving the form doesn't undo it.
const onAiApplied = (current: CurrentValues) => {
  if (!form.model_number && current.model_number) {
    form.model_number = current.model_number
  }
}

/* ======================= Submit ======================= */
const submit = () => {
  form.put(update(props.asset.id).url, {
    forceFormData: true,
    onSuccess: () => toast.fire({ icon: 'success', title: 'Asset updated successfully' }),
    onError: () => toast.fire({ icon: 'error', title: 'Failed to update asset' }),
  })
}
</script>

<template>
  <Head title="Edit Asset" />

  <AppLayout :breadcrumbs="breadcrumbs">

    <!-- HEADER WITH ASSET CODE -->
    <div class="mx-6 mt-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-white">Edit Asset</h1>

      <div class="px-4 py-2 rounded-xl bg-emerald-900/40 border border-emerald-700">
        <span class="text-xs text-emerald-300 block">Asset Code</span>
        <span class="text-sm font-semibold tracking-wider text-emerald-400">
          {{ props.asset.asset_code }}
        </span>
      </div>
    </div>

    <FormCard class="mx-6 mt-4">
      <form @submit.prevent="submit" class="space-y-8">

        <!-- ROW 1 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <Label>Asset Name *</Label>
            <DarkSelect v-model="form.assetname_id"
              :options="assetNames.map(a => ({ value: a.id, label: a.assetname }))" />
          </div>

          <div>
            <Label>Brand *</Label>
            <DarkSelect v-model="form.brand"
              :options="brands.map(b => ({ value: b, label: b }))" />
          </div>

          <div>
            <Label>Useful Life (Years)</Label>
            <Input v-model="form.usefullife" readonly />
          </div>
        </div>

        <!-- MODEL / PART NUMBER (used by AI auto-fill) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <Label>Model Number</Label>
            <Input v-model="form.model_number" placeholder="e.g. EliteBook 840 G8" />
          </div>

          <div>
            <Label>Part Number</Label>
            <Input v-model="form.part_number" placeholder="e.g. 3G2A3EA" />
          </div>
        </div>

        <!-- ROW 2 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <Label>Serial Number</Label>
            <Input v-model="form.serial_number" />
          </div>

          <div>
            <Label>Description</Label>
            <Input v-model="form.description" />
          </div>

          <div>
            <Label>Purchase Cost (GHS)</Label>
            <Input type="number" v-model="form.purchase_cost" />
          </div>
        </div>

        <!-- ROW 3 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <Label>Purchase Date</Label>
            <Input type="date" v-model="form.purchase_date" />
          </div>

          <div>
            <Label>Warranty Duration (Months)</Label>
            <Input v-model="form.warranty_duration" />
          </div>

          <div>
            <Label>Warranty End Date</Label>
            <Input v-model="form.warranty_end_date" readonly />
          </div>
        </div>

        <!-- ROW 4 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <Label>Unit / Division *</Label>
            <DarkSelect v-model="form.unit_id"
              :options="units.map(u => ({ value: u.id, label: u.name }))" />
          </div>

          <div>
            <Label>Location</Label>
            <Input :model-value="selectedUnit?.locationname ?? '—'" readonly />
          </div>
        </div>

        <!-- ROW 5 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div>
            <Label>Source of Funding *</Label>
            <DarkSelect v-model="form.source_id"
              :options="sources.map(s => ({ value: s.id, label: s.name }))" />
          </div>

          <div>
            <Label>Supplier *</Label>
            <DarkSelect v-model="form.supplier_id"
              :options="suppliers.map(s => ({ value: s.id, label: s.suppliername }))" />
          </div>

          <div>
            <Label>Maintenance Provider *</Label>
            <DarkSelect v-model="form.maintenance_id"
              :options="maintenanceProviders.map(m => ({ value: m.id, label: m.providername }))" />
          </div>
        </div>

        <!-- ROW 6 -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <Label>Status *</Label>
            <DarkSelect v-model="form.status"
              :options="[
                { value: 'active', label: 'Active' },
                { value: 'under_maintenance', label: 'Under Maintenance' },
                { value: 'obsolete', label: 'Obsolete' },
                { value: 'disposed', label: 'Disposed' },
              ]" />
          </div>

          <div>
            <Label>Asset Image</Label>
            <Input type="file" @change="onImageChange" />
            <img v-if="preview" :src="preview" class="mt-2 h-20 w-20 rounded border object-cover" />
          </div>
        </div>

        <!-- SUBMIT -->
        <div class="flex justify-end pt-6 border-t">
          <Button type="submit" :disabled="form.processing">
            Update Asset
          </Button>
        </div>

      </form>
    </FormCard>

    <AiResearchPanel
      v-if="canUseAi"
      class="mx-6 mt-4 mb-6"
      :asset-id="props.asset.id"
      @applied="onAiApplied"
    />
  </AppLayout>
</template>
