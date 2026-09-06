<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Visitor Records</h1>
                <Breadcrumb :items="['Dashboard', 'People', 'Visitor Records']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="drawerOpen = true">+ Check In Visitor</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="visitors" @reset="filterValues = {}" />

        <div class="grid grid-cols-3 gap-4">
            <StatCard label="Currently In" :value="visitors.filter((v) => v.status === 'checked_in').length" color="emerald" icon="🟢" />
            <StatCard label="Checked Out" :value="visitors.filter((v) => v.status === 'checked_out').length" color="slate" icon="⚪" />
            <StatCard label="Total Records" :value="visitors.length" color="indigo" icon="🪪" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Gate Pass</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Visitor</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Purpose</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Whom to Meet</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Check In</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Check Out</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredVisitors.length">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">No visitor records match your filters.</td>
                    </tr>
                    <tr v-for="v in filteredVisitors" :key="v.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ v.gate_pass_no }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ v.name }}<br /><span class="text-xs font-normal text-slate-400">{{ v.phone }}</span></td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ v.purpose || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ v.whom_to_meet || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatTime(v.check_in_at) }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ v.check_out_at ? formatTime(v.check_out_at) : '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(v.status === 'checked_in' ? 'Active' : 'Inactive')">
                                {{ v.status === 'checked_in' ? 'Checked In' : 'Checked Out' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button v-if="v.status === 'checked_in'" type="button" class="btn-outline !py-1 !text-xs" @click="checkOut(v)">Check Out</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(v)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" title="Check In Visitor" @close="drawerOpen = false">
            <div>
                <label class="form-label">Visitor Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Phone</label>
                <input v-model="form.phone" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Purpose</label>
                <input v-model="form.purpose" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Whom to Meet</label>
                <input v-model="form.whom_to_meet" type="text" class="form-input" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Checking In...' : 'Check In' }}</button>
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
    { key: 'status', label: 'Status', type: 'select', options: ['Checked In', 'Checked Out'] },
];

const loading = ref(true);
const saving = ref(false);
const visitors = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);

const form = reactive({ name: '', phone: '', purpose: '', whom_to_meet: '' });

const filteredVisitors = computed(() =>
    visitors.value.filter((v) => {
        if (filterValues.search && !`${v.name} ${v.phone} ${v.gate_pass_no}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status) {
            const wanted = filterValues.status === 'Checked In' ? 'checked_in' : 'checked_out';
            if (v.status !== wanted) return false;
        }
        return true;
    }),
);

function formatTime(value) {
    return new Date(value).toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

async function load() {
    loading.value = true;
    const { data } = await client.get('/people/visitors', { params: { limit: 200 } });
    visitors.value = data;
    loading.value = false;
}
load();

async function save() {
    saving.value = true;
    try {
        await client.post('/people/visitors', form);
        pushToast('Visitor checked in.', 'success');
        Object.assign(form, { name: '', phone: '', purpose: '', whom_to_meet: '' });
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function checkOut(visitor) {
    await client.post(`/people/visitors/${visitor.id}/check-out`);
    pushToast(`${visitor.name} checked out.`, 'success');
    await load();
}

async function remove(visitor) {
    visitors.value = visitors.value.filter((v) => v.id !== visitor.id);
    await client.delete(`/people/visitors/${visitor.id}`);
    pushToast('Visitor record deleted.', 'success');
}
</script>
