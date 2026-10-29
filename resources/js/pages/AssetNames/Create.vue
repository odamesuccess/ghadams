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
  { title: 'Asset Names', href: '/assetnames' },
  { title: 'Create', href: '#' },
]

/* =======================
   Form
======================= */
const form = useForm({
  assetname: '',
  assetname_abbreviation: '',
  usefullife: '',
  brand: '',
})

const submit = () => {
  form.post('/assetnames', {
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Asset name created successfully',
      })
      form.reset()
    },
  })
}
</script>

<template>
  <Head title="Create Asset Name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Create Asset Name">

      <form @submit.prevent="submit" class="space-y-8">

        <!-- GRID -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Asset Name -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Asset Name <span class="text-red-500">*</span>
            </Label>
            <Input
              v-model="form.assetname"
              placeholder="e.g. Desktop Computer"
            />
            <p v-if="form.errors.assetname" class="text-xs text-red-400">
              {{ form.errors.assetname }}
            </p>
          </div>

          <!-- Asset Abbreviation -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Asset Abbreviation <span class="text-red-500">*</span>
            </Label>
            <Input
              v-model="form.assetname_abbreviation"
              placeholder="e.g. DTC"
              @input="form.assetname_abbreviation = form.assetname_abbreviation.toUpperCase()"
            />
            <p v-if="form.errors.assetname_abbreviation" class="text-xs text-red-400">
              {{ form.errors.assetname_abbreviation }}
            </p>
          </div>

          <!-- Useful Life -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Useful Life (Years)
            </Label>
            <Input
              type="number"
              v-model="form.usefullife"
              placeholder="e.g. 5"
              min="0"
            />
            <p v-if="form.errors.usefullife" class="text-xs text-red-400">
              {{ form.errors.usefullife }}
            </p>
          </div>

          <!-- Brand -->
          <div class="space-y-1.5">
            <Label class="text-slate-200">
              Brand
            </Label>
            <Input
              v-model="form.brand"
              placeholder="e.g. Dell, HP, Lenovo"
            />
            <p v-if="form.errors.brand" class="text-xs text-red-400">
              {{ form.errors.brand }}
            </p>
          </div>

        </div>

        <!-- FOOTER -->
        <div class="flex justify-end border-t border-slate-700 pt-6">
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Creating...' : 'Create Asset Name' }}
          </Button>
        </div>

      </form>

    </FormCard>
  </AppLayout>
</template>
