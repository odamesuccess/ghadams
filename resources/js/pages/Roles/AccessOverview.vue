<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface LandingOption {
    route: string;
    url: string;
    label: string;
    permissions: string[];
}

interface AssignedUser {
    id: number;
    name: string;
    email: string;
}

interface RoleItem {
    id: number;
    name: string;
    is_special_role: boolean;
    landing_route: string | null;
    landing_options: LandingOption[];
    permission_count: number;
    user_count: number;
    permissions: string[];
    users: AssignedUser[];
}

const props = defineProps<{
    roles: RoleItem[];
}>();

const states = reactive(
    Object.fromEntries(
        props.roles.map((role) => [
            role.id,
            {
                landing_route:
                    role.landing_route ?? '',
                processing:
                    false,
            },
        ]),
    ),
) as Record<
    number,
    {
        landing_route: string;
        processing: boolean;
    }
>;

const saveSpecialLanding = (
    role: RoleItem,
): void => {
    if (!role.is_special_role) {
        return;
    }

    const state =
        states[role.id];

    state.processing = true;

    router.patch(
        `/roles/${role.id}/special-settings`,
        {
            landing_route:
                state.landing_route,
        },
        {
            preserveScroll: true,

            onFinish: () => {
                state.processing =
                    false;
            },
        },
    );
};
</script>

