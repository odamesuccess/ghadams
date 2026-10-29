<script setup lang="ts">
import { watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { toast } from '@/utils/sweetalert'

interface Props {
  breadcrumbs?: BreadcrumbItem[]
}

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
})

const page = usePage()

watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) {
      toast.fire({ icon: 'success', title: flash.success })
    }
    if (flash?.error) {
      toast.fire({ icon: 'error', title: flash.error })
    }
    if (flash?.warning) {
      toast.fire({ icon: 'warning', title: flash.warning })
    }
    if (flash?.info) {
      toast.fire({ icon: 'info', title: flash.info })
    }
  },
  { deep: true }
)
</script>


<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <slot />
  </AppLayout>
</template>
