<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Bell, CheckCheck, ExternalLink, LoaderCircle, RadioTower } from 'lucide-vue-next'

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

type FlashProps = {
  success?: string
  error?: string
  warning?: string
  info?: string
}

type RoleValue = string | { name?: string }
type PermissionValue = string | { name?: string }

type NotificationItem = {
  id: number
  type?: string | null
  title?: string | null
  message?: string | null
  read_at?: string | null
  created_at?: string | null
  actor?: {
    name?: string | null
    email?: string | null
  } | null
  recipient?: {
    name?: string | null
    email?: string | null
  } | null
}

type PageProps = {
  flash?: FlashProps
  notificationUnreadCount?: number
  notificationsUnreadCount?: number
  unreadNotificationsCount?: number
  notifications?: {
    unread_count?: number
  }
  auth?: {
    user?: {
      roles?: RoleValue[]
      permissions?: PermissionValue[]
    } | null
    roles?: RoleValue[]
    permissions?: PermissionValue[]
  }
}

const page = usePage<PageProps>()

const notifications = ref<NotificationItem[]>([])
const unreadCount = ref(0)
const isLoading = ref(false)
const isMarkingAllRead = ref(false)
const lastUpdatedAt = ref<string | null>(null)

let pollingTimer: number | null = null
let visibilityHandler: (() => void) | null = null

const normalizeNames = (values: Array<string | { name?: string }> | undefined | null) => {
  return (values ?? [])
    .map((value) => (typeof value === 'string' ? value : value?.name ?? ''))
    .filter(Boolean)
    .map((value) => String(value).toLowerCase())
}

const roles = computed(() => {
  return normalizeNames(page.props.auth?.user?.roles ?? page.props.auth?.roles)
})

const permissions = computed(() => {
  return normalizeNames(page.props.auth?.user?.permissions ?? page.props.auth?.permissions)
})

const isSuperAdmin = computed(() => roles.value.includes('super admin'))

const canViewNotifications = computed(() => {
  return (
    isSuperAdmin.value ||
    permissions.value.includes('notifications.view') ||
    permissions.value.includes('view.notifications') ||
    permissions.value.includes('notifications.index')
  )
})

const sharedUnreadCount = computed(() => {
  return Number(
    page.props.notificationUnreadCount ??
      page.props.notificationsUnreadCount ??
      page.props.unreadNotificationsCount ??
      page.props.notifications?.unread_count ??
      0
  )
})

const displayCount = computed(() => {
  return unreadCount.value > 99 ? '99+' : String(unreadCount.value)
})

const csrfToken = () => {
  return document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content') ?? ''
}

const formatTime = (value: string | null | undefined) => {
  if (!value) return 'Just now'

  try {
    return new Date(value).toLocaleString('en-GB', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return value
  }
}

const fetchLatestNotifications = async () => {
  if (!canViewNotifications.value || isLoading.value) return

  try {
    isLoading.value = true

    const response = await fetch('/notifications/latest', {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })

    if (!response.ok) return

    const data = await response.json()

    unreadCount.value = Number(
      data.unread_count ?? data.unreadCount ?? data.count ?? unreadCount.value
    )

    notifications.value = Array.isArray(data.notifications)
      ? data.notifications
      : notifications.value

    lastUpdatedAt.value = new Date().toLocaleTimeString('en-GB', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    })
  } catch {
    // Keep the global header quiet if the network briefly fails.
  } finally {
    isLoading.value = false
  }
}

const markAllAsRead = async () => {
  if (!canViewNotifications.value || isMarkingAllRead.value) return

  try {
    isMarkingAllRead.value = true

    const response = await fetch('/notifications/mark-all-read', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify({}),
    })

    if (!response.ok) return

    unreadCount.value = 0
    notifications.value = notifications.value.map((notification) => ({
      ...notification,
      read_at: notification.read_at ?? new Date().toISOString(),
    }))
  } catch {
    // Silent fail; user can still open the notifications page.
  } finally {
    isMarkingAllRead.value = false
  }
}

const startPolling = () => {
  if (!canViewNotifications.value) return

  fetchLatestNotifications()

  pollingTimer = window.setInterval(() => {
    fetchLatestNotifications()
  }, 15000)

  visibilityHandler = () => {
    if (!document.hidden) {
      fetchLatestNotifications()
    }
  }

  document.addEventListener('visibilitychange', visibilityHandler)
}

