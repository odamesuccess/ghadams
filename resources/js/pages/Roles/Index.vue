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
import { create, show, edit } from '@/routes/roles'
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
  is_special?: boolean
  landing_route?: string | null
  permissions_count?: number
  users?: User[]
}

/* =======================
   Props
======================= */
defineProps<{
  breadcrumbs?: BreadcrumbItem[]
  roles: Role[]
}>()

/* =======================
   Delete Handler
======================= */
const deleteRole = async (role: Role) => {
  const confirmed = await confirmDelete({
    title: 'Delete Role',
    text: `Are you sure you want to delete role "${role.name}"?`,
  })
  if (!confirmed) return

  router.delete(`/roles/${role.id}`, {
    preserveScroll: true,
    onSuccess: () =>
      toast.fire({ icon: 'success', title: 'Role deleted successfully' }),
    onError: () =>
      toast.fire({ icon: 'error', title: 'Failed to delete role' }),
  })
}

/* =======================
   Table Columns
======================= */
const columns: ColumnDef<Role>[] = [
  {
    id: 'role_type',
    header: 'Role Type',
    cell: ({ row }) => row.original.is_special ? 'Special' : 'Normal',
  },
  /* Role Name */
  {
    accessorKey: 'name',
    header: 'Role Name',
    cell: ({ row }) =>
      h('span', { class: 'font-medium' }, row.original.name),
  },

  /* Permissions Count */
  {
    accessorKey: 'permissions_count',
    header: 'Permissions',
    cell: ({ row }) =>
      h(
        'span',
        { class: 'text-sm text-slate-400' },
        row.original.permissions_count ?? '—'
      ),
  },

  /* =======================
     Used By Users
  ======================= */
  {
    header: 'Used By Users',
    id: 'users',
    cell: ({ row }) => {
      const users = row.original.users ?? []

      if (!users.length) {
        return h('span', { class: 'text-xs text-slate-400' }, '—')
      }

      const first = users[0].name
      const extra = users.length - 1

      return h(
        'span',
        {
          class:
            'inline-flex items-center rounded bg-indigo-700 px-2 py-0.5 text-xs text-white',
          title: users.map(u => u.name).join(', '),
        },
        extra > 0 ? `${first} +${extra} more` : first
      )
    },
  },

  /* =======================
     Actions (DELETE LOCKED ONLY)
  ======================= */
  {
    id: 'actions',
    header: '',
    cell: ({ row }) => {
      const role = row.original
      const isAssigned = (role.users?.length ?? 0) > 0
      const actions = []

      /* VIEW */
      if (can('roles.view')) {
        actions.push(
          h(
            Link,
            { href: show(role.id).url, class: 'btn-secondary text-xs' },
            'View'
          )
        )
      }

      /* EDIT — ALWAYS ALLOWED */
      if (can('roles.edit')) {
        actions.push(
          h(
            Link,
            {
              href: edit(role.id).url,
              class:
                'rounded px-2 py-1 text-xs text-white bg-indigo-600 hover:bg-indigo-700',
            },
            'Edit'
          )
        )
      }

      /* DELETE — DISABLED IF ASSIGNED */
      if (can('roles.delete')) {
        actions.push(
          h(
            'button',
            {
              disabled: isAssigned,
              title: isAssigned
                ? 'Role is assigned to users and cannot be deleted'
                : 'Delete role',
              class:
                'rounded px-2 py-1 text-xs text-white ' +
                (isAssigned
                  ? 'bg-red-400 cursor-not-allowed opacity-60'
                  : 'bg-red-600 hover:bg-red-700'),
              onClick: () => {
                if (!isAssigned) deleteRole(role)
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

  { title: 'Roles Management Dashboard' },


  ]">
<!-- Roles action bar -->
    <div class="mt-4 flex items-center justify-between gap-3 px-6">
      
      <Link
        v-if="can('roles.create')"
        :href="create().url"
        class="inline-flex shrink-0 items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
      >
        Create Role
      </Link>
    </div>



    <!-- Table -->
    <div class="mx-6 mt-4 overflow-x-auto rounded-lg border border-slate-700">
       <DataTable :data="roles" :columns="columns" searchable />
    </div>
  </AppLayout>
</template>
