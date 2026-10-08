<template>
    <div>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div v-if="!compact">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Salary history &amp; rollback</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Every imported sheet, slip created or edited by hand, payment and delete is listed here. <b>Rollback</b> undoes it exactly —
                    an import rollback removes the slips it created and restores the ones it changed. If a slip was changed again later
                    (e.g. marked paid), roll that later change back first.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <input v-if="!compact" v-model="search" type="search" class="form-input !w-48 !py-1.5 !text-xs" placeholder="Slip no., staff, sheet..." />
                <select v-model="source" class="form-input !w-auto !py-1.5 !text-xs" @change="load">
                    <option value="">All changes</option>
                    <option value="import">Imports</option>
                    <option value="manual">Manual slips</option>
                    <option value="payment">Payments</option>
                    <option value="generate">Generated</option>
                    <option value="bank">Bank workbook</option>
                    <option value="other">Other</option>
                </select>
                <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="loading" @click="load">{{ loading ? 'Loading...' : 'Refresh' }}</button>
            </div>
        </div>

        <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
            <table class="w-full min-w-[980px] text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-semibold">When</th>
                        <th class="px-3 py-2 font-semibold">Change</th>
                        <th class="px-3 py-2 font-semibold">Slip no.</th>
                        <th class="px-3 py-2 font-semibold">Staff</th>
                        <th class="px-3 py-2 font-semibold">Month</th>
                        <th class="px-3 py-2 text-right font-semibold">Amount</th>
                        <th class="px-3 py-2 font-semibold">By</th>
                        <th class="px-3 py-2 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="!loading && !shown.length">
                        <td colspan="8" class="px-3 py-6 text-center text-slate-400">{{ batches.length ? 'Nothing matches your search.' : 'No salary changes recorded yet.' }}</td>
                    </tr>
                    <template v-for="b in shown" :key="b.batch_id">
                        <tr :class="b.rolled_back ? 'opacity-60' : ''">
                            <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ when(b.created_at) }}</td>
                            <td class="px-3 py-2">
                                <span class="mr-1.5 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase" :class="SOURCE_TONE[b.source] || SOURCE_TONE.other">{{ SOURCE_LABEL[b.source] || b.source }}</span>
                                <span class="text-slate-700 dark:text-slate-200">{{ b.label }}</span>
                                <div class="mt-0.5 text-[11px]">
                                    <span v-if="b.created_count" class="text-emerald-600 dark:text-emerald-400">+{{ b.created_count }} new</span>
                                    <span v-if="b.updated_count" class="ml-1.5 text-sky-600 dark:text-sky-400">{{ b.updated_count }} changed</span>
                                    <span v-if="b.deleted_count" class="ml-1.5 text-rose-600 dark:text-rose-400">{{ b.deleted_count }} deleted</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-slate-600 dark:text-slate-300">
                                <template v-if="b.slip_count === 1">{{ b.slip_nos[0] }}</template>
                                <template v-else-if="b.slip_count > 1">{{ b.slip_nos[0] }} <span class="font-sans text-slate-400">+{{ b.slip_count - 1 }} more</span></template>
                                <template v-else>—</template>
                            </td>
                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">
                                <template v-if="b.staff">{{ b.staff }} <span v-if="b.staff_code" class="text-slate-400">{{ b.staff_code }}</span></template>
                                <span v-else class="text-slate-500">{{ b.staff_count }} staff</span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-slate-600 dark:text-slate-300">{{ monthRange(b) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-semibold text-slate-800 dark:text-slate-100">{{ b.amount ? inr(b.amount) : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ b.performed_by || '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                <button type="button" class="mr-1 text-primary-600 hover:underline dark:text-primary-400" @click="toggle(b)">{{ open[b.batch_id] ? 'Hide' : 'Details' }}</button>
                                <span v-if="b.rolled_back" class="rounded bg-slate-100 px-2 py-1 text-slate-500 dark:bg-slate-800">Rolled back{{ b.rolled_back_by ? ` by ${b.rolled_back_by}` : '' }}</span>
                                <button v-else type="button" class="btn-outline !border-rose-200 !py-1 !text-xs !text-rose-600 hover:!bg-rose-50 dark:!border-rose-500/30 dark:hover:!bg-rose-500/10" :disabled="busy === b.batch_id" @click="rollback(b)">
                                    {{ busy === b.batch_id ? 'Rolling back...' : 'Rollback' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="open[b.batch_id]">
                            <td colspan="8" class="bg-slate-50/70 px-3 py-2 dark:bg-slate-800/30">
                                <p v-if="!details[b.batch_id]" class="text-slate-400">Loading...</p>
                                <ul v-else class="max-h-64 space-y-1 overflow-y-auto">
                                    <li v-for="e in details[b.batch_id]" :key="e.id" class="flex flex-wrap gap-x-2">
                                        <span class="w-16 shrink-0 font-semibold capitalize" :class="ACTION_TONE[e.action]">{{ e.action }}</span>
                                        <span v-if="e.slip_no" class="font-mono text-slate-500">{{ e.slip_no }}</span>
                                        <span class="text-slate-700 dark:text-slate-200">{{ e.employee_name }}</span>
                                        <span class="text-slate-400">{{ e.employee_code }} · {{ periodLabel(e.period) }}</span>
                                        <span v-if="e.action !== 'updated' && e.net_salary != null" class="text-slate-500">net {{ inr(e.net_salary) }}</span>
                                        <span v-for="c in e.changes" :key="c.field" class="text-slate-500">{{ FIELD_LABEL[c.field] || c.field }}: {{ show(c.field, c.from) }} → <b>{{ show(c.field, c.to) }}</b></span>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import client from '../../api/client';
import { inr } from '../../utils/money';
import { pushToast } from '../../utils/toast';

const props = defineProps({
    /** Optional filters: { period, employee_type, employee_id } */
    filters: { type: Object, default: () => ({}) },
    compact: { type: Boolean, default: false },
    /** Bump to reload from outside (e.g. after an import). */
    refreshKey: { type: Number, default: 0 },
});
const emit = defineEmits(['rolled-back']);

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const SOURCE_LABEL = { import: 'Import', manual: 'Manual', payment: 'Payment', generate: 'Generate', bank: 'Bank', other: 'Other' };
const SOURCE_TONE = {
    import: 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    manual: 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
    payment: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    generate: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
    bank: 'bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-300',
    other: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};
const ACTION_TONE = { created: 'text-emerald-600 dark:text-emerald-400', updated: 'text-sky-600 dark:text-sky-400', deleted: 'text-rose-600 dark:text-rose-400' };
const FIELD_LABEL = { basic_salary: 'Basic', days_in_month: 'Days', present: 'Present', absent: 'Absent', cl: 'CL', this_month_salary: 'This month', advance: 'Adv', net_salary: 'Net', status: 'Status', paid_on: 'Paid on', payment_mode: 'Mode' };
const MONEY_FIELDS = ['basic_salary', 'this_month_salary', 'advance', 'net_salary'];

const source = ref('');
const search = ref('');
const loading = ref(false);
const batches = ref([]);

const shown = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return batches.value;
    return batches.value.filter((b) => [b.label, b.staff, b.staff_code, b.performed_by, ...(b.slip_nos || [])]
        .some((v) => String(v || '').toLowerCase().includes(term)));
});

function monthRange(b) {
    if (!b.period_from) return '—';
    return b.period_from === b.period_to ? periodLabel(b.period_from) : `${periodLabel(b.period_from)} – ${periodLabel(b.period_to)}`;
}
const open = reactive({});
const details = reactive({});
const busy = ref(null);

function periodLabel(p) {
    if (!p) return '';
    const [y, m] = String(p).split('-');
    return `${MONTHS[Number(m) - 1] || m} ${y}`;
}
function when(ts) {
    if (!ts) return '—';
    const d = new Date(String(ts).replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? ts : d.toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function show(field, value) {
    if (value === null || value === undefined || value === '') return '—';
    if (MONEY_FIELDS.includes(field)) return inr(value);
    if (field === 'paid_on') return String(value).slice(0, 10);
    return value;
}

async function load() {
    loading.value = true;
    try {
        const params = { ...props.filters };
        if (source.value) params.source = source.value;
        const { data } = await client.get('/finance-payroll/salary-history', { params });
        batches.value = data;
    } catch {
        pushToast('Could not load salary history.', 'error');
    } finally {
        loading.value = false;
    }
}

async function toggle(b) {
    open[b.batch_id] = !open[b.batch_id];
    if (open[b.batch_id] && !details[b.batch_id]) {
        try {
            const { data } = await client.get(`/finance-payroll/salary-history/${b.batch_id}`);
            details[b.batch_id] = data;
        } catch {
            open[b.batch_id] = false;
            pushToast('Could not load details.', 'error');
        }
    }
}

async function rollback(b) {
    const what = b.created_count && !b.updated_count ? `remove ${b.created_count} slip(s)` : `undo ${b.entries} change(s)`;
    if (!window.confirm(`Rollback "${b.label}"?\n\nThis will ${what}.`)) return;
    busy.value = b.batch_id;
    try {
        const { data } = await client.post(`/finance-payroll/salary-history/${b.batch_id}/rollback`);
        pushToast(data.message || 'Rolled back.', 'success');
        delete details[b.batch_id];
        await load();
        emit('rolled-back', b);
    } catch (err) {
        pushToast(err?.response?.status === 403 ? 'You do not have permission to roll back salary changes.' : (err?.response?.data?.message || 'Rollback failed.'), 'error');
    } finally {
        busy.value = null;
    }
}

watch(() => [props.refreshKey, JSON.stringify(props.filters)], load, { immediate: true });
</script>
