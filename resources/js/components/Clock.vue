<script setup lang="ts">
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
</script>

<template>
  <span class="text-sm text-muted-foreground font-medium">
    {{ time }}
  </span>
</template>
