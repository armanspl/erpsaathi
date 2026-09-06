<template>
    <Dropdown align="right">
        <template #trigger="{ open }">
            <button type="button" class="flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 transition hover:bg-slate-100 dark:hover:bg-slate-700" :class="open && 'bg-slate-100 dark:bg-slate-700'">
                <span class="erp-avatar flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold">{{ initials }}</span>
                <span class="hidden text-left leading-tight md:block">
                    <span class="block text-xs font-semibold text-slate-700 dark:text-slate-200">{{ erpStore.user.name }}</span>
                    <span class="block text-[11px] capitalize text-slate-400">{{ erpStore.user.role }}</span>
                </span>
                <svg class="hidden h-3.5 w-3.5 text-slate-400 md:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
            </button>
        </template>
        <template #panel="{ close }">
            <div class="w-64 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center gap-3 border-b border-slate-100 px-3 py-3 dark:border-slate-800">
                    <span class="erp-avatar flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold">{{ initials }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ erpStore.user.name }}</p>
                        <p class="truncate text-xs text-slate-400">{{ erpStore.user.email }}</p>
                    </div>
                </div>
                <div class="py-1">
                    <RouterLink to="/account/profile" class="menu-item" @click="close">👤 Profile</RouterLink>
                    <RouterLink to="/settings/school-settings" class="menu-item" @click="close">⚙️ Settings</RouterLink>
                    <button type="button" class="menu-item w-full justify-between" @click="toggleDarkMode">
                        <span>🌙 Dark Mode</span>
                        <span class="relative inline-flex h-5 w-9 items-center rounded-full transition" :class="erpStore.darkMode ? 'bg-primary-600' : 'bg-slate-200 dark:bg-slate-700'">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition" :class="erpStore.darkMode ? 'translate-x-[18px]' : 'translate-x-0.5'" />
                        </span>
                    </button>
                    <div class="px-3 py-2">
                        <p class="mb-1.5 text-xs text-slate-500 dark:text-slate-400">🎨 Template</p>
                        <div class="flex items-center gap-1.5">
                            <button
                                v-for="t in UI_TEMPLATES"
                                :key="t.id"
                                type="button"
                                :title="t.name"
                                class="h-5 w-5 rounded-full ring-2 ring-offset-1 ring-offset-white transition dark:ring-offset-slate-900"
                                :class="erpStore.uiTemplate === t.id ? 'ring-slate-800 dark:ring-white' : 'ring-transparent'"
                                :style="{ background: `linear-gradient(135deg, ${t.preview.accent}, ${t.preview.bg})` }"
                                @click="pick(t.id, close)"
                            />
                            <RouterLink to="/account/choose-template" class="ml-1 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" @click="close">More →</RouterLink>
                        </div>
                    </div>
                </div>
                <div class="border-t border-slate-100 py-1 dark:border-slate-800">
                    <button type="button" class="menu-item w-full text-rose-600 dark:text-rose-400" @click="logout">↪️ Logout</button>
                </div>
            </div>
        </template>
    </Dropdown>
</template>

<script setup>
import { computed } from 'vue';
import Dropdown from '../common/Dropdown.vue';
import { erpStore, toggleDarkMode, logout, setUiTemplate } from '../../store';
import { UI_TEMPLATES } from '../../utils/templates';
import { pushToast } from '../../utils/toast';

const initials = computed(() =>
    erpStore.user.name
        .split(' ')
        .map((p) => p[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

function pick(id, close) {
    if (erpStore.uiTemplate !== id) {
        setUiTemplate(id);
        const name = UI_TEMPLATES.find((t) => t.id === id)?.name || 'Template';
        pushToast(`${name} applied.`, 'success');
    }
    close?.();
}
</script>

<style scoped>
@reference '../../../../css/app.css';

.menu-item {
    @apply flex items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-slate-600 transition hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800;
}
.erp-avatar {
    background: linear-gradient(135deg, var(--erp-brand-from, #e2c98a), var(--erp-brand-to, #8a6d2f));
    color: var(--erp-brand-ink, #14120e);
}
</style>
