<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

defineProps<{
    compact?: boolean;
    title?: string;
    description?: string;
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :compact="compact">
        <slot />
    </AuthLayout>
</template>
