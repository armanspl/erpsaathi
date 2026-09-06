<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Academic Sessions</h1>
                <Breadcrumb :items="['Dashboard', 'Settings', 'Academic Sessions']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Session</button>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <StatCard label="Current" :value="counts.current" color="emerald" icon="✅" />
            <StatCard label="Closed" :value="counts.closed" color="slate" icon="🗄️" />
            <StatCard label="Upcoming" :value="counts.upcoming" color="amber" icon="⏳" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Session</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Start</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">End</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!sessions.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No academic sessions yet.</td>
                    </tr>
                    <tr v-for="s in sessions" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(s.start_date) }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(s.end_date) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset" :class="statusBadgeClass(s.status)">{{ s.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(s)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(s)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Session' : 'Add Session'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Session Name</label>
                <input v-model="form.name" type="text" class="form-input" placeholder="2027-2028" required />
            </div>
            <div>
                <label class="form-label">Start Date</label>
                <input v-model="form.start_date" type="date" class="form-input" required />
            </div>
            <div>
                <label class="form-label">End Date</label>
                <input v-model="form.end_date" type="date" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Status</label>
                <select v-model="form.status" class="form-input">
                    <option value="upcoming">Upcoming</option>
                    <option value="current">Current</option>
                    <option value="closed">Closed</option>
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
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';
import { loadSessions } from '../../store';

const loading = ref(true);
const saving = ref(false);
const sessions = ref([]);
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', start_date: '', end_date: '', status: 'upcoming' });

const counts = computed(() => ({
    current: sessions.value.filter((s) => s.status === 'current').length,
    closed: sessions.value.filter((s) => s.status === 'closed').length,
    upcoming: sessions.value.filter((s) => s.status === 'upcoming').length,
}));

function formatDate(d) {
    return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function load() {
    loading.value = true;
    sessions.value = await loadSessions();
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', start_date: '', end_date: '', status: 'upcoming' });
    drawerOpen.value = true;
}

function openEdit(session) {
    editing.value = session;
    Object.assign(form, {
        name: session.name,
        start_date: session.start_date.slice(0, 10),
        end_date: session.end_date.slice(0, 10),
        status: session.status,
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/settings/academic-sessions/${editing.value.id}`, form);
            pushToast('Academic session updated.', 'success');
        } else {
            await client.post('/settings/academic-sessions', form);
            pushToast('Academic session created.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(session) {
    sessions.value = sessions.value.filter((s) => s.id !== session.id);
    await client.delete(`/settings/academic-sessions/${session.id}`);
    pushToast(`Session "${session.name}" deleted.`, 'success');
    await load();
}
</script>
