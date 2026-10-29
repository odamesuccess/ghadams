<script setup lang="ts">
/* =======================
   Imports
======================= */
import DataTable from '@/components/DataTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { create } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';
import { can } from '@/utils/permissions';
import { profileImageUrl } from '@/utils/profileImage';
import { confirmDelete, toast, confirmAction, promptText } from '@/utils/sweetalert';
import type { FormDataConvertible } from '@inertiajs/core';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { computed, h, ref, watch } from 'vue';

/* =======================
   Types
======================= */
interface Role {
    id: number;
    name: string;
}

interface Unit {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    sex?: string | null;
    unit?: Unit | null;
    user_status?: string | null;
    contact_number?: string | null;
    profile_image?: string | null;
    email: string;
    roles: Role[];
    account_expires_at?: string | null;
    expired_date?: string | null;
    trial_ends_at?: string | null;
    trial_status?: string | null;
    deactivation_reason?: string | null;
    last_login_at?: string | null;
    last_logout_at?: string | null;
    last_seen_at?: string | null;
    updated_at: string;
}

interface BulkPayload {
    [key: string]: FormDataConvertible;
    user_ids: number[];
    deactivation_reason?: string;
}

/* =======================
   Props
======================= */
const props = defineProps<{
    users: {
        data: User[];
    };
    filters: {
        search?: string;
    };
    authUserId: number;
}>();

/* =======================
   Search
======================= */
const search = ref(props.filters.search ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        router.get(
            '/users',
            { search: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 350);
});

/* =======================
   Bulk Selection
======================= */
const selectedUserIds = ref<number[]>([]);
const bulkProcessing = ref(false);

const isSystemUser = (user: User) => {
    return String(user.user_status ?? '').toLowerCase() === 'issystem';
};

const isSelf = (user: User) => {
    return Number(user.id) === Number(props.authUserId);
};

const isSelectable = (user: User) => {
    return !isSelf(user) && !isSystemUser(user);
};

const selectableUsers = computed(() => {
    return props.users.data.filter(isSelectable);
});

const selectedCount = computed(() => {
    return selectedUserIds.value.length;
});

const hasSelection = computed(() => {
    return selectedCount.value > 0;
});

const allSelectableSelected = computed(() => {
    return (
        selectableUsers.value.length > 0 &&
        selectableUsers.value.every((user) =>
            selectedUserIds.value.includes(user.id),
        )
    );
});

const toggleUserSelection = (user: User, checked: boolean) => {
    if (!isSelectable(user)) {
        return;
    }

    if (checked) {
        if (!selectedUserIds.value.includes(user.id)) {
            selectedUserIds.value = [...selectedUserIds.value, user.id];
        }

        return;
    }

    selectedUserIds.value = selectedUserIds.value.filter(
        (id) => id !== user.id,
    );
};

const toggleAllVisibleUsers = (checked: boolean) => {
    if (!checked) {
        selectedUserIds.value = selectedUserIds.value.filter(
            (id) => !selectableUsers.value.some((user) => user.id === id),
        );

        return;
    }

    const merged = new Set<number>(selectedUserIds.value);

    selectableUsers.value.forEach((user) => {
        merged.add(user.id);
    });

    selectedUserIds.value = Array.from(merged);
};

const clearSelection = () => {
    selectedUserIds.value = [];
};

/*
  Keep selected IDs clean after search/pagination reload.
  This removes selected IDs that are no longer visible on the current table.
*/
watch(
    () => props.users.data,
    (users) => {
        const visibleIds = new Set(users.map((user) => user.id));

        selectedUserIds.value = selectedUserIds.value.filter((id) =>
            visibleIds.has(id),
        );
    },
    { deep: true },
);

