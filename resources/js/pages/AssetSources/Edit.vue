<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
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
  assetSource: {
    id: number
    name: string
    description: string | null
  }
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Asset Sources', href: '/assetsources' },
  { title: 'Edit', href: '#' },
]

/* =======================
   Form (INITIALIZED FROM PROPS ✅)
======================= */
const form = useForm({
  name: props.assetSource.name,
  description: props.assetSource.description ?? '',
})

/* =======================
   Submit
======================= */
const submit = () => {
  form.put(`/assetsources/${props.assetSource.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Asset source updated successfully',
      })
    },
  })
}
</script>

<template>
  <Head title="Edit Asset Source" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard :key="props.assetSource.id" title="Edit Asset Source">

      <form @submit.prevent="submit" class="space-y-8">

        <div class="grid grid-cols-1 gap-6">

          <!-- Source Name -->
          <div class="space-y-1.5">
            <Label>
              Source Name <span class="text-red-500">*</span>
            </Label>
            <Input v-model="form.name" />
            <p v-if="form.errors.name" class="text-xs text-red-400">
              {{ form.errors.name }}
            </p>
          </div>

          <!-- Description -->
          <div class="space-y-1.5">
            <Label>Description / Purpose</Label>
            <Input v-model="form.description" />
            <p v-if="form.errors.description" class="text-xs text-red-400">
              {{ form.errors.description }}
            </p>
          </div>

        </div>

        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing">
            Update Asset Source
          </Button>
        </div>

      </form>

    </FormCard>
  </AppLayout>
</template>
