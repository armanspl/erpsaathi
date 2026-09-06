<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div v-for="p in people" :key="p.role" class="rounded-lg border border-slate-100 p-4 dark:border-slate-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-primary-500">{{ p.role }}</p>
            <p class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ p.name || '—' }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ p.occupation || '—' }}</p>
            <div class="mt-3 space-y-1 text-xs text-slate-500 dark:text-slate-400">
                <p>📞 {{ p.phone || '—' }}</p>
                <p>✉️ {{ p.email || '—' }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({ student: { type: Object, required: true } });
const people = computed(() => [
    { role: 'Father', ...props.student.father },
    { role: 'Mother', ...props.student.mother },
    { role: 'Guardian', ...props.student.guardian },
]);
</script>
