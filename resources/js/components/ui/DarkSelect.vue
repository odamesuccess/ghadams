<script setup lang="ts">
defineProps<{
  modelValue: string | number | null
  options: { value: string | number | null; label: string }[]
  placeholder?: string
}>()

defineEmits(['update:modelValue'])
</script>

<template>
  <div class="relative">
    <select
      :value="modelValue"
      @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
      class="w-full appearance-none rounded-md
             border border-slate-700
             bg-slate-950
             px-3 py-2 pr-10
             text-sm text-slate-100
             focus:border-blue-500
             focus:ring-2 focus:ring-blue-500/40"
    >
      <option
        v-if="placeholder"
        disabled
        value=""
        class="bg-slate-900 text-slate-400"
      >
        {{ placeholder }}
      </option>

      <option
        v-for="opt in options"
        :key="opt.value ?? 'null'"
        :value="opt.value ?? ''"
        class="bg-slate-900 text-slate-100"
      >
        {{ opt.label }}
      </option>
    </select>

    <!-- Custom caret -->
    <div
      class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400"
    >
      ▾
    </div>
  </div>
</template>
