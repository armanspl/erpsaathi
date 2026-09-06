<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Holidays</h1>
                <Breadcrumb :items="['Dashboard', 'Attendance', 'Holidays']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Holiday</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="holidays" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Holidays" :value="holidays.length" color="indigo" icon="📅" />
            <StatCard label="Upcoming" :value="upcoming.length" color="emerald" icon="🔜" />
            <StatCard label="This Year" :value="thisYear.length" color="sky" icon="🗓️" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Holiday</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredHolidays.length">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-400">No holidays match your filters.</td>
                    </tr>
                    <tr v-for="h in filteredHolidays" :key="h.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ h.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(h.date) }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ h.type || '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(h)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(h)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Holiday' : 'Add Holiday'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Date</label>
                <input v-model="form.date" type="date" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Type</label>
                <input v-model="form.type" type="text" class="form-input" placeholder="National, Festival, Weekly..." />
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
import { fetchAttendanceLookups, invalidateAttendanceLookups } from '../../api/attendance';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const holidays = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', date: '', type: '' });

const upcoming = computed(() => holidays.value.filter((h) => new Date(h.date) >= new Date(new Date().toDateString())));
const thisYear = computed(() => holidays.value.filter((h) => new Date(h.date).getFullYear() === new Date().getFullYear()));

const filteredHolidays = computed(() =>
    holidays.value.filter((h) => {
        if (filterValues.search && !`${h.name} ${h.type}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    try {
        const lookups = await fetchAttendanceLookups({ force: true });
        holidays.value = lookups.holidays || [];
    } catch {
        const { data } = await client.get('/attendance/holidays');
        holidays.value = data;
    } finally {
        loading.value = false;
    }
}
load();

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', weekday: 'short' });
}

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', date: '', type: '' });
    drawerOpen.value = true;
}

function openEdit(holiday) {
    editing.value = holiday;
    Object.assign(form, { name: holiday.name, date: holiday.date.slice(0, 10), type: holiday.type || '' });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/attendance/holidays/${editing.value.id}`, form);
            pushToast('Holiday updated.', 'success');
        } else {
            await client.post('/attendance/holidays', form);
            pushToast('Holiday added.', 'success');
        }
        drawerOpen.value = false;
        invalidateAttendanceLookups();
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(holiday) {
    holidays.value = holidays.value.filter((h) => h.id !== holiday.id);
    await client.delete(`/attendance/holidays/${holiday.id}`);
    invalidateAttendanceLookups();
    pushToast(`Holiday "${holiday.name}" deleted.`, 'success');
}
</script>
