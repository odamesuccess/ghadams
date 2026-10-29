<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { watch, computed } from 'vue'
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

interface Props {
  organizations: Organization[]
}

const props = defineProps<Props>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Units', href: '/units' },
  { title: 'Create', href: '#' },
]

/* =======================
   Form
======================= */
const form = useForm({
  organization_id: '' as number | '',
  organization_abbreviation: '', // 🔹 auto-filled
  name: '',
  unit_abbreviation: '',
  locationname: '',
  location_abbreviation: '',

  email: '',
  phonenumber: '',
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
   Submit
======================= */
const submit = () => {
  form.post('/units', {
    onSuccess: () => {
      toast.fire({ icon: 'success', title: 'Unit created successfully' })
      form.reset()
    },
  })
}
</script>

<template>
  <Head title="Create Unit" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Create Unit">
      <form @submit.prevent="submit" class="space-y-8">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Organization Dropdown -->
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
<div class="space-y-1.5">
  <Label class="text-slate-200">Office Contact</Label>

  <Input
    type="tel"
    v-model="form.phonenumber"
    inputmode="tel"
    pattern="^\+?[0-9]*$"
    maxlength="15"
    placeholder="e.g. +233240000000"
    @input="form.phonenumber = form.phonenumber
      .replace(/[^0-9+]/g, '')        // allow digits + plus
      .replace(/(?!^)\+/g, '')        // allow + only at start
    "
  />
</div>


        </div>

        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Creating…' : 'Create Unit' }}
          </Button>
        </div>

      </form>
    </FormCard>
  </AppLayout>
</template>
