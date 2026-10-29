<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import FormCard from '@/components/ui/FormCard.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { toast } from '@/utils/sweetalert'
import type { BreadcrumbItem } from '@/types'

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Asset Sources', href: '/assetsources' },
  { title: 'Create', href: '#' },
]

const form = useForm({
  name: '',
  description: '',
})

const submit = () => {
  form.post('/assetsources', {
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Asset source created successfully',
      })
      form.reset()
    },
  })
}
</script>

<template>
  <Head title="Create Asset Source" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Create Asset Source">
      <form @submit.prevent="submit" class="space-y-8">

        <div class="grid grid-cols-1 gap-6">
          <div>
            <Label>Source Name *</Label>
            <Input v-model="form.name" />
            <p v-if="form.errors.name" class="text-xs text-red-400">
              {{ form.errors.name }}
            </p>
          </div>

          <div>
            <Label>Description / Purpose</Label>
            <Input v-model="form.description" />
          </div>
        </div>

        <div class="flex justify-end border-t pt-6">
          <Button type="submit" :disabled="form.processing">
            Create Asset Source
          </Button>
        </div>

      </form>
    </FormCard>
  </AppLayout>
</template>
