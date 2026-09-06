<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Staff</h1>
                <Breadcrumb :items="['Dashboard', 'People', 'Staff']" class="mt-1" />
            </div>
            <button v-if="can('people.staff.create')" type="button" class="btn-primary" @click="openAdd">+ Add Staff</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="staff" @reset="filterValues = {}" />

        <div class="grid grid-cols-3 gap-4">
            <StatCard label="Total" :value="staff.length" color="indigo" icon="🧑‍💼" />
            <StatCard label="Active" :value="staff.filter((s) => s.status === 'active').length" color="emerald" icon="✅" />
            <StatCard label="Inactive" :value="staff.filter((s) => s.status === 'inactive').length" color="rose" icon="⛔" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Emp ID</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Department</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Phone</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredStaff.length">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">No staff match your filters.</td>
                    </tr>
                    <tr v-for="s in filteredStaff" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ s.employee_id }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.department || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.email || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ s.phone || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset" :class="statusBadgeClass(s.status)">{{ s.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button v-if="can('people.staff.edit')" type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(s)">✎</button>
                                <button v-if="can('people.staff.delete')" type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(s)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Staff' : 'Add Staff'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Employee ID <span class="text-rose-500">*</span></label>
                <input v-model="form.employee_id" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Name <span class="text-rose-500">*</span></label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Department <span class="text-rose-500">*</span></label>
                <select v-model="form.department" class="form-input" required>
                    <option value="" disabled>Select department</option>
                    <option v-for="d in departmentOptions" :key="d" :value="d">{{ d }}</option>
                </select>
                <p class="mt-1 text-xs text-slate-400">Permissions come from Settings → Roles &amp; Permissions → Departments.</p>
                <input
                    v-if="allowCustomDepartment"
                    v-model="customDepartment"
                    type="text"
                    class="form-input mt-2"
                    placeholder="Or type a new department name"
                    @change="onCustomDepartment"
                />
            </div>
            <div>
                <label class="form-label">Phone</label>
                <input v-model="form.phone" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Email <span class="text-rose-500">*</span></label>
                <input v-model="form.email" type="email" class="form-input" required />
                <p class="mt-1 text-xs text-slate-400">Used to sign in to the ERP login page.</p>
            </div>
            <div>
                <label class="form-label">
                    {{ editing ? 'Password (leave blank to keep current)' : 'Password' }}
                    <span v-if="!editing" class="text-rose-500">*</span>
                </label>
                <input v-model="form.password" type="password" class="form-input" :required="!editing" autocomplete="new-password" />
                <p class="mt-1 text-xs text-slate-400">Staff can log in with this email and password.</p>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select v-model="form.status" class="form-input">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
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
import { invalidatePeopleLookups } from '../../api/people';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';
import { usePermissions } from '../../composables/usePermissions';

const { can } = usePermissions();

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'] },
];

const loading = ref(true);
const saving = ref(false);
const staff = ref([]);
const departmentOptions = ref([]);
const customDepartment = ref('');
const allowCustomDepartment = ref(true);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ employee_id: '', name: '', department: '', phone: '', email: '', password: '', status: 'active' });

const filteredStaff = computed(() =>
    staff.value.filter((s) => {
        if (filterValues.search && !`${s.name} ${s.employee_id} ${s.department} ${s.email}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && s.status !== filterValues.status.toLowerCase()) return false;
        return true;
    }),
);

function onCustomDepartment() {
    const name = customDepartment.value.trim();
    if (!name) return;
    if (!departmentOptions.value.includes(name)) {
        departmentOptions.value = [...departmentOptions.value, name].sort((a, b) => a.localeCompare(b));
    }
    form.department = name;
}

async function loadDepartments() {
    try {
        const { data } = await client.get('/settings/departments/suggestions');
        departmentOptions.value = Array.isArray(data) ? data : [];
    } catch {
        departmentOptions.value = [];
    }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await client.get('/people/staff');
        staff.value = Array.isArray(data) ? data : (data.data || []);
    } catch {
        staff.value = [];
        pushToast('You do not have permission to view staff.', 'error');
    } finally {
        loading.value = false;
    }
}
load();
loadDepartments();

function openAdd() {
    editing.value = null;
    customDepartment.value = '';
    Object.assign(form, { employee_id: '', name: '', department: departmentOptions.value[0] || '', phone: '', email: '', password: '', status: 'active' });
    drawerOpen.value = true;
}

function openEdit(member) {
    editing.value = member;
    customDepartment.value = '';
    Object.assign(form, {
        employee_id: member.employee_id,
        name: member.name,
        department: member.department || '',
        phone: member.phone || '',
        email: member.email || '',
        password: '',
        status: member.status,
    });
    if (member.department && !departmentOptions.value.includes(member.department)) {
        departmentOptions.value = [...departmentOptions.value, member.department];
    }
    drawerOpen.value = true;
}

async function save() {
    if (!form.department?.trim()) {
        pushToast('Department is required.', 'error');
        return;
    }
    if (!form.email?.trim()) {
        pushToast('Email is required for ERP login.', 'error');
        return;
    }
    if (!editing.value && !form.password) {
        pushToast('Password is required so the staff member can log in.', 'error');
        return;
    }
    saving.value = true;
    try {
        const payload = { ...form };
        if (editing.value && !payload.password) {
            delete payload.password;
        }
        if (editing.value) {
            await client.put(`/people/staff/${editing.value.id}`, payload);
            pushToast('Staff updated.', 'success');
        } else {
            await client.post('/people/staff', payload);
            pushToast('Staff added. They can sign in with their email and password.', 'success');
        }
        drawerOpen.value = false;
        invalidatePeopleLookups();
        await load();
        await loadDepartments();
    } catch (e) {
        const msg = e?.response?.data?.message
            || (e?.response?.data?.errors && Object.values(e.response.data.errors).flat()[0])
            || 'Could not save staff.';
        pushToast(msg, 'error');
    } finally {
        saving.value = false;
    }
}

async function remove(member) {
    staff.value = staff.value.filter((s) => s.id !== member.id);
    await client.delete(`/people/staff/${member.id}`);
    invalidatePeopleLookups();
    pushToast(`Staff "${member.name}" deleted.`, 'success');
}
</script>
