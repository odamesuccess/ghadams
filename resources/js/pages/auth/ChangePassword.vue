<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'

import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import InputError from '@/components/InputError.vue'

const form = useForm({
    password: '',
    password_confirmation: '',
})

/* show / hide toggles */
const showPassword = ref(false)
const showConfirm = ref(false)

/* Password strength */
const strength = computed(() => {
    const p = form.password
    let score = 0
    if (p.length >= 8) score++
    if (/[A-Z]/.test(p)) score++
    if (/[a-z]/.test(p)) score++
    if (/[0-9]/.test(p)) score++
    if (/[^A-Za-z0-9]/.test(p)) score++
    return score
})

const strengthLabel = computed(() =>
    strength.value <= 2 ? 'Weak' : strength.value <= 4 ? 'Medium' : 'Strong'
)

const strengthColor = computed(() =>
    strength.value <= 2
        ? 'bg-red-500'
        : strength.value <= 4
            ? 'bg-yellow-500'
            : 'bg-green-600'
)

const passwordsMatch = computed(() => {
    if (!form.password || !form.password_confirmation) return true
    return form.password === form.password_confirmation
})

</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-background">
        <div class="w-full max-w-md rounded-lg border bg-card p-6 shadow">
            <h2 class="mb-6 text-center text-xl font-semibold">
                Change Your Password
            </h2>

            <form class="flex flex-col gap-5" @submit.prevent="form.put('/force-change-password')"

                <!-- Password -->
                <div class="grid gap-2">
                    <Label for="password">New Password</Label>

                    <div class="relative">
                        <Input id="password" :type="showPassword ? 'text' : 'password'" v-model="form.password"
                            autocomplete="new-password" class="
    pr-10
    bg-background
    text-foreground
    caret-foreground
    placeholder:text-muted-foreground
  " />


                        <button type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-sm text-muted-foreground"
                            @click="showPassword = !showPassword">
                            {{ showPassword ? 'Hide' : 'Show' }}
                        </button>
                    </div>

                    <InputError :message="form.errors.password" />
                </div>

                <!-- Strength meter -->
                <div v-if="form.password.length" class="space-y-1">
                    <div class="h-2 w-full rounded bg-muted overflow-hidden">
                        <div class="h-2 transition-all" :class="strengthColor"
                            :style="{ width: Math.max(strength * 20, 5) + '%' }" />
                    </div>

                    <p class="text-sm font-medium" :class="{
                        'text-red-600': strengthLabel === 'Weak',
                        'text-yellow-600': strengthLabel === 'Medium',
                        'text-green-600': strengthLabel === 'Strong',
                    }">
                        Password strength: {{ strengthLabel }}
                    </p>
                </div>

                <!-- Confirm -->
                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm Password</Label>

                    <div class="relative">
                        <Input id="password_confirmation" :type="showConfirm ? 'text' : 'password'"
                            v-model="form.password_confirmation" autocomplete="new-password" class="
    pr-10
    bg-background
    text-foreground
    caret-foreground
    placeholder:text-muted-foreground
  " :class="!passwordsMatch && 'border-red-500'" />



                        <button type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-sm text-muted-foreground"
                            @click="showConfirm = !showConfirm">
                            {{ showConfirm ? 'Hide' : 'Show' }}
                        </button>
                    </div>

                    <p v-if="!passwordsMatch" class="text-sm font-medium text-red-600">
                        Passwords do not match
                    </p>
                </div>

                <!-- Submit -->
                <Button type="submit" class="mt-2 w-full" :disabled="form.processing || !passwordsMatch">
                    Update Password
                </Button>
            </form>
        </div>
    </div>
</template>
