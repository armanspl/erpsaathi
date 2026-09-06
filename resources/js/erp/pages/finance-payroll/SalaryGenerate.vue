<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Salary Generate</h1>
                <Breadcrumb :items="['Dashboard', 'Finance & Payroll', 'Salary Generate']" class="mt-1" />
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <label class="form-label">Period</label>
                    <input v-model="period" type="month" class="form-input" />
                </div>
                <button type="button" class="btn-primary" :disabled="generating" @click="generate">{{ generating ? 'Generating...' : `⚙️ Generate Slips for ${period}` }}</button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard label="Employees" :value="rows.length" color="indigo" icon="👥" />
            <StatCard label="With Structure" :value="rows.filter((r) => r.has_structure).length" color="emerald" icon="✅" />
            <StatCard label="Teachers" :value="rows.filter((r) => r.employee_type === 'teacher').length" color="sky" icon="🎓" />
            <StatCard label="Staff" :value="rows.filter((r) => r.employee_type === 'staff').length" color="amber" icon="🧑‍💼" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Basic</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Allowances</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Deductions</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Net</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!rows.length">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">No teachers or staff found.</td>
                    </tr>
                    <tr v-for="r in rows" :key="`${r.employee_type}-${r.employee_id}`" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ r.name }}</p>
                            <p class="text-xs text-slate-400">{{ r.employee_code }}<span v-if="r.meta"> · {{ r.meta }}</span></p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400 capitalize">{{ r.employee_type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">₹{{ r.basic_salary.toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">₹{{ r.allowances.toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">₹{{ r.deductions.toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">₹{{ (r.basic_salary + r.allowances - r.deductions).toLocaleString('en-IN') }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="btn-outline !py-1 !text-xs" @click="openEdit(r)">{{ r.has_structure ? 'Edit' : 'Set Structure' }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="`Salary Structure — ${editing?.name}`" @close="drawerOpen = false">
            <div class="grid grid-cols-1 gap-3">
                <div>
                    <label class="form-label">Basic Salary</label>
                    <input v-model.number="form.basic_salary" type="number" step="0.01" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Allowances</label>
                    <input v-model.number="form.allowances" type="number" step="0.01" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Deductions</label>
                    <input v-model.number="form.deductions" type="number" step="0.01" class="form-input" />
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
import { reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const generating = ref(false);
const rows = ref([]);
const period = ref(new Date().toISOString().slice(0, 7));
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ basic_salary: 0, allowances: 0, deductions: 0 });

async function load() {
    loading.value = true;
    const { data } = await client.get('/finance-payroll/salary-structures');
    rows.value = data;
    loading.value = false;
}
load();

function openEdit(row) {
    editing.value = row;
    Object.assign(form, { basic_salary: row.basic_salary, allowances: row.allowances, deductions: row.deductions });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        await client.post('/finance-payroll/salary-structures', {
            employee_type: editing.value.employee_type,
            employee_id: editing.value.employee_id,
            ...form,
        });
        pushToast('Salary structure saved.', 'success');
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function generate() {
    generating.value = true;
    try {
        const { data } = await client.post('/finance-payroll/salary-slips/generate', { period: period.value });
        pushToast(`Generated ${data.generated} salary slip(s) for ${period.value}.`, 'success');
    } finally {
        generating.value = false;
    }
}
</script>
