<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { confirmLogout } from '@/utils/sweetalert';
import { LogOut, User as UserIcon } from 'lucide-vue-next';

import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

interface Props {
    user: User;
}

const handleLogout = async () => {
    const confirmed = await confirmLogout();
    if (!confirmed) return;

    router.flushAll();
    router.post(logout().url);
};

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>

    <DropdownMenuSeparator />

    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="flex w-full items-center cursor-pointer"
                :href="edit()"
                prefetch
            >
                <UserIcon class="mr-2 h-4 w-4" />
                User Details
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>

    <DropdownMenuSeparator />

    <DropdownMenuItem :as-child="true">
        <button
            type="button"
            class="flex w-full items-center cursor-pointer text-red-500 hover:bg-red-500/10 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300"
            @click="handleLogout"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </button>
    </DropdownMenuItem>
</template>
