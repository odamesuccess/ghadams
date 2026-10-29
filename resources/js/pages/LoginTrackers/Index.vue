<script setup lang="ts">
import { h, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import type { ColumnDef } from '@tanstack/vue-table'
import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/DataTable.vue'
import type { BreadcrumbItem } from '@/types'
import { can } from '@/utils/permissions'
import { confirmDelete } from '@/utils/sweetalert'

interface UserSummary {
  id: number
  name: string
  email: string
}

interface LoginTracker {
  id: number
  user?: UserSummary | null
  email?: string | null
  event: string
  ip_address?: string | null
  browser?: string | null
  platform?: string | null
  device?: string | null
  login_at?: string | null
  logout_at?: string | null
  session_duration_seconds?: number | null
  duration_seconds?: number | null
  description?: string | null
  created_at: string
}

const props = defineProps<{
  loginTrackers: { data: LoginTracker[] }
  events: string[]
  filters: { search?: string; event?: string }
}>()

const search = ref(props.filters.search ?? '')
const event = ref(props.filters.event ?? '')

let reloadTimer: ReturnType<typeof setTimeout> | null = null

const reload = () => {
  if (reloadTimer) {
    clearTimeout(reloadTimer)
  }

  reloadTimer = setTimeout(() => {
    router.get(
      '/login-trackers',
      {
        search: search.value,
        event: event.value,
      },
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      },
    )
  }, 300)
}

watch(search, reload)
watch(event, reload)

const formatFullDateTime = (value?: string | null) => {
  if (!value) return '—'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return '—'

  return date.toLocaleString('en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  })
}

const formatDuration = (seconds?: number | null) => {
  const value = Number(seconds ?? 0)

  if (!value) return '—'

  const hours = Math.floor(value / 3600)
  const minutes = Math.floor((value % 3600) / 60)
  const remainingSeconds = value % 60

  if (hours > 0) {
    return `${hours}h ${minutes}m ${remainingSeconds}s`
  }

  if (minutes > 0) {
    return `${minutes}m ${remainingSeconds}s`
  }

  return `${remainingSeconds}s`
}

const eventClass = (value: string) => {
  if (value.includes('success') || value.includes('completed')) {
    return 'border-green-500/30 bg-green-600/20 text-green-300'
  }

  if (value.includes('failed') || value.includes('expired')) {
    return 'border-red-500/30 bg-red-600/20 text-red-300'
  }

  if (value.includes('inactive')) {
    return 'border-orange-500/30 bg-orange-600/20 text-orange-300'
  }

  if (value.includes('logout')) {
    return 'border-blue-500/30 bg-blue-600/20 text-blue-300'
  }

  return 'border-slate-500/30 bg-slate-600/20 text-slate-300'
}

const shortText = (value?: string | null, fallback = '—') => {
  if (!value) return fallback
  return value
}

const dateCellClass = 'block w-[230px] min-w-[230px] whitespace-nowrap text-slate-100'
const normalCellClass = 'block w-[130px] max-w-[130px] truncate text-slate-100'
const emailCellClass = 'block w-[140px] max-w-[140px] truncate text-slate-100'
const durationCellClass = 'block w-[100px] min-w-[100px] whitespace-nowrap text-slate-100'
const smallCellClass = 'block w-[110px] max-w-[110px] truncate text-slate-100'

const columns: ColumnDef<LoginTracker>[] = [
  {
    header: 'User',
    cell: ({ row }) =>
      h(
        'span',
        {
          class: normalCellClass,
          title: row.original.user?.name ?? '—',
        },
        row.original.user?.name ?? '—',
      ),
  },
  {
    header: 'Email',
    accessorKey: 'email',
    cell: ({ row, getValue }) => {
      const email = shortText((getValue() as string | null) ?? row.original.user?.email ?? null)

      return h(
        'span',
        {
          class: emailCellClass,
          title: email,
        },
        email,
      )
    },
  },
  {
    header: 'Event',
    accessorKey: 'event',
    cell: ({ getValue }) => {
      const value = String(getValue() ?? '—')

      return h(
        'span',
        {
          class: `whitespace-nowrap rounded border px-2 py-0.5 text-xs font-semibold ${eventClass(value)}`,
        },
        value.replaceAll('_', ' '),
      )
    },
  },
  {
    header: 'IP',
    accessorKey: 'ip_address',
    cell: ({ getValue }) =>
      h(
        'span',
        {
          class: 'block w-[100px] whitespace-nowrap text-slate-100',
          title: shortText(getValue() as string | null),
        },
        shortText(getValue() as string | null),
      ),
  },
 


  {
    header: 'Login',
    accessorKey: 'login_at',
    cell: ({ row }) => {
      const value = formatFullDateTime(row.original.login_at ?? row.original.created_at)

      return h(
        'span',
        {
          class: dateCellClass,
          title: value,
        },
        value,
      )
    },
  },
  {
    header: 'Logout',
    accessorKey: 'logout_at',
    cell: ({ getValue }) => {
      const value = formatFullDateTime(getValue() as string | null)

      return h(
        'span',
        {
          class: dateCellClass,
          title: value,
        },
        value,
      )
    },
  },
  {
    header: 'Duration',
    accessorKey: 'session_duration_seconds',
    cell: ({ row }) => {
      const value = formatDuration(
        row.original.session_duration_seconds ?? row.original.duration_seconds ?? null,
      )

      return h(
        'span',
        {
          class: durationCellClass,
          title: value,
        },
        value,
      )
    },
  },
  {
    header: 'Date',
    accessorKey: 'created_at',
    cell: ({ getValue }) => {
      const value = formatFullDateTime(getValue() as string)

      return h(
        'span',
        {
          class: dateCellClass,
          title: value,
        },
        value,
      )
    },
  },
  {
    header: 'Actions',
    id: 'actions',
    cell: ({ row }) =>
      can('login-trackers.delete')
        ? h(
            'button',
            {
              class: 'whitespace-nowrap rounded bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700',
              onClick: async () => {
                const confirmed = await confirmDelete({
                  title: 'Delete Login Record',
                  text: 'Are you sure you want to delete this login tracker record?',
                })
                if (confirmed) {
                  router.delete(`/login-trackers/${row.original.id}`, {
                    preserveScroll: true,
                  })
                }
              },
            },
            'Delete',
          )
        : '—',
  },
]

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Security', href: '/login-trackers' },
  { title: 'Login Tracker', href: '/login-trackers' },
]
</script>

<template>
  <Head title="Login Tracker" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="mx-6 mt-4 rounded-lg border border-slate-700 bg-slate-900 p-4">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <input
          v-model="search"
          type="text"
          placeholder="Search user, email, IP, browser..."
          class="w-full rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-400 md:max-w-sm"
        />

        <select
          v-model="event"
          class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-100"
        >
          <option value="">All events</option>
          <option
            v-for="item in events"
            :key="item"
            :value="item"
          >
            {{ item.replaceAll('_', ' ') }}
          </option>
        </select>
      </div>
    </div>

    <div class="mx-6 mt-4 rounded-lg border border-slate-700 bg-slate-900">
      <div class="overflow-x-auto">
        <div class="min-w-[1750px]">
          <DataTable
            :data="loginTrackers.data"
            :columns="columns"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
