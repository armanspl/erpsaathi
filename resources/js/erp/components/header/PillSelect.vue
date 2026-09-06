<template>
    <Dropdown align="left" close-on-click>
        <template #trigger>
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-600 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                <span class="text-sm">{{ icon }}</span>
                <span class="hidden max-w-[7.5rem] truncate sm:inline">{{ modelValue }}</span>
                <svg class="h-3 w-3 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
            </button>
        </template>
        <template #panel="{ close }">
            <div class="w-48 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <p class="px-2.5 pb-1 pt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ label }}</p>
                <button
                    v-for="opt in options"
                    :key="opt"
                    type="button"
                    class="flex w-full items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800"
                    :class="opt === modelValue ? 'font-semibold text-primary-600 dark:text-primary-400' : 'text-slate-600 dark:text-slate-300'"
                    @click="$emit('update:modelValue', opt), close()"
                >
                    {{ opt }}
                    <svg v-if="opt === modelValue" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </button>
            </div>
        </template>
    </Dropdown>
</template>

<script setup>
import Dropdown from '../common/Dropdown.vue';

defineProps({
    modelValue: { type: String, required: true },
    options: { type: Array, required: true },
    icon: { type: String, default: '📅' },
    label: { type: String, default: 'Select' },
});
defineEmits(['update:modelValue']);
</script>
