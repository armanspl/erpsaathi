<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Events</h1>
                <Breadcrumb :items="['Dashboard', 'Communication', 'Events']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Event</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="events" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Events" :value="filteredEvents.length" color="indigo" icon="🎉" />
            <StatCard label="Upcoming" :value="upcomingCount" color="emerald" icon="📅" />
            <StatCard label="Cancelled" :value="filteredEvents.filter((e) => e.status === 'Cancelled').length" color="rose" icon="⛔" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Title</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Venue</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Time</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredEvents.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No events match your filters.</td>
                    </tr>
                    <tr v-for="e in filteredEvents" :key="e.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ e.title }}</p>
                            <p class="max-w-md truncate text-xs text-slate-400">{{ e.description }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.venue || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(e.event_date) }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.start_time || '—' }}<span v-if="e.end_time"> – {{ e.end_time }}</span></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(eventBadge(e))">{{ isPast(e) && e.status !== 'Cancelled' ? 'Completed' : e.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(e)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(e)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Event' : 'Add Event'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Title</label>
                <input v-model="form.title" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Description</label>
                <textarea v-model="form.description" rows="3" class="form-input" />
            </div>
            <div>
                <label class="form-label">Venue</label>
                <input v-model="form.venue" type="text" class="form-input" />
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="form-label">Date</label>
                    <input v-model="form.event_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Start</label>
                    <input v-model="form.start_time" type="time" class="form-input" />
                </div>
                <div>
                    <label class="form-label">End</label>
                    <input v-model="form.end_time" type="time" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select v-model="form.status" class="form-input">
                    <option>Scheduled</option>
                    <option>Cancelled</option>
                </select>
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Scheduled', 'Cancelled'] },
];

const loading = ref(true);
const saving = ref(false);
const events = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ title: '', description: '', venue: '', event_date: new Date().toISOString().slice(0, 10), start_time: '', end_time: '', status: 'Scheduled' });

const isPast = (e) => new Date(e.event_date) < new Date(new Date().toDateString());
const eventBadge = (e) => (e.status === 'Cancelled' ? 'Inactive' : isPast(e) ? 'Pending' : 'Active');
const upcomingCount = computed(() => filteredEvents.value.filter((e) => e.status !== 'Cancelled' && !isPast(e)).length);

const filteredEvents = computed(() =>
    events.value.filter((e) => {
        if (filterValues.search && !`${e.title} ${e.venue}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && e.status !== filterValues.status) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const { data } = await client.get('/communication/events');
    events.value = data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { title: '', description: '', venue: '', event_date: new Date().toISOString().slice(0, 10), start_time: '', end_time: '', status: 'Scheduled' });
    drawerOpen.value = true;
}

function openEdit(event) {
    editing.value = event;
    Object.assign(form, {
        title: event.title,
        description: event.description || '',
        venue: event.venue || '',
        event_date: event.event_date.slice(0, 10),
        start_time: event.start_time || '',
        end_time: event.end_time || '',
        status: event.status,
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/communication/events/${editing.value.id}`, form);
            pushToast('Event updated.', 'success');
        } else {
            await client.post('/communication/events', form);
            pushToast('Event added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(event) {
    events.value = events.value.filter((e) => e.id !== event.id);
    await client.delete(`/communication/events/${event.id}`);
    pushToast(`Event "${event.title}" deleted.`, 'success');
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
