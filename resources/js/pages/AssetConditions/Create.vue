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
  { title: 'Asset Conditions', href: '/assetconditions' },
  { title: 'Create', href: '#' },
]

/* =======================
   Form
======================= */
const form = useForm<{
  conditionname: string
}>({
  conditionname: '',
})

/* =======================
   Submit
======================= */
const submit = () => {
  form.post('/assetconditions', {
    preserveScroll: true,
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Asset condition created successfully',
      })
      form.reset()
    },
  })
}
</script>

<template>
  <Head title="Create Asset Condition" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Create Asset Condition">
      <form @submit.prevent="submit" class="space-y-8">

        <!-- GRID -->
        <div class="grid grid-cols-1 gap-6">

          <!-- Condition Name -->
          <div class="space-y-1.5">
            <Label>
              Condition Name <span class="text-red-500">*</span>
            </Label>
            <Input v-model="form.conditionname" />
            <p v-if="form.errors.conditionname" class="text-xs text-red-400">
              {{ form.errors.conditionname }}
            </p>
          </div>

        </div>

        <!-- FOOTER -->
        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing">
            Create Asset Condition
          </Button>
        </div>

      </form>
    </FormCard>
  </AppLayout>
</template>
