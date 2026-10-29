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
  assetCondition: {
    id: number
    conditionname: string
  }
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Asset Conditions', href: '/assetconditions' },
  { title: 'Edit', href: '#' },
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
   Safe hydration
======================= */
watch(
  () => props.assetCondition,
  (condition) => {
    if (!condition) return
    form.conditionname = condition.conditionname
  },
  { immediate: true }
)

/* =======================
   Submit
======================= */
const submit = () => {
  form.put(`/assetconditions/${props.assetCondition.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Asset condition updated successfully',
      })
    },
  })
}
</script>

<template>
  <Head title="Edit Asset Condition" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Edit Asset Condition">
      <form @submit.prevent="submit" class="space-y-8">

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

        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing">
            Update Asset Condition
          </Button>
        </div>

      </form>
    </FormCard>
  </AppLayout>
</template>
