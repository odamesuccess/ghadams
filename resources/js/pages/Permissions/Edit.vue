<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { toast } from '@/utils/sweetalert'
import { update, index } from '@/routes/permissions'

/* =======================
   Props
======================= */
const props = defineProps<{
  permission: {
    id: number
    name: string
  }
}>()

/* =======================
   Form
======================= */
const form = useForm({
  name: props.permission.name,
})

/* =======================
   Submit
======================= */
const submit = () => {
  form.put(update(props.permission.id).url, {
    onSuccess: () => {
      toast.fire({
        icon: 'success',
        title: 'Permission updated successfully',
      })
    },
    onError: () => {
      toast.fire({
        icon: 'error',
        title: 'Failed to update permission',
      })
    },
  })
}
</script>

<template>
  <Head title="Edit Permission" />

  <AppLayout>
    <form
      @submit.prevent="submit"
      class="mx-6 mt-6 max-w-md rounded-lg border bg-white dark:bg-slate-900 p-6 space-y-4"
    >
      <!-- Title -->
      <h2 class="text-lg font-semibold">
        Edit Permission
      </h2>

      <!-- Permission Name -->
      <div class="space-y-1">
        <label class="text-sm font-medium">
          Permission Name
        </label>

        <input
          v-model="form.name"
          type="text"
          placeholder="e.g. users.create"
          class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm
                 focus:outline-none focus:ring-2 focus:ring-blue-600"
        />

        <p v-if="form.errors.name" class="text-xs text-red-500">
          {{ form.errors.name }}
        </p>
      </div>

      <!-- Actions -->
      <div class="flex gap-2 pt-2">
        <button
          type="submit"
          class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white
                 hover:bg-blue-700 disabled:opacity-50"
          :disabled="form.processing"
        >
          Update
        </button>

        <Link
          :href="index().url"
          class="rounded bg-slate-600 px-4 py-2 text-sm font-semibold text-white
                 hover:bg-slate-700"
        >
          Cancel
        </Link>
      </div>
    </form>
  </AppLayout>
</template>
