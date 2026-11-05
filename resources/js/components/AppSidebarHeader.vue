<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import GlobalNotificationBell from '@/components/GlobalNotificationBell.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Set for admins when synthetic demonstration records are in the register.
const demoDataset = computed(
    () => usePage().props.demoDataset as { label: string; assets: number } | null | undefined,
);

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-10 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <span
                v-if="demoDataset"
                class="rounded-full border border-amber-400/60 bg-amber-400/15 px-2.5 py-0.5 text-[11px] font-semibold tracking-wide text-amber-600 dark:text-amber-300"
                :title="`${demoDataset.label}: ${demoDataset.assets.toLocaleString()} synthetic assets are in this register. They are not real GES records.`"
            >
                DEMO DATA
            </span>
            <GlobalNotificationBell />
        </div>
    </header>
</template>
