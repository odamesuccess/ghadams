<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
import { can } from '@/utils/permissions'
import { confirmDelete, toast } from '@/utils/sweetalert'
import { edit, index } from '@/routes/permissions'

/* =======================
   Types
======================= */
interface User {
    id: number
    name: string
}

interface Role {
    id: number
    name: string
    users?: User[]
}

interface Permission {
    id: number
    name: string
    roles: Role[]
}

/* =======================
   Props
======================= */
const props = defineProps<{
    permission: Permission
}>()

/* =======================
   Delete
======================= */
const deletePermission = async () => {
    const confirmed = await confirmDelete({
        title: 'Delete Permission',
        text: `Are you sure you want to delete permission "${props.permission.name}"?`,
    })
    if (!confirmed) return

    router.delete(`/permissions/${props.permission.id}`, {
        onSuccess: () =>
            toast.fire({ icon: 'success', title: 'Permission deleted successfully' }),
        onError: () =>
            toast.fire({ icon: 'error', title: 'Failed to delete permission' }),
    })
}

/* =======================
   Computed Users
======================= */
const users = (() => {
    const map = new Map<number, string>()

    props.permission.roles.forEach(role => {
        role.users?.forEach(user => {
            map.set(user.id, user.name)
        })
    })

    return Array.from(map.values())
})()
</script>

<template>

    <Head :title="permission.name" />

    <AppLayout>
        <!-- ================= HEADER ================= -->
        <div class="flex items-center justify-between px-6 mt-4">
            <Link :href="index().url" class="rounded bg-slate-700 px-3 py-1.5 text-sm text-white hover:bg-slate-600">
                ← Back to Permissions
            </Link>

            
        </div>

        <!-- ================= CARD ================= -->
        <div class="mx-6 mt-6 rounded-xl border border-slate-700 bg-white dark:bg-slate-900 to-slate-800 p-6 space-y-6">



            <!-- Title -->
            <div>
                <h1 class="text-2xl font-semibold text-white">
                    {{ permission.name }}
                </h1>
                <p class="text-sm text-slate-400">
                    System Permission
                </p>
            </div>

            <!-- ================= ROLES ================= -->
            <div>
                <h3 class="text-sm font-semibold text-slate-300 mb-2">
                    Used by Roles
                </h3>

                <div class="flex flex-wrap gap-2">
                    <span v-for="role in permission.roles" :key="role.id"
                        class="rounded-full bg-slate-700 px-3 py-1 text-xs text-white">
                        {{ role.name }}
                    </span>

                    <span v-if="permission.roles.length === 0" class="text-xs text-slate-400">
                        —
                    </span>
                </div>
            </div>

            <!-- ================= USERS ================= -->
            <div>
                <h3 class="text-sm font-semibold text-slate-300 mb-2">
                    Used by Users
                </h3>

                <div class="flex flex-wrap gap-3">
                    <span v-for="user in users" :key="user"
                        class="rounded bg-indigo-700/30 px-3 py-1 text-xs text-indigo-300">
                        {{ user }}
                    </span>

                    <span v-if="users.length === 0" class="text-xs text-slate-400">
                        —
                    </span>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
