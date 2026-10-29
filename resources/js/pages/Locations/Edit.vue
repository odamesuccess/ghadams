<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import FormCard from '@/components/ui/FormCard.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { toast } from '@/utils/sweetalert'
import type { BreadcrumbItem } from '@/types'

/* =======================
   Props
======================= */
const props = defineProps<{
  location: {
    id: number
    locationname: string
    officenumber: string | null
  }
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Location Names', href: '/locations' },
  { title: 'Edit', href: '#' },
]

/* =======================
   Form
======================= */
const form = useForm<{
  locationname: string
  officenumber: string
}>({
  locationname: '',
  officenumber: '',
})

/* =======================
   Safe hydration
======================= */
watch(
  () => props.location,
  (location) => {
    if (!location) return

    form.locationname = location.locationname
    form.officenumber = location.officenumber ?? ''
  },
  { immediate: true }
)

/* =======================
   Submit
======================= */
const submit = () => {
  form.put(`/locations/${props.location.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Location updated successfully',
      })
    },
  })
}
</script>

<template>
  <Head title="Edit Location" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Edit Location">
      <form @submit.prevent="submit" class="space-y-8">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Location Name -->
          <div class="space-y-1.5 md:col-span-2">
            <Label>
              Location Name <span class="text-red-500">*</span>
            </Label>
            <Input v-model="form.locationname" />
            <p v-if="form.errors.locationname" class="text-xs text-red-400">
              {{ form.errors.locationname }}
            </p>
          </div>

          <!-- Door Tag -->
          <div class="space-y-1.5">
            <Label>Door Tag</Label>
            <Input v-model="form.officenumber" />
            <p v-if="form.errors.officenumber" class="text-xs text-red-400">
              {{ form.errors.officenumber }}
            </p>
          </div>

        </div>

        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing">
            Update Location
          </Button>
        </div>

      </form>
    </FormCard>
  </AppLayout>
</template>
