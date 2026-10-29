<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

import AppLayout from '@/layouts/AppLayout.vue'
import FormCard from '@/components/ui/FormCard.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { toast } from '@/utils/sweetalert'


import type { BreadcrumbItem } from '@/types'

/* =======================
   Props from Controller
======================= */
interface LinkData {
  id: number
  name: string
  url: string
  status: 'active' | 'expired' | 'disabled'
  logo?: string | null
  expires_at?: string | null
}

const props = defineProps<{
  link: LinkData
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Links', href: '/links' },
  { title: 'Edit', href: '#' },
]

/* =======================
   Form State
======================= */
const form = useForm({
  name: props.link.name,
  url: props.link.url,
  status: props.link.status,
  logo: null as File | null,
  expires_at: props.link.expires_at ?? '',
})

/* =======================
   Logo Preview
======================= */
const logoPreview = ref<string | null>(
  props.link.logo ? `/storage/${props.link.logo}` : null
)

const onLogoChange = (e: Event) => {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  form.logo = file
  logoPreview.value = URL.createObjectURL(file)
}

/* =======================
   Validation Helper
======================= */
const isValid = computed(() => {
  return form.name.trim() !== '' && form.url.trim() !== ''
})

/* =======================
   Submit (PUT)
======================= */
const submit = () => {
  form.put(`/links/${props.link.id}`, {
    forceFormData: true,

    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Link updated successfully',
      })
    },
  })
}

</script>

<template>
  <Head title="Edit Link" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Edit Link">

      <form @submit.prevent="submit" class="space-y-8">

        <!-- GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- NAME -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Link Name <span class="text-red-500">*</span>
            </Label>

            <Input v-model="form.name" />

            <p v-if="form.errors.name" class="text-xs text-red-400">
              {{ form.errors.name }}
            </p>
          </div>

          <!-- URL -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Target URL <span class="text-red-500">*</span>
            </Label>

            <Input v-model="form.url" />

            <p v-if="form.errors.url" class="text-xs text-red-400">
              {{ form.errors.url }}
            </p>
          </div>

          <!-- EXPIRES -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">Expiry Date</Label>

            <Input type="date" v-model="form.expires_at" />

            <p v-if="form.errors.expires_at" class="text-xs text-red-400">
              {{ form.errors.expires_at }}
            </p>
          </div>

          <!-- STATUS -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Status <span class="text-red-500">*</span>
            </Label>

            <select
              v-model="form.status"
              class="w-full rounded-md border bg-slate-800 px-3 py-2 text-sm text-white"
            >
              <option value="active">Active</option>
              <option value="expired">Expired</option>
              <option value="disabled">Disabled</option>
            </select>

            <p v-if="form.errors.status" class="text-xs text-red-400">
              {{ form.errors.status }}
            </p>
          </div>

          <!-- LOGO -->
          <div class="space-y-1.5 md:col-span-2">
            <Label class="text-slate-200">App Logo</Label>

            <Input type="file" accept="image/*" @change="onLogoChange" />

            <!-- Preview -->
            <img
              v-if="logoPreview"
              :src="logoPreview"
              class="mt-2 h-16 w-16 object-contain rounded border"
            />

            <p v-if="form.errors.logo" class="text-xs text-red-400">
              {{ form.errors.logo }}
            </p>
          </div>

        </div>

        <!-- FOOTER -->
        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing || !isValid">
            {{ form.processing ? 'Updating...' : 'Update Link' }}
          </Button>
        </div>

      </form>

    </FormCard>
  </AppLayout>
</template>