<template>
    <Head title="Role Access Overview" />

    <AppLayout>
        <div
            class="mx-auto w-full space-y-4 p-4 lg:p-5"
        >
            <div
                class="flex items-center justify-between gap-3 rounded-xl border border-slate-700 bg-slate-900 p-4"
            >
                <div>
                    <div
                        class="text-xs font-semibold uppercase tracking-wide text-blue-400"
                    >
                        GhanaDAMS Access Control
                    </div>

                    <h1
                        class="mt-1 text-xl font-bold text-slate-100"
                    >
                        Role Access Overview
                    </h1>

                    <p
                        class="mt-1 text-sm text-slate-400"
                    >
                        One role per user. Roles marked Special
                        has a custom permission-backed landing page.
                        Super Admin and every other role use Dashboard.
                    </p>
                </div>

                <Link
                    href="/roles"
                    class="shrink-0 rounded-lg border border-slate-600 bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700"
                >
                    Manage Roles
                </Link>
            </div>

            <div
                class="grid grid-cols-2 gap-3 lg:grid-cols-4"
            >
                <div
                    class="rounded-xl border border-slate-700 bg-slate-900 p-3"
                >
                    <div class="text-xs text-slate-400">
                        Total Roles
                    </div>

                    <div class="mt-1 text-2xl font-bold text-white">
                        {{ roles.length }}
                    </div>
                </div>

                <div
                    class="rounded-xl border border-slate-700 bg-slate-900 p-3"
                >
                    <div class="text-xs text-slate-400">
                        Special Roles
                    </div>

                    <div class="mt-1 text-2xl font-bold text-amber-300">
                        {{
                            roles.filter(
                                (role) =>
                                    role.is_special_role,
                            ).length
                        }}
                    </div>
                </div>

                <div
                    class="rounded-xl border border-slate-700 bg-slate-900 p-3"
                >
                    <div class="text-xs text-slate-400">
                        Dashboard Roles
                    </div>

                    <div class="mt-1 text-2xl font-bold text-blue-300">
                        {{
                            roles.filter(
                                (role) =>
                                    !role.is_special_role,
                            ).length
                        }}
                    </div>
                </div>

                <div
                    class="rounded-xl border border-slate-700 bg-slate-900 p-3"
                >
                    <div class="text-xs text-slate-400">
                        User Assignments
                    </div>

                    <div class="mt-1 text-2xl font-bold text-emerald-300">
                        {{
                            roles.reduce(
                                (total, role) =>
                                    total +
                                    role.user_count,
                                0,
                            )
                        }}
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <section
                    v-for="role in roles"
                    :key="role.id"
                    class="rounded-xl border bg-slate-900"
                    :class="
                        role.is_special_role
                            ? 'border-amber-500/50'
                            : 'border-slate-700'
                    "
                >
                    <div
                        class="grid gap-4 p-4 lg:grid-cols-[220px_minmax(320px,1fr)_auto]"
                    >
                        <div>
                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <h2
                                    class="font-semibold text-slate-100"
                                >
                                    {{ role.name }}
                                </h2>

                                <span
                                    class="rounded-full px-2 py-1 text-xs font-semibold"
                                    :class="
                                        role.is_special_role
                                            ? 'bg-amber-500/15 text-amber-300'
                                            : 'bg-blue-500/10 text-blue-300'
                                    "
                                >
                                    {{
                                        role.is_special_role
                                            ? 'Special Role'
                                            : 'Dashboard'
                                    }}
                                </span>
                            </div>

                            <div
                                class="mt-2 text-xs text-slate-400"
                            >
                                {{ role.permission_count }}
                                permissions
                                ·
                                {{ role.user_count }}
                                users
                            </div>
                        </div>

                        <div>
                            <template
                                v-if="role.is_special_role"
                            >
                                <label
                                    class="mb-1 block text-xs font-semibold text-slate-400"
                                >
                                    Special Landing Page
                                </label>

                                <select
                                    v-model="
                                        states[role.id]
                                            .landing_route
                                    "
                                    class="h-9 w-full rounded-md border border-slate-700 bg-slate-950 px-3 text-sm text-slate-100"
                                >
                                    <option value="">
                                        Select permitted page
                                    </option>

                                    <option
                                        v-for="option in role.landing_options"
                                        :key="option.route"
                                        :value="option.route"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>

                                <p
                                    v-if="
                                        role.landing_options
                                            .length === 0
                                    "
                                    class="mt-1 text-xs text-amber-300"
                                >
                                    No suitable landing page is
                                    available yet. Assign permissions
                                    to this role first.
                                </p>

                                <p
                                    v-else
                                    class="mt-1 text-xs text-slate-500"
                                >
                                    Only pages backed by permissions
                                    assigned to this role appear.
                                </p>
                            </template>

                            <template v-else>
                                <label
                                    class="mb-1 block text-xs font-semibold text-slate-400"
                                >
                                    Landing Page
                                </label>

                                <div
                                    class="flex h-9 items-center rounded-md border border-slate-700 bg-slate-950 px-3 text-sm font-semibold text-blue-300"
                                >
                                    Dashboard
                                </div>
                            </template>
                        </div>

                        <div
                            class="flex items-end justify-end"
                        >
                            <button
                                v-if="role.is_special_role"
                                type="button"
                                class="h-9 rounded-md bg-blue-600 px-4 text-sm font-semibold text-white disabled:opacity-50"
                                :disabled="
                                    states[role.id]
                                        .processing ||
                                    !states[role.id]
                                        .landing_route
                                "
                                @click="
                                    saveSpecialLanding(role)
                                "
                            >
                                {{
                                    states[role.id]
                                        .processing
                                        ? 'Saving...'
                                        : 'Save Landing'
                                }}
                            </button>

                            <span
                                v-else
                                class="inline-flex h-9 items-center rounded-md border border-slate-700 bg-slate-800 px-3 text-xs font-semibold text-slate-300"
                            >
                                Fixed: Dashboard
                            </span>
                        </div>
                    </div>

                    <div
                        class="grid gap-4 border-t border-slate-700 p-4 lg:grid-cols-2"
                    >
                        <div>
                            <div
                                class="mb-2 text-sm font-semibold text-slate-200"
                            >
                                Permissions
                            </div>

                            <div
                                v-if="role.permissions.length"
                                class="flex flex-wrap gap-1.5"
                            >
                                <span
                                    v-for="permission in role.permissions"
                                    :key="permission"
                                    class="rounded-md bg-blue-500/10 px-2 py-1 text-xs text-blue-300"
                                >
                                    {{ permission }}
                                </span>
                            </div>

                            <div
                                v-else
                                class="text-sm text-slate-500"
                            >
                                No permissions assigned.
                            </div>
                        </div>

                        <div>
                            <div
                                class="mb-2 text-sm font-semibold text-slate-200"
                            >
                                Assigned Users
                            </div>

                            <div
                                v-if="role.users.length"
                                class="grid gap-2 sm:grid-cols-2"
                            >
                                <div
                                    v-for="user in role.users"
                                    :key="user.id"
                                    class="rounded-md border border-slate-700 bg-slate-950/50 p-2"
                                >
                                    <div
                                        class="text-sm font-semibold text-slate-200"
                                    >
                                        {{ user.name }}
                                    </div>

                                    <div
                                        class="text-xs text-slate-500"
                                    >
                                        {{ user.email }}
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="text-sm text-slate-500"
                            >
                                No users assigned.
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>