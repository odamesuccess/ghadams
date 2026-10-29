<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { ChevronDown } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar'
import type { NavItem } from '@/types'

const props = defineProps<{ items: NavItem[] }>()
const page = usePage()
const openGroup = ref<string | null>(null)

// Wayfinder links may be strings or route objects with a `url` property.
const itemUrl = (item: NavItem): string => {
    if (typeof item.href === 'string') return item.href
    if (item.href && typeof item.href === 'object' && 'url' in item.href) {
        const url = (item.href as { url?: unknown }).url
        return typeof url === 'string' ? url : ''
    }
    return ''
}

const cleanPath = (url: string): string => {
    if (!url || url === '#') return ''
    try {
        const path = new URL(url, 'http://localhost').pathname
        return path === '/' ? '/' : path.replace(/\/+$/, '')
    } catch {
        return url.split('?')[0]?.replace(/\/+$/, '') || '/'
    }
}

const currentPath = computed(() => cleanPath(page.url))

// Prefer the most specific link so /assets does not override /assets/123.
const selectedItem = computed<NavItem | null>(() => {
    let best: NavItem | null = null
    let bestLength = -1
    for (const entry of props.items) {
        const candidates = entry.children?.length ? entry.children : [entry]
        for (const item of candidates) {
            const path = cleanPath(itemUrl(item))
            if (!path || path === '#') continue
            const matches = currentPath.value === path ||
                (path !== '/' && currentPath.value.startsWith(`${path}/`))
            if (matches && path.length > bestLength) {
                best = item
                bestLength = path.length
            }
        }
    }
    return best
})

const isSelected = (item: NavItem): boolean => selectedItem.value === item
const groupContainsSelected = (item: NavItem): boolean =>
    Boolean(item.children?.some(isSelected))

// Expand the group containing the current page on first load and navigation.
watch(
    () => [page.url, props.items] as const,
    () => {
        const group = props.items.find(groupContainsSelected)
        if (group) openGroup.value = group.title
    },
    { immediate: true, flush: 'post' },
)

const toggle = (title: string): void => {
    openGroup.value = openGroup.value === title ? null : title
}
</script>

<template>
    <SidebarMenu>
        <template v-for="item in items" :key="item.title">
            <!-- Existing top-level links and their permission-filtered order. -->
            <SidebarMenuItem v-if="!item.children">
                <SidebarMenuButton as-child :is-active="isSelected(item)">
                    <Link
                        :href="item.href"
                        class="flex items-center gap-2"
                        :aria-current="isSelected(item) ? 'page' : undefined"
                    >
                        <component v-if="item.icon" :is="item.icon" class="h-4 w-4" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <!-- A group remains expanded when one of its children is active. -->
            <SidebarMenuItem v-else>
                <SidebarMenuButton
                    :is-active="groupContainsSelected(item)"
                    :aria-expanded="openGroup === item.title"
                    @click="toggle(item.title)"
                >
                    <component v-if="item.icon" :is="item.icon" class="mr-2 h-4 w-4" />
                    <span>{{ item.title }}</span>
                    <ChevronDown
                        class="ml-auto h-4 w-4 transition-transform"
                        :class="{ 'rotate-180': openGroup === item.title }"
                    />
                </SidebarMenuButton>

                <div v-if="openGroup === item.title" class="ml-6 mt-1 space-y-1">
                    <SidebarMenuButton
                        v-for="child in item.children"
                        :key="child.title"
                        as-child
                        size="sm"
                        :is-active="isSelected(child)"
                    >
                        <Link
                            :href="child.href"
                            class="flex items-center gap-2"
                            :aria-current="isSelected(child) ? 'page' : undefined"
                        >
                            <component v-if="child.icon" :is="child.icon" class="h-4 w-4" />
                            <span>{{ child.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </div>
            </SidebarMenuItem>
        </template>
    </SidebarMenu>
</template>
