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
  { title: 'Sources', href: '/sources' },
  { title: 'Create', href: '#' },
]

/* =======================
   Form
======================= */
const form = useForm<{
  name: string
  description: string
}>({
  name: '',
  description: '',
})

/* =======================
   Submit
======================= */
const submit = () => {
  form.post('/sources', {
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Source created successfully',
      })
      form.reset()
    },
  })
}
</script>

<template>
  <Head title="Create Source" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Create Source">

      <form @submit.prevent="submit" class="space-y-8">

        <!-- GRID -->
        <div class="grid grid-cols-1 gap-6">

          <!-- Source Name -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Source Name <span class="text-red-500">*</span>
            </Label>
            <Input v-model="form.name" />
            <p v-if="form.errors.name" class="text-xs text-red-400">
              {{ form.errors.name }}
            </p>
          </div>

          <!-- Description -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Description
            </Label>
            <Input v-model="form.description" />
            <p v-if="form.errors.description" class="text-xs text-red-400">
              {{ form.errors.description }}
            </p>
          </div>

        </div>

        <!-- FOOTER -->
        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing">
            Create Source
          </Button>
        </div>

      </form>

    </FormCard>
  </AppLayout>
</template>
