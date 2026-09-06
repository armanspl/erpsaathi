<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Salary Slips</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Generate monthly salary slips for teaching and non-teaching staff.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
                    <span class="text-lg leading-none">+</span> Create Salary Slip
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <button type="button" class="flex w-full items-center justify-between px-5 py-3.5 text-left" @click="filtersOpen = !filtersOpen">
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"/></svg>
                    Filters
                </span>
                <svg class="h-4 w-4 text-slate-400 transition" :class="filtersOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div v-show="filtersOpen" class="border-t border-slate-100 px-5 pb-5 pt-4 dark:border-slate-800">
                <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    <div>
                        <label class="form-label">Search</label>
                        <input v-model="filters.search" type="search" class="form-input" placeholder="Slip #, staff name" />
                    </div>
                    <div>
                        <label class="form-label">Month</label>
                        <select v-model="filters.month" class="form-input">
                            <option value="">All months</option>
                            <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Year</label>
                        <select v-model="filters.year" class="form-input">
                            <option value="">All years</option>
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Staff type</label>
                        <select v-model="filters.employee_type" class="form-input">
                            <option value="">All staff types</option>
                            <option value="teacher">Teaching</option>
                            <option value="staff">Non-Teaching</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select v-model="filters.status" class="form-input">
                            <option value="">All statuses</option>
                            <option>Draft</option>
                            <option>Pending</option>
                            <option>Paid</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-end gap-1 border-b border-slate-100 px-4 py-2 dark:border-slate-800">
                <button
                    v-for="mode in viewModes"
                    :key="mode.id"
                    type="button"
                    class="rounded-md p-1.5 transition"
                    :class="viewMode === mode.id ? 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100' : 'text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'"
                    :title="mode.label"
                    @click="viewMode = mode.id"
                >
                    <span v-html="mode.icon" />
                </button>
            </div>

            <div v-if="loading" class="px-6 py-20 text-center text-sm text-slate-400">Loading...</div>
            <div v-else-if="!paged.length" class="px-6 py-20 text-center text-sm text-slate-400">No salary slips found.</div>

            <div v-else class="overflow-x-auto">
                <table v-if="viewMode === 'table'" class="w-full min-w-[880px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('slip_no')">Slip # {{ sortArrow('slip_no') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('employee_name')">Staff {{ sortArrow('employee_name') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Period</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('net_salary')">Net pay {{ sortArrow('net_salary') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('status')">Status {{ sortArrow('status') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="s in paged" :key="s.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ s.slip_no || '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-slate-100">{{ s.employee_name }}</div>
                                <div class="text-xs capitalize text-slate-400">{{ s.employee_type }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ periodLabel(s.period) }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ inr(s.net_salary) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusTone(s.status)">{{ s.status }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button v-if="s.status === 'Pending'" type="button" class="btn-outline !py-1 !text-xs" @click="openPay(s)">Mark Paid</button>
                                    <button type="button" title="Download PDF" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="downloadPdf(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    </button>
                                    <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                    </button>
                                    <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(s)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- List view -->
                <div v-else-if="viewMode === 'list'" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="s in paged" :key="s.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <div>
                            <div class="font-medium text-slate-800 dark:text-slate-100">{{ s.employee_name }} <span class="text-xs font-normal text-slate-400">· {{ periodLabel(s.period) }}</span></div>
                            <div class="text-xs text-slate-400">{{ s.slip_no }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ inr(s.net_salary) }}</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusTone(s.status)">{{ s.status }}</span>
                        </div>
                    </div>
                </div>

                <!-- Grid / cards -->
                <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="s in paged" :key="s.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-semibold text-slate-800 dark:text-slate-100">{{ s.employee_name }}</div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusTone(s.status)">{{ s.status }}</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-400">{{ s.slip_no }} · {{ periodLabel(s.period) }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ inr(s.net_salary) }}</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <p class="text-xs text-slate-400">Showing {{ showingFrom }}–{{ showingTo }} of {{ filtered.length }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    <select v-model.number="perPage" class="form-input !w-auto !py-1.5 !text-xs">
                        <option :value="10">10 / page</option>
                        <option :value="20">20 / page</option>
                        <option :value="50">50 / page</option>
                    </select>
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <button type="button" class="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700" :disabled="page <= 1" @click="page -= 1">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <span>Page {{ page }} of {{ totalPages }}</span>
                        <button type="button" class="rounded-md border border-slate-200 p-1 disabled:opacity-40 dark:border-slate-700" :disabled="page >= totalPages" @click="page += 1">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Salary Slip modal -->
        <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="formOpen = false" />
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit Salary Slip' : 'Create Salary Slip' }}</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="formOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Staff type</label>
                            <select v-model="form.employee_type" class="form-input" @change="onStaffTypeChange">
                                <option value="teacher">Teaching</option>
                                <option value="staff">Non-Teaching</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Staff member</label>
                            <select v-model="form.employeeKey" class="form-input">
                                <option :value="null">Select staff member</option>
                                <option v-for="p in staffOptions" :key="p.key" :value="p.key">{{ p.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Month</label>
                            <select v-model="form.month" class="form-input">
                                <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Year</label>
                            <select v-model="form.year" class="form-input">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Earnings</h3>
                            <button type="button" class="btn-outline !py-1 !text-xs" @click="form.earnings.push({ label: '', amount: 0 })">+ Add row</button>
                        </div>
                        <div class="mt-3 space-y-2">
                            <div v-for="(row, i) in form.earnings" :key="i" class="flex items-center gap-2">
                                <input v-model="row.label" type="text" class="form-input flex-1" placeholder="Label (e.g. Basic, HRA)" />
                                <input v-model.number="row.amount" type="number" min="0" step="0.01" class="form-input w-32" />
                                <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" @click="form.earnings.splice(i, 1)">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Gross: {{ inr(grossTotal) }}</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Deductions</h3>
                            <button type="button" class="btn-outline !py-1 !text-xs" @click="form.deduction_items.push({ label: '', amount: 0 })">+ Add row</button>
                        </div>
                        <div class="mt-3 space-y-2">
                            <div v-for="(row, i) in form.deduction_items" :key="i" class="flex items-center gap-2">
                                <input v-model="row.label" type="text" class="form-input flex-1" placeholder="Label (e.g. PF, TDS)" />
                                <input v-model.number="row.amount" type="number" min="0" step="0.01" class="form-input w-32" />
                                <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" @click="form.deduction_items.splice(i, 1)">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                            <p v-if="!form.deduction_items.length" class="text-sm text-slate-400">No deductions added.</p>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Deductions: {{ inr(deductionsTotal) }}</p>
                    </div>

                    <div class="rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800/60">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Net salary: {{ inr(netTotal) }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Payment mode</label>
                            <select v-model="form.payment_mode" class="form-input">
                                <option value="Bank">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select v-model="form.status" class="form-input">
                                <option>Draft</option>
                                <option>Pending</option>
                                <option>Paid</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Remarks</label>
                        <textarea v-model="form.remarks" rows="3" class="form-input" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="formOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : editing ? 'Save' : 'Create salary slip' }}</button>
                </div>
            </div>
        </div>

        <!-- Mark paid modal -->
        <div v-if="payOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="payOpen = false" />
            <div class="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Mark Paid — {{ paying?.employee_name }}</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="form-label">Payment Mode</label>
                        <select v-model="payForm.payment_mode" class="form-input">
                            <option>Cash</option>
                            <option>Bank</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Paid On</label>
                        <input v-model="payForm.paid_on" type="date" class="form-input" />
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn-outline" @click="payOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="markPaid">{{ saving ? 'Saving...' : 'Confirm Payment' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { fetchPeopleLookups } from '../../api/people';
import client from '../../api/client';
import { downloadPdf as fetchAndOpenPdf } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';
import { inr } from '../../utils/money';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const viewModes = [
    { id: 'table', label: 'Table', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>' },
    { id: 'list', label: 'List', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>' },
    { id: 'grid', label: 'Grid', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>' },
];

const currentYear = new Date().getFullYear();
const years = [currentYear - 1, currentYear, currentYear + 1];

const filtersOpen = ref(true);
const viewMode = ref('table');
const loading = ref(true);
const saving = ref(false);
const slips = ref([]);
const teachers = ref([]);
const staff = ref([]);
const drivers = ref([]);
const filters = reactive({ search: '', month: '', year: '', employee_type: '', status: '' });
const page = ref(1);
const perPage = ref(20);
const sortKey = ref('period');
const sortDir = ref('desc');

const formOpen = ref(false);
const editing = ref(null);
const form = reactive({
    employee_type: 'teacher',
    employeeKey: null,
    month: String(new Date().getMonth() + 1).padStart(2, '0'),
    year: currentYear,
    earnings: [{ label: 'Basic', amount: 0 }],
    deduction_items: [],
    payment_mode: 'Bank',
    status: 'Draft',
    remarks: '',
});

const payOpen = ref(false);
const paying = ref(null);
const payForm = reactive({ payment_mode: 'Bank', paid_on: new Date().toISOString().slice(0, 10) });

const staffOptions = computed(() => {
    if (form.employee_type === 'teacher') {
        return teachers.value.map((t) => ({ key: `teacher:${t.id}`, name: t.name }));
    }
    return [
        ...staff.value.map((s) => ({ key: `staff:${s.id}`, name: s.name })),
        ...drivers.value.map((d) => ({ key: `driver:${d.id}`, name: `${d.name} (Driver)` })),
    ];
});
const grossTotal = computed(() => form.earnings.reduce((sum, r) => sum + (Number(r.amount) || 0), 0));
const deductionsTotal = computed(() => form.deduction_items.reduce((sum, r) => sum + (Number(r.amount) || 0), 0));
const netTotal = computed(() => grossTotal.value - deductionsTotal.value);

function periodLabel(period) {
    if (!period) return '—';
    const [y, m] = period.split('-');
    return `${MONTHS[Number(m) - 1] || m} ${y}`;
}

function statusTone(status) {
    if (status === 'Paid') return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30';
    if (status === 'Pending') return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30';
    return 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-700/40 dark:text-slate-300 dark:ring-slate-600';
}

const filtered = computed(() => {
    let rows = slips.value;
    if (filters.search.trim()) {
        const term = filters.search.trim().toLowerCase();
        rows = rows.filter((s) => `${s.slip_no || ''} ${s.employee_name || ''}`.toLowerCase().includes(term));
    }
    if (filters.month) rows = rows.filter((s) => String(s.period || '').slice(5, 7) === filters.month);
    if (filters.year) rows = rows.filter((s) => String(s.period || '').slice(0, 4) === String(filters.year));
    if (filters.employee_type === 'teacher') rows = rows.filter((s) => s.employee_type === 'teacher');
    else if (filters.employee_type === 'staff') rows = rows.filter((s) => s.employee_type === 'staff' || s.employee_type === 'driver');
    if (filters.status) rows = rows.filter((s) => s.status === filters.status);

    return [...rows].sort((a, b) => {
        let av = a[sortKey.value] ?? '';
        let bv = b[sortKey.value] ?? '';
        if (typeof av === 'string') av = av.toLowerCase();
        if (typeof bv === 'string') bv = bv.toLowerCase();
        if (av < bv) return sortDir.value === 'asc' ? -1 : 1;
        if (av > bv) return sortDir.value === 'asc' ? 1 : -1;
        return 0;
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)));
const paged = computed(() => {
    const start = (page.value - 1) * perPage.value;
    return filtered.value.slice(start, start + perPage.value);
});
const showingFrom = computed(() => (filtered.value.length ? (page.value - 1) * perPage.value + 1 : 0));
const showingTo = computed(() => Math.min(page.value * perPage.value, filtered.value.length));

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
    return sortDir.value === 'asc' ? '\u2191' : '\u2193';
}

async function load() {
    loading.value = true;
    try {
        const [slipsRes, lookups] = await Promise.all([
            client.get('/finance-payroll/salary-slips'),
            fetchPeopleLookups(),
        ]);
        slips.value = slipsRes.data;
        teachers.value = lookups.teachers || [];
        staff.value = lookups.staff || [];
        drivers.value = lookups.drivers || [];
    } finally {
        loading.value = false;
    }
}
load();

function onStaffTypeChange() {
    form.employeeKey = null;
}

function openCreate() {
    editing.value = null;
    Object.assign(form, {
        employee_type: 'teacher',
        employeeKey: null,
        month: String(new Date().getMonth() + 1).padStart(2, '0'),
        year: currentYear,
        earnings: [{ label: 'Basic', amount: 0 }],
        deduction_items: [],
        payment_mode: 'Bank',
        status: 'Draft',
        remarks: '',
    });
    formOpen.value = true;
}

function openEdit(slip) {
    editing.value = slip;
    const [y, m] = String(slip.period).split('-');
    Object.assign(form, {
        employee_type: slip.employee_type === 'teacher' ? 'teacher' : 'staff',
        employeeKey: `${slip.employee_type}:${slip.employee_id}`,
        month: m,
        year: Number(y),
        earnings: (slip.earnings && slip.earnings.length) ? slip.earnings.map((r) => ({ ...r })) : [{ label: 'Basic', amount: Number(slip.basic_salary) || 0 }],
        deduction_items: (slip.deduction_items || []).map((r) => ({ ...r })),
        payment_mode: slip.payment_mode || 'Bank',
        status: slip.status,
        remarks: slip.remarks || '',
    });
    formOpen.value = true;
}

async function save() {
    if (!form.employeeKey) {
        pushToast('Select a staff member.', 'error');
        return;
    }
    saving.value = true;
    try {
        const [realType, realId] = form.employeeKey.split(':');
        const payload = {
            employee_type: realType,
            employee_id: Number(realId),
            period: `${form.year}-${form.month}`,
            earnings: form.earnings.filter((r) => r.label.trim()),
            deduction_items: form.deduction_items.filter((r) => r.label.trim()),
            payment_mode: form.payment_mode,
            status: form.status,
            remarks: form.remarks || null,
        };
        if (editing.value) {
            await client.put(`/finance-payroll/salary-slips/${editing.value.id}`, payload);
            pushToast('Salary slip updated.', 'success');
        } else {
            await client.post('/finance-payroll/salary-slips', payload);
            pushToast('Salary slip created.', 'success');
        }
        formOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(slip) {
    if (!window.confirm(`Delete salary slip ${slip.slip_no}?`)) return;
    slips.value = slips.value.filter((s) => s.id !== slip.id);
    await client.delete(`/finance-payroll/salary-slips/${slip.id}`);
    pushToast('Salary slip deleted.', 'success');
}

function openPay(slip) {
    paying.value = slip;
    Object.assign(payForm, { payment_mode: 'Bank', paid_on: new Date().toISOString().slice(0, 10) });
    payOpen.value = true;
}

async function markPaid() {
    saving.value = true;
    try {
        await client.patch(`/finance-payroll/salary-slips/${paying.value.id}/pay`, payForm);
        pushToast(`Salary marked paid for ${paying.value.employee_name}.`, 'success');
        payOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function downloadPdf(slip) {
    try {
        await fetchAndOpenPdf(
            `/finance-payroll/salary-slips/${slip.id}/pdf`,
            `${slip.slip_no || 'salary-slip'}.pdf`,
        );
    } catch {
        pushToast('Could not open salary slip PDF.', 'error');
    }
}

watch(perPage, () => { page.value = 1; });
watch(totalPages, (n) => { if (page.value > n) page.value = n; });
watch(() => [filters.search, filters.month, filters.year, filters.employee_type, filters.status], () => { page.value = 1; });
</script>
