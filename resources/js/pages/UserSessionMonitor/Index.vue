<script setup lang="ts">
import SessionMonitorDataTable from '@/components/SessionMonitorDataTable.vue'
import type { ColumnDef } from '@tanstack/vue-table'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { profileImageUrl } from '@/utils/profileImage'
import { Head, router } from '@inertiajs/vue3'
import {
  Activity,
  CircleDot,
  Clock3,
  Eye,
  MapPin,
  RefreshCw,
  Search,
  ShieldAlert,
  UsersRound,
  Wifi,
  WifiOff,
} from 'lucide-vue-next'
import { computed, h, onBeforeUnmount, onMounted, ref, watch } from 'vue'

interface UserSession {
  id: number
  name: string
  email: string
  profile_image?: string | null
  role: string
  unit: string
  presence: 'online' | 'idle' | 'offline'
  current_page: string
  current_url?: string | null
  login_at?: string | null
  last_activity_at?: string | null
  last_login_at?: string | null
  last_logout_at?: string | null
  session_duration_seconds?: number | null
  ip_address?: string | null
  city?: string | null
  region?: string | null
  country?: string | null
  active_sessions: number
  security_flags: string[]
}

const props = defineProps<{
  monitorRows: UserSession[]
  summary: {
    online: number
    idle: number
    offline: number
    active_today: number
  }
  filters: {
    search?: string
    status?: string
    role?: string
    unit?: string
    country?: string
  }
  roles: string[]
  units: string[]
  countries: string[]
  presenceRules: {
    online_minutes: number
    idle_minutes: number
  }
  refreshedAt: string
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'User Session Monitor', href: '/user-session-monitor' },
]

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? '')
const role = ref(props.filters.role ?? '')
const unit = ref(props.filters.unit ?? '')
const country = ref(props.filters.country ?? '')
const selected = ref<UserSession | null>(null)
const refreshing = ref(false)
let searchTimer: ReturnType<typeof setTimeout> | null = null
let refreshTimer: ReturnType<typeof setInterval> | null = null

const query = (page = 1) => ({
  search: search.value || undefined,
  status: status.value || undefined,
  role: role.value || undefined,
  unit: unit.value || undefined,
  country: country.value || undefined,
  page,
})

const applyFilters = (page = 1) => {
  router.get('/user-session-monitor', query(page), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

watch(search, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => applyFilters(), 300)
})

watch([status, role, unit, country], () => applyFilters())

const resetFilters = () => {
  search.value = ''
  status.value = ''
  role.value = ''
  unit.value = ''
  country.value = ''
  router.get('/user-session-monitor', {}, { replace: true })
}

const refresh = () => {
  refreshing.value = true
  router.reload({
    only: ['monitorRows', 'summary', 'countries', 'refreshedAt'],
    onFinish: () => {
      refreshing.value = false
    },
  })
}

onMounted(() => {
  refreshTimer = setInterval(refresh, 60000)
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
  if (refreshTimer) clearInterval(refreshTimer)
})

const summaryCards = computed(() => [
  {
    label: 'Online',
    value: props.summary.online,
    class: 'border-emerald-500/25 bg-emerald-500/8 text-emerald-300',
    icon: Wifi,
  },
  {
    label: 'Idle',
    value: props.summary.idle,
    class: 'border-amber-500/25 bg-amber-500/8 text-amber-300',
    icon: Clock3,
  },
  {
    label: 'Offline',
    value: props.summary.offline,
    class: 'border-slate-700 bg-slate-900/60 text-slate-300',
    icon: WifiOff,
  },
  {
    label: 'Active Today',
    value: props.summary.active_today,
    class: 'border-blue-500/25 bg-blue-500/8 text-blue-300',
    icon: Activity,
  },
])

const presenceClass = (value: UserSession['presence']) => {
  if (value === 'online') return 'border-emerald-500/25 bg-emerald-500/10 text-emerald-300'
  if (value === 'idle') return 'border-amber-500/25 bg-amber-500/10 text-amber-300'
  return 'border-slate-700 bg-slate-900 text-slate-400'
}

const presenceDotClass = (value: UserSession['presence']) => {
  if (value === 'online') return 'bg-emerald-400'
  if (value === 'idle') return 'bg-amber-400'
  return 'bg-slate-500'
}

const initials = (name: string) =>
  name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('') || 'U'

const formatDateTime = (value?: string | null) => {
  if (!value) return '\u2014'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '\u2014'

  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date)
}

const relativeTime = (value?: string | null) => {
  if (!value) return 'Never'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Never'
  const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000))
  if (seconds < 45) return 'Now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} hr ago`
  return `${Math.floor(seconds / 86400)} d ago`
}

const duration = (seconds?: number | null) => {
  if (seconds == null || !Number.isFinite(seconds)) return '\u2014'
  const totalSeconds = Math.max(0, Math.round(seconds))
  if (totalSeconds < 60) return `${totalSeconds}s`
  const minutes = Math.floor(totalSeconds / 60)
  if (minutes < 60) return `${minutes}m`
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  return `${hours}h ${mins}m`
}

