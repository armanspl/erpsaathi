<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Meeting Recordings</h1>
            <Breadcrumb :items="['Dashboard', 'Meetings', 'Meeting Recordings']" class="mt-1" />
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="recordings" @reset="filterValues = {}" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard label="Recordings Available" :value="filteredMeetings.length" color="indigo" icon="🎬" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Title</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Recording</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredMeetings.length">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-400">No recordings available yet.</td>
                    </tr>
                    <tr v-for="m in filteredMeetings" :key="m.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ m.title }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ m.type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(m.meeting_date) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a :href="m.recording_url" target="_blank" rel="noopener" class="btn-outline !py-1 !text-xs">▶ Watch</a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const meetings = ref([]);
const filterValues = reactive({});

const filteredMeetings = computed(() =>
    meetings.value.filter((m) => {
        if (filterValues.search && !`${m.title}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

client.get('/meetings', { params: { has_recording: 1 } }).then(({ data }) => {
    meetings.value = data;
    loading.value = false;
});

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
