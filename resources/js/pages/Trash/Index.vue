<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import type { BreadcrumbItem } from '@/types'
import { ArchiveRestore, Search, Trash2, RotateCcw } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { confirmDelete } from '@/utils/sweetalert'

const props = defineProps<{
  filters: { module?: string; search?: string }
  modules: Array<{ key: string; label: string }>
  items: {
    data: Array<{ id: number; label: string; deleted_at: string | null; created_at: string | null }>
    links: Array<{ url: string | null; label: string; active: boolean }>
    from: number | null
    to: number | null
    total: number
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Recycle Bin', href: '/trash' },
]

const selectedModule = ref(props.filters.module ?? 'assets')
const search = ref(props.filters.search ?? '')

const selectedModuleLabel = computed(() => props.modules.find((m) => m.key === selectedModule.value)?.label ?? 'Records')

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch([selectedModule, search], () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    router.get('/trash', { module: selectedModule.value, search: search.value || undefined }, {
      preserveState: true,
      replace: true,
    })
  }, 350)
})

const restoreItem = (id: number) => {
  router.post(`/trash/${selectedModule.value}/${id}/restore`, {}, {
    preserveScroll: true,
  })
}

const forceDeleteItem = async (id: number, label: string) => {
  const confirmed = await confirmDelete({
    title: 'Permanently Delete',
    text: `Permanently delete ${label}? This action cannot be undone.`,
    confirmText: 'Delete Forever',
  })
  if (!confirmed) return

  router.delete(`/trash/${selectedModule.value}/${id}/force-delete`, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="Recycle Bin" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <section class="space-y-4 p-4 sm:p-6">
      <div class="rounded-2xl border border-slate-800 bg-slate-950/80 p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="min-w-0">
            <div class="flex items-center gap-2 text-slate-100">
              <ArchiveRestore class="h-5 w-5" />
              <h1 class="truncate text-xl font-bold">Recycle Bin / Soft Deletes</h1>
            </div>
            <p class="mt-1 text-sm text-slate-400">
              Restore soft-deleted records or permanently remove records when necessary.
            </p>
          </div>

          <div class="rounded-full border border-slate-700 px-3 py-1 text-xs font-semibold text-slate-300">
            {{ items.total }} deleted {{ items.total === 1 ? 'record' : 'records' }}
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-800 bg-slate-950/80 p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[220px_1fr]">
          <select
            v-model="selectedModule"
            class="h-10 rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm text-slate-100 outline-none focus:border-blue-500"
          >
            <option v-for="module in modules" :key="module.key" :value="module.key">
              {{ module.label }}
            </option>
          </select>

          <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
            <input
              v-model="search"
              type="search"
              class="h-10 w-full rounded-lg border border-slate-700 bg-slate-900 pl-9 pr-3 text-sm text-slate-100 outline-none placeholder:text-slate-500 focus:border-blue-500"
              :placeholder="`Search deleted ${selectedModuleLabel.toLowerCase()}...`"
            />
          </div>
        </div>
      </div>

      <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/80 shadow-sm">
        <div v-fill-viewport-table="{ bottomGap: 72, minHeight: 320 }" class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-800 text-sm">
            <thead class="bg-slate-900/70 text-left text-xs uppercase tracking-wide text-slate-400">
              <tr>
                <th class="px-4 py-3">Record</th>
                <th class="px-4 py-3">Deleted At</th>
                <th class="px-4 py-3">Created At</th>
                <th class="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800 text-slate-200">
              <tr v-if="items.data.length === 0">
                <td colspan="4" class="px-4 py-8 text-center text-slate-400">
                  No deleted records found for this module.
                </td>
              </tr>

              <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-900/50">
                <td class="px-4 py-3 font-medium">{{ item.label }}</td>
                <td class="px-4 py-3 text-slate-400">{{ item.deleted_at ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-400">{{ item.created_at ?? '—' }}</td>
                <td class="px-4 py-3">
                  <div class="flex justify-end gap-2">
                    <button
                      type="button"
                      class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500"
                      @click="restoreItem(item.id)"
                    >
                      <RotateCcw class="h-4 w-4" />
                      Restore
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-500"
                      @click="forceDeleteItem(item.id, item.label)"
                    >
                      <Trash2 class="h-4 w-4" />
                      Permanent Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-slate-800 px-4 py-3 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between">
          <div>
            Showing {{ items.from ?? 0 }} to {{ items.to ?? 0 }} of {{ items.total }} records
          </div>
          <div class="flex flex-wrap gap-2">
            <Link
              v-for="link in items.links"
              :key="link.label"
              :href="link.url ?? '#'"
              class="rounded-lg border px-3 py-1.5 text-xs"
              :class="link.active ? 'border-blue-500 bg-blue-500 text-white' : 'border-slate-700 text-slate-300 hover:bg-slate-800'"
              v-html="link.label"
            />
          </div>
        </div>
      </div>
    </section>
  </AppLayout>
</template>