/* =======================
   Bulk Actions
======================= */
const runBulkAction = async (action: 'activate' | 'deactivate' | 'delete') => {
    if (!hasSelection.value) {
        toast.fire({
            icon: 'warning',
            title: 'Please select at least one user',
        });

        return;
    }

    if (bulkProcessing.value) {
        return;
    }

    const userIds = [...selectedUserIds.value];

    let url = '';

    const payload: BulkPayload = {
        user_ids: userIds,
    };

    if (action === 'activate') {
        const confirmed = await confirmAction({
            title: 'Activate Selected Users',
            text: `Make ${userIds.length} selected user(s) active?`,
            confirmText: 'Activate',
            icon: 'question',
        });
        if (!confirmed) {
            return;
        }

        url = '/users/bulk-activate';
    }

    if (action === 'deactivate') {
        const reason = await promptText({
            title: 'Deactivate Selected Users',
            text: `Enter a reason for deactivating ${userIds.length} selected user(s).`,
            placeholder: 'Bulk deactivated by administrator.',
            confirmText: 'Deactivate',
        });

        if (reason === null) {
            return;
        }

        url = '/users/bulk-deactivate';
        payload.deactivation_reason =
            reason || 'Bulk deactivated by administrator.';
    }

    if (action === 'delete') {
        const confirmed = await confirmDelete({
            title: 'Delete Selected Users',
            text: `Are you sure you want to delete ${userIds.length} selected user(s)? This action cannot be undone.`,
        });
        if (!confirmed) {
            return;
        }

        url = '/users/bulk-delete';
    }

    bulkProcessing.value = true;

    router.post(url, payload, {
        preserveScroll: true,

        onSuccess: () => {
            clearSelection();

            toast.fire({
                icon: 'success',
                title:
                    action === 'activate'
                        ? 'Selected users activated successfully'
                        : action === 'deactivate'
                          ? 'Selected users deactivated successfully'
                          : 'Selected users deleted successfully',
            });
        },

        onError: () => {
            toast.fire({
                icon: 'error',
                title:
                    action === 'activate'
                        ? 'Failed to activate selected users'
                        : action === 'deactivate'
                          ? 'Failed to deactivate selected users'
                          : 'Failed to delete selected users',
            });
        },

        onFinish: () => {
            bulkProcessing.value = false;
        },
    });
};

/* =======================
   Helpers
======================= */
const formatDate = (value?: string | null) => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const formatShortDate = (value?: string | null) => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const normaliseLabel = (value?: string | null) => {
    return String(value ?? '')
        .replace(/[._-]/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase())
        .trim();
};

const expiryDate = (user: User) => {
    if (isSystemUser(user)) return null;
    return (
        user.account_expires_at ??
        user.trial_ends_at ??
        user.expired_date ??
        null
    );
};

const accountStatus = (user: User) => {
    if (isSystemUser(user)) {
        return 'IsSystem';
    }

    const expiry = expiryDate(user);

    if (expiry && new Date(expiry) < new Date()) {
        return 'Expired';
    }

    return user.user_status ?? 'Active';
};

const userInitials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

const statusClass = (status: string) => {
    const lowered = status.toLowerCase();

    if (lowered === 'active' || lowered === 'issystem') {
        return 'border-emerald-500/30 bg-emerald-500/15 text-emerald-200';
    }

    if (lowered === 'expired' || lowered === 'inactive') {
        return 'border-red-500/30 bg-red-500/15 text-red-200';
    }

    return 'border-amber-500/30 bg-amber-500/15 text-amber-200';
};

