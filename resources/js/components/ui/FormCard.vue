<script setup lang="ts">
defineProps<{
  title?: string   // ✅ optional now
  maxWidth?: string
}>()

</script>

<template>
  <div
    class="mx-6 mt-6 w-full rounded-xl border border-slate-800
           bg-gradient-to-br from-[#0b1437] via-[#0f1c4d] to-[#0b1437]
           p-8 shadow-lg"
    :class="maxWidth"
  >
    <h2 class="mb-6 text-lg font-semibold text-white">
      {{ title }}
    </h2>

    <slot />
  </div>
</template>
