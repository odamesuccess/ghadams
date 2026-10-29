<script setup lang="ts">
/* =======================
   Imports
======================= */
import AppLayout from '@/layouts/AppLayout.vue';
import { edit, index } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';
import { profileImageUrl } from '@/utils/profileImage';
import {
    Building,
    Cake,
    Calendar,
    Clock,
    Download,
    Mail,
    Pencil,
    Phone,
    Shield,
    User,
} from 'lucide-vue-next';

/* =======================
   Types
======================= */
interface Role {
    id: number;
    name: string;
    description?: string;
}

interface Unit {
    id: number;
    name: string;
    description?: string;
}

interface User {
    id: number;
    name: string;
    sex?: string | null;
    user_status?: string | null;
    email: string;
    contact_number?: string | null;
    date_of_birth?: string | null;
    profile_image?: string | null;
    created_at: string;
    updated_at: string;
    roles: Role[];
    unit?: Unit | null;
}

/* =======================
   Props
======================= */
const props = defineProps<{ user: User }>();

/* =======================
   Helpers
======================= */
const formatDate = (value?: string | null) =>
    value
        ? new Date(value).toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '—';

const formatDateTime = (value?: string | null) =>
    value
        ? new Date(value).toLocaleString(undefined, {
              month: 'short',
              day: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

const computeAge = (dob?: string | null) => {
    if (!dob) return '—';

    const d = new Date(dob);
    if (isNaN(d.getTime())) return '—';

    const t = new Date();
    let age = t.getFullYear() - d.getFullYear();

    if (
        t.getMonth() < d.getMonth() ||
        (t.getMonth() === d.getMonth() && t.getDate() < d.getDate())
    ) {
        age--;
    }

    return `${age} years`;
};

const userInitials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

const statusConfig = (status?: string | null) => {
    switch (status) {
        case 'Active':
            return {
                class: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                icon: '●',
            };

        case 'Inactive':
            return {
                class: 'bg-amber-50 text-amber-700 border-amber-200',
                icon: '○',
            };

        case 'IsSystem':
            return {
                class: 'bg-rose-50 text-rose-700 border-rose-200',
                icon: '⚠',
            };

        default:
            return {
                class: 'bg-slate-50 text-slate-700 border-slate-200',
                icon: '—',
            };
    }
};

const sexConfig = (sex?: string | null) => {
    switch (sex?.toLowerCase()) {
        case 'male':
            return { class: 'bg-blue-50 text-blue-700', icon: '♂' };

        case 'female':
            return { class: 'bg-pink-50 text-pink-700', icon: '♀' };

        default:
            return { class: 'bg-slate-50 text-slate-700', icon: '—' };
    }
};

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/' },
    { title: 'Users', href: index().url },
    { title: props.user.name, href: '#' },
];
</script>

<template>
    <Head :title="`${user.name} - User Profile`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Action Bar -->
        <div
            class="mt-4 flex flex-col items-start justify-between gap-4 px-4 sm:flex-row sm:items-center sm:px-6"
        >
            <div class="flex items-center gap-3">
                <div class="h-4 w-px bg-slate-200 dark:bg-slate-700"></div>

                <h1
                    class="text-lg font-semibold text-slate-800 dark:text-slate-200"
                >
                    User Profile
                </h1>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link :href="index().url" class="btn-secondary">
                    ← Back to Users
                </Link>

                <Button variant="outline" class="gap-2" as-child>
                    <a
                        :href="`/users/${user.id}/pdf`"
                        target="_blank"
                        rel="noopener"
                    >
                        <Download class="h-4 w-4" />
                        Export
                    </a>
                </Button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="space-y-6 p-4 sm:p-6">
            <!-- Profile Header Card -->
            <div
                class="overflow-hidden rounded-2xl border border-slate-200 bg-linear-to-r from-white to-slate-50 shadow-sm dark:border-slate-700 dark:from-slate-900 dark:to-slate-800"
            >
                <div class="p-6 md:p-8">
                    <div
                        class="flex flex-col items-start gap-6 lg:flex-row lg:items-center lg:gap-8"
                    >
                        <!-- Profile image with initials fallback -->
                        <div class="relative">
                            <img
                                v-if="profileImageUrl(user.profile_image)"
                                :src="profileImageUrl(user.profile_image)!"
                                :alt="`${user.name} profile image`"
                                class="h-32 w-32 rounded-2xl border-4 border-white object-cover shadow-lg lg:h-40 lg:w-40 dark:border-slate-800"
                            />

                            <div
                                v-else
                                class="relative flex h-32 w-32 items-center justify-center overflow-hidden rounded-2xl border-4 border-white bg-linear-to-br from-slate-600 to-slate-800 text-4xl font-bold text-white shadow-lg lg:h-40 lg:w-40 dark:border-slate-800"
                            >
                                {{ userInitials(user.name) }}
                            </div>

                            <!-- Status Badge -->
                            <div class="absolute -right-2 -bottom-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium"
                                    :class="
                                        statusConfig(user.user_status).class
                                    "
                                >
                                    <span class="text-[10px]">
                                        {{
                                            statusConfig(user.user_status).icon
                                        }}
                                    </span>
                                    {{ user.user_status ?? 'Unknown' }}
                                </span>
                            </div>
                        </div>

                        <!-- Identity -->
                        <div class="flex-1 space-y-4">
                            <div>
                                <h2
                                    class="text-2xl font-bold text-slate-900 lg:text-3xl dark:text-white"
                                >
                                    {{ user.name }}
                                </h2>

                                <p
                                    class="mt-1 text-slate-600 dark:text-slate-300"
                                >
                                    {{ user.email }}
                                </p>
                            </div>

                            <!-- Quick Stats -->
                            <div class="flex flex-wrap gap-4">
                                <div
                                    class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800"
                                >
                                    <Building class="h-4 w-4 text-slate-500" />
                                    <span class="text-sm font-medium">
                                        {{ user.unit?.name ?? 'No Unit' }}
                                    </span>
                                </div>

                                <div
                                    class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-800"
                                >
                                    <Cake class="h-4 w-4 text-slate-500" />
                                    <span class="text-sm font-medium">
                                        {{ computeAge(user.date_of_birth) }}
                                    </span>
                                </div>

                                <div
                                    class="flex items-center gap-2 rounded-lg border px-3 py-2"
                                    :class="sexConfig(user.sex).class"
                                >
                                    <User class="h-4 w-4" />
                                    <span class="text-sm font-medium">
                                        {{ user.sex ?? 'Not specified' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Roles -->
                            <div v-if="user.roles.length > 0">
                                <h3
                                    class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    <Shield class="h-4 w-4" />
                                    User Roles
                                </h3>

                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="rounded-lg border border-blue-200 bg-linear-to-r from-blue-500/10 to-blue-600/10 px-3 py-1.5 text-xs font-medium text-blue-700 dark:border-blue-800 dark:text-blue-300"
                                    >
                                        {{ role.name.replace(/_/g, ' ') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Personal Information -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Contact Card -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h3
                            class="mb-4 border-b border-slate-200 pb-3 text-lg font-semibold text-slate-900 dark:border-slate-700 dark:text-white"
                        >
                            Contact Information
                        </h3>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="space-y-1">
                                <div
                                    class="flex items-center gap-2 text-slate-600 dark:text-slate-400"
                                >
                                    <Mail class="h-4 w-4" />
                                    <span class="text-sm font-medium"
                                        >Email Address</span
                                    >
                                </div>

                                <p
                                    class="pl-6 font-medium text-slate-900 dark:text-white"
                                >
                                    {{ user.email }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <div
                                    class="flex items-center gap-2 text-slate-600 dark:text-slate-400"
                                >
                                    <Phone class="h-4 w-4" />
                                    <span class="text-sm font-medium"
                                        >Contact Number</span
                                    >
                                </div>

                                <p
                                    class="pl-6 font-medium text-slate-900 dark:text-white"
                                >
                                    {{ user.contact_number ?? 'Not provided' }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <div
                                    class="flex items-center gap-2 text-slate-600 dark:text-slate-400"
                                >
                                    <Calendar class="h-4 w-4" />
                                    <span class="text-sm font-medium"
                                        >Date of Birth</span
                                    >
                                </div>

                                <p
                                    class="pl-6 font-medium text-slate-900 dark:text-white"
                                >
                                    {{ formatDate(user.date_of_birth) }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <div
                                    class="flex items-center gap-2 text-slate-600 dark:text-slate-400"
                                >
                                    <Cake class="h-4 w-4" />
                                    <span class="text-sm font-medium">Age</span>
                                </div>

                                <p
                                    class="pl-6 font-medium text-slate-900 dark:text-white"
                                >
                                    {{ computeAge(user.date_of_birth) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h3
                            class="mb-4 border-b border-slate-200 pb-3 text-lg font-semibold text-slate-900 dark:border-slate-700 dark:text-white"
                        >
                            System Information
                        </h3>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="space-y-1">
                                <div
                                    class="flex items-center gap-2 text-slate-600 dark:text-slate-400"
                                >
                                    <Clock class="h-4 w-4" />
                                    <span class="text-sm font-medium"
                                        >Account Created</span
                                    >
                                </div>

                                <p
                                    class="pl-6 font-medium text-slate-900 dark:text-white"
                                >
                                    {{ formatDateTime(user.created_at) }}
                                </p>

                                <p
                                    class="pl-6 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Member for
                                    {{
                                        Math.floor(
                                            (Date.now() -
                                                new Date(
                                                    user.created_at,
                                                ).getTime()) /
                                                (1000 * 60 * 60 * 24),
                                        )
                                    }}
                                    days
                                </p>
                            </div>

                            <div class="space-y-1">
                                <div
                                    class="flex items-center gap-2 text-slate-600 dark:text-slate-400"
                                >
                                    <Clock class="h-4 w-4" />
                                    <span class="text-sm font-medium"
                                        >Last Updated</span
                                    >
                                </div>

                                <p
                                    class="pl-6 font-medium text-slate-900 dark:text-white"
                                >
                                    {{ formatDateTime(user.updated_at) }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <div
                                    class="flex items-center gap-2 text-slate-600 dark:text-slate-400"
                                >
                                    <Building class="h-4 w-4" />
                                    <span class="text-sm font-medium"
                                        >Assigned Unit</span
                                    >
                                </div>

                                <p
                                    class="pl-6 font-medium text-slate-900 dark:text-white"
                                >
                                    {{ user.unit?.name ?? '--' }}
                                </p>

                                <p
                                    v-if="user.unit?.description"
                                    class="pl-6 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{ user.unit.description }}
                                </p>
                            </div>

                            <div class="space-y-1">
                                <div
                                    class="flex items-center gap-2 text-slate-600 dark:text-slate-400"
                                >
                                    <User class="h-4 w-4" />
                                    <span class="text-sm font-medium"
                                        >User ID</span
                                    >
                                </div>

                                <p
                                    class="pl-6 font-medium text-slate-900 dark:text-white"
                                >
                                    #{{ user.id.toString().padStart(6, '0') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Side Panel -->
                <div class="space-y-6">
                    <!-- Quick Actions -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h3
                            class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            Quick Actions
                        </h3>

                        <div class="space-y-3">
                            <Link :href="edit(user.id).url" class="block">
                                <Button class="w-full justify-start gap-3">
                                    <Pencil class="h-4 w-4" />
                                    Edit Profile
                                </Button>
                            </Link>

                            <Button
                                variant="outline"
                                class="w-full justify-start gap-3"
                                as-child
                            >
                                <a
                                    :href="`https://mail.google.com/mail/?view=cm&fs=1&to=${user.email}&su=${encodeURIComponent('Hello ' + user.name)}&body=${encodeURIComponent('Hi ' + user.name)}`"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <Mail class="h-4 w-4" />
                                    Send via Gmail
                                </a>
                            </Button>

                            <Button
                                variant="outline"
                                class="w-full justify-start gap-3"
                                as-child
                                :disabled="!user.contact_number"
                            >
                                <a
                                    :href="
                                        user.contact_number
                                            ? `tel:${user.contact_number}`
                                            : '#'
                                    "
                                    :class="{
                                        'pointer-events-none opacity-50':
                                            !user.contact_number,
                                    }"
                                >
                                    <Phone class="h-4 w-4" />
                                    Call User
                                </a>
                            </Button>
                        </div>
                    </div>

                    <!-- Status Summary -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    >
                        <h3
                            class="mb-4 text-lg font-semibold text-slate-900 dark:text-white"
                        >
                            Status Summary
                        </h3>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-400"
                                >
                                    Account Status
                                </span>

                                <span
                                    class="rounded-full px-3 py-1 text-xs font-medium"
                                    :class="
                                        statusConfig(user.user_status).class
                                    "
                                >
                                    {{ user.user_status ?? 'Unknown' }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-400"
                                >
                                    Role Count
                                </span>

                                <span class="font-medium">
                                    {{ user.roles.length }}
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span
                                    class="text-sm text-slate-600 dark:text-slate-400"
                                >
                                    Profile Complete
                                </span>

                                <span class="font-medium">
                                    {{
                                        Math.round(
                                            ([
                                                user.name,
                                                user.email,
                                                user.contact_number,
                                                user.date_of_birth,
                                                user.unit,
                                            ].filter(Boolean).length /
                                                5) *
                                                100,
                                        )
                                    }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
* {
    transition:
        background-color 0.2s ease,
        border-color 0.2s ease;
}
</style>
