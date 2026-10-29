<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
};

const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);
const dismissed = ref(false);
const isInstalled = ref(false);

const isStandaloneDisplay = () =>
    window.matchMedia('(display-mode: standalone)').matches ||
    (window.navigator as Navigator & { standalone?: boolean }).standalone === true;

const canShowPrompt = computed(() => {
    return Boolean(deferredPrompt.value) && !dismissed.value && !isInstalled.value;
});

const handleBeforeInstallPrompt = (event: Event) => {
    event.preventDefault();

    if (localStorage.getItem('ghadams-pwa-install-dismissed') === 'yes') {
        dismissed.value = true;
        return;
    }

    deferredPrompt.value = event as BeforeInstallPromptEvent;
};

const handleInstalled = () => {
    isInstalled.value = true;
    deferredPrompt.value = null;
};

const installApp = async () => {
    if (!deferredPrompt.value) {
        return;
    }

    const promptEvent = deferredPrompt.value;

    deferredPrompt.value = null;
    await promptEvent.prompt();

    const choice = await promptEvent.userChoice;

    if (choice.outcome === 'dismissed') {
        dismissed.value = true;
        localStorage.setItem('ghadams-pwa-install-dismissed', 'yes');
    }
};

const dismissPrompt = () => {
    dismissed.value = true;
    localStorage.setItem('ghadams-pwa-install-dismissed', 'yes');
};

onMounted(() => {
    isInstalled.value = isStandaloneDisplay();
    dismissed.value = localStorage.getItem('ghadams-pwa-install-dismissed') === 'yes';

    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.addEventListener('appinstalled', handleInstalled);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.removeEventListener('appinstalled', handleInstalled);
});
</script>

<template>
    <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-3 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-3 opacity-0"
    >
        <div
            v-if="canShowPrompt"
            class="fixed bottom-4 left-4 right-4 z-50 mx-auto max-w-md rounded-2xl border border-white/10 bg-slate-950/95 p-4 text-white shadow-2xl backdrop-blur md:left-auto md:right-6 md:mx-0"
            role="dialog"
            aria-live="polite"
            aria-label="Install GHADAMS application"
        >
            <div class="flex items-start gap-3">
                <img
                    src="/images/ghadams_logo.jpg"
                    alt="GHADAMS logo"
                    class="h-11 w-11 rounded-xl bg-white/10 object-contain p-1"
                >

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold">Install GHADAMS</p>
                    <p class="mt-1 text-xs leading-5 text-slate-300">
                        Add GHADAMS to your device for faster access and a professional app-like experience.
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-300"
                            @click="installApp"
                        >
                            Install App
                        </button>

                        <button
                            type="button"
                            class="rounded-xl border border-white/10 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-slate-300"
                            @click="dismissPrompt"
                        >
                            Not now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>
