<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { toast } from '@/utils/sweetalert'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Organizations', href: '/organizations' },
  { title: 'Create', href: '/organizations/create' },
]

/* =======================
   Form State
======================= */
const form = useForm({
  organizations_name: '',
  organizations_email: '',
  organizations_phone: '',
  organizations_address: '',
  organization_abbreviation: '',
})

/* =======================
   Submit
======================= */
const submit = () => {
  form.post('/organizations', {
    preserveScroll: true,
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Organization created successfully',
      })
      form.reset()
    },
    onError: () => {
      toast.fire({
        icon: 'error',
        title: 'Failed to create organization',
      })
    },
  })
}
</script>

<template>
  <Head title="Create Organization" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- PAGE BACKGROUND -->
    <div
      class="min-h-[calc(100vh-4rem)]
             bg-gradient-to-b from-slate-950 via-slate-900 to-slate-800
             px-6 pb-10"
    >
      <!-- Back -->
      <div class="mt-4">
        <Link href="/organizations" class="btn-secondary">
          ← Back to Organizations
        </Link>
      </div>

      <!-- CARD -->
      <div
        class="mt-6 w-full rounded-xl
               border border-slate-700/60
               bg-slate-900/90
               shadow-[0_0_0_1px_rgba(148,163,184,0.15),0_30px_80px_rgba(0,0,0,0.85)]"
      >
        <form @submit.prevent="submit" class="space-y-8 p-8">

          <!-- Header -->
          <h2 class="text-lg font-semibold text-slate-100 tracking-tight">
            Create a new organization
          </h2>

          <!-- Fields -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- Name -->
<div class="space-y-1.5">
  <Label class="text-slate-300">
    Organization Name <span class="text-red-500">*</span>
  </Label>
  <Input
    v-model="form.organizations_name"
    placeholder="e.g. Ghana Education Service"
    class="bg-slate-950 border-slate-700 text-slate-100
           focus:border-blue-500 focus:ring-blue-500"
  />
  <p v-if="form.errors.organizations_name" class="text-xs text-red-400">
    {{ form.errors.organizations_name }}
  </p>
</div>

<!-- Abbreviation -->
<div class="space-y-1.5">
  <Label class="text-slate-300">
    Organization Abbreviation <span class="text-red-500">*</span>
  </Label>
  <Input
    v-model="form.organization_abbreviation"
    placeholder="e.g. GES"
    class="bg-slate-950 border-slate-700 text-slate-100
           focus:border-blue-500 focus:ring-blue-500"
  />
  <p v-if="form.errors.organization_abbreviation" class="text-xs text-red-400">
    {{ form.errors.organization_abbreviation }}
  </p>
</div>

<!-- Email -->
<div class="space-y-1.5">
  <Label class="text-slate-300">Email</Label>
  <Input
    type="email"
    v-model="form.organizations_email"
    placeholder="e.g. info@organization.com"
    class="bg-slate-950 border-slate-700 text-slate-100"
  />
  <p v-if="form.errors.organizations_email" class="text-xs text-red-400">
    {{ form.errors.organizations_email }}
  </p>
</div>

<!-- Phone -->
<div class="space-y-1.5">
  <Label class="text-slate-300">Phone</Label>
  <Input
    type="tel"
    v-model="form.organizations_phone"
    placeholder="e.g. +233 24 000 0000"
    class="bg-slate-950 border-slate-700 text-slate-100"
  />
  <p v-if="form.errors.organizations_phone" class="text-xs text-red-400">
    {{ form.errors.organizations_phone }}
  </p>
</div>

<!-- Address -->
<div class="space-y-1.5 md:col-span-2">
  <Label class="text-slate-300">Address</Label>
  <Input
    v-model="form.organizations_address"
    placeholder="e.g. Accra, Ghana"
    class="bg-slate-950 border-slate-700 text-slate-100"
  />
  <p v-if="form.errors.organizations_address" class="text-xs text-red-400">
    {{ form.errors.organizations_address }}
  </p>
</div>

          </div>

          <!-- Footer -->
          <div class="flex justify-end border-t border-slate-700 pt-6">
            <Button type="submit" :disabled="form.processing">
              {{ form.processing ? 'Creating...' : 'Create Organization' }}
            </Button>
          </div>

        </form>
      </div>
    </div>
  </AppLayout>
</template>
