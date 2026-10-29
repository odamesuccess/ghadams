<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import FormCard from '@/components/ui/FormCard.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { toast } from '@/utils/sweetalert'
import type { BreadcrumbItem } from '@/types'

/* =======================
   Props (STRICT + SAFE)
======================= */
const props = defineProps<{
  assetLocation: {
    id: number
    locationname: string
    officenumber: string | null
  }
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Asset Locations', href: '/assetlocations' },
  { title: 'Edit', href: '#' },
]

/* =======================
   Form (EXACT DB SHAPE)
======================= */
const form = useForm<{
  locationname: string
  officenumber: string
}>({
  locationname: props.assetLocation.locationname,
  officenumber: props.assetLocation.officenumber ?? '',
})

/* =======================
   Submit
======================= */
const submit = () => {
  form.put(`/assetlocations/${props.assetLocation.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Asset location updated successfully',
      })
    },
  })
}
</script>

<template>
  <Head title="Edit Asset Location" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Edit Asset Location">
      <form @submit.prevent="submit" class="space-y-6">

        <!-- Location Name -->
        <div>
          <Label>
            Location Name <span class="text-red-500">*</span>
          </Label>

          <Input v-model="form.locationname" />

          <p
            v-if="form.errors.locationname"
            class="text-xs text-red-400"
          >
            {{ form.errors.locationname }}
          </p>
        </div>

        <!-- Office Number -->
        <div>
          <Label>Office Number</Label>

          <Input v-model="form.officenumber" />

          <p
            v-if="form.errors.officenumber"
            class="text-xs text-red-400"
          >
            {{ form.errors.officenumber }}
          </p>
        </div>

        <!-- Actions -->
        <div class="flex justify-end border-t pt-6">
          <Button type="submit" :disabled="form.processing">
            Update Location
          </Button>
        </div>

      </form>
    </FormCard>
  </AppLayout>
</template>