const stopPolling = () => {
  if (pollingTimer) {
    window.clearInterval(pollingTimer)
    pollingTimer = null
  }

  if (visibilityHandler) {
    document.removeEventListener('visibilitychange', visibilityHandler)
    visibilityHandler = null
  }
}

watch(
  sharedUnreadCount,
  (value) => {
    unreadCount.value = Number(value ?? 0)
  },
  { immediate: true }
)

onMounted(() => {
  startPolling()
})

onBeforeUnmount(() => {
  stopPolling()
})
</script>

<template>
  <DropdownMenu v-if="canViewNotifications">
    <DropdownMenuTrigger :as-child="true">
      <button
        type="button"
        title="Notifications"
        aria-label="Open notifications"
        class="relative inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-700/80 bg-slate-950 text-slate-300 shadow-sm transition hover:border-yellow-400/60 hover:bg-slate-900 hover:text-white focus:outline-none focus:ring-2 focus:ring-yellow-400/30"
      >
        <Bell
          class="h-5 w-5 text-yellow-400"
          :class="unreadCount > 0 ? 'animate-pulse' : ''"
        />

        <span
          v-if="unreadCount > 0"
          class="absolute -right-1.5 -top-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-slate-950"
        >
          {{ displayCount }}
        </span>
      </button>
    </DropdownMenuTrigger>

    <DropdownMenuContent align="end" class="w-96 max-w-[calc(100vw-2rem)] border-slate-800 bg-slate-950 p-0 text-slate-100 shadow-2xl">
      <div class="border-b border-slate-800 p-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <Bell class="h-4 w-4 text-yellow-400" />
              <h3 class="font-semibold text-white">Notifications</h3>
              <span
                v-if="unreadCount > 0"
                class="rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-bold text-white"
              >
                {{ displayCount }} unread
              </span>
            </div>
            <p class="mt-1 text-xs text-slate-400">
              Real-time system activity, approvals, maintenance, and security alerts.
            </p>
          </div>

          <LoaderCircle v-if="isLoading" class="h-4 w-4 animate-spin text-slate-400" />
          <RadioTower v-else class="h-4 w-4 text-emerald-400" />
        </div>

        <div class="mt-3 flex items-center justify-between gap-2 text-xs text-slate-500">
          <span>Auto-refreshes every 15 seconds</span>
          <span v-if="lastUpdatedAt">Updated {{ lastUpdatedAt }}</span>
        </div>
      </div>

      <div class="max-h-96 overflow-y-auto p-2">
        <div
          v-for="notification in notifications.slice(0, 10)"
          :key="notification.id"
          class="rounded-xl border border-transparent p-3 transition hover:border-slate-800 hover:bg-slate-900/70"
          :class="notification.read_at ? 'opacity-75' : 'bg-slate-900/40'"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span
                  class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                  :class="notification.read_at ? 'bg-slate-800 text-slate-400' : 'bg-emerald-500/10 text-emerald-300'"
                >
                  {{ notification.read_at ? 'Read' : 'Unread' }}
                </span>
                <span class="rounded-full bg-blue-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-300">
                  {{ notification.type ?? 'System' }}
                </span>
                <span class="text-[11px] text-slate-500">
                  {{ formatTime(notification.created_at) }}
                </span>
              </div>

              <h4 class="mt-2 truncate text-sm font-semibold text-white">
                {{ notification.title ?? 'System notification' }}
              </h4>
              <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-300">
                {{ notification.message ?? 'A new system event was recorded.' }}
              </p>
              <p class="mt-1 text-[11px] text-slate-500">
                Actor: {{ notification.actor?.name ?? 'System' }}
              </p>
            </div>
          </div>
        </div>

        <div v-if="!notifications.length" class="p-8 text-center">
          <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-900 text-slate-400">
            <Bell class="h-5 w-5" />
          </div>
          <p class="mt-3 text-sm font-semibold text-slate-300">No notifications yet</p>
          <p class="mt-1 text-xs text-slate-500">New alerts will appear here automatically.</p>
        </div>
      </div>

      <div class="flex items-center justify-between gap-2 border-t border-slate-800 p-3">
        <button
          type="button"
          :disabled="unreadCount === 0 || isMarkingAllRead"
          class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-300 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
          @click="markAllAsRead"
        >
          <CheckCheck class="h-4 w-4" />
          Mark all read
        </button>

        <Link
          href="/notifications"
          class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-500"
        >
          View all
          <ExternalLink class="h-4 w-4" />
        </Link>
      </div>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