const locationText = (user: UserSession) => {
  const values = [user.city, user.region, user.country]
    .filter((value) => value && !['Unknown', 'Local'].includes(value))
  if (values.length) return values.join(', ')
  if (user.country === 'Local') return 'Local network'
  return 'Unknown'
}

// Uses the same TanStack column API and Auto pagination as LMS.
const textLines = (primary: string, secondary?: string | null) => h('div', { class: 'min-w-0' }, [
  h('p', { class: 'truncate font-semibold text-slate-100', title: primary }, primary),
  secondary ? h('p', { class: 'truncate text-[10px] text-slate-500', title: secondary }, secondary) : null,
])
const columns: ColumnDef<UserSession>[] = [
  {
    accessorKey: 'name', header: 'User',
    cell: ({ row }) => {
      const user = row.original
      const image = profileImageUrl(user.profile_image)
      return h('div', { class: 'flex min-w-[180px] items-center gap-2.5' }, [
        image ? h('img', { src: image, alt: user.name, class: 'h-8 w-8 shrink-0 rounded-full border border-slate-700 object-cover' })
          : h('span', { class: 'grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-800 text-[10px] font-bold' }, initials(user.name)),
        textLines(user.name, user.email),
      ])
    },
  },
  { accessorKey: 'role', header: 'Role / Unit', cell: ({ row }) => textLines(row.original.role, row.original.unit) },
  {
    accessorKey: 'presence', header: 'Presence',
    cell: ({ row }) => h('span', { class: 'inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-[10px] font-bold capitalize '+presenceClass(row.original.presence) }, [
      h('span', { class: 'h-1.5 w-1.5 rounded-full '+presenceDotClass(row.original.presence) }), row.original.presence,
    ]),
  },
  { accessorKey: 'current_page', header: 'Current Page', cell: ({ row }) => textLines(row.original.current_page, row.original.security_flags.join(' \u00b7 ')) },
  { accessorKey: 'country', header: 'Approx. Location', cell: ({ row }) => textLines(locationText(row.original), row.original.ip_address ?? '\u2014') },
  { accessorKey: 'login_at', header: 'Login', cell: ({ row }) => textLines(formatDateTime(row.original.login_at), duration(row.original.session_duration_seconds)) },
  { accessorKey: 'last_activity_at', header: 'Activity', cell: ({ row }) => textLines(relativeTime(row.original.last_activity_at)) },
  { accessorKey: 'active_sessions', header: 'Sessions', cell: ({ row }) => h('span', { class: 'font-semibold' }, String(row.original.active_sessions)) },
  {
    id: 'actions', header: 'Action',
    cell: ({ row }) => h('button', {
      type: 'button', class: 'inline-flex items-center gap-1 rounded-md border border-blue-500/30 bg-blue-500/10 px-2 py-1 text-xs text-blue-300',
      onClick: () => { selected.value = row.original },
    }, [h(Eye, { class: 'h-3 w-3' }), 'View']),
  },
]

const refreshedLabel = computed(() => formatDateTime(props.refreshedAt))
</script>

