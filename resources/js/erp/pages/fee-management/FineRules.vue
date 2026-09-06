<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Fine Rules</h1>
                <Breadcrumb :items="['Dashboard', 'Fee Management', 'Fine Rules']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Fine Rule</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="fine rules" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4">
            <StatCard label="Total Rules" :value="rules.length" color="indigo" icon="⚠️" />
            <StatCard label="Per-Day Rules" :value="rules.filter((r) => r.type === 'per_day').length" color="amber" icon="📅" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Grace Days</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredRules.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No fine rules match your filters.</td>
                    </tr>
                    <tr v-for="r in filteredRules" :key="r.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</td>
                        <td class="px-4 py-3 capitalize text-slate-500 dark:text-slate-400">{{ r.type.replace('_', ' ') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">₹{{ Number(r.amount).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ r.grace_days }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(r)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(r)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Fine Rule' : 'Add Fine Rule'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Name</label>
                <input v-model="form.name" type="text" class="form-input" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Type</label>
                    <select v-model="form.type" class="form-input">
                        <option value="per_day">Per Day</option>
                        <option value="fixed">Fixed</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Amount (₹)</label>
                    <input v-model.number="form.amount" type="number" class="form-input" required />
                </div>
            </div>
            <div>
                <label class="form-label">Grace Days</label>
                <input v-model.number="form.grace_days" type="number" class="form-input" />
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
import { fetchFeeLookups, invalidateFeeLookups } from '../../api/feeManagement';
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const rules = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ name: '', type: 'fixed', amount: null, grace_days: 0 });

const filteredRules = computed(() =>
    rules.value.filter((r) => {
        if (filterValues.search && !r.name.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const fee = await fetchFeeLookups({ force: true });
    rules.value = fee.fine_rules || [];
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { name: '', type: 'fixed', amount: null, grace_days: 0 });
    drawerOpen.value = true;
}

function openEdit(rule) {
    editing.value = rule;
    Object.assign(form, { name: rule.name, type: rule.type, amount: Number(rule.amount), grace_days: rule.grace_days });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/fee-management/fine-rules/${editing.value.id}`, form);
            pushToast('Fine rule updated.', 'success');
        } else {
            await client.post('/fee-management/fine-rules', form);
            pushToast('Fine rule added.', 'success');
        }
        drawerOpen.value = false;
        invalidateFeeLookups();
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(rule) {
    rules.value = rules.value.filter((r) => r.id !== rule.id);
    await client.delete(`/fee-management/fine-rules/${rule.id}`);
    invalidateFeeLookups();
    pushToast(`Fine rule "${rule.name}" deleted.`, 'success');
}
</script>
