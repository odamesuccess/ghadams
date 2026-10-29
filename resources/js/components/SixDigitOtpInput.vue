<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue';

interface Props {
    disabled?: boolean;
    autofocus?: boolean;
    id?: string;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    autofocus: false,
    id: 'otp',
});

const model = defineModel<string>({ default: '' });
const digits = ref<string[]>(Array(6).fill(''));
const inputs = ref<HTMLInputElement[]>([]);

const normalize = (value: string): string =>
    (value ?? '').replace(/\D/g, '').slice(0, 6);

const setInputRef = (element: Element | null, index: number): void => {
    if (element instanceof HTMLInputElement) {
        inputs.value[index] = element;
    }
};

const syncDigitsFromModel = (value: string): void => {
    const clean = normalize(value);

    digits.value = Array.from({ length: 6 }, (_, index) => clean[index] ?? '');

    if (model.value !== clean) {
        model.value = clean;
    }
};

watch(
    model,
    (value) => {
        syncDigitsFromModel(value ?? '');
    },
    { immediate: true },
);

const updateModel = (): void => {
    model.value = digits.value.join('');
};

const focusBox = (index: number): void => {
    nextTick(() => {
        const input = inputs.value[index];
        input?.focus();
        input?.select();
    });
};

const applyDigits = (startIndex: number, value: string): void => {
    const clean = normalize(value);

    if (!clean) {
        return;
    }

    for (let offset = 0; offset < clean.length; offset += 1) {
        const target = startIndex + offset;

        if (target > 5) {
            break;
        }

        digits.value[target] = clean[offset];
    }

    updateModel();
    focusBox(Math.min(startIndex + clean.length, 5));
};

const handleInput = (event: Event, index: number): void => {
    const input = event.target as HTMLInputElement;
    const clean = normalize(input.value);

    if (clean.length > 1) {
        applyDigits(index, clean);
        return;
    }

    digits.value[index] = clean;
    input.value = clean;
    updateModel();

    if (clean && index < 5) {
        focusBox(index + 1);
    }
};

const handleKeydown = (event: KeyboardEvent, index: number): void => {
    if (event.key === 'Backspace') {
        event.preventDefault();

        if (digits.value[index]) {
            digits.value[index] = '';
            updateModel();
            focusBox(index);
            return;
        }

        if (index > 0) {
            digits.value[index - 1] = '';
            updateModel();
            focusBox(index - 1);
        }

        return;
    }

    if (event.key === 'Delete') {
        event.preventDefault();
        digits.value[index] = '';
        updateModel();
        focusBox(index);
        return;
    }

    if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        focusBox(index - 1);
        return;
    }

    if (event.key === 'ArrowRight' && index < 5) {
        event.preventDefault();
        focusBox(index + 1);
    }
};

const handlePaste = (event: ClipboardEvent, index: number): void => {
    const pasted = event.clipboardData?.getData('text') ?? '';
    const clean = normalize(pasted);

    if (!clean) {
        return;
    }

    event.preventDefault();
    applyDigits(index, clean);
};

onMounted(() => {
    if (props.autofocus) {
        focusBox(0);
    }
});
</script>

<template>
    <div
        class="flex w-full items-center justify-center gap-2 sm:gap-3"
        role="group"
        aria-label="Six digit authentication code"
    >
        <input
            v-for="(_, index) in digits"
            :id="index === 0 ? id : `${id}-${index + 1}`"
            :key="index"
            :ref="(element) => setInputRef(element as Element | null, index)"
            :value="digits[index]"
            :disabled="disabled"
            :autofocus="autofocus && index === 0"
            :autocomplete="index === 0 ? 'one-time-code' : 'off'"
            :aria-label="`Authentication code digit ${index + 1}`"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="1"
            class="size-11 rounded-lg border border-input bg-background text-center text-xl font-semibold tabular-nums text-foreground shadow-sm outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 sm:size-12"
            @input="handleInput($event, index)"
            @keydown="handleKeydown($event, index)"
            @paste="handlePaste($event, index)"
            @focus="($event.target as HTMLInputElement).select()"
        />
    </div>
</template>
