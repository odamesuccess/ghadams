<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import FormCard from '@/components/ui/FormCard.vue'
import { toast } from '@/utils/sweetalert'

/* =======================
   Types
======================= */
interface Organization {
  id: number
  organizations_name: string
  organization_abbreviation: string
}

interface Unit {
  id: number
  organization_id: number | null
  organization_abbreviation: string
  name: string
  unit_abbreviation: string
  locationname: string
  location_abbreviation: string
  officenumber: string
  email: string
  phonenumber: string
}

interface Props {
  organizations: Organization[]
  unit: Unit
}

const props = defineProps<Props>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Units', href: '/units' },
  { title: 'Edit', href: '#' },
]

/* =======================
   Form (PRE-FILLED)
======================= */
const form = useForm({
  organization_id: props.unit.organization_id ?? '',
  organization_abbreviation: props.unit.organization_abbreviation ?? '',
  name: props.unit.name ?? '',
  unit_abbreviation: props.unit.unit_abbreviation ?? '',
  locationname: props.unit.locationname ?? '',
  location_abbreviation: props.unit.location_abbreviation ?? '',
  officenumber: props.unit.officenumber ?? '',
  email: props.unit.email ?? '',
  phonenumber: props.unit.phonenumber ?? '',
})

/* =======================
   AUTO-FILL ORG CODE
======================= */
watch(
  () => form.organization_id,
  (id) => {
    const org = props.organizations.find(o => o.id === id)
    form.organization_abbreviation = org?.organization_abbreviation ?? ''
  }
)

/* =======================
   FORCE UPPERCASE
======================= */
watch(() => form.unit_abbreviation, v => {
  if (v && v !== v.toUpperCase()) form.unit_abbreviation = v.toUpperCase()
})

watch(() => form.location_abbreviation, v => {
  if (v && v !== v.toUpperCase()) form.location_abbreviation = v.toUpperCase()
})

/* =======================
   Submit (UPDATE)
======================= */
const submit = () => {
  form.put(`/units/${props.unit.id}`, {
    onSuccess: () => {
      toast.fire({ icon: 'success', title: 'Unit updated successfully' })
    },
  })
}
</script>

<template>
  <Head title="Edit Unit" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Edit Unit">
      <form @submit.prevent="submit" class="space-y-8">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Organization Dropdown -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">Organization</Label>

            <select
              v-model="form.organization_id"
              class="w-full rounded-md border border-slate-700
                     bg-black px-3 py-2 text-sm text-slate-200
                     focus:ring-2 focus:ring-blue-600 focus:outline-none"
            >
              <option value="">— No Organization —</option>
              <option
                v-for="org in props.organizations"
                :key="org.id"
                :value="org.id"
              >
                {{ org.organizations_name }} ({{ org.organization_abbreviation }})
              </option>
            </select>
          </div>

          <!-- Organization Code (AUTO + READONLY) -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">Organization Abbreviation</Label>
            <Input v-model="form.organization_abbreviation" readonly />
          </div>

          <!-- Unit Name -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Unit Name <span class="text-red-500">*</span>
            </Label>
            <Input v-model="form.name" />
          </div>

          <!-- Unit Code -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Unit Abbreviation <span class="text-red-500">*</span>
            </Label>
            <Input
              v-model="form.unit_abbreviation"
              placeholder="ACC, HR, IT"
              class="uppercase"
            />
          </div>

          <!-- Location Name -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">Location Name</Label>
            <Input v-model="form.locationname" />
          </div>

          <!-- Location Code -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">Location Abbreviation</Label>
            <Input
              v-model="form.location_abbreviation"
              placeholder="HQ, KUM, ACC"
              class="uppercase"
            />
          </div>



          <!-- Email -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">Email</Label>
            <Input type="email" v-model="form.email" />
          </div>

          <!-- Phone -->
          <div class="space-y-1.5 ">
            <Label class="text-slate-200">Phone </Label>
            <Input v-model="form.phonenumber" />
          </div>

        </div>

        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Updating…' : 'Update Unit' }}
          </Button>
        </div>

      </form>
    </FormCard>
  </AppLayout>
</template>
