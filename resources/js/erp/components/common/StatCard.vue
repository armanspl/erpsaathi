<template>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-start justify-between">
            <div class="min-w-0">
                <p class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ label }}</p>
                <p class="mt-1.5 text-2xl font-bold text-slate-800 dark:text-slate-100">{{ value }}</p>
                <p v-if="trend" class="mt-1 flex items-center gap-1 text-xs font-medium" :class="trend > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path v-if="trend > 0" stroke-linecap="round" stroke-linejoin="round" d="m18 15-6-6-6 6" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                    </svg>
                    {{ Math.abs(trend) }}%
                </p>
            </div>
            <div v-if="icon" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-lg" :class="c.bg">
                <span>{{ icon }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { colorClasses } from '../../utils/colors';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: '' },
    color: { type: String, default: 'indigo' },
    trend: { type: Number, default: 0 },
});

const c = computed(() => colorClasses(props.color));
</script>
