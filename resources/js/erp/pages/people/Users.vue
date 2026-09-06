<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Users</h1>
                <Breadcrumb :items="['Dashboard', 'People', 'Users']" class="mt-1" />
            </div>
            <button v-if="isAdmin" type="button" class="btn-primary" @click="openAdd">+ Add User</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="users" @reset="filterValues = {}" />

        <div class="grid grid-cols-3 gap-4">
            <StatCard label="Total Users" :value="users.length" color="indigo" icon="👤" />
            <StatCard label="Active" :value="users.filter((u) => u.is_active).length" color="emerald" icon="✅" />
            <StatCard label="Admins" :value="users.filter((u) => u.role === 'admin').length" color="violet" icon="🛡️" />
        </div>

        <p v-if="!isAdmin" class="text-xs text-slate-400">Only admins can create, edit, or remove user accounts. You have read-only access here.</p>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Role</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th v-if="isAdmin" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td :colspan="isAdmin ? 5 : 4" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredUsers.length">
                        <td :colspan="isAdmin ? 5 : 4" class="px-4 py-10 text-center text-slate-400">No users match your filters.</td>
                    </tr>
                    <tr v-for="u in filteredUsers" :key="u.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ u.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ u.email }}</td>
                        <td class="px-4 py-3 capitalize text-slate-500 dark:text-slate-400">{{ u.role }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(u.is_active ? 'Active' : 'Inactive')">{{ u.is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td v-if="isAdmin" class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(u)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(u)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit User' : 'Add User'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Email</label>
                <input v-model="form.email" type="email" class="form-input" required />
            </div>
            <div>
                <label class="form-label">{{ editing ? 'New Password (leave blank to keep current)' : 'Password' }}</label>
                <input v-model="form.password" type="password" class="form-input" />
            </div>
            <div>
                <label class="form-label">Role</label>
                <select v-model="form.role" class="form-input">
                    <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input v-model="form.is_active" type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                Active
            </label>
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
import { erpStore } from '../../store';

const isAdmin = computed(() => erpStore.user.role === 'admin');

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'] },
];

const loading = ref(true);
const saving = ref(false);
const users = ref([]);
const roles = ref(['admin', 'staff']);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', email: '', password: '', role: 'staff', is_active: true });

const filteredUsers = computed(() =>
    users.value.filter((u) => {
        if (filterValues.search && !`${u.name} ${u.email}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status) {
            const wantActive = filterValues.status === 'Active';
            if (u.is_active !== wantActive) return false;
        }
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [usersRes, rolesRes] = await Promise.all([client.get('/people/users'), client.get('/settings/roles')]);
    users.value = usersRes.data;
    const slugs = rolesRes.data.map((r) => r.slug);
    roles.value = [...new Set([...slugs, 'admin', 'staff'])];
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', email: '', password: '', role: 'staff', is_active: true });
    drawerOpen.value = true;
}

function openEdit(user) {
    editing.value = user;
    Object.assign(form, { name: user.name, email: user.email, password: '', role: user.role, is_active: user.is_active });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/people/users/${editing.value.id}`, form);
            pushToast('User updated.', 'success');
        } else {
            await client.post('/people/users', form);
            pushToast('User created.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(user) {
    users.value = users.value.filter((u) => u.id !== user.id);
    await client.delete(`/people/users/${user.id}`);
    pushToast(`User "${user.name}" deleted.`, 'success');
}
</script>