<template>
  <Head title="User Session Monitor" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-3 my-3 space-y-3 text-slate-100 sm:mx-4 lg:mx-5">
      <section class="flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 shadow-xl lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <UsersRound class="h-4 w-4 text-blue-300" />
            <h1 class="text-lg font-black text-white sm:text-xl">User Session Monitor</h1>
          </div>
          <p class="mt-1 text-xs text-slate-400">
            Online, idle and offline presence with current page, approximate login location and concurrent sessions.
          </p>
        </div>

        <div class="flex items-center gap-2 text-[11px] text-slate-400">
          <span class="hidden sm:inline">Last refresh: {{ refreshedLabel }}</span>
          <button
            type="button"
            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-900 px-3 font-semibold text-slate-200 transition hover:border-blue-500/50 hover:text-white"
            @click="refresh"
          >
            <RefreshCw class="h-3.5 w-3.5" :class="refreshing ? 'animate-spin' : ''" />
            Refresh
          </button>
        </div>
      </section>

      <section class="grid grid-cols-2 gap-2 lg:grid-cols-4">
        <div
          v-for="card in summaryCards"
          :key="card.label"
          class="flex min-h-[68px] items-center justify-between rounded-xl border px-3 py-2.5"
          :class="card.class"
        >
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wide opacity-80">{{ card.label }}</p>
            <p class="mt-0.5 text-2xl font-black text-white">{{ card.value }}</p>
          </div>
          <component :is="card.icon" class="h-5 w-5 opacity-80" />
        </div>
      </section>

      <section class="rounded-2xl border border-slate-800 bg-slate-950 p-2.5">
        <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-[minmax(220px,1.7fr)_repeat(4,minmax(130px,0.8fr))_auto]">
          <label class="relative block">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-500" />
            <input
              v-model="search"
              type="search"
              placeholder="Search user, unit, IP or location..."
              class="h-9 w-full rounded-lg border border-slate-700 bg-black pl-9 pr-3 text-xs text-white outline-none placeholder:text-slate-500 focus:border-blue-500"
            />
          </label>

          <select v-model="status" class="h-9 rounded-lg border border-slate-700 bg-black px-2 text-xs text-white outline-none focus:border-blue-500">
            <option value="">All Statuses</option>
            <option value="online">Online</option>
            <option value="idle">Idle</option>
            <option value="offline">Offline</option>
          </select>

          <select v-model="role" class="h-9 rounded-lg border border-slate-700 bg-black px-2 text-xs text-white outline-none focus:border-blue-500">
            <option value="">All Roles</option>
            <option v-for="item in roles" :key="item" :value="item">{{ item }}</option>
          </select>

          <select v-model="unit" class="h-9 rounded-lg border border-slate-700 bg-black px-2 text-xs text-white outline-none focus:border-blue-500">
            <option value="">All Units</option>
            <option v-for="item in units" :key="item" :value="item">{{ item }}</option>
          </select>

          <select v-model="country" class="h-9 rounded-lg border border-slate-700 bg-black px-2 text-xs text-white outline-none focus:border-blue-500">
            <option value="">All Countries</option>
            <option v-for="item in countries" :key="item" :value="item">{{ item }}</option>
          </select>

          <button
            type="button"
            class="h-9 rounded-lg border border-slate-700 bg-slate-900 px-3 text-xs font-semibold text-slate-200 hover:text-white"
            @click="resetFilters"
          >
            Reset
          </button>
        </div>
      </section>

      <section class="min-w-0">
        <SessionMonitorDataTable
          :data="props.monitorRows"
          :columns="columns"
          :searchable="false"
          :preserve-page-on-data-change="true"
          empty-text="No users match the selected filters."
        />
      </section>
    </div>

    <div v-if="selected" class="fixed inset-0 z-50 grid place-items-center bg-black/70 p-3" @click.self="selected = null">
      <section class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-700 bg-slate-950 shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-800 px-4 py-3">
          <div>
            <p class="text-xs font-black text-white">Session Details</p>
            <p class="mt-0.5 text-[10px] text-slate-500">{{ selected.name }}</p>
          </div>
          <button type="button" class="rounded-md border border-slate-700 px-2 py-1 text-xs text-slate-300" @click="selected = null">Close</button>
        </header>

        <div class="grid grid-cols-2 gap-2 p-4 text-xs">
          <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-2.5">
            <p class="text-[9px] font-bold uppercase text-slate-500">Presence</p>
            <p class="mt-1 font-bold capitalize text-white">{{ selected.presence }}</p>
          </div>
          <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-2.5">
            <p class="text-[9px] font-bold uppercase text-slate-500">Session Duration</p>
            <p class="mt-1 font-bold text-white">{{ duration(selected.session_duration_seconds) }}</p>
          </div>
          <div class="col-span-2 rounded-lg border border-slate-800 bg-slate-900/60 p-2.5">
            <p class="text-[9px] font-bold uppercase text-slate-500">Current Page</p>
            <p class="mt-1 font-bold text-white">{{ selected.current_page }}</p>
          </div>
          <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-2.5">
            <p class="text-[9px] font-bold uppercase text-slate-500">Login Time</p>
            <p class="mt-1 font-semibold text-white">{{ formatDateTime(selected.login_at) }}</p>
          </div>
          <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-2.5">
            <p class="text-[9px] font-bold uppercase text-slate-500">Last Activity</p>
            <p class="mt-1 font-semibold text-white">{{ formatDateTime(selected.last_activity_at) }}</p>
          </div>
          <div class="col-span-2 rounded-lg border border-slate-800 bg-slate-900/60 p-2.5">
            <p class="text-[9px] font-bold uppercase text-slate-500">Approximate Location</p>
            <p class="mt-1 font-semibold text-white">{{ locationText(selected) }}</p>
            <p class="mt-1 text-[10px] text-slate-500">IP: {{ selected.ip_address ?? '\u2014' }}</p>
          </div>
          <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-2.5">
            <p class="text-[9px] font-bold uppercase text-slate-500">Previous / Last Login</p>
            <p class="mt-1 font-semibold text-white">{{ formatDateTime(selected.last_login_at) }}</p>
          </div>
          <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-2.5">
            <p class="text-[9px] font-bold uppercase text-slate-500">Active Sessions</p>
            <p class="mt-1 font-semibold text-white">{{ selected.active_sessions }}</p>
          </div>
          <div v-if="selected.security_flags.length" class="col-span-2 rounded-lg border border-amber-500/25 bg-amber-500/10 p-2.5">
            <div class="flex items-center gap-1.5 text-amber-300">
              <ShieldAlert class="h-3.5 w-3.5" />
              <p class="text-[9px] font-black uppercase">Security Flags</p>
            </div>
            <p class="mt-1 text-[11px] font-semibold text-amber-200">{{ selected.security_flags.join(' \u00b7 ') }}</p>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>

