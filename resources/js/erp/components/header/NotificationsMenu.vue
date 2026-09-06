<template>
    <Dropdown align="right">
        <template #trigger="{ open }">
            <button type="button" class="header-icon-btn relative" :class="open && 'bg-slate-100 dark:bg-slate-700'" title="Notifications">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.7 21a2 2 0 0 1-3.4 0" /></svg>
                <span
                    v-if="totalCount > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-0.5 text-[10px] font-semibold text-white ring-2 ring-white dark:ring-slate-900"
                >
                    {{ totalCount > 99 ? '99+' : totalCount }}
                </span>
            </button>
        </template>
        <template #panel="{ close }">
            <div class="w-80 rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Notifications</p>
                    <span class="text-xs font-medium text-primary-600 dark:text-primary-400">
                        {{ loading ? 'Updating...' : `${totalCount} alert${totalCount === 1 ? '' : 's'}` }}
                    </span>
                </div>
                <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                    <li v-if="loading && !notifications.length" class="px-4 py-8 text-center text-xs text-slate-400">Loading...</li>
                    <li v-else-if="!visibleNotifications.length" class="px-4 py-8 text-center text-xs text-slate-400">You're all caught up.</li>
                    <li
                        v-for="n in visibleNotifications"
                        :key="n.key || n.label"
                        class="flex cursor-pointer items-start gap-3 px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-slate-800/60"
                        @click="go(n), close()"
                    >
                        <span class="mt-0.5 text-lg">{{ n.icon }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ n.label }}</p>
                            <p class="text-xs text-slate-400">{{ n.hint }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ n.count }}</span>
                    </li>
                </ul>
                <div class="border-t border-slate-100 p-2 dark:border-slate-800">
                    <button
                        type="button"
                        class="w-full rounded-lg py-1.5 text-center text-xs font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-500/10"
                        @click="router.push('/account/notifications'), close()"
                    >
                        View all notifications
                    </button>
                </div>
            </div>
        </template>
    </Dropdown>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Dropdown from '../common/Dropdown.vue';
import client from '../../api/client';
import { erpStore } from '../../store';

const router = useRouter();

const loading = ref(false);
const notifications = ref([]);
const totalCount = ref(0);

const visibleNotifications = computed(() =>
    notifications.value.filter((n) => Number(n.count) > 0),
);

async function load() {
    loading.value = true;
    try {
        const { data } = await client.get('/notifications');
        notifications.value = Array.isArray(data?.items) ? data.items : [];
        totalCount.value = Number(data?.total ?? notifications.value.reduce((s, n) => s + Number(n.count || 0), 0));
    } catch {
        notifications.value = [];
        totalCount.value = 0;
    } finally {
        loading.value = false;
    }
}

function go(n) {
    if (n?.path) router.push(n.path);
}

onMounted(load);
watch(() => erpStore.currentSession, load);
</script>
