<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Drivers</h1>
                <Breadcrumb :items="['Dashboard', 'People', 'Drivers']" class="mt-1" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline" :disabled="exportingProfiles" title="SALARY DETAILS + STAFF DETAILS workbook, re-importable from Salary Sheet" @click="exportProfiles">
                    {{ exportingProfiles ? 'Exporting...' : 'Export profiles (Excel)' }}
                </button>
                <button type="button" class="btn-primary" @click="openAdd">+ Add Driver</button>
            </div>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="drivers" @reset="filterValues = {}" />

        <div class="grid grid-cols-3 gap-4">
            <StatCard label="Total" :value="drivers.length" color="indigo" icon="🚗" />
            <StatCard label="Active" :value="drivers.filter((d) => d.status === 'active').length" color="emerald" icon="✅" />
            <StatCard label="Inactive" :value="drivers.filter((d) => d.status === 'inactive').length" color="rose" icon="⛔" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('employee_id')">Emp ID {{ sortArrow('employee_id') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('name')">Name {{ sortArrow('name') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('phone')">Phone {{ sortArrow('phone') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('email')">Email {{ sortArrow('email') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('license_no')">License No {{ sortArrow('license_no') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('vehicle_no')">Vehicle No {{ sortArrow('vehicle_no') }}</th>
                        <th class="cursor-pointer px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('status')">Status {{ sortArrow('status') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredDrivers.length">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">No drivers match your filters.</td>
                    </tr>
                    <tr v-for="d in sortedDrivers" :key="d.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ d.employee_id }}</td>
                        <td class="px-4 py-3">
                            <button type="button" class="text-left font-medium text-slate-800 hover:text-primary-600 hover:underline dark:text-slate-100" @click="openView(d)">{{ d.name }}</button>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ d.phone || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ d.email || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ d.license_no || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ d.vehicle_no || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize ring-1 ring-inset" :class="statusBadgeClass(d.status)">{{ d.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" class="btn-outline !py-1 !text-xs" @click="openView(d)">View</button>
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(d)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(d)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Driver' : 'Add Driver'" wide @close="drawerOpen = false">
            <div>
                <label class="form-label">EMP code <span class="text-rose-500">*</span></label>
                <input v-model="form.employee_id" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Name <span class="text-rose-500">*</span></label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Phone number</label>
                <input v-model="form.phone" type="tel" inputmode="tel" class="form-input" placeholder="10-digit mobile" />
            </div>
            <div>
                <label class="form-label">Email (Gmail)</label>
                <input v-model="form.email" type="email" class="form-input" placeholder="name@gmail.com" />
            </div>
            <div>
                <label class="form-label">License No</label>
                <input v-model="form.license_no" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Vehicle No</label>
                <input v-model="form.vehicle_no" type="text" class="form-input" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="form-label">Basic salary (present)</label>
                    <input v-model="form.salary" type="number" min="0" step="0.01" class="form-input" placeholder="₹" />
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select v-model="form.status" class="form-input">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                <EmployeeProfileFormFields :profile="profile" type="driver" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>

        <EmployeeProfileModal
            :open="viewOpen"
            :person="viewTarget"
            type="driver"
            @close="viewOpen = false"
            @edit="(d) => { viewOpen = false; openEdit(d); }"
        />
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import EmployeeProfileFormFields from '../../components/people/EmployeeProfileFormFields.vue';
import EmployeeProfileModal from '../../components/people/EmployeeProfileModal.vue';
import { emptyProfile, profileFromFields } from '../../utils/customFields';
import { downloadStaffProfiles } from '../../utils/downloadExport';
import { fetchPeopleLookups, invalidatePeopleLookups } from '../../api/people';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Active', 'Inactive'] },
];

const loading = ref(true);
const saving = ref(false);
const drivers = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ employee_id: '', name: '', phone: '', email: '', license_no: '', vehicle_no: '', status: 'active', salary: '' });
const profile = reactive(emptyProfile());

function payload() {
    return { ...form, salary: form.salary === '' ? null : form.salary, profile: { ...profile } };
}

const viewOpen = ref(false);
const viewTarget = ref(null);

function openView(driver) {
    viewTarget.value = driver;
    viewOpen.value = true;
}

const exportingProfiles = ref(false);

async function exportProfiles() {
    exportingProfiles.value = true;
    try {
        await downloadStaffProfiles({ type: 'driver' });
    } catch (err) {
        pushToast(err?.response?.status === 403 ? 'You do not have permission to export profiles (Salary Sheet → Export).' : 'Could not export profiles.', 'error');
    } finally {
        exportingProfiles.value = false;
    }
}

const filteredDrivers = computed(() =>
    drivers.value.filter((d) => {
        if (filterValues.search && !`${d.name} ${d.employee_id} ${d.vehicle_no || ''} ${d.phone || ''} ${d.email || ''}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && d.status !== filterValues.status.toLowerCase()) return false;
        return true;
    }),
);

const sortKey = ref('name');
const sortDir = ref('asc');

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
}
function sortArrow(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

const sortedDrivers = computed(() => {
    return [...filteredDrivers.value].sort((a, b) => {
        let av = a[sortKey.value];
        let bv = b[sortKey.value];
        av = av ?? '';
        bv = bv ?? '';
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});

async function load() {
    loading.value = true;
    const lookups = await fetchPeopleLookups({ force: true });
    drivers.value = lookups.drivers || [];
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { employee_id: '', name: '', phone: '', email: '', license_no: '', vehicle_no: '', status: 'active', salary: '' });
    Object.assign(profile, emptyProfile());
    drawerOpen.value = true;
}

function openEdit(driver) {
    editing.value = driver;
    Object.assign(form, { employee_id: driver.employee_id, name: driver.name, phone: driver.phone || '', email: driver.email || '', license_no: driver.license_no || '', vehicle_no: driver.vehicle_no || '', status: driver.status, salary: driver.salary ?? '' });
    Object.assign(profile, profileFromFields(driver.custom_field_values));
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/people/drivers/${editing.value.id}`, payload());
            pushToast('Driver updated.', 'success');
        } else {
            await client.post('/people/drivers', payload());
            pushToast('Driver added.', 'success');
        }
        drawerOpen.value = false;
        invalidatePeopleLookups();
        await load();
    } catch (e) {
        const msg = (e?.response?.data?.errors && Object.values(e.response.data.errors).flat()[0])
            || e?.response?.data?.message
            || 'Could not save driver.';
        pushToast(msg, 'error');
    } finally {
        saving.value = false;
    }
}

async function remove(driver) {
    drivers.value = drivers.value.filter((d) => d.id !== driver.id);
    await client.delete(`/people/drivers/${driver.id}`);
    invalidatePeopleLookups();
    pushToast(`Driver "${driver.name}" deleted.`, 'success');
}
</script>
