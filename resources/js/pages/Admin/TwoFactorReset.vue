<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';

interface UserItem {
    id: number;
    name: string;
    email: string;
    user_status: string | null;
    two_factor_enabled: boolean;
}

const props = defineProps<{
    users: UserItem[];
}>();

const requestedUserId =
    typeof window === 'undefined'
        ? ''
        : (new URLSearchParams(window.location.search).get('user') ?? '');

const selectedId = ref<string>(requestedUserId);

const selectedUser = computed<UserItem | null>(() => {
    return (
        props.users.find(
            (user) => String(user.id) === selectedId.value,
        ) ?? null
    );
});

const form = useForm({
    email: '',
    password: '',
});

watch(selectedId, () => {
    form.clearErrors();
    form.email = '';
    form.password = '';
});

const submit = (): void => {
    if (!selectedUser.value) {
        return;
    }

    form.post(
        `/users/${selectedUser.value.id}/two-factor/reset`,
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedId.value = '';
                form.reset();
            },
        },
    );
};
</script>

<template>
    <Head title="Two-Factor Authentication Reset" />

    <AppLayout>
        <div class="mx-auto w-full max-w-4xl p-4 sm:p-6">
            <div
                class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
            >
                <div class="border-b border-border p-5">
                    <h1 class="text-xl font-bold text-foreground">
                        Two-Factor Authentication Reset
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Securely reset another user's authenticator.
                        The user will be required to enrol again.
                    </p>
                </div>

                <form
                    class="space-y-5 p-5"
                    @submit.prevent="submit"
                >
                    <div>
                        <label
                            for="target-user"
                            class="mb-1.5 block text-sm font-medium text-foreground"
                        >
                            User
                        </label>

                        <select
                            id="target-user"
                            v-model="selectedId"
                            class="h-11 w-full rounded-lg border border-input bg-background px-3 text-foreground"
                            required
                        >
                            <option value="">
                                Select user
                            </option>

                            <option
                                v-for="user in users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }} — {{ user.email }}
                                —
                                {{
                                    user.two_factor_enabled
                                        ? '2FA Confirmed'
                                        : '2FA Not Configured'
                                }}
                            </option>
                        </select>
                    </div>

                    <template v-if="selectedUser">
                        <div
                            class="rounded-xl border border-border bg-muted/30 p-4"
                        >
                            <div class="font-semibold text-foreground">
                                {{ selectedUser.name }}
                            </div>

                            <div class="text-sm text-muted-foreground">
                                {{ selectedUser.email }}
                            </div>

                            <div class="mt-2 text-xs font-medium">
                                {{
                                    selectedUser.two_factor_enabled
                                        ? 'Two-factor authentication is currently confirmed.'
                                        : 'Two-factor authentication is currently not confirmed.'
                                }}
                            </div>
                        </div>

                        <div>
                            <label
                                for="target-email"
                                class="mb-1.5 block text-sm font-medium text-foreground"
                            >
                                Confirm exact target email
                            </label>

                            <input
                                id="target-email"
                                v-model="form.email"
                                type="email"
                                autocomplete="off"
                                class="h-11 w-full rounded-lg border border-input bg-background px-3 text-foreground"
                                :placeholder="selectedUser.email"
                                required
                            />

                            <p
                                v-if="form.errors.email"
                                class="mt-1 text-sm text-destructive"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="admin-password"
                                class="mb-1.5 block text-sm font-medium text-foreground"
                            >
                                Your administrator password
                            </label>

                            <input
                                id="admin-password"
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
                                class="h-11 w-full rounded-lg border border-input bg-background px-3 text-foreground"
                                required
                            />

                            <p
                                v-if="form.errors.password"
                                class="mt-1 text-sm text-destructive"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div
                            class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm"
                        >
                            Resetting will permanently invalidate the
                            user's current QR secret and recovery codes.
                            GhanaDAMS will force fresh enrolment.
                        </div>

                        <button
                            type="submit"
                            class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-destructive px-4 font-semibold text-destructive-foreground disabled:opacity-50"
                            :disabled="
                                form.processing ||
                                form.email.length === 0 ||
                                form.password.length === 0
                            "
                        >
                            {{
                                form.processing
                                    ? 'Resetting...'
                                    : 'Reset Two-Factor Authentication'
                            }}
                        </button>
                    </template>
                </form>
            </div>
        </div>
    </AppLayout>
</template>