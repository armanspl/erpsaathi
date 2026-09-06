<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Library Members</h1>
                <Breadcrumb :items="['Dashboard', 'Library', 'Library Members']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Member</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="members" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Members" :value="members.length" color="indigo" icon="🪪" />
            <StatCard label="Active" :value="members.filter((m) => m.status === 'Active').length" color="emerald" icon="✅" />
            <StatCard label="Blocked" :value="members.filter((m) => m.status === 'Blocked').length" color="rose" icon="⛔" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Card No.</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Member</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Max Books</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredMembers.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No members match your filters.</td>
                    </tr>
                    <tr v-for="m in filteredMembers" :key="m.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ m.library_card_no }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ m.member_name }}</p>
                            <p class="text-xs text-slate-400">{{ m.member_code }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 capitalize">{{ m.member_type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ m.max_books }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(m.status)">{{ m.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(m)">✎</button>
                                <button type="button" title="Remove" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(m)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Member' : 'Add Member'" @close="drawerOpen = false">
            <template v-if="!editing">
                <div>
                    <label class="form-label">Member Type</label>
                    <select v-model="newForm.member_type" class="form-input" @change="newForm.member_id = null">
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Person</label>
                    <select v-model.number="newForm.member_id" class="form-input">
                        <option :value="null">Select person</option>
                        <option v-for="p in candidatesForType" :key="p.id" :value="p.id">{{ p.name }} ({{ p.admission_no || p.employee_id }})</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Max Books</label>
                        <input v-model.number="newForm.max_books" type="number" min="1" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Joined Date</label>
                        <input v-model="newForm.joined_date" type="date" class="form-input" />
                    </div>
                </div>
            </template>
            <template v-else>
                <div>
                    <label class="form-label">Max Books</label>
                    <input v-model.number="editForm.max_books" type="number" min="1" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select v-model="editForm.status" class="form-input">
                        <option>Active</option>
                        <option>Blocked</option>
                    </select>
                </div>
            </template>
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
    { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Blocked'] },
];

const loading = ref(true);
const saving = ref(false);
const members = ref([]);
const students = ref([]);
const teachers = ref([]);
const staff = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const newForm = reactive({ member_type: 'student', member_id: null, max_books: 3, joined_date: new Date().toISOString().slice(0, 10) });
const editForm = reactive({ status: 'Active', max_books: 3 });

const candidatesForType = computed(() => {
    const assignedIds = new Set(members.value.filter((m) => m.member_type === newForm.member_type).map((m) => m.member_id));
    const pool = { student: students.value, teacher: teachers.value, staff: staff.value }[newForm.member_type] || [];
    return pool.filter((p) => !assignedIds.has(p.id));
});

const filteredMembers = computed(() =>
    members.value.filter((m) => {
        if (filterValues.search && !`${m.member_name} ${m.library_card_no}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && m.status !== filterValues.status) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [membersRes, studentsRes, teachersRes, staffRes] = await Promise.all([
        client.get('/library/members'),
        client.get('/people/students'),
        client.get('/people/teachers'),
        client.get('/people/staff'),
    ]);
    members.value = membersRes.data;
    students.value = studentsRes.data;
    teachers.value = teachersRes.data;
    staff.value = staffRes.data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(newForm, { member_type: 'student', member_id: null, max_books: 3, joined_date: new Date().toISOString().slice(0, 10) });
    drawerOpen.value = true;
}

function openEdit(member) {
    editing.value = member;
    Object.assign(editForm, { status: member.status, max_books: member.max_books });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/library/members/${editing.value.id}`, editForm);
            pushToast('Member updated.', 'success');
        } else {
            await client.post('/library/members', newForm);
            pushToast('Member added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(member) {
    members.value = members.value.filter((m) => m.id !== member.id);
    await client.delete(`/library/members/${member.id}`);
    pushToast(`Removed "${member.member_name}" from library membership.`, 'success');
}
</script>
