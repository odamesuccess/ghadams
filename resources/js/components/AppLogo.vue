<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLogoIcon from '@/components/AppLogoIcon.vue'
import type { AppPageProps } from '@/types'
import { ref, onMounted, onUnmounted } from 'vue'

const time = ref('')

const updateTime = () => {
  const now = new Date()
  time.value = now.toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  })
}

let timer: number

onMounted(() => {
  updateTime()
  timer = window.setInterval(updateTime, 1000)
})

onUnmounted(() => {
  clearInterval(timer)
})


const page = usePage<AppPageProps>()
const user = computed(() => page.props.auth.user)

const roleLabel = computed(() =>
  user.value?.roles?.[0]?.name?.replace('.', ' ') ?? 'User'
)

const unitLabel = computed(() =>
  user.value?.unit?.name ?? ''
)


</script>


<template>
  <div class="flex items-center gap-2">
    <!-- LOGO -->
    <div
      class="flex aspect-square size-12 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
      <AppLogoIcon class="size-10 fill-current text-white dark:text-black" />
    </div>

   <div class="grid flex-1 text-left leading-tight">
  <span class="truncate text-sm font-semibold">
    Ghadams System
  </span>
 <!-- ROLE -->
  <span class="truncate text-xs ">
    {{ roleLabel }}
  </span>
  <!-- CLOCK -->
  <span class="truncate text-xs text-muted-foreground">
    {{ time }}
  </span>


</div>

  </div>
</template>

