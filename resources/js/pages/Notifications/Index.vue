<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { confirmDelete } from '@/utils/sweetalert'

const props = defineProps<{
  filters: any
  notifications: any
  unreadCount: number
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Notifications', href: '#' },
]

const searchForm = useForm({
  search: (props.filters?.search ?? '') as string,
  type: (props.filters?.type ?? '') as string,
  read: (props.filters?.read ?? '') as string,
})

const applyFilters = () => {
  router.get('/notifications', searchForm.data(), {
    preserveState: true,
    replace: true,
  })
}

const resetFilters = () => {
  searchForm.search = ''
  searchForm.type = ''
  searchForm.read = ''

  router.get('/notifications', {}, {
    preserveState: true,
    replace: true,
  })
}

const destroyRecord = async (url: string) => {
  const confirmed = await confirmDelete({
    title: 'Delete Notification',
    text: 'Are you sure you want to delete this notification?',
  })
  if (confirmed) {
    router.delete(url, { preserveScroll: true })
  }
}

const postTo = (url: string, data: Record<string, any> = {}) => {
  router.post(url, data, { preserveScroll: true })
}

const fmt = (value: string | null | undefined) => {
  if (!value) return '—'

  try {
    return new Date(value).toLocaleString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return value
  }
}
</script>

<template>
  <Head title="Notifications" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-4 my-4 space-y-4 rounded-2xl border border-slate-800 bg-slate-950/95 p-4 text-slate-100 shadow-2xl sm:mx-6 sm:p-6">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div class="space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-bold tracking-tight text-white">
              Notifications
            </h1>

            <span class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1 text-xs font-semibold text-slate-300">
              {{ notifications.total ?? notifications.data?.length ?? 0 }} records
            </span>

            <span class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300">
              {{ unreadCount }} unread
            </span>
          </div>

          <p class="max-w-3xl text-sm text-slate-400">
            Live system alerts for asset actions, approvals, maintenance, login/logout, security events, and user activities.
          </p>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            @click="postTo('/notifications/mark-all-read')"
            class="inline-flex items-center justify-center rounded-xl border border-blue-500/30 bg-blue-500/10 px-3 py-2 text-sm font-semibold text-blue-300 transition hover:bg-blue-500/20"
          >
            Mark all read
          </button>

          <button
            type="button"
            @click="destroyRecord('/notifications/clear-all')"
            class="inline-flex items-center justify-center rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm font-semibold text-red-300 transition hover:bg-red-500/20"
          >
            Clear all
          </button>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-3">
        <div class="grid gap-2 md:grid-cols-12">
          <input
            v-model="searchForm.search"
            @keyup.enter="applyFilters"
            class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 md:col-span-5"
            placeholder="Search notifications..."
          />

          <select
            v-model="searchForm.type"
            class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 md:col-span-2"
          >
            <option value="">All Types</option>
            <option value="activity">Activity</option>
            <option value="security">Security</option>
            <option value="system">System</option>
          </select>

          <select
            v-model="searchForm.read"
            class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 md:col-span-2"
          >
            <option value="">All Statuses</option>
            <option value="unread">Unread</option>
            <option value="read">Read</option>
          </select>

          <div class="grid grid-cols-2 gap-2 md:col-span-3">
            <button
              type="button"
              @click="applyFilters"
              class="rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500"
            >
              Search
            </button>

            <button
              type="button"
              @click="resetFilters"
              class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm font-semibold text-slate-300 transition hover:bg-slate-800"
            >
              Reset
            </button>
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-800">
        <div class="divide-y divide-slate-800 bg-slate-950">
          <div
            v-for="item in notifications.data"
            :key="item.id"
            class="grid gap-2 px-4 py-3 transition hover:bg-slate-900/70"
          >
            <!-- Row 1: badges, date, actions -->
            <div class="flex min-w-0 items-center justify-between gap-3">
              <div class="flex min-w-0 flex-wrap items-center gap-2">
                <span
                  class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                  :class="item.read_at
                    ? 'bg-slate-800 text-slate-300 ring-1 ring-slate-700'
                    : 'bg-emerald-500/10 text-emerald-300 ring-1 ring-emerald-500/20'"
                >
                  {{ item.read_at ? 'Read' : 'Unread' }}
                </span>

                <span class="shrink-0 rounded-full bg-blue-500/10 px-2.5 py-1 text-xs font-semibold text-blue-300 ring-1 ring-blue-500/20">
                  {{ item.type ?? 'system' }}
                </span>

                <span class="shrink-0 text-xs font-medium text-slate-500">
                  {{ fmt(item.created_at) }}
                </span>
              </div>

              <div class="flex shrink-0 items-center gap-3 text-sm">
                <button
                  v-if="!item.read_at"
                  type="button"
                  @click="postTo(`/notifications/${item.id}/read`)"
                  class="font-medium text-blue-300 transition hover:text-blue-200 hover:underline"
                >
                  Mark read
                </button>

                <button
                  type="button"
                  @click="destroyRecord(`/notifications/${item.id}`)"
                  class="font-medium text-red-300 transition hover:text-red-200 hover:underline"
                >
                  Delete
                </button>
              </div>
            </div>

            <!-- Row 2: all details on one row -->
            <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm">
              <span class="shrink-0 font-semibold text-white">
                {{ item.title }}
              </span>

              <span class="hidden text-slate-600 sm:inline">•</span>

              <span class="min-w-0 max-w-full truncate text-slate-300 sm:max-w-[42rem]">
                {{ item.message }}
              </span>

              <span class="hidden text-slate-600 md:inline">•</span>

              <span class="shrink-0 text-xs text-slate-500">
                Actor:
                <span class="text-slate-400">{{ item.actor?.name ?? 'System' }}</span>
              </span>

              <span
                v-if="item.recipient"
                class="shrink-0 text-xs text-slate-500"
              >
                <span class="mx-1 text-slate-600">•</span>
                Recipient:
                <span class="text-slate-400">{{ item.recipient.name }}</span>
              </span>
            </div>
          </div>

          <div
            v-if="!notifications.data?.length"
            class="px-4 py-12 text-center"
          >
            <div class="text-base font-semibold text-slate-300">
              No notifications found
            </div>

            <div class="mt-1 text-sm text-slate-500">
              Try adjusting the search term, type, or status filter.
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="notifications.links?.length"
        class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 p-3 text-sm text-slate-400 lg:flex-row lg:items-center lg:justify-between"
      >
        <div>
          Showing
          <span class="font-semibold text-slate-200">{{ notifications.from ?? 0 }}</span>
          to
          <span class="font-semibold text-slate-200">{{ notifications.to ?? 0 }}</span>
          of
          <span class="font-semibold text-slate-200">{{ notifications.total ?? 0 }}</span>
          notifications
        </div>

        <div class="flex flex-wrap gap-1">
          <button
            v-for="link in notifications.links"
            :key="link.label"
            :disabled="!link.url"
            v-html="link.label"
            @click="link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })"
            class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40"
            :class="link.active
              ? 'border-blue-500 bg-blue-600 text-white'
              : 'border-slate-700 bg-slate-950 text-slate-300 hover:bg-slate-800'"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
