<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Roles &amp; Permissions</h1>
                <Breadcrumb :items="['Dashboard', 'Settings', 'Roles & Permissions']" class="mt-1" />
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Assign page actions from a live ERP audit. Staff inherit permissions from their <strong class="font-medium text-slate-700 dark:text-slate-200">Department</strong>.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900">
            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="tab === 'departments' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                @click="tab = 'departments'"
            >
                Departments
            </button>
            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="tab === 'roles' ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                @click="tab = 'roles'"
            >
                Roles
            </button>
        </div>

        <!-- Departments -->
        <template v-if="tab === 'departments'">
            <div class="flex justify-end">
                <button type="button" class="btn-primary" @click="openAddDepartment">+ Add Department</button>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-if="loadingDepartments" class="col-span-full rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading...</div>
                <div v-else-if="!departments.length" class="col-span-full rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
                    No departments yet. Create one (e.g. Accounts, Admin) and tick the page actions staff in that department may use.
                </div>
                <div v-for="d in departments" :key="d.id" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ d.name }}</p>
                            <p class="text-xs text-slate-400">{{ d.description || 'No description' }}</p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase" :class="d.is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-500'">
                            {{ d.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="mb-3 text-xs text-slate-400">{{ d.permissions?.length || 0 }} permission(s)</p>
                    <div class="flex gap-2">
                        <button type="button" class="btn-outline flex-1 !py-1.5 !text-xs" @click="openEditDepartment(d)">Edit permissions</button>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:border-slate-700" @click="removeDepartment(d)">Delete</button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Roles (legacy coarse + optional full keys) -->
        <template v-else>
            <div class="flex justify-end">
                <button type="button" class="btn-primary" @click="openAddRole">+ Add Role</button>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-if="loadingRoles" class="col-span-full rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading...</div>
                <div v-for="role in roles" :key="role.id" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-2 flex items-start justify-between">
                        <div>
                            <p class="font-semibold text-slate-800 dark:text-slate-100">{{ role.name }}</p>
                            <p class="text-xs text-slate-400">{{ role.description || 'No description' }}</p>
                        </div>
                        <span v-if="role.is_system" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-slate-800">System</span>
                    </div>
                    <p class="mb-3 text-xs text-slate-400">
                        {{ role.permissions?.includes('*') ? 'All permissions' : `${role.permissions?.length || 0} permission(s)` }}
                    </p>
                    <div class="flex gap-2">
                        <button type="button" class="btn-outline flex-1 !py-1.5 !text-xs" @click="openEditRole(role)">Edit</button>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 disabled:opacity-40 dark:border-slate-700" :disabled="role.is_system" @click="removeRole(role)">Delete</button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Department / Role editor -->
        <SlideOver :open="drawerOpen" :title="drawerTitle" @close="drawerOpen = false" wide>
            <template v-if="drawerMode === 'department'">
                <div>
                    <label class="form-label">Department name</label>
                    <input v-model="deptForm.name" type="text" class="form-input" list="dept-suggestions" required />
                    <datalist id="dept-suggestions">
                        <option v-for="s in suggestions" :key="s" :value="s" />
                    </datalist>
                    <p class="mt-1 text-xs text-slate-400">Must match the Department field on Staff records.</p>
                </div>
                <div>
                    <label class="form-label">Description</label>
                    <input v-model="deptForm.description" type="text" class="form-input" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="deptForm.is_active" type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-primary-600" />
                    Active
                </label>
            </template>
            <template v-else>
                <div>
                    <label class="form-label">Role Name</label>
                    <input v-model="roleForm.name" type="text" class="form-input" :disabled="editingRole?.is_system" required />
                </div>
                <div>
                    <label class="form-label">Description</label>
                    <input v-model="roleForm.description" type="text" class="form-input" />
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <input v-model="fullAccess" type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-primary-600" />
                    Full access (all modules)
                </label>
            </template>

            <div class="mt-2" :class="fullAccess && drawerMode === 'role' && 'pointer-events-none opacity-40'">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Page actions (from ERP audit)</p>
                    <button type="button" class="text-xs font-medium text-primary-600 hover:underline" @click="selectAllVisible">Select all visible</button>
                </div>
                <div class="max-h-[55vh] space-y-3 overflow-y-auto rounded-lg border border-slate-100 p-3 dark:border-slate-800">
                    <div v-for="mod in catalogByModule" :key="mod.key" class="rounded-lg border border-slate-100 dark:border-slate-800">
                        <button type="button" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm font-semibold text-slate-800 dark:text-slate-100" @click="toggleModule(mod.key)">
                            <span>{{ mod.label }}</span>
                            <span class="text-xs font-normal text-slate-400">{{ moduleSelectedCount(mod) }}/{{ moduleActionCount(mod) }}</span>
                        </button>
                        <div v-show="expandedModules[mod.key]" class="space-y-3 border-t border-slate-100 px-3 py-2 dark:border-slate-800">
                            <div v-for="page in mod.pages" :key="page.page" class="pl-1">
                                <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ page.page_label }}</p>
                                <div class="mt-1 grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                                    <label v-for="action in page.actions" :key="action.key" class="flex items-center gap-1.5 text-[11px] text-slate-600 dark:text-slate-300">
                                        <input v-model="selectedPermissions" type="checkbox" :value="action.key" class="h-3.5 w-3.5 rounded border-slate-300 text-primary-600" />
                                        {{ action.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="saveDrawer">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const tab = ref('departments');
const catalogTree = ref([]);
const departments = ref([]);
const roles = ref([]);
const suggestions = ref([]);
const loadingDepartments = ref(true);
const loadingRoles = ref(true);
const saving = ref(false);
const drawerOpen = ref(false);
const drawerMode = ref('department');
const editingDepartment = ref(null);
const editingRole = ref(null);
const fullAccess = ref(false);
const selectedPermissions = ref([]);
const expandedModules = reactive({});

const deptForm = reactive({ name: '', description: '', is_active: true });
const roleForm = reactive({ name: '', description: '' });

const drawerTitle = computed(() => {
    if (drawerMode.value === 'department') {
        return editingDepartment.value ? `Department — ${editingDepartment.value.name}` : 'Add Department';
    }
    return editingRole.value ? `Role — ${editingRole.value.name}` : 'Add Role';
});

const catalogByModule = computed(() => {
    const map = new Map();
    for (const page of catalogTree.value) {
        if (!map.has(page.module)) {
            map.set(page.module, { key: page.module, label: page.module_label, pages: [] });
        }
        map.get(page.module).pages.push(page);
    }
    return [...map.values()];
});

function moduleActionCount(mod) {
    return mod.pages.reduce((n, p) => n + p.actions.length, 0);
}
function moduleSelectedCount(mod) {
    const set = new Set(selectedPermissions.value);
    return mod.pages.reduce((n, p) => n + p.actions.filter((a) => set.has(a.key)).length, 0);
}
function toggleModule(key) {
    expandedModules[key] = !expandedModules[key];
}
function selectAllVisible() {
    const keys = catalogTree.value.flatMap((p) => p.actions.map((a) => a.key));
    selectedPermissions.value = [...new Set([...selectedPermissions.value, ...keys])];
}

async function loadCatalog() {
    const { data } = await client.get('/settings/permissions/catalog');
    catalogTree.value = data.tree || [];
    const seen = new Set();
    for (const page of catalogTree.value) {
        if (!seen.has(page.module)) {
            seen.add(page.module);
            if (expandedModules[page.module] === undefined) {
                expandedModules[page.module] = page.module === 'people' || page.module === 'fee-management';
            }
        }
    }
}

async function loadDepartments() {
    loadingDepartments.value = true;
    try {
        const [{ data }, sug] = await Promise.all([
            client.get('/settings/departments'),
            client.get('/settings/departments/suggestions'),
        ]);
        departments.value = data;
        suggestions.value = sug.data || [];
    } finally {
        loadingDepartments.value = false;
    }
}

async function loadRoles() {
    loadingRoles.value = true;
    try {
        const { data } = await client.get('/settings/roles');
        roles.value = data;
    } finally {
        loadingRoles.value = false;
    }
}

loadCatalog();
loadDepartments();
loadRoles();

function openAddDepartment() {
    drawerMode.value = 'department';
    editingDepartment.value = null;
    Object.assign(deptForm, { name: '', description: '', is_active: true });
    selectedPermissions.value = [];
    fullAccess.value = false;
    drawerOpen.value = true;
}

function openEditDepartment(d) {
    drawerMode.value = 'department';
    editingDepartment.value = d;
    Object.assign(deptForm, { name: d.name, description: d.description || '', is_active: !!d.is_active });
    selectedPermissions.value = [...(d.permissions || [])];
    fullAccess.value = false;
    drawerOpen.value = true;
}

function openAddRole() {
    drawerMode.value = 'role';
    editingRole.value = null;
    Object.assign(roleForm, { name: '', description: '' });
    selectedPermissions.value = [];
    fullAccess.value = false;
    drawerOpen.value = true;
}

function openEditRole(role) {
    drawerMode.value = 'role';
    editingRole.value = role;
    Object.assign(roleForm, { name: role.name, description: role.description || '' });
    fullAccess.value = !!role.permissions?.includes('*');
    selectedPermissions.value = fullAccess.value ? [] : [...(role.permissions || [])];
    drawerOpen.value = true;
}

async function saveDrawer() {
    saving.value = true;
    try {
        if (drawerMode.value === 'department') {
            if (!deptForm.name.trim()) {
                pushToast('Department name is required.', 'error');
                return;
            }
            const payload = {
                name: deptForm.name.trim(),
                description: deptForm.description,
                is_active: deptForm.is_active,
                permissions: [...selectedPermissions.value],
            };
            if (editingDepartment.value) {
                await client.put(`/settings/departments/${editingDepartment.value.id}`, payload);
                pushToast('Department permissions saved.', 'success');
            } else {
                await client.post('/settings/departments', payload);
                pushToast('Department created.', 'success');
            }
            await loadDepartments();
        } else {
            if (!roleForm.name.trim()) {
                pushToast('Role name is required.', 'error');
                return;
            }
            const payload = {
                name: roleForm.name.trim(),
                description: roleForm.description,
                permissions: fullAccess.value ? ['*'] : [...selectedPermissions.value],
            };
            if (editingRole.value) {
                await client.put(`/settings/roles/${editingRole.value.id}`, payload);
                pushToast('Role updated.', 'success');
            } else {
                await client.post('/settings/roles', payload);
                pushToast('Role created.', 'success');
            }
            await loadRoles();
        }
        drawerOpen.value = false;
    } catch (e) {
        pushToast(e?.response?.data?.message || 'Could not save.', 'error');
    } finally {
        saving.value = false;
    }
}

async function removeDepartment(d) {
    if (!window.confirm(`Delete department "${d.name}"?`)) return;
    await client.delete(`/settings/departments/${d.id}`);
    departments.value = departments.value.filter((x) => x.id !== d.id);
    pushToast('Department deleted.', 'success');
}

async function removeRole(role) {
    if (role.is_system) return;
    if (!window.confirm(`Delete role "${role.name}"?`)) return;
    await client.delete(`/settings/roles/${role.id}`);
    roles.value = roles.value.filter((x) => x.id !== role.id);
    pushToast('Role deleted.', 'success');
}
</script>
