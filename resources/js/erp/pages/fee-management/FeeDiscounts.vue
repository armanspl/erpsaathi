<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Fee Discounts</h1>
                <Breadcrumb :items="['Dashboard', 'Fee Management', 'Fee Discounts']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Discount</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="discounts" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total Discounts" :value="discounts.length" color="indigo" icon="🏷️" />
            <StatCard label="Percentage-based" :value="discounts.filter((d) => d.type === 'percentage').length" color="sky" icon="％" />
            <StatCard label="Fixed Amount" :value="discounts.filter((d) => d.type === 'fixed').length" color="emerald" icon="₹" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Fee Head</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Value</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Reason</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredDiscounts.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No discounts match your filters.</td>
                    </tr>
                    <tr v-for="d in filteredDiscounts" :key="d.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ d.student.name }}<br /><span class="font-mono text-xs font-normal text-slate-400">{{ d.student.admission_no }}</span></td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ d.fee_head?.name || 'All Fees' }}</td>
                        <td class="px-4 py-3 capitalize text-slate-500 dark:text-slate-400">{{ d.type }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ d.type === 'percentage' ? `${d.value}%` : `₹${Number(d.value).toLocaleString('en-IN')}` }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ d.reason || '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(d)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(d)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Discount' : 'Add Discount'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Student</label>
                <select v-model="form.student_id" class="form-input">
                    <option :value="null">Select student</option>
                    <option v-for="s in students" :key="s.id" :value="s.id">{{ s.name }} ({{ s.admission_no }})</option>
                </select>
            </div>
            <div>
                <label class="form-label">Fee Head (optional — leave blank to apply to total fee)</label>
                <select v-model="form.fee_head_id" class="form-input">
                    <option :value="null">All Fees</option>
                    <option v-for="h in heads" :key="h.id" :value="h.id">{{ h.name }}</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Type</label>
                    <select v-model="form.type" class="form-input">
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed Amount</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Value</label>
                    <input v-model.number="form.value" type="number" class="form-input" required />
                </div>
            </div>
            <div>
                <label class="form-label">Reason</label>
                <input v-model="form.reason" type="text" class="form-input" placeholder="Sibling discount, staff ward..." />
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
import { fetchFeeLookups, fetchFeeStudentsLite, invalidateFeeLookups } from '../../api/feeManagement';
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const discounts = ref([]);
const students = ref([]);
const heads = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ student_id: null, fee_head_id: null, type: 'percentage', value: null, reason: '' });

const filteredDiscounts = computed(() =>
    discounts.value.filter((d) => {
        if (filterValues.search && !`${d.student.name} ${d.student.admission_no} ${d.reason}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [discountsRes, studentsRes, fee] = await Promise.all([
        client.get('/fee-management/discounts'),
        fetchFeeStudentsLite(),
        fetchFeeLookups(),
    ]);
    discounts.value = discountsRes.data;
    students.value = studentsRes || [];
    heads.value = fee.heads || [];
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { student_id: null, fee_head_id: null, type: 'percentage', value: null, reason: '' });
    drawerOpen.value = true;
}

function openEdit(discount) {
    editing.value = discount;
    Object.assign(form, { student_id: discount.student.id, fee_head_id: discount.fee_head?.id ?? null, type: discount.type, value: Number(discount.value), reason: discount.reason || '' });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/fee-management/discounts/${editing.value.id}`, form);
            pushToast('Discount updated.', 'success');
        } else {
            await client.post('/fee-management/discounts', form);
            pushToast('Discount added.', 'success');
        }
        drawerOpen.value = false;
        invalidateFeeLookups();
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(discount) {
    discounts.value = discounts.value.filter((d) => d.id !== discount.id);
    await client.delete(`/fee-management/discounts/${discount.id}`);
    invalidateFeeLookups();
    pushToast('Discount deleted.', 'success');
}
</script>
