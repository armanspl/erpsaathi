<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ pageTitle }}</h1>
                <Breadcrumb :items="['Dashboard', 'Meetings', pageTitle]" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Schedule</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="meetings" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total" :value="filteredMeetings.length" color="indigo" icon="🎥" />
            <StatCard label="Scheduled" :value="filteredMeetings.filter((m) => m.status === 'Scheduled').length" color="emerald" icon="📅" />
            <StatCard label="Completed" :value="filteredMeetings.filter((m) => m.status === 'Completed').length" color="sky" icon="✅" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Title</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date / Time</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Audience</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredMeetings.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Nothing matches your filters.</td>
                    </tr>
                    <tr v-for="m in filteredMeetings" :key="m.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ m.title }}</p>
                            <p class="text-xs text-slate-400">
                                <span v-if="m.teacher">{{ m.teacher.name }}<span v-if="m.school_class"> · Class {{ m.school_class.name }}</span> · </span>{{ m.venue || m.meeting_link || '—' }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(m.meeting_date) }}, {{ m.start_time }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ m.audience }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(m.status === 'Scheduled' ? 'Active' : m.status === 'Completed' ? 'Active' : 'Inactive')">{{ m.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(m)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(m)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit' : `Schedule ${routeType || 'Meeting'}`" @close="drawerOpen = false">
            <div v-if="!routeType">
                <label class="form-label">Type</label>
                <select v-model="form.type" class="form-input">
                    <option>Online Meeting</option>
                    <option>Parent Teacher Meeting</option>
                    <option>Broadcast</option>
                </select>
            </div>
            <div>
                <label class="form-label">Title</label>
                <input v-model="form.title" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Description</label>
                <textarea v-model="form.description" rows="2" class="form-input" />
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="form-label">Date</label>
                    <input v-model="form.meeting_date" type="date" class="form-input" />
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
                <label class="form-label">Meeting Link</label>
                <input v-model="form.meeting_link" type="text" class="form-input" placeholder="https://..." />
            </div>
            <div>
                <label class="form-label">Venue</label>
                <input v-model="form.venue" type="text" class="form-input" />
            </div>
            <template v-if="(routeType || form.type) === 'Parent Teacher Meeting'">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Teacher</label>
                        <select v-model.number="form.teacher_id" class="form-input">
                            <option :value="null">Select teacher</option>
                            <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model.number="form.school_class_id" class="form-input">
                            <option :value="null">Select class</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>
            </template>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Audience</label>
                    <select v-model="form.audience" class="form-input">
                        <option>All</option>
                        <option>Students</option>
                        <option>Teachers</option>
                        <option>Staff</option>
                        <option>Parents</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select v-model="form.status" class="form-input">
                        <option>Scheduled</option>
                        <option>Completed</option>
                        <option>Cancelled</option>
                    </select>
                </div>
            </div>
            <div v-if="form.status === 'Completed'">
                <label class="form-label">Recording URL</label>
                <input v-model="form.recording_url" type="text" class="form-input" placeholder="https://..." />
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
import { useRoute } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const route = useRoute();
const routeTypeMap = {
    '/meetings/online-meetings': 'Online Meeting',
    '/meetings/parent-teacher-meeting': 'Parent Teacher Meeting',
    '/meetings/broadcast': 'Broadcast',
};
const routeType = computed(() => routeTypeMap[route.path] || null);
const pageTitle = computed(() => (route.path === '/meetings/parent-teacher-meeting' ? 'Parent Teacher Meeting' : routeType.value || 'Meetings'));

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Scheduled', 'Completed', 'Cancelled'] },
];

const loading = ref(true);
const saving = ref(false);
const meetings = ref([]);
const teachers = ref([]);
const classes = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({
    type: routeType.value || 'Online Meeting', title: '', description: '', meeting_date: new Date().toISOString().slice(0, 10),
    start_time: '', end_time: '', meeting_link: '', venue: '', teacher_id: null, school_class_id: null,
    audience: 'All', status: 'Scheduled', recording_url: '',
});

const filteredMeetings = computed(() =>
    meetings.value.filter((m) => {
        if (routeType.value && m.type !== routeType.value) return false;
        if (filterValues.search && !`${m.title}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && m.status !== filterValues.status) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [meetingsRes, teachersRes, classesRes] = await Promise.all([
        client.get('/meetings'),
        client.get('/people/teachers'),
        client.get('/academics/classes'),
    ]);
    meetings.value = meetingsRes.data;
    teachers.value = teachersRes.data;
    classes.value = classesRes.data;
    loading.value = false;
}
load();

function resetForm() {
    Object.assign(form, {
        type: routeType.value || 'Online Meeting', title: '', description: '', meeting_date: new Date().toISOString().slice(0, 10),
        start_time: '', end_time: '', meeting_link: '', venue: '', teacher_id: null, school_class_id: null,
        audience: 'All', status: 'Scheduled', recording_url: '',
    });
}

function openAdd() {
    editing.value = null;
    resetForm();
    drawerOpen.value = true;
}

function openEdit(meeting) {
    editing.value = meeting;
    Object.assign(form, {
        type: meeting.type,
        title: meeting.title,
        description: meeting.description || '',
        meeting_date: meeting.meeting_date.slice(0, 10),
        start_time: meeting.start_time,
        end_time: meeting.end_time || '',
        meeting_link: meeting.meeting_link || '',
        venue: meeting.venue || '',
        teacher_id: meeting.teacher_id,
        school_class_id: meeting.school_class_id,
        audience: meeting.audience,
        status: meeting.status,
        recording_url: meeting.recording_url || '',
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        const payload = { ...form, end_time: form.end_time || null, recording_url: form.recording_url || null };
        if (editing.value) {
            await client.put(`/meetings/${editing.value.id}`, payload);
            pushToast('Updated.', 'success');
        } else {
            await client.post('/meetings', payload);
            pushToast(`${form.type} scheduled.`, 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(meeting) {
    meetings.value = meetings.value.filter((m) => m.id !== meeting.id);
    await client.delete(`/meetings/${meeting.id}`);
    pushToast(`"${meeting.title}" deleted.`, 'success');
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
