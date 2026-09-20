<template>
    <div class="space-y-5">
        <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Events & Calendar</h1>

        <div class="flex flex-wrap gap-1.5 border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="t in visibleTabs"
                :key="t.key"
                type="button"
                class="rounded-t-lg px-3.5 py-2 text-sm font-medium transition"
                :class="tab === t.key ? 'border-b-2 border-primary-500 text-primary-600 dark:text-primary-400' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                @click="tab = t.key"
            >
                {{ t.label }}
            </button>
        </div>

        <div v-if="tab === 'notices'" class="space-y-3">
            <div v-for="n in notices" :key="n.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ n.title }}</h3>
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ n.type }}</span>
                </div>
                <p class="mt-1 text-xs text-slate-400">{{ n.publish_date }}</p>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ n.content }}</p>
            </div>
            <p v-if="!notices.length" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">No notices right now.</p>
        </div>

        <div v-else-if="tab === 'events'" class="space-y-3">
            <div v-for="e in events" :key="e.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ e.title }}</h3>
                    <span class="text-xs text-slate-400">{{ e.event_date }}</span>
                </div>
                <p v-if="e.venue" class="mt-1 text-xs text-slate-400">📍 {{ e.venue }}</p>
                <p v-if="e.description" class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ e.description }}</p>
            </div>
            <p v-if="!events.length" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">No upcoming events.</p>
        </div>

        <div v-else-if="tab === 'calendar'" class="space-y-3">
            <div v-for="e in calendar" :key="e.id" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="categoryClass(e.category)">{{ e.category }}</span>
                    <h3 class="mt-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ e.title }}</h3>
                </div>
                <span class="text-xs text-slate-400">{{ e.date_label || e.start_date }}</span>
            </div>
            <p v-if="!calendar.length" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">No calendar entries for this session.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import client from '../api/client';
import { isModuleVisible } from '../store';

const ALL_TABS = [
    { key: 'notices', label: 'Notices', moduleKey: 'notices' },
    { key: 'events', label: 'Events', moduleKey: 'events_calendar' },
    { key: 'calendar', label: 'Calendar', moduleKey: 'events_calendar' },
];
const visibleTabs = ALL_TABS.filter((t) => isModuleVisible(t.moduleKey));
const tab = ref(visibleTabs[0]?.key || 'events');

const notices = ref([]);
const events = ref([]);
const calendar = ref([]);
const loaded = { notices: false, events: false, calendar: false };

function categoryClass(category) {
    const key = (category || '').toLowerCase();
    if (key === 'holiday') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400';
    if (key === 'examination') return 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400';
    if (key === 'deadline') return 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400';
    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
}

async function loadTab(key) {
    if (loaded[key]) return;
    if (key === 'notices') {
        const { data } = await client.get('/notices');
        notices.value = data;
    } else if (key === 'events') {
        const { data } = await client.get('/events');
        events.value = data;
    } else if (key === 'calendar') {
        const { data } = await client.get('/calendar');
        calendar.value = data;
    }
    loaded[key] = true;
}

watch(tab, (key) => loadTab(key), { immediate: true });
</script>
