<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AppLogoIcon from '@/components/AppLogoIcon.vue'

defineProps<{
    compact?: boolean
    title?: string
    description?: string
}>()
</script>

<template>
    <!-- BACKGROUND IMAGE -->
    <div
        class="relative min-h-screen bg-cover bg-center bg-no-repeat"
        style="background-image: url('/images/bg.jpg')"
    >
        <!-- DARK OVERLAY -->
        <div class="absolute inset-0 bg-slate-950/60"></div>

        <!-- PAGE CONTENT -->
        <div
            class="relative z-10 flex min-h-screen items-center justify-center px-4 sm:px-6 lg:px-8"
            :class="compact ? 'py-4' : 'py-8'"
        >
            <!-- AUTH CARD WRAPPER -->
            <div class="w-full" :class="compact ? 'max-w-lg' : 'max-w-190'">
                <div
                    class="rounded-2xl border border-white/10 bg-slate-900/95 shadow-2xl backdrop-blur-md"
                    :class="compact ? 'p-5 sm:p-6' : 'px-8 py-8 sm:px-10 sm:py-10 lg:px-12'"
                >
                    <!-- HEADER -->
                    <div class="flex flex-col items-center" :class="compact ? 'mb-4 gap-2' : 'mb-8 gap-4'">
                        <Link
                            href="/"
                            class="flex flex-col items-center gap-3 font-medium"
                        >
                            <div
                                class="flex items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20" :class="compact ? 'h-12 w-12' : 'h-16 w-16'"
                            >
                                <AppLogoIcon class="text-white" :class="compact ? 'h-9 w-9' : 'h-12 w-12'" />
                            </div>

                            <span class="sr-only">
                                GhanaDAMS
                            </span>
                        </Link>

                        <div class="space-y-2 text-center">
                            <h1
                                class="font-extrabold tracking-tight text-white" :class="compact ? 'text-2xl' : 'text-3xl sm:text-4xl'"
                            >
                                {{ title ?? 'GhanaDAMS' }}
                            </h1>

                            <p
                                v-if="description"
                                class="mx-auto max-w-2xl text-slate-300" :class="compact ? 'text-sm leading-5' : 'text-base leading-7'"
                            >
                                {{ description }}
                            </p>
                        </div>
                    </div>

                    <!-- FORM CONTENT -->
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
