<template>
    <div class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row dark:border-slate-800">
        <p class="text-xs text-slate-500 dark:text-slate-400">
            Showing <span class="font-medium text-slate-700 dark:text-slate-300">{{ startRow }}</span> to
            <span class="font-medium text-slate-700 dark:text-slate-300">{{ endRow }}</span> of
            <span class="font-medium text-slate-700 dark:text-slate-300">{{ total }}</span> entries
        </p>
        <div class="flex items-center gap-1">
            <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" :disabled="modelValue <= 1" @click="$emit('update:modelValue', modelValue - 1)">
                Prev
            </button>
            <button
                v-for="(p, i) in pageNumbers"
                :key="i"
                type="button"
                class="min-w-[2rem] rounded-lg px-2.5 py-1.5 text-xs font-medium transition"
                :class="p === modelValue ? 'bg-primary-600 text-white shadow-sm' : p === '...' ? 'cursor-default text-slate-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800'"
                :disabled="p === '...'"
                @click="p !== '...' && $emit('update:modelValue', p)"
            >
                {{ p }}
            </button>
            <button type="button" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" :disabled="modelValue >= totalPages" @click="$emit('update:modelValue', modelValue + 1)">
                Next
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Number, required: true },
    perPage: { type: Number, default: 10 },
    total: { type: Number, required: true },
});
defineEmits(['update:modelValue']);

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)));
const startRow = computed(() => (props.total === 0 ? 0 : (props.modelValue - 1) * props.perPage + 1));
const endRow = computed(() => Math.min(props.total, props.modelValue * props.perPage));

const pageNumbers = computed(() => {
    const total = totalPages.value;
    const current = props.modelValue;
    const range = [];
    const delta = 1;
    for (let i = Math.max(1, current - delta); i <= Math.min(total, current + delta); i++) range.push(i);
    if (range[0] > 1) range.unshift(range[0] === 2 ? 1 : '...');
    if (range[0] !== 1 && range[0] !== '...') range.unshift(1);
    if (range[range.length - 1] < total) range.push(range[range.length - 1] === total - 1 ? total : '...');
    if (range[range.length - 1] !== total && range[range.length - 1] !== '...') range.push(total);
    return [...new Set(range)];
});
</script>
