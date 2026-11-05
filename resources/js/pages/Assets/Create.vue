<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import FormCard from '@/components/ui/FormCard.vue'
import type { BreadcrumbItem } from '@/types'
import { index, create } from '@/routes/assets'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import DarkSelect from '@/components/ui/DarkSelect.vue'
import { toast } from '@/utils/sweetalert'

/* =======================
   Props
======================= */
const props = defineProps<{
    assetNames: { id: number; assetname: string; usefullife: number }[]
    brands: string[]
    suppliers: { id: number; suppliername: string }[]
    maintenanceProviders: { id: number; providername: string }[]
    sources: { id: number; name: string }[]
    units: { id: number; name: string; locationname: string }[]
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Create Asset', href: create().url },
]

/* =======================
   Helpers
======================= */
const today = new Date().toISOString().split('T')[0]

/* =======================
   Image Preview + Validation
======================= */
const preview = ref<string | null>(null)

const onImageChange = (e: Event) => {
    const input = e.target as HTMLInputElement
    const file = input.files?.[0]

    if (!file) {
        preview.value = null
        form.asset_image = null
        return
    }

    if (!['image/png', 'image/jpeg', 'image/jpg'].includes(file.type)) {
        toast.fire({
            icon: 'error',
            title: 'Invalid image type',
            text: 'Only PNG or JPG images are allowed',
        })
        input.value = ''
        preview.value = null
        return
    }

    if (file.size > 2 * 1024 * 1024) {
        toast.fire({
            icon: 'error',
            title: 'Image too large',
            text: 'Image must not exceed 2MB',
        })
        input.value = ''
        preview.value = null
        return
    }

    form.asset_image = file
    preview.value = URL.createObjectURL(file)
}

/* =======================
   Form State (WITH DEFAULTS)
======================= */
const form = useForm({
    assetname_id: '',
    brand: '',
    usefullife: '',
    unit_id: '',

    serial_number: 'N/A',      // ✅ default
    description: 'N/A',        // ✅ default
    model_number: '',
    part_number: '',

    purchase_cost: '',
    purchase_date: today,      // ✅ today default

    warranty_duration: '',
    warranty_end_date: '',

    source_id: '',
    supplier_id: '',
    maintenance_id: '',
    status: 'active',

    asset_image: null as File | null,
})

/* =======================
   Asset → Useful Life
======================= */
const selectedAsset = computed(() =>
    props.assetNames.find(a => a.id === Number(form.assetname_id))
)

watch(() => form.assetname_id, () => {
    form.usefullife = selectedAsset.value
        ? String(selectedAsset.value.usefullife)
        : ''
})

/* =======================
   Warranty End Date
======================= */
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

/* =======================
   Submit
======================= */
const submit = () => {
    form.post(index().url, {
        forceFormData: true,

        onSuccess: () => {
            toast.fire({ icon: 'success', title: 'Asset created successfully' })

            form.reset()

            // ✅ restore defaults after reset
            form.purchase_date = today
            form.serial_number = 'N/A'
            form.description = 'N/A'

            preview.value = null
        },

        onError: () => {
            toast.fire({ icon: 'error', title: 'Failed to create asset' })
        },
    })
}
</script>


<template>

    <Head title="Create Asset" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <FormCard title="Register New Asset" class="mx-6 mt-6">
            <form @submit.prevent="submit" class="space-y-8">

                <!-- ROW 1 -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <Label>Asset Name *</Label>
                        <DarkSelect v-model="form.assetname_id" placeholder="Select asset name"
                            :options="assetNames.map(a => ({ value: a.id, label: a.assetname }))" />
                    </div>

                    <div>
                        <Label>Brand *</Label>
                        <DarkSelect v-model="form.brand" placeholder="Select brand"
                            :options="brands.map(b => ({ value: b, label: b }))" />
                    </div>

                    <div>
                        <Label>Useful Life (Years)</Label>
                        <Input v-model="form.usefullife" readonly class="bg-slate-800" />
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

                    <div class="flex items-end">
                        <p class="text-xs text-slate-400">
                            Save the asset first, then use <span class="font-medium text-indigo-300">Auto-fill with AI</span>
                            on the edit or detail page to look up specs and price.
                        </p>
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
                        <Label>Purchase Cost</Label>
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
                        <Label>Warranty Duration</Label>
                        <DarkSelect v-model="form.warranty_duration" placeholder="Select duration" :options="[
                            { value: 6, label: '6 months' },
                            { value: 12, label: '12 months' },
                            { value: 24, label: '24 months' },
                        ]" />
                    </div>

                    <div>
                        <Label>Warranty End Date</Label>
                        <Input v-model="form.warranty_end_date" readonly class="bg-slate-800" />
                    </div>
                </div>
                <div>
                    <Label>Unit / Division *</Label>
                    <DarkSelect v-model="form.unit_id" placeholder="Select Unit / Division" :options="props.units.map(u => ({
                        value: u.id,
                        label: u.name,
                    }))" />
                </div>
                <!-- ROW 4 -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <Label>Asset Location *</Label>
                        <DarkSelect v-model="form.unit_id" placeholder="Select location"
                            :options="units.map(l => ({ value: l.id, label: l.locationname }))" />
                    </div>

                    <div>
                        <Label>Asset Source of Funding / Project *</Label>
                        <DarkSelect v-model="form.source_id" placeholder="Select Source"
                            :options="sources.map(c => ({ value: c.id, label: c.name }))" />
                    </div>

                    <div>
                        <Label>Supplier *</Label>
                        <DarkSelect v-model="form.supplier_id" placeholder="Select supplier"
                            :options="suppliers.map(s => ({ value: s.id, label: s.suppliername }))" />
                    </div>
                </div>

                <!-- ROW 5 -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <Label>Maintenance Provider *</Label>
                        <DarkSelect v-model="form.maintenance_id" placeholder="Select provider" :options="maintenanceProviders.map(m => ({
                            value: m.id,
                            label: m.providername,
                        }))" />
                    </div>

                    <div>
                        <Label>Status *</Label>
                        <DarkSelect v-model="form.status" placeholder="Select status" :options="[
                            { value: 'active', label: 'Active' },
                            { value: 'under_maintenance', label: 'Under Maintenance' },
                            { value: 'obsolete', label: 'Obsolete' },
                            { value: 'disposed', label: 'Disposed' },
                        ]" />
                    </div>

                    <div>
                        <Label>Asset Image</Label>
                        <Input type="file" accept="image/png,image/jpeg" @change="onImageChange" />

                        <img v-if="preview" :src="preview" class="mt-2 h-20 w-20 rounded-md border object-cover" />
                    </div>
                </div>

                <!-- SUBMIT -->
                <div class="flex justify-end border-t pt-6">
                    <Button type="submit" :disabled="form.processing">
                        Create Asset
                    </Button>
                </div>

            </form>
        </FormCard>
    </AppLayout>
</template>
