<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { can } from '@/utils/permissions'
import type { BreadcrumbItem } from '@/types'
import { confirmDelete, toast } from '@/utils/sweetalert'

interface LinkData {
  id: number
  name: string
  url: string
  status: 'active' | 'expired' | 'disabled'
  logo?: string | null
  qr_code?: string | null
  expires_at?: string | null
  created_at?: string | null
  updated_at?: string | null
}

const props = defineProps<{ link: LinkData }>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Links', href: '/links' },
  { title: 'Details', href: '#' },
]

const deleteLink = async () => {
  const confirmed = await confirmDelete({
    title: 'Delete Link',
    text: `Are you sure you want to delete ${props.link.name}?`,
  })
  if (!confirmed) return

  router.delete(`/links/${props.link.id}`, {
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'QR link deleted successfully',
      })
      router.visit('/links')
    },
  })
}

const goBack = () => {
  router.visit('/links')
}
</script>

<template>
  <Head title="Link Details" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="px-6 py-4">

      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold text-white">
          Link Details
        </h1>

        <div class="flex items-center gap-2">
          <button
            @click="goBack"
            class="rounded bg-slate-700 px-4 py-2 text-sm text-white hover:bg-slate-600"
          >
            Back
          </button>

          <Link
            v-if="can('links.edit')"
            :href="`/links/${link.id}/edit`"
            class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
          >
            Edit
          </Link>

          <button
            v-if="can('links.delete')"
            @click="deleteLink"
            class="rounded bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
          >
            Delete
          </button>
        </div>
      </div>

      <!-- Main Card -->
      <div class="rounded-lg border border-slate-700 bg-slate-800/60 p-6">

        <div class="flex flex-col lg:flex-row items-start gap-12">

          <!-- LEFT: Link Info -->
          <div class="flex-1 space-y-5">

            <div>
              <p class="text-xs text-slate-400">Link Name</p>
              <p class="text-lg font-semibold text-white">
                {{ link.name }}
              </p>
            </div>

            <div>
              <p class="text-xs text-slate-400">Target URL</p>
              <a
                :href="link.url"
                target="_blank"
                class="text-blue-400 hover:text-blue-300 break-all text-sm"
              >
                {{ link.url }}
              </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm pt-2">
              <div>
                <p class="text-xs text-slate-400">Expires</p>
                <p class="text-white">
                  {{ link.expires_at ?? '—' }}
                </p>
              </div>

              <div>
                <p class="text-xs text-slate-400">Updated</p>
                <p class="text-white">
                  {{ link.updated_at ?? '—' }}
                </p>
              </div>

              <div>
                <p class="text-xs text-slate-400">Created</p>
                <p class="text-white">
                  {{ link.created_at ?? '—' }}
                </p>
              </div>
            </div>

          </div>

          <!-- RIGHT SIDE: Logo + QR -->
          <div class="flex items-start gap-10">

            <!-- Logo -->
            <div v-if="link.logo" class="flex flex-col items-center">
              <p class="text-xs text-slate-400 mb-3">Logo</p>
              <img
                :src="link.logo"
                class="h-24 w-24 object-contain rounded border border-slate-700 bg-slate-900 p-2"
              />
            </div>

            <!-- QR + Download (Aligned Side by Side) -->
            <div v-if="link.qr_code" class="flex items-center gap-6">

              <div class="flex flex-col items-center">
                <p class="text-xs text-slate-400 mb-3">QR Code</p>

                <img
                  :src="link.qr_code"
                  class="h-52 w-52 object-contain rounded border border-slate-700 bg-white p-3 shadow-lg"
                />
              </div>

              <div class="flex flex-col justify-center">
                <a
                  :href="link.qr_code"
                  download
                  class="rounded bg-emerald-600 px-5 py-3 text-sm font-medium text-white hover:bg-emerald-700 transition"
                >
                  Download QR
                </a>
              </div>

            </div>

          </div>

        </div>

      </div>

    </div>
  </AppLayout>
</template>
