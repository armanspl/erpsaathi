<template>
    <nav class="flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400" aria-label="Breadcrumb">
        <template v-for="(item, i) in items" :key="i">
            <span :class="i === items.length - 1 ? 'font-medium text-slate-700 dark:text-slate-200' : ''">{{ item }}</span>
            <svg v-if="i < items.length - 1" class="h-3.5 w-3.5 text-slate-300 dark:text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />
            </svg>
        </template>
    </nav>
</template>

<script setup>
defineProps({ items: { type: Array, required: true } });
</script>
