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
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Asset Locations', href: '/assetlocations' },
  { title: 'Create', href: '#' },
]

/* =======================
   Form (STRICT + SAFE)
======================= */
const form = useForm<{
  locationname: string
  officenumber: string
}>({
  locationname: '',
  officenumber: '',
})

/* =======================
   Submit
======================= */
const submit = () => {
  form.post('/assetlocations', {
    preserveScroll: true,
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Asset location created successfully',
      })
      form.reset()
    },
  })
}
</script>

<template>
  <Head title="Create Asset Location" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Create Asset Location">
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
            Create Location
          </Button>
        </div>

      </form>
    </FormCard>
  </AppLayout>
</template>
