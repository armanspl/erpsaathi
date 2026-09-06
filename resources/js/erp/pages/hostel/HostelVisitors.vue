<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Hostel Visitors</h1>
                <Breadcrumb :items="['Dashboard', 'Hostel', 'Hostel Visitors']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Log Visitor</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="visitors" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Visits" :value="visitors.length" color="indigo" icon="🧑‍🤝‍🧑" />
            <StatCard label="Currently In" :value="visitors.filter((v) => !v.out_time).length" color="amber" icon="🚪" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Visitor</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Visiting</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">In / Out</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredVisitors.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No visitors match your filters.</td>
                    </tr>
                    <tr v-for="v in filteredVisitors" :key="v.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ v.visitor_name }}</p>
                            <p class="text-xs text-slate-400">{{ v.relation || '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ v.student.name }} ({{ v.student.admission_no }})</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(v.visit_date) }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ v.in_time || '—' }} / {{ v.out_time || '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800" @click="openEdit(v)">✏️</button>
                            <button v-if="!v.out_time" type="button" class="btn-outline !py-1 !text-xs" @click="checkout(v)">Check Out</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Visitor' : 'Log Visitor'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Student Being Visited</label>
                <select v-model.number="form.student_id" class="form-input">
                    <option :value="null">Select student</option>
                    <option v-for="s in students" :key="s.id" :value="s.id">{{ s.name }} ({{ s.admission_no }})</option>
                </select>
            </div>
            <div>
                <label class="form-label">Visitor Name</label>
                <input v-model="form.visitor_name" type="text" class="form-input" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Relation</label>
                    <input v-model="form.relation" type="text" class="form-input" placeholder="Father, Mother..." />
                </div>
                <div>
                    <label class="form-label">Visit Date</label>
                    <input v-model="form.visit_date" type="date" class="form-input" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">In Time</label>
                    <input v-model="form.in_time" type="time" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Purpose</label>
                    <input v-model="form.purpose" type="text" class="form-input" />
                </div>
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
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const visitors = ref([]);
const students = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ student_id: null, visitor_name: '', relation: '', purpose: '', visit_date: new Date().toISOString().slice(0, 10), in_time: '' });

const filteredVisitors = computed(() =>
    visitors.value.filter((v) => {
        if (filterValues.search && !`${v.visitor_name} ${v.student.name}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [visitorsRes, studentsRes] = await Promise.all([client.get('/hostel/visitors'), client.get('/people/students')]);
    visitors.value = visitorsRes.data;
    students.value = studentsRes.data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { student_id: null, visitor_name: '', relation: '', purpose: '', visit_date: new Date().toISOString().slice(0, 10), in_time: '' });
    drawerOpen.value = true;
}

function openEdit(visitor) {
    editing.value = visitor;
    Object.assign(form, {
        student_id: visitor.student.id,
        visitor_name: visitor.visitor_name,
        relation: visitor.relation || '',
        purpose: visitor.purpose || '',
        visit_date: visitor.visit_date ? visitor.visit_date.slice(0, 10) : '',
        in_time: visitor.in_time || '',
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/hostel/visitors/${editing.value.id}`, form);
            pushToast('Visitor updated.', 'success');
        } else {
            await client.post('/hostel/visitors', form);
            pushToast('Visitor logged.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function checkout(visitor) {
    await client.patch(`/hostel/visitors/${visitor.id}/checkout`);
    pushToast(`${visitor.visitor_name} checked out.`, 'success');
    await load();
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
