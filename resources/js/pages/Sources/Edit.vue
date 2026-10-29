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
  source: {
    id: number
    name: string
    description: string | null
  }
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Sources', href: '/sources' },
  { title: 'Edit', href: '#' },
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
   Safe hydration
======================= */
watch(
  () => props.source,
  (source) => {
    if (!source) return

    form.name = source.name
    form.description = source.description ?? ''
  },
  { immediate: true }
)

/* =======================
   Submit
======================= */
const submit = () => {
  form.put(`/sources/${props.source.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Source updated successfully',
      })
    },
  })
}
</script>

<template>
  <Head title="Edit Source" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Edit Source">
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
            <Label>Description</Label>
            <Input v-model="form.description" />
            <p v-if="form.errors.description" class="text-xs text-red-400">
              {{ form.errors.description }}
            </p>
          </div>

        </div>

        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing">
            Update Source
          </Button>
        </div>

      </form>
    </FormCard>
  </AppLayout>
</template>
