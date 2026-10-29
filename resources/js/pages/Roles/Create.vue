<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

import { index, store } from '@/routes/roles'
import { toast } from '@/utils/sweetalert'

interface LandingOption {
  route: string
  label: string
  permissions: string[]
  permission_groups: string[][]
}

/* =======================
   Props
======================= */
const props = defineProps<{
  permissions: string[]
  landingOptions: LandingOption[]
}>()

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Roles', href: index().url },
  { title: 'Create', href: '#' },
]

/* =======================
   Form State
======================= */
const form = useForm<{
  name: string
  permissions: string[]
  is_special: boolean
  landing_route: string
}>({
  name: '',
  is_special: false,
  landing_route: '',
  permissions: [],
})

const availableLandingOptions = computed(() => props.landingOptions.filter(option =>
  option.permission_groups.every(group => group.some(permission => form.permissions.includes(permission))),
))
watch(() => [form.is_special, [...form.permissions]], () => {
  if (!form.is_special || !availableLandingOptions.value.some(option => option.route === form.landing_route)) {
    form.landing_route = ''
  }
}, { deep: true })

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
  const groups: Record<string, string[]> = {}

  props.permissions.forEach((permission) => {
    const module = getPermissionModule(permission)

    if (!groups[module]) {
      groups[module] = []
    }

    groups[module].push(permission)
  })

  return Object.entries(groups)
    .map(([module, permissions]) => ({
      module,
      permissions: permissions.sort((a, b) =>
        formatPermissionLabel(a).localeCompare(formatPermissionLabel(b))
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
              permission.toLowerCase().includes(search) ||
              formatPermissionLabel(permission).toLowerCase().includes(search) ||
              getPermissionModule(permission).toLowerCase().includes(search)
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

const visiblePermissions = computed(() => {
  return filteredGroups.value.flatMap((group) => group.permissions)
})

const selectedCount = computed(() => form.permissions.length)

const pageStart = computed(() => {
  if (!totalGroups.value) return 0

  return (currentPage.value - 1) * groupsPerPage.value + 1
})

const pageEnd = computed(() => {
  return Math.min(currentPage.value * groupsPerPage.value, totalGroups.value)
})

/* =======================
   Select All Logic
======================= */
const allSelected = computed({
  get() {
    return (
      form.permissions.length === props.permissions.length &&
      props.permissions.length > 0
    )
  },
  set(value: boolean) {
    form.permissions = value ? [...props.permissions] : []
  },
})

const searchResultsSelected = computed({
  get() {
    return (
      visiblePermissions.value.length > 0 &&
      visiblePermissions.value.every((permission) => form.permissions.includes(permission))
    )
  },
  set(value: boolean) {
    if (value) {
      form.permissions = Array.from(new Set([...form.permissions, ...visiblePermissions.value]))
      return
    }

    form.permissions = form.permissions.filter(
      (permission) => !visiblePermissions.value.includes(permission)
    )
  },
})

const isGroupAllSelected = (permissions: string[]) => {
  return permissions.length > 0 && permissions.every((permission) => form.permissions.includes(permission))
}

const toggleGroup = (permissions: string[]) => {
  if (isGroupAllSelected(permissions)) {
    form.permissions = form.permissions.filter((permission) => !permissions.includes(permission))
    return
  }

  form.permissions = Array.from(new Set([...form.permissions, ...permissions]))
}

const goToPage = (page: number) => {
  if (page < 1 || page > totalPages.value) return

  currentPage.value = page
}

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

watch(permissionSearch, () => {
  currentPage.value = 1
})

watch(groupsPerPage, () => {
  currentPage.value = 1
})

/* =======================
   Submit
======================= */
const submit = () => {
  form.post(store().url, {
    preserveScroll: true,
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Role created successfully',
      })

      form.reset()
      permissionSearch.value = ''
      currentPage.value = 1
    },
    onError: () => {
      toast.fire({
        icon: 'error',
        title: 'Failed to create role',
      })
    },
  })
}
</script>

<template>
  <Head title="Create Role" />

  <AppLayout :breadcrumbs="breadcrumbs">


    <div class="min-h-screen bg-slate-950 px-4 py-4 text-slate-100 sm:px-6">
      <form @submit.prevent="submit" class="space-y-4">
        <!-- Header + Role Name + Search + Actions -->
        <div class="grid gap-4 rounded-2xl border border-slate-700/70 bg-slate-900/80 p-4 text-slate-100 shadow-xl xl:grid-cols-12 xl:items-end">
          <!-- Page Title -->
          <div class="xl:col-span-3">
            <h1 class="text-xl font-bold tracking-tight text-white">
              Create Role
            </h1>

            <p class="mt-1 text-sm text-slate-400">
              Create a new role and assign permissions.
            </p>
          </div>

          <!-- Role Name -->
          <div class="grid gap-2 xl:col-span-3">
            <Label class="text-sm font-semibold text-white">
              Role Name <span class="text-red-500">*</span>
            </Label>

            <Input
              v-model="form.name"
              placeholder="e.g. Admin, Manager"
              class="h-10 w-full rounded-md border border-slate-800 bg-black text-white placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />

            <p v-if="form.errors.name" class="text-xs text-red-400">
              {{ form.errors.name }}
            </p>
          </div>

          <!-- Search Permissions -->
          <div class="grid gap-2 xl:col-span-4">
            <Label class="text-sm font-semibold text-white">
              Search Permissions
            </Label>

            <input
              v-model="permissionSearch"
              type="text"
              placeholder="Search by permission or group..."
              class="h-10 w-full rounded-md border border-slate-800 bg-black px-3 text-sm text-slate-100 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </div>

          <!-- Actions -->
          <div class="flex flex-wrap justify-start gap-2 xl:col-span-2 xl:justify-end">
            <Link
              :href="index().url"
              class="inline-flex h-10 items-center justify-center rounded-xl bg-slate-600 px-4 text-sm font-semibold text-white transition hover:bg-slate-500"
            >
              ← Back
            </Link>

            <Button
              type="submit"
              :disabled="form.processing"
              class="h-10 bg-white text-slate-950 hover:bg-slate-200"
            >
              {{ form.processing ? 'Creating...' : 'Create Role' }}
            </Button>
          </div>
        </div>

        <div class="rounded-xl border border-slate-700 bg-slate-900 p-4 text-white">
          <label class="flex items-center gap-2 font-semibold">
            <input v-model="form.is_special" type="checkbox" :disabled="form.name === 'Super Admin'" />
            Special role — use the selected permitted landing page
          </label>
          <p class="mt-1 text-sm text-slate-400">Pages, including dashboards, are available only when their required permissions are assigned.</p>
          <div v-if="form.is_special" class="mt-3 space-y-2">
            <Label for="landing_route">Landing page</Label>
            <select id="landing_route" v-model="form.landing_route" class="h-10 w-full rounded border border-slate-600 bg-black px-3 text-white">
              <option value="">Automatic - first permitted page</option>
              <option v-for="option in availableLandingOptions" :key="option.route" :value="option.route">{{ option.label }}</option>
            </select>
            <p v-if="availableLandingOptions.length === 0" class="text-sm text-amber-300">Assign a page's Menu or View permission below to make it available.</p>
            <p v-if="form.errors.landing_route" class="text-sm text-red-400">{{ form.errors.landing_route }}</p>
          </div>
          <p v-if="form.errors.is_special" class="text-sm text-red-400">{{ form.errors.is_special }}</p>
        </div>

        <!-- Permissions Area -->
        <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-4 text-slate-100 shadow-xl">
          <!-- Top Summary -->
          <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-400">
            <span>
              Showing {{ visiblePermissionCount }} of {{ permissions.length }} permissions
            </span>

            <span class="rounded-full border border-slate-700 bg-slate-950 px-3 py-1 text-xs font-semibold text-slate-300">
              {{ selectedCount }} selected
            </span>
          </div>

          <!-- Selection Controls -->
          <div class="mt-3 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-wrap items-center gap-5 text-sm text-white">
              <label class="inline-flex cursor-pointer items-center gap-2">
                <input
                  type="checkbox"
                  v-model="allSelected"
                  class="h-4 w-4 rounded border-slate-600"
                />
                <span>Select All</span>
              </label>

              <label class="inline-flex cursor-pointer items-center gap-2">
                <input
                  type="checkbox"
                  v-model="searchResultsSelected"
                  class="h-4 w-4 rounded border-slate-600"
                />
                <span>
                  Select Search Results
                  <span class="text-slate-400">({{ visiblePermissionCount }}/{{ permissions.length }})</span>
                </span>
              </label>
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

                  <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 text-xs text-white">
                    <input
                      type="checkbox"
                      :checked="isGroupAllSelected(group.permissions)"
                      @change="toggleGroup(group.permissions)"
                      class="h-4 w-4 rounded border-slate-600"
                    />
                    <span>Select Group</span>
                  </label>
                </div>

                <!-- Permissions -->
                <div class="grid grid-cols-1 gap-x-8 gap-y-3 px-3 py-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                  <label
                    v-for="permission in group.permissions"
                    :key="permission"
                    class="inline-flex min-w-0 cursor-pointer items-center gap-2 text-sm text-white"
                  >
                    <input
                      type="checkbox"
                      :value="permission"
                      v-model="form.permissions"
                      class="h-4 w-4 shrink-0 rounded border-slate-600"
                    />

                    <span class="truncate">
                      {{ titleCase(normalizePermissionText(permission)) }}
                    </span>
                  </label>
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

          <p v-if="form.errors.permissions" class="mt-3 text-xs text-red-400">
            {{ form.errors.permissions }}
          </p>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
