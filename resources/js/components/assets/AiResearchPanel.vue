<script setup lang="ts">
import { RefreshCw, Sparkles } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

import AssetSuggestionReview from '@/components/assets/AssetSuggestionReview.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    type AssetSuggestion,
    type CurrentValues,
    type ResearchMode,
    type SuggestionEdits,
    useAssetResearch,
} from '@/composables/useAssetResearch';
import { toast } from '@/utils/sweetalert';

const props = defineProps<{
    assetId: number;
}>();

const emit = defineEmits<{
    /** Fired after a suggestion is approved, with the asset's new values. */
    applied: [current: CurrentValues];
}>();

const {
    data,
    loading,
    loadError,
    researching,
    researchError,
    notice,
    timedOut,
    reviewing,
    reviewable,
    latestFailure: failedPending,
    history,
    load,
    startResearch,
    approve,
    reject,
} = useAssetResearch(props.assetId);

const showHistory = ref(false);

onMounted(load);

const supported = computed(() => Boolean(data.value?.category));
const lastMode = ref<ResearchMode>('full');

function run(mode: ResearchMode, force = false): void {
    lastMode.value = mode;
    startResearch(mode, force);
}

async function onApprove(
    suggestion: AssetSuggestion,
    edits: SuggestionEdits,
): Promise<void> {
    try {
        const current = await approve(suggestion, edits);
        toast.fire({
            icon: 'success',
            title: 'Suggestion applied to the asset',
        });
        if (current) emit('applied', current);
    } catch (error) {
        toast.fire({
            icon: 'error',
            title:
                error instanceof Error
                    ? error.message
                    : 'Could not approve the suggestion',
        });
    }
}

async function onReject(suggestion: AssetSuggestion): Promise<void> {
    try {
        await reject(suggestion);
        toast.fire({
            icon: 'success',
            title: suggestion.error_message
                ? 'Dismissed'
                : 'Suggestion rejected',
        });
    } catch (error) {
        toast.fire({
            icon: 'error',
            title:
                error instanceof Error
                    ? error.message
                    : 'Could not reject the suggestion',
        });
    }
}