/* =======================
   Table Columns
======================= */
const columns: ColumnDef<User>[] = [
    {
        id: 'select',
        header: () =>
            h('input', {
                type: 'checkbox',
                checked: allSelectableSelected.value,
                disabled: selectableUsers.value.length === 0,
                class: 'h-4 w-4 rounded border-slate-500 bg-slate-950 text-blue-600 disabled:cursor-not-allowed disabled:opacity-50',
                title:
                    selectableUsers.value.length === 0
                        ? 'No selectable users on this page'
                        : 'Select all visible users',
                onChange: (event: Event) =>
                    toggleAllVisibleUsers(
                        (event.target as HTMLInputElement).checked,
                    ),
            }),
        cell: ({ row }) => {
            const user = row.original;

            return h('input', {
                type: 'checkbox',
                checked: selectedUserIds.value.includes(user.id),
                disabled: !isSelectable(user),
                class: 'h-4 w-4 rounded border-slate-500 bg-slate-950 text-blue-600 disabled:cursor-not-allowed disabled:opacity-50',
                title: !isSelectable(user)
                    ? 'System/current user cannot be selected'
                    : 'Select user',
                onChange: (event: Event) =>
                    toggleUserSelection(
                        user,
                        (event.target as HTMLInputElement).checked,
                    ),
            });
        },
    },
    {
        header: 'Name',
        accessorKey: 'name',
        cell: ({ row }) => {
            const user = row.original;
            const imageUrl = profileImageUrl(user.profile_image);

            const avatar = imageUrl
                ? h('img', {
                      src: imageUrl,
                      alt: `${user.name} profile image`,
                      class: 'h-9 w-9 shrink-0 rounded-full border border-slate-700 object-cover',
                  })
                : h(
                      'div',
                      {
                          class: 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-700 bg-slate-800 text-xs font-bold text-slate-200',
                      },
                      userInitials(user.name),
                  );

            return h('div', { class: 'flex min-w-0 items-center gap-3' }, [
                avatar,
                h('div', { class: 'min-w-0' }, [
                    h(
                        'p',
                        {
                            class: 'truncate text-sm font-semibold text-slate-100',
                        },
                        user.name,
                    ),
                    user.contact_number
                        ? h(
                              'p',
                              {
                                  class: 'mt-0.5 truncate text-xs text-slate-500',
                              },
                              user.contact_number,
                          )
                        : null,
                ]),
            ]);
        },
    },
    {
        header: 'Sex',
        accessorKey: 'sex',
        cell: ({ getValue }) =>
            h(
                'span',
                { class: 'text-sm text-slate-200' },
                (getValue() as string | null) || '—',
            ),
    },
    {
        header: 'Unit Name',
        id: 'unit',
        cell: ({ row }) =>
            h(
                'span',
                {
                    class: 'block max-w-[220px] truncate text-sm text-slate-200',
                },
                row.original.unit?.name ?? '—',
            ),
    },
    {
        header: 'User Status',
        id: 'user_status',
        cell: ({ row }) => {
            const status = accountStatus(row.original);

            return h(
                'span',
                {
                    class: `inline-flex rounded border px-2 py-0.5 text-xs font-semibold ${statusClass(status)}`,
                },
                status,
            );
        },
    },
    {
        header: 'Email',
        accessorKey: 'email',
        cell: ({ getValue }) =>
            h(
                'span',
                {
                    class: 'block max-w-[230px] truncate text-sm text-slate-200',
                },
                getValue() as string,
            ),
    },
    {
        header: 'Trial / Expiry',
        id: 'trial_expiry',
        cell: ({ row }) => {
            const user = row.original;
            const expiry = expiryDate(user);
            const trialLabel = user.trial_status
                ? normaliseLabel(user.trial_status)
                : 'Account';

            return h('div', { class: 'space-y-1' }, [
                h(
                    'p',
                    { class: 'text-xs font-bold text-slate-100' },
                    isSystemUser(user) ? 'Exempt' : formatShortDate(expiry),
                ),
                h(
                    'span',
                    {
                        class: 'inline-flex rounded bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-200',
                    },
                    trialLabel,
                ),
            ]);
        },
    },
    {
        header: 'Roles',
        id: 'roles',
        cell: ({ row }) => {
            const roles = row.original.roles ?? [];

            if (!roles.length) {
                return h('span', { class: 'text-xs text-slate-400' }, '—');
            }

            const firstRole = normaliseLabel(roles[0].name);
            const extraCount = roles.length - 1;

            return h(
                'span',
                {
                    class: 'inline-flex items-center rounded bg-slate-700 px-2 py-0.5 text-xs text-white',
                    title: roles
                        .map((role) => normaliseLabel(role.name))
                        .join(', '),
                },
                extraCount > 0 ? `${firstRole} +${extraCount} more` : firstRole,
            );
        },
    },
    {
        header: 'Updated',
        accessorKey: 'updated_at',
        cell: ({ getValue }) =>
            h(
                'span',
                { class: 'text-sm text-slate-200' },
                formatDate(getValue() as string),
            ),
    },
    {
        header: 'Actions',
        id: 'actions',
        cell: ({ row }) => {
            const user = row.original;
            const actions = [];

            if (can('users.view')) {
                actions.push(
                    h(
                        Link,
                        {
                            href: `/users/${user.id}`,
                            class: 'rounded bg-slate-700 px-2 py-1 text-xs font-semibold text-white hover:bg-slate-600',
                        },
                        () => 'View',
                    ),
                );
            }

            if (can('users.edit')) {
                actions.push(
                    h(
                        Link,
                        {
                            href: `/users/${user.id}/edit`,
                            class: 'rounded bg-blue-600 px-2 py-1 text-xs font-semibold text-white hover:bg-blue-700',
                        },
                        () => 'Edit',
                    ),
                );
            }

            return h(
                'div',
                { class: 'flex flex-wrap justify-end gap-2' },
                actions,
            );
        },
    },
];

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users Management Dashboard', href: '/users' },
    { title: 'All Users', href: '/users' },
];
</script>

