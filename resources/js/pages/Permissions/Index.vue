<script setup lang="ts">
/* =======================
   Imports
======================= */
import { h } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import type { ColumnDef } from '@tanstack/vue-table'
import { can } from '@/utils/permissions'

import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'
import { create, show, edit } from '@/routes/permissions'
import { confirmDelete, toast } from '@/utils/sweetalert'

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
  users: User[]
}

interface Permission {
  id: number
  name: string
  roles: Role[]
}

/* =======================
   Props
======================= */
defineProps<{
  breadcrumbs?: BreadcrumbItem[]
  permissions: Permission[]
}>()

/* =======================
   Delete Handler
======================= */
const deletePermission = async (permission: Permission) => {
  const confirmed = await confirmDelete({
    title: 'Delete Permission',
    text: `Are you sure you want to delete permission "${permission.name}"?`,
  })
  if (!confirmed) return

  router.delete(`/permissions/${permission.id}`, {
    preserveScroll: true,
    onSuccess: () =>
      toast.fire({
        icon: 'success',
        title: 'Permission deleted successfully',
      }),
    onError: () =>
      toast.fire({
        icon: 'error',
        title: 'Failed to delete permission',
      }),
  })
}

/* =======================
   Table Columns
======================= */
const columns: ColumnDef<Permission>[] = [
  /* Permission Name */
  {
    accessorKey: 'name',
    header: 'Permission Name',
    cell: ({ row }) =>
      h('span', { class: 'font-medium' }, row.original.name),
  },

  /* Used By Roles */
  {
    header: 'Used By Roles',
    id: 'roles',
    cell: ({ row }) => {
      const roles = row.original.roles ?? []

      if (!roles.length) {
        return h('span', { class: 'text-xs text-slate-400' }, '—')
      }

      const first = roles[0].name
      const extra = roles.length - 1

      return h(
        'span',
        {
          class:
            'inline-flex items-center rounded bg-slate-700 px-2 py-0.5 text-xs text-white',
          title: roles.map(r => r.name).join(', '),
        },
        extra > 0 ? `${first} +${extra} more` : first
      )
    },
  },

  /* Used By Users */
  {
    header: 'Used By Users',
    id: 'users',
    cell: ({ row }) => {
      const roles = row.original.roles ?? []
      const usersMap = new Map<number, string>()

      roles.forEach(role => {
        role.users?.forEach(user => {
          usersMap.set(user.id, user.name)
        })
      })

      const users = Array.from(usersMap.values())

      if (!users.length) {
        return h('span', { class: 'text-xs text-slate-400' }, '—')
      }

      const first = users[0]
      const extra = users.length - 1

      return h(
        'span',
        {
          class:
            'inline-flex items-center rounded bg-indigo-700 px-2 py-0.5 text-xs text-white',
          title: users.join(', '),
        },
        extra > 0 ? `${first} +${extra} more` : first
      )
    },
  },

  /* =======================
     Actions (LOCKED IF IN USE)
  ======================= */
  {
    id: 'actions',
    header: '',
    cell: ({ row }) => {
      const permission = row.original

      const isInUse =
        permission.roles.length > 0 ||
        permission.roles.some(role => role.users?.length > 0)

      const actions = []

      /* VIEW */
      if (can('permissions.show')) {
        actions.push(
          h(
            Link,
            {
              href: show(permission.id).url,
              class: 'btn-secondary text-xs',
            },
            'View'
          )
        )
      }

      /* EDIT (disabled if in use) */
      if (can('permissions.edit')) {
        actions.push(
          h(
            Link,
            {
              href: isInUse ? '#' : edit(permission.id).url,
              title: isInUse
                ? 'Permission is in use and cannot be edited'
                : 'Edit permission',
              class:
                'rounded px-2 py-1 text-xs text-white ' +
                (isInUse
                  ? 'bg-indigo-400 cursor-not-allowed opacity-60 pointer-events-none'
                  : 'bg-indigo-600 hover:bg-indigo-700'),
            },
            'Edit'
          )
        )
      }

      /* DELETE (disabled if in use) */
      if (can('permissions.delete')) {
        actions.push(
          h(
            'button',
            {
              disabled: isInUse,
              title: isInUse
                ? 'Permission is in use and cannot be deleted'
                : 'Delete permission',
              class:
                'rounded px-2 py-1 text-xs text-white ' +
                (isInUse
                  ? 'bg-red-400 cursor-not-allowed opacity-60'
                  : 'bg-red-600 hover:bg-red-700'),
              onClick: () => {
                if (!isInUse) deletePermission(permission)
              },
            },
            'Delete'
          )
        )
      }

      return h(
        'div',
        { class: 'flex justify-end gap-2 whitespace-nowrap' },
        actions.length ? actions : '—'
      )
    },
  },
]
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs ?? [
     { title: 'Permission Management Dashboard', href: '/Permissions' },
   { title: 'Index ', href: '/permissions/index' },
  ]">
    <!-- Action Bar -->
    <div class="mt-4 flex items-center justify-end px-6">
      <Link
        v-if="can('permissions.create')"
        :href="create().url"
        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
      >
        Create Permission
      </Link>
    </div>

    <!-- Table -->
    <div class="mx-6 mt-4 overflow-x-auto rounded-lg border border-slate-700">
      <DataTable :data="permissions" :columns="columns" searchable/>


    </div>
  </AppLayout>
</template>
