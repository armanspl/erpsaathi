<template>
    <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <header class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <h3 class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">{{ title }}</h3>
        </header>
        <div :class="twoCol ? 'grid sm:grid-cols-2' : ''">
            <div
                v-for="row in rows"
                :key="row.label"
                class="flex items-start justify-between gap-4 border-b border-slate-50 px-4 py-2.5 last:border-0 dark:border-slate-800/80"
            >
                <span class="shrink-0 text-sm text-slate-400">{{ row.label }}</span>
                <span class="text-right text-sm font-semibold text-slate-800 dark:text-slate-100">{{ display(row.value) }}</span>
            </div>
        </div>
    </section>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    rows: { type: Array, default: () => [] },
    twoCol: { type: Boolean, default: false },
});

function display(value) {
    return value === null || value === undefined || value === '' ? '—' : value;
}
</script>
