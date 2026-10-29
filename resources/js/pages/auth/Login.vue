<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3'

import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import AuthBase from '@/layouts/AuthLayout.vue'

import { store } from '@/routes/login'
import { request as forgotPassword } from '@/routes/password'

defineProps<{
  status?: string
  canResetPassword: boolean
  canRegister: boolean
}>()
</script>

<template>
  <AuthBase
    compact
    title="GhanaDAMS"
    description="Sign in to the Ghana Digital Asset Management System"
  >
    <Head title="GhanaDAMS Login" />

    <!-- Status Message -->
    <div
      v-if="status"
      class="mb-3 rounded-xl border border-emerald-400/40 bg-emerald-500/10 px-3 py-2 text-center text-sm font-semibold text-emerald-200"
    >
      {{ status }}
    </div>

    <Form
      v-bind="store.form()"
      :reset-on-success="['password']"
      v-slot="{ errors, processing }"
      class="flex flex-col gap-3"
    >
      <div class="grid gap-3">
        <!-- Email -->
        <div class="grid gap-1.5">
          <Label
            for="email"
            class="text-sm font-semibold text-slate-100"
          >
            Email address
          </Label>

          <Input
            id="email"
            type="email"
            name="email"
            required
            autofocus
            autocomplete="email"
            placeholder="email@example.com"
            class="h-11 rounded-xl border-white/10 bg-black/60 px-4 text-sm text-white placeholder:text-slate-400 focus-visible:ring-2 focus-visible:ring-blue-500"
          />

          <InputError :message="errors.email" />
        </div>

        <!-- Password -->
        <div class="grid gap-1.5">
          <Label
            for="password"
            class="text-sm font-semibold text-slate-100"
          >
            Password
          </Label>

          <Input
            id="password"
            type="password"
            name="password"
            required
            autocomplete="current-password"
            placeholder="Enter your password"
            class="h-11 rounded-xl border-white/10 bg-black/60 px-4 text-sm text-white placeholder:text-slate-400 focus-visible:ring-2 focus-visible:ring-blue-500"
          />

          <InputError :message="errors.password" />
        </div>

        <!-- First-time login notice -->
        <div
          class="rounded-xl border border-blue-300/30 bg-blue-50 p-3 text-sm leading-5 text-slate-800 shadow-sm"
        >
          <p class="mb-1 text-base font-extrabold text-slate-950">
            Is this your first time here?
          </p>

          <p>
            Log in using your email address and
            <span class="font-extrabold text-red-600">
              Password123!
            </span>
            as the default password.
          </p>

          <p class="mt-1">
            All first-time users are required to change the default password after logging in.
          </p>

          <p class="mt-2">
            For assistance, call
            <a
              href="tel:0242976760"
              class="font-extrabold text-blue-700 hover:underline"
            >
              0242976760
            </a>
            or WhatsApp
            <a
              href="https://wa.me/233242976760"
              target="_blank"
              rel="noopener noreferrer"
              class="font-extrabold text-blue-700 hover:underline"
            >
              0242976760
            </a>.
          </p>
        </div>

        <!-- Remember me and forgot password -->
        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
          <Label
            for="remember"
            class="flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-100"
          >
            <Checkbox
              id="remember"
              name="remember"
              class="border-white/30"
            />
            <span>Remember me</span>
          </Label>

          <Link
            v-if="canResetPassword"
            :href="forgotPassword().url"
            class="text-sm font-bold text-blue-300 hover:text-blue-200 hover:underline"
          >
            Forgotten password?
          </Link>
        </div>

        <!-- Submit -->
        <Button
          type="submit"
          class="mt-0 h-11 w-full rounded-xl bg-blue-600 text-sm font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-70"
          :disabled="processing"
        >
          <Spinner
            v-if="processing"
            class="mr-2"
          />

          <span>
            {{ processing ? 'Logging in...' : 'Log in' }}
          </span>
        </Button>
      </div>
    </Form>
  </AuthBase>
</template>
