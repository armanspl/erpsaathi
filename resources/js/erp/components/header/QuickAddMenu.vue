<template>
    <Dropdown align="right">
        <template #trigger="{ open }">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700" :class="open && 'bg-primary-700'">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" /></svg>
                <span class="hidden sm:inline">Quick Add</span>
            </button>
        </template>
        <template #panel="{ close }">
            <div class="w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <button v-for="item in items" :key="item.label" type="button" class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm text-slate-600 transition hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800" @click="go(item), close()">
                    <span class="text-base">{{ item.icon }}</span>
                    {{ item.label }}
                </button>
            </div>
        </template>
    </Dropdown>
</template>

<script setup>
import { useRouter } from 'vue-router';
import Dropdown from '../common/Dropdown.vue';
import { pushToast } from '../../utils/toast';

const items = [
    { label: 'Student', icon: '🎓', path: '/people/students' },
    { label: 'Fee Receipt', icon: '🧾', path: '/fee-management/pay-fee' },
    { label: 'Expense', icon: '💸', path: '/finance-and-payroll/office-expenses' },
    { label: 'Notice', icon: '📢', path: '/communication/notices' },
    { label: 'Homework', icon: '📚', path: '/academics/homework' },
    { label: 'Event', icon: '🎉', path: '/communication/events' },
];

const router = useRouter();
function go(item) {
    router.push({ path: item.path, query: { add: '1' } });
    pushToast(`Opening "Add ${item.label}" form...`, 'info');
}
</script>
