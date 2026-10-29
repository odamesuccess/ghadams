<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import FormCard from '@/components/ui/FormCard.vue'
import { toast } from '@/utils/sweetalert'

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Maintenance Providers', href: '/maintenanceproviders' },
  { title: 'Create', href: '#' },
]

const form = useForm({
  providername: '',
  phonenumber: '',
  email: '',
})

const submit = () => {
  form.post('/maintenanceproviders', {
    onSuccess: () => {
      toast.fire({ icon: 'success', title: 'Maintenance provider created successfully' })
      form.reset()
    },
  })
}
</script>

<template>
  <Head title="Create Maintenance Provider" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Create Maintenance Provider">
      <form @submit.prevent="submit" class="space-y-8">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Provider Name -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Provider Name <span class="text-red-500">*</span>
            </Label>
            <Input v-model="form.providername" />
            <p v-if="form.errors.providername" class="text-xs text-red-400">
              {{ form.errors.providername }}
            </p>
          </div>

          <!-- Phone -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Phone <span class="text-red-500">*</span>
            </Label>
            <Input v-model="form.phonenumber" />
            <p v-if="form.errors.phonenumber" class="text-xs text-red-400">
              {{ form.errors.phonenumber }}
            </p>
          </div>

          <!-- Email -->
          <div class="space-y-1.5 md:col-span-2">
            <Label class="text-slate-200">
              Email <span class="text-red-500">*</span>
            </Label>
            <Input type="email" v-model="form.email" />
            <p v-if="form.errors.email" class="text-xs text-red-400">
              {{ form.errors.email }}
            </p>
          </div>

        </div>

        <!-- Footer -->
        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing">
            Create Provider
          </Button>
        </div>

      </form>
    </FormCard>
  </AppLayout>
</template>