<template>
    <Head title="Users Management Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <section
            class="min-h-screen bg-[#020617] px-2 py-4 text-slate-100 sm:px-4 lg:px-5"
        >
            <!-- Action bar -->
            <div
                class="mb-3 flex flex-col gap-3 border-b border-slate-700/80 pb-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div
                    class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
                >
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search users..."
                        class="h-10 w-full rounded-md border border-slate-700 bg-black px-3 text-sm text-slate-200 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-600/40 sm:max-w-xs"
                    />

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="can('users.activate')"
                            type="button"
                            :disabled="!hasSelection || bulkProcessing"
                            class="inline-flex h-10 items-center justify-center rounded-md bg-emerald-700 px-4 text-sm font-bold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="runBulkAction('activate')"
                        >
                            <span v-if="bulkProcessing">Processing...</span>
                            <span v-else>Make Selected Active</span>
                        </button>

                        <button
                            v-if="can('users.deactivate')"
                            type="button"
                            :disabled="!hasSelection || bulkProcessing"
                            class="inline-flex h-10 items-center justify-center rounded-md bg-orange-700 px-4 text-sm font-bold text-white hover:bg-orange-800 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="runBulkAction('deactivate')"
                        >
                            <span v-if="bulkProcessing">Processing...</span>
                            <span v-else>Make Selected Inactive</span>
                        </button>

                        <button
                            v-if="can('users.delete')"
                            type="button"
                            :disabled="!hasSelection || bulkProcessing"
                            class="inline-flex h-10 items-center justify-center rounded-md bg-red-700 px-4 text-sm font-bold text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="runBulkAction('delete')"
                        >
                            <span v-if="bulkProcessing">Processing...</span>
                            <span v-else>Delete Selected</span>
                        </button>

                        <button
                            v-if="hasSelection"
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-md border border-slate-700 bg-slate-900 px-3 text-xs font-semibold text-slate-200 hover:bg-slate-800"
                            @click="clearSelection"
                        >
                            Clear ({{ selectedCount }})
                        </button>
                    </div>
                </div>

                <Link
                    v-if="can('users.create')"
                    :href="create().url"
                    class="inline-flex h-10 items-center justify-center rounded-md bg-blue-600 px-4 text-sm font-bold text-white hover:bg-blue-700"
                >
                    Create New User
                </Link>
            </div>

            <!-- Table -->
            <div
                class="overflow-x-auto rounded-lg border border-slate-500/80 bg-slate-950/30"
            >
                <DataTable :data="users.data" :columns="columns" />
            </div>
        </section>
    </AppLayout>
</template>
