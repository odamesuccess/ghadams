<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { RotateCcw, ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { enable, show } from '@/routes/two-factor';
import { BreadcrumbItem } from '@/types';

interface Props {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two-Factor Authentication',
        href: show.url(),
    },
];

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);
const restartingSetup = ref<boolean>(false);

const restartSetup = (): void => {
    if (restartingSetup.value) {
        return;
    }

    restartingSetup.value = true;

    router.post(
        '/settings/two-factor/restart',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                clearTwoFactorAuthData();
                showSetupModal.value = true;
            },
            onFinish: () => {
                restartingSetup.value = false;
            },
        },
    );
};

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Two-Factor Authentication" />

        <h1 class="sr-only">Two-Factor Authentication Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Two-Factor Authentication"
                    description="Required security protection for every GhanaDAMS account"
                />

                <div
                    v-if="!twoFactorEnabled"
                    class="flex flex-col items-start justify-start space-y-4"
                >
                    <Badge variant="destructive">Setup Required</Badge>

                    <div class="w-full rounded-lg border p-4">
                        <div class="flex items-start gap-3">
                            <ShieldCheck class="mt-0.5 size-5 shrink-0" />
                            <div class="space-y-1">
                                <p class="font-medium">
                                    Two-factor authentication is mandatory.
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Connect an authenticator app and confirm the six-digit
                                    security code. If a correct code is rejected, use
                                    Restart 2FA Setup to generate a completely fresh QR code
                                    and secret, then scan the new QR code in your authenticator.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="hasSetupData"
                            @click="showSetupModal = true"
                        >
                            <ShieldCheck />Continue Setup
                        </Button>
                        <Form
                            v-else
                            v-bind="enable.form()"
                            @success="showSetupModal = true"
                            #default="{ processing }"
                        >
                            <Button type="submit" :disabled="processing">
                                <ShieldCheck />Set Up 2FA
                            </Button>
                        </Form>

                        <Button
                            type="button"
                            variant="outline"
                            :disabled="restartingSetup"
                            @click="restartSetup"
                        >
                            <RotateCcw />
                            {{ restartingSetup ? 'Restarting...' : 'Restart 2FA Setup' }}
                        </Button>
                    </div>

                    <p class="text-sm text-muted-foreground">
                        Restarting setup is available only while 2FA is not yet confirmed.
                        It does not disable an already confirmed account.
                    </p>
                </div>

                <div
                    v-else
                    class="flex flex-col items-start justify-start space-y-4"
                >
                    <Badge variant="default">Required & Enabled</Badge>

                    <p class="text-muted-foreground">
                        Your account is protected with two-factor authentication.
                        Each new login requires the six-digit code from your authenticator
                        application, or one of your emergency recovery codes.
                    </p>

                    <TwoFactorRecoveryCodes />

                    <p class="text-sm text-muted-foreground">
                        Two-factor authentication is enforced for all GhanaDAMS users and
                        cannot be disabled from account settings.
                    </p>
                </div>

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
