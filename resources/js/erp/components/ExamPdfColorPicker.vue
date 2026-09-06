<template>
    <div class="flex flex-wrap items-center gap-2">
        <label class="form-label mb-0 whitespace-nowrap">PDF colour</label>
        <span
            class="relative inline-flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700"
            :class="isNone ? 'opacity-50' : ''"
        >
            <input
                type="color"
                class="absolute inset-0 h-full w-full cursor-pointer border-none bg-transparent p-0 opacity-0"
                :value="swatchColor"
                :disabled="saving"
                @change="onPick($event.target.value)"
            />
            <span class="pointer-events-none h-full w-full" :style="{ backgroundColor: swatchColor }" />
        </span>
        <button
            type="button"
            class="text-xs font-medium hover:underline"
            :class="isNone ? 'text-slate-700 underline dark:text-slate-200' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
            :disabled="saving"
            @click="onNone"
        >
            None
        </button>
        <button
            v-if="exam?.pdf_accent_color"
            type="button"
            class="text-xs font-medium text-slate-400 hover:text-slate-600 hover:underline dark:hover:text-slate-300"
            :disabled="saving"
            @click="onReset"
        >
            Reset
        </button>
        <span v-if="saving" class="text-xs text-slate-400">Saving...</span>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import client from '../api/client';
import { pushToast } from '../utils/toast';

const props = defineProps({
    exam: { type: Object, default: null },
});
const emit = defineEmits(['update']);

const DEFAULT_COLOR = '#1e3a5f';
// Neutral grey the swatch shows while "None" (greyscale PDF, see
// DocumentDataBuilder::accentPalette()) is active — <input type="color"> can't render a
// literal "no colour" state, so this just stands in as a visual cue, not the applied colour.
const NONE_SWATCH = '#94a3b8';
const saving = ref(false);
const isNone = computed(() => props.exam?.pdf_accent_color === 'none');
const swatchColor = computed(() => (isNone.value ? NONE_SWATCH : props.exam?.pdf_accent_color || DEFAULT_COLOR));

async function save(hex, successMessage) {
    if (!props.exam) return;
    saving.value = true;
    try {
        const { data } = await client.patch(`/exams/${props.exam.id}/pdf-color`, { pdf_accent_color: hex });
        emit('update', { ...props.exam, pdf_accent_color: data.pdf_accent_color });
        pushToast(successMessage, 'success');
    } catch {
        pushToast('Could not save the PDF colour.', 'error');
    } finally {
        saving.value = false;
    }
}

function onPick(hex) {
    save(hex, 'PDF colour updated — new downloads will use it.');
}

function onNone() {
    save('none', 'PDF set to greyscale — new downloads will print without colour.');
}

function onReset() {
    save(null, 'PDF colour reset to default.');
}
</script>
