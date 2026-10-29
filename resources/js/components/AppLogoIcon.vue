<script setup lang="ts">
import type { HTMLAttributes } from 'vue'

defineOptions({
    inheritAttrs: false,
})

interface Props {
    className?: HTMLAttributes['class']
}

defineProps<Props>()

const staticIcon = '/images/ghadams_logo.jpg'
const altText = 'GHADAMS Logo'
</script>

<template>
    <img
        :src="staticIcon"
        :alt="altText"
        :class="className"
        v-bind="$attrs"
    />
</template>
