<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layouts/app/AppSidebarLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { edit, index } from '@/routes/roles'

/* =======================
   Types
======================= */
interface Permission {
  name: string
}

interface User {
  id: number
  name: string
}

interface Role {
  id: number
  name: string
  permissions: Permission[]
  users: User[]
}

/* =======================
   Props
======================= */
const props = defineProps<{
  role: Role
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Roles', href: index().url },
  { title: 'View Role', href: '#' },
]

/* =======================
   Search / Pagination State
======================= */
const permissionSearch = ref('')
const currentPage = ref(1)
const groupsPerPage = ref(6)

const groupsPerPageOptions = [6, 9, 12, 18, 24]

/* =======================
   Helpers
======================= */
const actionWords = [
  'view',
  'create',
  'edit',
  'update',
  'delete',
  'restore',
  'export',
  'print',
  'save',
  'scan',
  'generate',
  'approve',
  'reject',
  'assign',
  'transfer',
  'dispose',
  'complete',
  'clear',
  'activate',
  'deactivate',
  'manage',
  'menu',
  'force',
]

const titleCase = (value: string) => {
  return value
    .replace(/[._-]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

const normalizePermissionText = (value: string) => {
  return value
    .replace(/[._-]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
}

const formatPermissionLabel = (permission: string) => {
  const parts = permission.split('.')

  if (parts.length >= 2) {
    const action = parts.pop() ?? ''
    return titleCase(action)
  }

  return titleCase(normalizePermissionText(permission))
}

const getPermissionModule = (permission: string) => {
  const parts = permission.split('.')

  if (parts.length >= 2) {
    parts.pop()
    return titleCase(parts.join(' '))
  }

  const words = permission.split(/[\s._-]+/).filter(Boolean)

  if (!words.length) {
    return 'General'
  }

  const first = words[0]?.toLowerCase()
  const last = words[words.length - 1]?.toLowerCase()

  if (first && actionWords.includes(first)) {
    return titleCase(words.slice(1).join(' ') || 'General')
  }

  if (last && actionWords.includes(last)) {
    return titleCase(words.slice(0, -1).join(' ') || 'General')
  }

  return titleCase(words[0] ?? 'General')
}

/* =======================
   Permission Groups
======================= */
const groupedPermissions = computed(() => {
  const groups: Record<string, Permission[]> = {}

  props.role.permissions.forEach((permission) => {
    const module = getPermissionModule(permission.name)

    if (!groups[module]) {
      groups[module] = []
    }

    groups[module].push(permission)
  })

  return Object.entries(groups)
    .map(([module, permissions]) => ({
      module,
      permissions: permissions.sort((a, b) =>
        formatPermissionLabel(a.name).localeCompare(formatPermissionLabel(b.name))
      ),
    }))
    .sort((a, b) => a.module.localeCompare(b.module))
})

const filteredGroups = computed(() => {
  const search = permissionSearch.value.trim().toLowerCase()

  if (!search) {
    return groupedPermissions.value
  }

  return groupedPermissions.value
    .map((group) => {
      const moduleMatches = group.module.toLowerCase().includes(search)

      const permissions = moduleMatches
        ? group.permissions
        : group.permissions.filter((permission) => {
            return (
              permission.name.toLowerCase().includes(search) ||
              formatPermissionLabel(permission.name).toLowerCase().includes(search) ||
              getPermissionModule(permission.name).toLowerCase().includes(search)
            )
          })

      return {
        module: group.module,
        permissions,
      }
    })
    .filter((group) => group.permissions.length > 0)
})

const totalGroups = computed(() => filteredGroups.value.length)

const totalPages = computed(() => {
  return Math.max(1, Math.ceil(totalGroups.value / groupsPerPage.value))
})

const paginatedGroups = computed(() => {
  const start = (currentPage.value - 1) * groupsPerPage.value
  const end = start + groupsPerPage.value

  return filteredGroups.value.slice(start, end)
})

const visiblePermissionCount = computed(() => {
  return filteredGroups.value.reduce((total, group) => total + group.permissions.length, 0)
})

const pageStart = computed(() => {
  if (!totalGroups.value) return 0

  return (currentPage.value - 1) * groupsPerPage.value + 1
})

const pageEnd = computed(() => {
  return Math.min(currentPage.value * groupsPerPage.value, totalGroups.value)
})

const visiblePageNumbers = computed(() => {
  const pages: number[] = []
  const maxVisible = 3

  let start = Math.max(1, currentPage.value - 1)
  let end = Math.min(totalPages.value, start + maxVisible - 1)

  if (end - start + 1 < maxVisible) {
    start = Math.max(1, end - maxVisible + 1)
  }

  for (let page = start; page <= end; page++) {
    pages.push(page)
  }

  return pages
})

const goToPage = (page: number) => {
  if (page < 1 || page > totalPages.value) return

  currentPage.value = page
}

watch(permissionSearch, () => {
  currentPage.value = 1
})

watch(groupsPerPage, () => {
  currentPage.value = 1
})
</script>

<template>
  <Head :title="`Role: ${role.name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="min-h-screen bg-slate-950 px-4 py-4 text-slate-100 sm:px-6">
      <div class="space-y-4">
        <!-- Header + Summary + Users all on one row -->
        <div class="grid gap-4 xl:grid-cols-12">
          <!-- Role Header -->
          <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-4 text-slate-100 shadow-xl xl:col-span-3">
            <div class="flex h-full flex-col justify-between gap-4">
              <div>
                <h1 class="truncate text-2xl font-bold tracking-tight text-white">
                  {{ role.name }}
                </h1>

                <p class="mt-1 text-sm text-slate-400">
                  Role details, assigned permissions, and users
                </p>
              </div>

              <div class="flex flex-wrap gap-2">
                <Link
                  :href="index().url"
                  class="inline-flex items-center justify-center rounded-xl bg-slate-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-500"
                >
                  ← Back
                </Link>

                <Link
                  :href="edit(role.id).url"
                  class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500"
                >
                  Edit
                </Link>
              </div>
            </div>
          </div>

          <!-- Role Name -->
          <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-4 text-slate-100 shadow-lg xl:col-span-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Role Name
            </p>

            <p class="mt-2 truncate text-lg font-bold text-white">
              {{ role.name }}
            </p>
          </div>

          <!-- Assigned Users Count -->
          <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-4 text-slate-100 shadow-lg xl:col-span-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Users
            </p>

            <p class="mt-2 text-lg font-bold text-white">
              {{ role.users.length }}
            </p>
          </div>

          <!-- Permissions Count -->
          <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-4 text-slate-100 shadow-lg xl:col-span-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Permissions
            </p>

            <p class="mt-2 text-lg font-bold text-white">
              {{ role.permissions.length }}
            </p>
          </div>

          <!-- Used By Users -->
          <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-4 text-slate-100 shadow-xl xl:col-span-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <h2 class="text-sm font-bold text-white">
                  Used By Users
                </h2>

                <p class="mt-1 text-xs text-slate-400">
                  Users currently assigned to this role.
                </p>
              </div>

              <span class="shrink-0 rounded-full border border-slate-700 bg-slate-950 px-3 py-1 text-xs font-semibold text-slate-300">
                {{ role.users.length }} users
              </span>
            </div>

            <div
              v-if="role.users.length"
              class="mt-3 flex flex-wrap gap-2"
            >
              <span
                v-for="user in role.users"
                :key="user.id"
                class="max-w-[145px] truncate rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-1 text-xs font-semibold text-indigo-200"
              >
                {{ user.name }}
              </span>
            </div>

            <div
              v-else
              class="mt-3 rounded-xl border border-slate-800 bg-slate-950 px-3 py-3 text-center"
            >
              <p class="text-xs text-slate-400">
                No users assigned.
              </p>
            </div>
          </div>
        </div>

        <!-- Permissions -->
        <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-4 text-slate-100 shadow-xl">
          <!-- Top Row -->
          <div class="grid gap-4 lg:grid-cols-2 lg:items-end">
            <div class="grid gap-2">
              <label class="text-sm font-semibold text-white">
                Search Permissions
              </label>

              <input
                v-model="permissionSearch"
                type="text"
                placeholder="Search by permission or group..."
                class="h-10 w-full rounded-md border border-slate-800 bg-black px-3 text-sm text-slate-100 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              />
            </div>

            <div class="flex flex-wrap items-center justify-start gap-3 text-sm text-slate-400 lg:justify-end">
              <span>
                Showing {{ visiblePermissionCount }} of {{ role.permissions.length }} permissions
              </span>

              <span class="rounded-full border border-slate-700 bg-slate-950 px-3 py-1 text-xs font-semibold text-slate-300">
                {{ totalGroups }} groups
              </span>
            </div>
          </div>

          <!-- Controls -->
          <div class="mt-4 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div>
              <h2 class="text-base font-bold text-white">
                Permissions
              </h2>

              <p class="mt-1 text-sm text-slate-400">
                Permissions are grouped by module for easier review.
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <div class="flex items-center gap-2 text-sm text-slate-300">
                <span class="font-semibold text-slate-200">Groups per page</span>

                <select
                  v-model.number="groupsPerPage"
                  class="h-10 rounded-lg border border-slate-800 bg-black px-3 text-sm font-semibold text-white outline-none focus:border-blue-500"
                >
                  <option
                    v-for="option in groupsPerPageOptions"
                    :key="option"
                    :value="option"
                  >
                    {{ option }}
                  </option>
                </select>
              </div>

              <span class="text-sm text-slate-400">
                Page {{ currentPage }} of {{ totalPages }}
              </span>
            </div>
          </div>

          <!-- Permission Groups -->
          <div class="mt-4 rounded-xl border border-slate-700/80 bg-slate-950/40 p-3">
            <div class="space-y-3">
              <section
                v-for="group in paginatedGroups"
                :key="group.module"
                class="overflow-hidden rounded-lg border border-slate-800 bg-slate-900/80"
              >
                <!-- Group Header -->
                <div class="flex items-center justify-between gap-3 border-b border-slate-800 px-3 py-2">
                  <div class="flex min-w-0 items-center gap-2">
                    <h3 class="truncate text-sm font-bold text-white">
                      {{ group.module }}
                    </h3>

                    <span class="shrink-0 text-xs text-slate-400">
                      {{ group.permissions.length }}
                      {{ group.permissions.length === 1 ? 'permission' : 'permissions' }}
                    </span>
                  </div>
                </div>

                <!-- Permissions -->
                <div class="grid grid-cols-1 gap-x-8 gap-y-3 px-3 py-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                  <div
                    v-for="permission in group.permissions"
                    :key="permission.name"
                    class="inline-flex min-w-0 items-center gap-2 text-sm text-white"
                  >
                    <span class="h-2 w-2 shrink-0 rounded-full bg-emerald-400"></span>

                    <span class="truncate">
                      {{ titleCase(normalizePermissionText(permission.name)) }}
                    </span>
                  </div>
                </div>
              </section>

              <div
                v-if="!paginatedGroups.length"
                class="rounded-lg border border-slate-800 bg-slate-950 px-4 py-10 text-center"
              >
                <p class="font-semibold text-slate-300">
                  No permissions found
                </p>

                <p class="mt-1 text-sm text-slate-500">
                  Try another permission name or group keyword.
                </p>
              </div>
            </div>

            <!-- Pagination -->
            <div
              v-if="totalGroups > 0"
              class="mt-4 flex flex-col gap-3 border-t border-slate-800 pt-3 text-sm text-slate-400 lg:flex-row lg:items-center lg:justify-between"
            >
              <div>
                Showing groups {{ pageStart }} - {{ pageEnd }} of {{ totalGroups }}
              </div>

              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  :disabled="currentPage === 1"
                  @click="goToPage(currentPage - 1)"
                  class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40"
                  :class="currentPage === 1
                    ? 'bg-slate-900 text-slate-500'
                    : 'bg-slate-950 text-slate-300 hover:bg-slate-800'"
                >
                  Previous
                </button>

                <button
                  v-for="pageNumber in visiblePageNumbers"
                  :key="pageNumber"
                  type="button"
                  @click="goToPage(pageNumber)"
                  class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition"
                  :class="pageNumber === currentPage
                    ? 'border-blue-500 bg-blue-600 text-white'
                    : 'border-slate-700 bg-slate-950 text-slate-300 hover:bg-slate-800'"
                >
                  {{ pageNumber }}
                </button>

                <button
                  type="button"
                  :disabled="currentPage === totalPages"
                  @click="goToPage(currentPage + 1)"
                  class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40"
                  :class="currentPage === totalPages
                    ? 'bg-slate-900 text-slate-500'
                    : 'bg-slate-950 text-slate-300 hover:bg-slate-800'"
                >
                  Next
                </button>
              </div>
            </div>
          </div>

          <div
            v-if="!role.permissions.length"
            class="mt-4 rounded-xl border border-slate-800 bg-slate-950 px-4 py-6 text-center"
          >
            <p class="text-sm text-slate-400">
              No permissions assigned to this role.
            </p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
