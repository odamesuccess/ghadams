<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { toast } from '@/utils/sweetalert'
import { can } from '@/utils/permissions'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

/* =======================
   Types
======================= */
interface Organization {
  id: number
  organizations_name: string
  organizations_email?: string | null
  organizations_phone?: string | null
  organizations_address?: string | null
  organization_abbreviation?: string | null
}

/* =======================
   Props (from Controller)
======================= */
const props = defineProps<{
  organization: Organization
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Organizations', href: '/organizations' },
  { title: 'Edit', href: `/organizations/${props.organization.id}/edit` },
]

/* =======================
   Form State
======================= */
const form = useForm({
  organizations_name: props.organization.organizations_name,
  organizations_email: props.organization.organizations_email ?? '',
  organizations_phone: props.organization.organizations_phone ?? '',
  organizations_address: props.organization.organizations_address ?? '',
  organization_abbreviation: props.organization.organization_abbreviation ?? '',
})

/* =======================
   Submit
======================= */
const submit = () => {
  if (!can('organizations.edit')) {
    toast.fire({
      icon: 'error',
      title: 'You are not authorized to edit organizations',
    })
    return
  }

  form.put(`/organizations/${props.organization.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Organization updated successfully',
      })
    },
    onError: () => {
      toast.fire({
        icon: 'error',
        title: 'Failed to update organization',
      })
    },
  })
}
</script>

<template>
  <Head title="Edit Organization" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Back -->
    <div class="mt-4 px-6">
      <Link href="/organizations" class="btn-secondary">
        ← Back to Organizations
      </Link>
    </div>

    <!-- Card -->
    <div class="mx-6 mt-6 rounded-lg border bg-background shadow-sm">
      <form @submit.prevent="submit" class="space-y-8 p-8">

        <!-- Header -->
        <h2 class="text-lg font-semibold">
          Edit Organization
        </h2>

        <!-- Fields -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Organization Name -->
          <div class="space-y-1.5 ">
            <Label>Organization Name *</Label>
            <Input
              v-model="form.organizations_name"
              placeholder="e.g. Ghana Education Service"
            />
            <p v-if="form.errors.organizations_name" class="text-xs text-red-500">
              {{ form.errors.organizations_name }}
            </p>
          </div>

          <!-- Abbreviation -->
          <div class="space-y-1.5">
            <Label>Organization Abbreviation *</Label>
            <Input
              v-model="form.organization_abbreviation"
              placeholder="e.g. GES"
            />
            <p v-if="form.errors.organization_abbreviation" class="text-xs text-red-500">
              {{ form.errors.organization_abbreviation }}
            </p>
          </div>

          <!-- Email -->
          <div class="space-y-1.5">
            <Label>Email</Label>
            <Input
              type="email"
              v-model="form.organizations_email"
              placeholder="e.g. info@organization.com"
            />
            <p v-if="form.errors.organizations_email" class="text-xs text-red-500">
              {{ form.errors.organizations_email }}
            </p>
          </div>

          <!-- Phone -->
          <div class="space-y-1.5">
            <Label>Phone</Label>
            <Input
              type="tel"
              v-model="form.organizations_phone"
              placeholder="e.g. +233 24 000 0000"
            />
            <p v-if="form.errors.organizations_phone" class="text-xs text-red-500">
              {{ form.errors.organizations_phone }}
            </p>
          </div>

          <!-- Address -->
          <div class="space-y-1.5 md:col-span-2">
            <Label>Address</Label>
            <Input
              v-model="form.organizations_address"
              placeholder="e.g. Accra, Ghana"
            />
            <p v-if="form.errors.organizations_address" class="text-xs text-red-500">
              {{ form.errors.organizations_address }}
            </p>
          </div>

        </div>

        <!-- Footer -->
        <div class="flex justify-end border-t pt-6">
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Updating...' : 'Update Organization' }}
          </Button>
        </div>

      </form>
    </div>
  </AppLayout>
</template>