const formatDate = (value: string | null) =>
    value
        ? new Date(value).toLocaleString('en-GB', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

const statusClass = (s: AssetSuggestion) =>
    s.error_message
        ? 'text-red-300'
        : {
              approved: 'text-emerald-300',
              rejected: 'text-slate-400',
              pending: 'text-amber-300',
          }[s.status];
</script>

<template>
    <section class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
        <!-- Header -->
        <div
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h2
                    class="flex items-center gap-2 text-sm font-semibold tracking-wide text-slate-200 uppercase"
                >
                    <Sparkles class="size-4 text-indigo-300" /> AI asset
                    research
                </h2>
                <p class="mt-1 text-xs text-slate-400">
                    Looks up specs and current market price on the web. Nothing
                    changes until a reviewer approves it.
                </p>
            </div>

            <div
                v-if="data?.can_research && supported"
                class="flex flex-wrap gap-2"
            >
                <Button
                    size="sm"
                    :disabled="researching !== null"
                    @click="run('full')"
                >
                    <Spinner v-if="researching === 'full'" />
                    <Sparkles v-else />
                    Auto-fill with AI
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="researching !== null"
                    @click="run('price_only')"
                >
                    <Spinner v-if="researching === 'price_only'" />
                    <RefreshCw v-else />
                    Refresh price
                </Button>
            </div>
        </div>

        <div class="mt-4 space-y-4">
            <!-- Initial load -->
            <div
                v-if="loading"
                class="flex items-center gap-2 text-sm text-slate-400"
            >
                <Spinner /> Loading AI suggestions…
            </div>

            <p
                v-else-if="loadError"
                class="rounded-lg border border-red-800 bg-red-950/30 p-3 text-sm text-red-200"
            >
                {{ loadError }}
                <button class="ml-2 underline" @click="load">Retry</button>
            </p>

            <template v-else-if="data">
                <p v-if="!supported" class="text-sm text-slate-400">
                    AI research isn't available for this asset type.
                </p>

                <!-- In progress -->
                <div
                    v-if="researching"
                    class="flex items-center gap-3 rounded-lg border border-indigo-800 bg-indigo-950/30 p-3 text-sm text-indigo-100"
                >
                    <Spinner />
                    <span>
                        {{
                            researching === 'full'
                                ? 'Researching specs and price'
                                : 'Checking current prices'
                        }}… This usually takes 30 seconds to 2 minutes. You can
                        leave this page; the result will be saved.
                    </span>
                </div>

                <!-- Request errors / notices -->
                <p
                    v-if="researchError"
                    class="rounded-lg border border-red-800 bg-red-950/30 p-3 text-sm text-red-200"
                >
                    {{ researchError }}
                </p>

                <div
                    v-if="notice"
                    class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-700 bg-slate-800/50 p-3 text-sm text-slate-200"
                >
                    <span>{{ notice }}</span>
                    <Button
                        v-if="data.can_research"
                        size="sm"
                        variant="outline"
                        @click="run(lastMode, true)"
                        >Run again anyway</Button
                    >
                </div>

                <div
                    v-if="timedOut"
                    class="flex flex-wrap items-center gap-3 rounded-lg border border-amber-800 bg-amber-950/30 p-3 text-sm text-amber-100"
                >
                    <span
                        >Research is taking longer than expected. It may still
                        finish in the background.</span
                    >
                    <Button size="sm" variant="outline" @click="load"
                        >Check again</Button
                    >
                </div>

                <!-- Latest research failed -->
                <div
                    v-if="failedPending && !researching"
                    class="rounded-lg border border-red-800 bg-red-950/30 p-3 text-sm text-red-100"
                >
                    <p class="font-medium">The last research attempt failed.</p>
                    <p class="mt-1 text-red-200/90">
                        {{ failedPending.error_message }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button
                            v-if="data.can_research && supported"
                            size="sm"
                            @click="run(failedPending.mode, true)"
                            >Try again</Button
                        >
                        <Button
                            v-if="data.can_review"
                            size="sm"
                            variant="outline"
                            :disabled="reviewing"
                            @click="onReject(failedPending)"
                            >Dismiss</Button
                        >
                    </div>
                </div>

                <!-- Suggestion to review -->
                <AssetSuggestionReview
                    v-if="reviewable"
                    :suggestion="reviewable"
                    :schema="data.schema"
                    :current="data.current"
                    :can-review="data.can_review"
                    :busy="reviewing"
                    @approve="(edits) => onApprove(reviewable!, edits)"
                    @reject="onReject(reviewable!)"
                />

                <!-- Empty -->
                <p
                    v-if="
                        supported &&
                        !reviewable &&
                        !failedPending &&
                        !researching &&
                        !history.length
                    "
                    class="text-sm text-slate-400"
                >
                    No AI suggestions yet. Use
                    <span class="font-medium text-slate-200"
                        >Auto-fill with AI</span
                    >
                    to look this asset up by its type and brand. Saving a model
                    or part number first gives an exact match.
                </p>

                <!-- History -->
                <div v-if="history.length">
                    <button
                        class="text-xs font-medium text-slate-400 hover:text-slate-200"
                        @click="showHistory = !showHistory"
                    >
                        {{ showHistory ? 'Hide' : 'Show' }} previous suggestions
                        ({{ history.length }})
                    </button>
                    <ul
                        v-if="showHistory"
                        class="mt-2 divide-y divide-slate-800 rounded-lg border border-slate-800 text-sm"
                    >
                        <li
                            v-for="item in history"
                            :key="item.id"
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2"
                        >
                            <span class="text-slate-300">#{{ item.id }}</span>
                            <span class="text-slate-400">{{
                                item.mode === 'price_only'
                                    ? 'Price refresh'
                                    : 'Full research'
                            }}</span>
                            <span
                                class="capitalize"
                                :class="statusClass(item)"
                                >{{
                                    item.error_message ? 'failed' : item.status
                                }}</span
                            >
                            <span
                                v-if="item.confidence"
                                class="text-xs text-slate-500 capitalize"
                                >{{ item.confidence }} confidence</span
                            >
                            <span class="ml-auto text-xs text-slate-500">
                                {{ formatDate(item.created_at)
                                }}<template v-if="item.reviewer">
                                    · {{ item.reviewer.name }}</template
                                >
                            </span>
                        </li>
                    </ul>
                </div>
            </template>
        </div>
    </section>
</template>
