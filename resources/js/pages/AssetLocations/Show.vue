<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import FormCard from '@/components/ui/FormCard.vue'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import type { BreadcrumbItem } from '@/types'

/* =======================
   Props
======================= */
const props = defineProps<{
  assetLocation: {
    id: number
    locationname: string
    officenumber: string | null
    created_at?: string | null
    updated_at?: string | null
  }
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Asset Locations', href: '/assetlocations' },
  { title: 'View', href: '#' },
]
</script>

<template>
  <Head title="View Asset Location" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Asset Location Details" class="mx-6 mt-6">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Location Name -->
        <div>
          <Label>Location Name</Label>
          <div class="form-dark">
            {{ assetLocation.locationname }}
          </div>
        </div>

        <!-- Office Number -->
        <div>
          <Label>Office Number</Label>
          <div class="form-dark">
            {{ assetLocation.officenumber ?? '—' }}
          </div>
        </div>

        <!-- Created At -->
        <div>
          <Label>Created At</Label>
          <div class="form-dark">
            {{ assetLocation.created_at ?? '—' }}
          </div>
        </div>

        <!-- Updated At -->
        <div>
          <Label>Last Updated</Label>
          <div class="form-dark">
            {{ assetLocation.updated_at ?? '—' }}
          </div>
        </div>

      </div>

      <!-- ACTIONS -->
      <div class="flex justify-end gap-4 border-t pt-6 mt-6">
        <Link href="/assetlocations">
          <Button variant="secondary">
            Back
          </Button>
        </Link>

        <Link :href="`/assetlocations/${assetLocation.id}/edit`">
          <Button>
            Edit Location
          </Button>
        </Link>
      </div>

    </FormCard>
  </AppLayout>
</template>
