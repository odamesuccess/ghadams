<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar'
import { Link } from '@inertiajs/vue3'
import { toUrl } from '@/lib/utils'
import { type NavItem } from '@/types'

interface Props {
    items: NavItem[]
    class?: string
}

defineProps<Props>()

const resolveHref = (item: NavItem): string => {
    if (!item.href) {
        return '#'
    }

    return toUrl(item.href)
}
</script>

<template>
    <SidebarGroup
        :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`"
    >
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem
                    v-for="item in items"
                    :key="item.title"
                >
                    <SidebarMenuButton
                        class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                        as-child
                    >
                        <Link :href="resolveHref(item)">
                            <component
                                v-if="item.icon"
                                :is="item.icon"
                            />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
