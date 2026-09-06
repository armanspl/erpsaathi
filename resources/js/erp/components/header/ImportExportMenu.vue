<template>
    <Dropdown align="right">
        <template #trigger="{ open }">
            <button type="button" class="header-icon-btn" :class="open && 'bg-slate-100 dark:bg-slate-700'" title="Import / Export">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>
            </button>
        </template>
        <template #panel="{ close }">
            <div class="w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <p class="px-1 pb-2 text-sm font-semibold text-slate-800 dark:text-slate-100">Global Import / Export</p>
                <div class="grid grid-cols-2 gap-2">
                    <button v-for="item in items" :key="item.label" type="button" class="flex flex-col items-start gap-1 rounded-lg border border-slate-100 p-2.5 text-left transition hover:border-primary-200 hover:bg-primary-50/50 dark:border-slate-800 dark:hover:border-primary-500/30 dark:hover:bg-primary-500/10" @click="go(item), close()">
                        <span class="text-base">{{ item.icon }}</span>
                        <span class="text-xs font-medium text-slate-700 dark:text-slate-200">{{ item.label }}</span>
                    </button>
                </div>
            </div>
        </template>
    </Dropdown>
</template>

<script setup>
import { useRouter } from 'vue-router';
import Dropdown from '../common/Dropdown.vue';

const items = [
    { label: 'Students', icon: '🎓', type: 'students' },
    { label: 'Finance', icon: '🏦', type: 'finance' },
    { label: 'Transport', icon: '🚌', type: 'transport' },
    { label: 'Attendance', icon: '📅', type: 'attendance' },
    { label: 'Library', icon: '📖', type: 'library' },
    { label: 'Complete Excel', icon: '📊', type: 'complete-workbook' },
    { label: 'Complete Backup', icon: '💾', type: 'complete-backup' },
];

const router = useRouter();
function go(item) {
    router.push({ path: '/import-export', query: { type: item.type } });
}
</script>
