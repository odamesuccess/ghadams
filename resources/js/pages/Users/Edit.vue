<script setup lang="ts">
/* =======================
   Imports
======================= */
import { Button } from '@/components/ui/button';
import DarkSelect from '@/components/ui/DarkSelect.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { index, update } from '@/routes/users';
import type { BreadcrumbItem } from '@/types';
import { profileImageUrl } from '@/utils/profileImage';
import { toast, confirmAction } from '@/utils/sweetalert';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, RotateCcw } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

/* =======================
   Types
======================= */
interface Role {
    name: string;
}

interface Unit {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    sex?: string | null;
    unit_id?: number | null;
    user_status?: string | null;
    account_expires_at?: string | null;
    deactivation_reason?: string | null;
    last_login_at?: string | null;
    last_logout_at?: string | null;
    last_seen_at?: string | null;
    email: string;
    contact_number?: string | null;
    date_of_birth?: string | null;
    profile_image?: string | null;
    roles: Role[];
}

/* =======================
   Props
======================= */
const props = defineProps<{
    user: User;
    roles: string[];
    units: Unit[];
}>();

/* =======================
   Breadcrumbs
======================= */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Users', href: index().url },
    { title: 'Edit User', href: '#' },
];

/* =======================
   Reset Password
======================= */
const resetPassword = async () => {
    const confirmed = await confirmAction({
        title: 'Reset User Password',
        text: "Send a password reset link to this user's email?",
        confirmText: 'Send Reset Link',
        icon: 'question',
    });
    if (!confirmed) return;

    router.post(
        `/users/${props.user.id}/reset-password`,
        {},
        {
            onSuccess: () => {
                toast.fire({
                    icon: 'success',
                    title: 'Password reset link sent successfully.',
                });
            },
            onError: () => {
                toast.fire({
                    icon: 'error',
                    title: 'Failed to reset password.',
                });
            },
        },
    );
};

/* =======================
   Form State
======================= */
const form = useForm({
    _method: 'put',
    name: props.user.name,
    sex: props.user.sex ?? '',
    unit_id: props.user.unit_id ?? null,
    user_status: props.user.user_status ?? '',
    deactivation_reason: props.user.deactivation_reason ?? '',
    email: props.user.email,
    contact_number: props.user.contact_number ?? '',
    date_of_birth: props.user.date_of_birth
        ? props.user.date_of_birth.slice(0, 10)
        : '',
    roles: [] as string[],
    profile_image: null as File | null,
});

/* =======================
   Roles Logic
======================= */
/* =======================
   Profile Image
======================= */
const newProfilePreviewUrl = ref<string | null>(null);

const displayedProfileImage = computed(
    () =>
        newProfilePreviewUrl.value ?? profileImageUrl(props.user.profile_image),
);

const onProfileImageChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    form.profile_image = file;

    if (newProfilePreviewUrl.value) {
        URL.revokeObjectURL(newProfilePreviewUrl.value);
        newProfilePreviewUrl.value = null;
    }

    if (file) {
        newProfilePreviewUrl.value = URL.createObjectURL(file);
    }
};

onBeforeUnmount(() => {
    if (newProfilePreviewUrl.value) {
        URL.revokeObjectURL(newProfilePreviewUrl.value);
    }
});

/* =======================
   Submit
======================= */
const selectedRole = ref(props.user.roles.length === 1 ? props.user.roles[0].name : '');

const submit = () => {
    if (!selectedRole.value) {
        toast.fire({
            icon: 'warning',
            title: 'Please assign one role',
        });
        return;
    }

    form.roles = [selectedRole.value];
    form.post(update(props.user.id).url, {
        forceFormData: true,
        onSuccess: () => {
            form.profile_image = null;

            if (newProfilePreviewUrl.value) {
                URL.revokeObjectURL(newProfilePreviewUrl.value);
                newProfilePreviewUrl.value = null;
            }

            toast.fire({
                icon: 'success',
                title: 'User updated successfully.',
            });
        },
        onError: () => {
            toast.fire({
                icon: 'error',
                title: 'Failed to update user.',
            });
        },
    });
};

