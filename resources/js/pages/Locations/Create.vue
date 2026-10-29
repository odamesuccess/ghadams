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
  { title: 'Locations Names', href: '/locations' },
  { title: 'Create', href: '#' },
]

/* =======================
   Form
======================= */
const form = useForm({
  locationname: '',

  officenumber: '',
})

const submit = () => {
  form.post('/locations', {
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Location name created successfully',
      })
      form.reset()
    },
  })
}
</script>

<template>
  <Head title="Create Location Name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Create Location Name">

      <form @submit.prevent="submit" class="space-y-8">

        <!-- GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Asset Name -->
          <div class="space-y-1.5 md:col-span-2">
            <Label class="text-slate-200">
              Loaction Name <span class="text-red-500">*</span>
            </Label>
            <Input v-model="form.locationname" />
            <p v-if="form.errors.locationname" class="text-xs text-red-400">
              {{ form.errors.locationname }}
            </p>
          </div>



          <!-- Brand -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Door Tag
            </Label>
            <Input v-model="form.officenumber" />
            <p v-if="form.errors.officenumber" class="text-xs text-red-400">
              {{ form.errors.officenumber }}
            </p>
          </div>

        </div>

        <!-- FOOTER -->
        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing">
            Create  Name
          </Button>
        </div>

      </form>

    </FormCard>
  </AppLayout>
</template>
