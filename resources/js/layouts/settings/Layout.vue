<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

import Heading from '@/components/Heading.vue'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { useActiveUrl } from '@/composables/useActiveUrl'
import { toUrl } from '@/lib/utils'
import { edit as editAppearance } from '@/routes/appearance'
import { edit as editProfile } from '@/routes/profile'
import { show as showTwoFactor } from '@/routes/two-factor'

import { type NavItem } from '@/types'

const sidebarNavItems: NavItem[] = [
    { title: 'Profile', href: editProfile() },
    { title: 'Two-Factor Authentication', href: showTwoFactor() },
]

const { urlIsActive } = useActiveUrl()
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="Settings" description="Manage your profile and account settings" />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <!-- SIDEBAR -->
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-1" aria-label="Settings">
                    <Button v-for="item in sidebarNavItems" :key="item.href ? toUrl(item.href) : item.title"
                        variant="ghost" :class="[
                            'w-full justify-start',
                            { 'bg-muted': item.href && urlIsActive(item.href) },
                        ]" as-child>
                        <Link v-if="item.href" :href="item.href">
                            {{ item.title }}
                        </Link>

                        <!-- fallback for items without href (safety) -->
                        <span v-else>
                            {{ item.title }}
                        </span>
                    </Button>

                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <!-- CONTENT (FIXED) -->
            <div class="flex-1">
                <section class="mx-auto w-full max-w-[90vw] space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
