<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

import HeadingSmall from '@/components/HeadingSmall.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/settings/Layout.vue'
import { edit } from '@/routes/profile'

import type { BreadcrumbItem, AppPageProps } from '@/types'

/* =========================
   BREADCRUMBS
========================= */
const breadcrumbItems: BreadcrumbItem[] = [
  { title: 'Profile', href: edit().url },
]

/* =========================
   PAGE DATA (GLOBAL TYPES)
========================= */
const page = usePage<AppPageProps>()

const user = computed(() => page.props.auth.user)
const roles = computed(() => user.value?.roles ?? [])

/* =========================
   HELPERS
========================= */
const formatDate = (date?: string | null) => {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

const initials = computed(() => {
  if (!user.value?.name) return ''
  return user.value.name
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
})
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbItems">
    <Head title="My Profile" />

    <SettingsLayout>
      <section v-if="user" class="mx-auto w-[90%] max-w-[90vw] space-y-6">
        <HeadingSmall
          title="User Profile"
          description="Personal and account information (read-only)"
        />

        <!-- PROFILE SUMMARY -->
        <div class="rounded-xl border bg-background p-6">
          <div class="flex flex-col gap-8 sm:flex-row sm:items-center">
            <div class="relative shrink-0">
              <img
                v-if="user.profile_image"
                :src="`/storage/${user.profile_image}`"
                class="h-40 w-40 rounded-lg object-cover border"
              />
              <div
                v-else
                class="flex items-center justify-center h-40 w-40 rounded-lg border bg-muted text-3xl font-semibold"
              >
                {{ initials }}
              </div>
            </div>

            <div class="space-y-4">
              <div>
                <p class="text-xs uppercase text-muted-foreground">Full name</p>
                <p class="text-xl font-semibold">{{ user.name }}</p>
              </div>

              <div>
                <p class="text-xs uppercase text-muted-foreground">Email</p>
                <p class="text-sm">{{ user.email }}</p>
              </div>

              <div>
                <p class="text-xs uppercase text-muted-foreground">User status</p>
                <span class="rounded-full px-3 py-1 text-xs font-medium bg-red-100 text-red-700">
                  {{ user.user_status }}
                </span>
              </div>

              <div v-if="roles.length">
                <p class="text-xs uppercase text-muted-foreground">Role(s)</p>
                <div class="flex flex-wrap gap-2">
                  <span
                    v-for="role in roles"
                    :key="role.id"
                    class="rounded-md bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700"
                  >
                    {{ role.name.replace('.', ' ') }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ACCOUNT DETAILS -->
        <div class="rounded-xl border bg-background p-6">
          <h3 class="mb-4 text-sm font-semibold text-muted-foreground">
            Account Details
          </h3>

          <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            <div>
              <dt class="text-sm text-muted-foreground">Contact Number</dt>
              <dd class="font-medium">{{ user.contact_number ?? '—' }}</dd>
            </div>

            <div>
              <dt class="text-sm text-muted-foreground">Sex</dt>
              <dd class="font-medium">{{ user.sex ?? '—' }}</dd>
            </div>

            <div>
              <dt class="text-sm text-muted-foreground">Unit Name</dt>
              <dd class="font-medium">
                {{ user.unit?.name ?? '—' }}
              </dd>
            </div>

            <div>
              <dt class="text-sm text-muted-foreground">Date of Birth</dt>
              <dd class="font-medium">{{ formatDate(user.date_of_birth) }}</dd>
            </div>

            <div>
              <dt class="text-sm text-muted-foreground">Account Created</dt>
              <dd class="font-medium">{{ formatDate(user.created_at) }}</dd>
            </div>

            <div>
              <dt class="text-sm text-muted-foreground">Last Updated</dt>
              <dd class="font-medium">{{ formatDate(user.updated_at) }}</dd>
            </div>
          </dl>
        </div>
      </section>
    </SettingsLayout>
  </AppLayout>
</template>
