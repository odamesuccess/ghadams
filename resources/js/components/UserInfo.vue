<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { profileImageUrl } from '@/utils/profileImage';

interface Props {
    user: User;
    showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();
const imageFailed = ref(false);

watch(
    () => props.user.profile_image,
    () => {
        imageFailed.value = false;
    },
);

const avatarUrl = computed(() => {
    if (imageFailed.value) {
        return null;
    }

    return profileImageUrl(props.user.profile_image);
});
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage
            v-if="avatarUrl"
            :src="avatarUrl"
            :alt="user.name"
            @error="imageFailed = true"
        />
        <AvatarFallback
            class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white"
        >
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">
            {{ user.email }}
        </span>
    </div>
</template>
