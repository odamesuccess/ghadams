<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import FormCard from '@/components/ui/FormCard.vue'
import type { BreadcrumbItem } from '@/types'
import { index, edit } from '@/routes/assets'

import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'

/* =======================
   Props
======================= */
const props = defineProps<{
  asset: any
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Assets', href: index().url },
  { title: 'View Asset', href: '#' },
]
</script>

<template>
  <Head title="View Asset" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <FormCard title="Asset Information Details" class="mx-6 mt-6">
      <!-- Two-column layout for image and details -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

        <!-- LEFT COLUMN: IMAGE (4 columns on large screens) -->
        <div class="lg:col-span-3">
          <div class="sticky top-6">
            <Label class="text-lg font-semibold">Asset Image</Label>

            <div class="mt-4">
              <div
                v-if="asset.asset_image"
                class="relative rounded-xl border border-gray-300 dark:border-gray-700 bg-black/5 dark:bg-gray-900 overflow-hidden"
              >
                <img
                  :src="`/storage/${asset.asset_image}`"
                  :alt="asset.asset_name?.assetname || 'Asset Image'"
                  class="w-full h-auto max-h-[650px] object-contain p-4"
                />
              </div>

              <div
                v-else
                class="flex flex-col items-center justify-center h-[300px] rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900"
              >
                <div class="text-5xl mb-4 text-gray-400">📷</div>
                <p class="text-gray-500 dark:text-gray-400">No image uploaded</p>
              </div>
            </div>

            <!-- Asset Code below image -->
            <div class="mt-8 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
              <Label class="text-sm font-medium text-gray-500 dark:text-gray-400">Asset Code</Label>
              <div class="mt-1 text-xl font-mono font-semibold">
                {{ asset.asset_code }}
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN: ASSET DETAILS (8 columns on large screens) -->
        <div class="lg:col-span-8">
          <!-- Asset Information Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- Asset Name -->
            <div>
              <Label>Asset Name</Label>
              <div class="form-dark">
                {{ asset.asset_name?.assetname }}
              </div>
            </div>

            <!-- Brand -->
            <div>
              <Label>Brand</Label>
              <div class="form-dark">
                {{ asset.brand }}
              </div>
            </div>

            <!-- Useful Life -->
            <div>
              <Label>Useful Life (Years)</Label>
              <div class="form-dark">
                {{ asset.asset_name?.usefullife }}
              </div>
            </div>

            <!-- Serial Number -->
            <div>
              <Label>Serial Number</Label>
              <div class="form-dark">
                {{ asset.serial_number || '—' }}
              </div>
            </div>

            <!-- Purchase Cost -->
            <div>
              <Label>Purchase Cost</Label>
              <div class="form-dark">
                {{ asset.purchase_cost ?? '—' }}
              </div>
            </div>

            <!-- Purchase Date -->
            <div>
              <Label>Purchase Date</Label>
              <div class="form-dark">
                {{ asset.purchase_date }}
              </div>
            </div>

            <!-- Warranty Duration -->
            <div>
              <Label>Warranty Duration (Months)</Label>
              <div class="form-dark">
                {{ asset.warranty_duration ?? '—' }}
              </div>
            </div>

            <!-- Warranty End Date -->
            <div>
              <Label>Warranty End Date</Label>
              <div class="form-dark">
                {{ asset.warranty_end_date ?? '—' }}
              </div>
            </div>

            <!-- Location -->
            <div>
              <Label>Location</Label>
              <div class="form-dark">
                {{ asset.asset_location?.locationname }}
              </div>
            </div>

            <!-- Condition -->
            <div>
              <Label>Condition</Label>
              <div class="form-dark">
                {{ asset.condition?.conditionname }}
              </div>
            </div>

            <!-- Supplier -->
            <div>
              <Label>Supplier</Label>
              <div class="form-dark">
                {{ asset.supplier?.suppliername }}
              </div>
            </div>

            <!-- Maintenance Provider -->
            <div>
              <Label>Maintenance Provider</Label>
              <div class="form-dark">
                {{ asset.maintenance_provider?.providername }}
              </div>
            </div>

            <!-- Status -->
            <div>
              <Label>Status</Label>
              <div class="form-dark capitalize">
                {{ asset.status.replace('_', ' ') }}
              </div>
            </div>

          </div> <!-- End of Asset Information Grid -->

          <!-- System Information -->
          <div class="md:col-span-3 border-t pt-6 mt-6">
            <h3 class="text-sm font-semibold mb-4 text-muted-foreground">
              System Information
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <!-- 👤 Added By -->
              <div>
                <Label>👤 Added By</Label>
                <div class="form-dark">
                  {{ asset.user?.name ?? '—' }}
                </div>
              </div>

              <!-- 🏢 Unit -->
              <div>
                <Label>🏢 Unit</Label>
                <div class="form-dark">
                  {{ asset.unit?.name ?? '—' }}
                </div>
              </div>

              <!-- 🏛 Organization -->
              <div>
                <Label>🏛 Organization</Label>
                <div class="form-dark">
                  {{ asset.organization?.name ?? '—' }}
                </div>
              </div>
            </div>
          </div>

          <!-- ACTIONS -->
          <div class="flex justify-end gap-4 border-t pt-6 mt-6">
            <Link :href="index().url">
              <Button variant="secondary">Back</Button>
            </Link>


          </div>
        </div> <!-- End of Right Column -->

      </div> <!-- End of Two-column layout -->

    </FormCard>
  </AppLayout>
</template>