// Use local calendar components so the birthday cannot shift across timezones.
const retirementExpiry = computed(() => {
    if (form.user_status === 'IsSystem') return ''; 
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(form.date_of_birth);
    if (!match) return '';
    const year = Number(match[1]) + 60;
    const month = Number(match[2]);
    const lastDay = new Date(year, month, 0).getDate();
    const day = Math.min(Number(match[3]), lastDay);
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
});

</script>

<template>
    <Head title="Edit User" />

    <AppLayout :breadcrumbs="breadcrumbs">
<!-- Card -->
        <div class="mx-3 mt-2 rounded-lg border bg-slate-900 shadow-sm lg:mx-4">
            <form @submit.prevent="submit" class="flex flex-col gap-3 p-4 lg:min-h-[calc(100dvh-9rem)] lg:gap-4 lg:p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-slate-100">
                        Edit User Account
                    </h2>

                    <Link
                        :href="index().url"
                        class="btn-secondary inline-flex shrink-0 items-center gap-1.5"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to Users
                    </Link>
                </div>

                <!-- ================= PROFILE IMAGE ================= -->
                <div
                    class="rounded-lg border border-slate-700 bg-slate-950/50 p-3"
                >
                    <div
                        class="flex flex-col gap-2.5 sm:flex-row sm:items-center"
                    >
                        <div
                            class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-700 bg-slate-800 text-sm font-bold text-slate-300"
                        >
                            <img
                                v-if="displayedProfileImage"
                                :src="displayedProfileImage"
                                :alt="`${user.name} profile image`"
                                class="h-full w-full object-cover"
                            />
                            <span v-else>
                                {{
                                    user.name
                                        .split(' ')
                                        .filter(Boolean)
                                        .map((part) => part[0])
                                        .slice(0, 2)
                                        .join('')
                                        .toUpperCase()
                                }}
                            </span>
                        </div>

                        <div class="min-w-0 flex-1 space-y-1">
                            <Label for="profile_image" class="text-slate-300">
                                Profile Image
                            </Label>
                            <input
                                id="profile_image"
                                type="file"
                                accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                                class="block h-8 w-full rounded-md border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-slate-200 file:mr-2 file:rounded file:border-0 file:bg-blue-600 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-500"
                                @change="onProfileImageChange"
                            />
                            <p class="text-[11px] leading-tight text-slate-500">
                                Leave blank to keep the current image.
                            </p>
                            <p
                                v-if="form.errors.profile_image"
                                class="text-xs text-red-400"
                            >
                                {{ form.errors.profile_image }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ================= BASIC INFO ================= -->
                <div class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="space-y-1">
                        <Label class="text-slate-300">Full Name *</Label>
                        <Input class="h-8" v-model="form.name" />
                        <p v-if="form.errors.name" class="text-xs text-red-400">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <Label class="text-slate-300">Sex *</Label>
                        <DarkSelect
                            v-model="form.sex"
                            placeholder="Select sex"
                            :options="[
                                { value: 'Male', label: 'Male' },
                                { value: 'Female', label: 'Female' },
                            ]"
                        />
                        <p v-if="form.errors.sex" class="text-xs text-red-400">
                            {{ form.errors.sex }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <Label class="text-slate-300">Date of Birth *</Label>
                        <Input class="h-8" type="date" v-model="form.date_of_birth" />
                        <p
                            v-if="form.errors.date_of_birth"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.date_of_birth }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <Label class="text-slate-300">Email *</Label>
                        <Input class="h-8" type="email" v-model="form.email" />
                        <p
                            v-if="form.errors.email"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <Label class="text-slate-300">Contact Number</Label>
                        <Input class="h-8" type="tel" v-model="form.contact_number" />
                        <p
                            v-if="form.errors.contact_number"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.contact_number }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <Label class="text-slate-300">User Status</Label>
                        <DarkSelect
                            v-model="form.user_status"
                            :disabled="form.user_status === 'IsSystem'"
                            placeholder="Select status"
                            :options="[
                                { value: 'Active', label: 'Active' },
                                { value: 'Inactive', label: 'Inactive' },
                                { value: 'IsSystem', label: 'System User' },
                            ]"
                        />

                        <p
                            v-if="form.user_status === 'IsSystem'"
                            class="text-xs text-red-400"
                        >
                            System user status cannot be changed.
                        </p>

                        <p
                            v-if="form.errors.user_status"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.user_status }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <Label class="text-slate-300"
                            >Account Expiry Date</Label
                        >
                        <Input class="h-8"
                            type="date"
                            :model-value="retirementExpiry" readonly
                        /><p class="text-xs text-slate-400">{{ form.user_status === 'IsSystem' ? 'Exempt: system accounts do not expire.' : 'Automatic: date of birth + 60 years.' }}</p>
                    </div>

                    <div
                        v-if="form.user_status === 'Inactive'"
                        class="space-y-1 sm:col-span-2 lg:col-span-2"
                    >
                        <Label class="text-slate-300"
                            >Deactivation Reason</Label
                        >
                        <textarea
                            v-model="form.deactivation_reason"
                            rows="1"
                            class="h-8 w-full resize-none rounded-md border border-slate-700 bg-slate-950 px-2 py-1 text-sm text-slate-100"
                            placeholder="Why is this account inactive?"
                        />
                        <p
                            v-if="form.errors.deactivation_reason"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.deactivation_reason }}
                        </p>
                    </div>

                    <!-- Unit -->
                    <div class="space-y-1 sm:col-span-2 lg:col-span-1">
                        <Label class="text-slate-300">Unit</Label>
                        <DarkSelect
                            v-model="form.unit_id"
                            placeholder="Select unit"
                            :options="
                                props.units.map((unit) => ({
                                    value: unit.id,
                                    label: unit.name,
                                }))
                            "
                        />
                        <p
                            v-if="form.errors.unit_id"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.unit_id }}
                        </p>
                    </div>
                </div>

                <!-- ================= ROLES ================= -->
                <div class="flex min-h-0 flex-1 flex-col gap-2.5">
                    <div class="flex items-center justify-between gap-3">
                        <Label class="text-slate-300">Assigned Role *</Label>

                        
                    </div>

                    <div
                        class="grid flex-1 grid-cols-2 content-center gap-x-4 gap-y-3 rounded-md border border-slate-700 p-4 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <label
                            v-for="role in props.roles"
                            :key="role"
                            class="flex items-center gap-2 text-xs text-slate-200 sm:text-sm"
                        >
                            <input
                                type="radio"
                                name="assigned_role"
                                :value="role"
                                :checked="selectedRole === role"
                                @change="selectedRole = role"
                            />
                            {{ role.replaceAll('.', ' ') }}
                        </label>
                    </div>

                    <p v-if="form.errors.roles" class="text-xs text-red-400">
                        {{ form.errors.roles }}
                    </p>
                </div>

                <!-- Footer -->
                <div
                    class="mt-auto flex flex-wrap items-center gap-2 border-t border-slate-700 pt-3"
                >
                    <Button
                        type="button"
                        variant="secondary"
                        class="flex items-center gap-2"
                        @click="resetPassword"
                    >
                        <RotateCcw class="h-4 w-4" />
                        Send Reset Link
                    </Button>
                        <a
                            data-ghana-2fa-reset
                            :href="`/admin/two-factor-reset?user=${user.id}`"
                            class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 text-xs font-semibold text-amber-300 transition hover:bg-amber-500/20 hover:text-amber-200 sm:text-sm"
                            title="Reset this user's two-factor authentication"
                        >
                            <RotateCcw class="h-4 w-4" />
                            Reset 2FA
                        </a>

                    <Button type="submit" class="ml-auto" :disabled="form.processing">
                        <span v-if="form.processing">Updating...</span>
                        <span v-else>Update User</span>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
