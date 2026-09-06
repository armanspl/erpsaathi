<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Office Expenses</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Track day-to-day school spending with categories and approval status.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="openCategoryTypes">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h6v6h-6v-6z"/></svg>
                    Category types
                </button>
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
                    <span class="text-lg leading-none">+</span> Create Office Expense
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
                        <input v-model="filters.search" type="search" class="form-input" placeholder="Paid to, notes, expense #" />
                    </div>
                    <div>
                        <label class="form-label">Category</label>
                        <select v-model="filters.expense_category_id" class="form-input">
                            <option value="">All categories</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select v-model="filters.status" class="form-input">
                            <option value="">All statuses</option>
                            <option>Pending</option>
                            <option>Approved</option>
                            <option>Rejected</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">From date</label>
                        <input v-model="filters.from" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">To date</label>
                        <input v-model="filters.to" type="date" class="form-input" />
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
            <div v-else-if="!paged.length" class="px-6 py-20 text-center text-sm text-slate-400">No expenses match your filters.</div>

            <div v-else class="overflow-x-auto">
                <table v-if="viewMode === 'table'" class="w-full min-w-[880px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('voucher_no')">Expense # {{ sortArrow('voucher_no') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('category')">Category {{ sortArrow('category') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('amount')">Amount {{ sortArrow('amount') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('date')">Date {{ sortArrow('date') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('paid_to')">Paid to {{ sortArrow('paid_to') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('status')">Status {{ sortArrow('status') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="e in paged" :key="e.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ e.voucher_no }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.expense_category?.name || '—' }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ inr(e.amount) }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.date }}</td>
                            <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ e.paid_to || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusTone(e.status)">{{ e.status }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(e)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                    </button>
                                    <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(e)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- List view -->
                <div v-else-if="viewMode === 'list'" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="e in paged" :key="e.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <div>
                            <div class="font-medium text-slate-800 dark:text-slate-100">{{ e.paid_to || '—' }} <span class="text-xs font-normal text-slate-400">· {{ e.expense_category?.name }}</span></div>
                            <div class="text-xs text-slate-400">{{ e.voucher_no }} · {{ e.date }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ inr(e.amount) }}</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusTone(e.status)">{{ e.status }}</span>
                        </div>
                    </div>
                </div>

                <!-- Grid / cards -->
                <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="e in paged" :key="e.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-semibold text-slate-800 dark:text-slate-100">{{ e.paid_to || '—' }}</div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusTone(e.status)">{{ e.status }}</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-400">{{ e.voucher_no }} · {{ e.expense_category?.name }} · {{ e.date }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ inr(e.amount) }}</p>
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

        <!-- Create / Edit Office Expense modal -->
        <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="formOpen = false" />
            <div class="relative z-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit Office Expense' : 'Create Office Expense' }}</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" @click="formOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Category</label>
                        <select v-model.number="form.expense_category_id" class="form-input">
                            <option :value="null">Select category</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Amount</label>
                        <input v-model.number="form.amount" type="number" min="0" step="0.01" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Expense date</label>
                        <input v-model="form.date" type="date" class="form-input" />
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Paid to</label>
                        <input v-model="form.paid_to" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select v-model="form.status" class="form-input">
                            <option>Pending</option>
                            <option>Approved</option>
                            <option>Rejected</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Notes</label>
                        <textarea v-model="form.notes" rows="3" class="form-input" />
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="formOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : editing ? 'Save' : 'Create expense' }}</button>
                </div>
            </div>
        </div>

        <!-- Category types modal -->
        <div v-if="categoryTypesOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="categoryTypesOpen = false" />
            <div class="relative z-10 flex max-h-[85vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Category types</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Enable or disable expense categories and sub-categories. Transport sub-categories sync from active vehicles.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="categoryTypesOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div>
                        <label class="form-label">Add category type</label>
                        <div class="flex gap-2">
                            <input v-model="newCategoryName" type="text" class="form-input flex-1" placeholder="Category name" @keyup.enter="addCategoryType" />
                            <button type="button" class="btn-primary shrink-0" :disabled="!newCategoryName.trim()" @click="addCategoryType">+ Add</button>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100 rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                        <div v-for="cat in categoryTree" :key="cat.id">
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-slate-800 dark:text-slate-100" :class="!cat.is_active && 'text-slate-400 line-through'">{{ cat.name }}</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ cat.type }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="btn-outline !py-1 !text-xs" @click="toggleCategory(cat)">{{ cat.is_active ? 'Disable' : 'Enable' }}</button>
                                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="toggleExpand(cat.id)">
                                        <svg class="h-4 w-4 transition" :class="expanded.has(cat.id) && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div v-if="expanded.has(cat.id)" class="bg-slate-50 px-4 py-3 dark:bg-slate-800/40">
                                <div v-for="child in cat.children" :key="child.id" class="flex items-center justify-between gap-2 py-1.5 text-sm">
                                    <span class="text-slate-600 dark:text-slate-300">{{ child.name }}</span>
                                    <span v-if="child.synced" class="text-[11px] text-slate-400">synced</span>
                                    <button v-else type="button" class="btn-outline !py-0.5 !text-[11px]" @click="toggleCategory(child)">{{ child.is_active ? 'Disable' : 'Enable' }}</button>
                                </div>
                                <p v-if="!cat.children.length" class="text-xs text-slate-400">No sub-categories yet.</p>
                                <div v-if="cat.type !== 'transport'" class="mt-2 flex gap-2">
                                    <input v-model="newChildName[cat.id]" type="text" class="form-input flex-1 !py-1 !text-xs" placeholder="Sub-category name" @keyup.enter="addSubCategory(cat)" />
                                    <button type="button" class="btn-outline !py-1 !text-xs shrink-0" @click="addSubCategory(cat)">+ Add</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="categoryTypesOpen = false">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';
import { inr } from '../../utils/money';

const viewModes = [
    { id: 'table', label: 'Table', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>' },
    { id: 'list', label: 'List', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>' },
    { id: 'grid', label: 'Grid', icon: '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>' },
];

const filtersOpen = ref(true);
const viewMode = ref('table');
const loading = ref(true);
const saving = ref(false);
const expenses = ref([]);
const categories = ref([]);
const filters = reactive({ search: '', expense_category_id: '', status: '', from: '', to: '' });
const page = ref(1);
const perPage = ref(20);
const sortKey = ref('date');
const sortDir = ref('desc');

const formOpen = ref(false);
const editing = ref(null);
const form = reactive({
    expense_category_id: null,
    amount: '',
    date: new Date().toISOString().slice(0, 10),
    paid_to: '',
    status: 'Pending',
    notes: '',
});

const categoryTypesOpen = ref(false);
const categoryTree = ref([]);
const expanded = ref(new Set());
const newCategoryName = ref('');
const newChildName = reactive({});

function statusTone(status) {
    if (status === 'Approved') return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30';
    if (status === 'Rejected') return 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/30';
    return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30';
}

const filtered = computed(() => {
    let rows = expenses.value;
    if (filters.search.trim()) {
        const term = filters.search.trim().toLowerCase();
        rows = rows.filter((e) => `${e.voucher_no || ''} ${e.paid_to || ''} ${e.notes || ''}`.toLowerCase().includes(term));
    }
    if (filters.expense_category_id) rows = rows.filter((e) => e.expense_category_id === filters.expense_category_id);
    if (filters.status) rows = rows.filter((e) => e.status === filters.status);
    if (filters.from) rows = rows.filter((e) => e.date >= filters.from);
    if (filters.to) rows = rows.filter((e) => e.date <= filters.to);

    return [...rows].sort((a, b) => {
        let av = sortKey.value === 'category' ? (a.expense_category?.name ?? '') : a[sortKey.value] ?? '';
        let bv = sortKey.value === 'category' ? (b.expense_category?.name ?? '') : b[sortKey.value] ?? '';
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
        const [expensesRes, categoriesRes] = await Promise.all([
            client.get('/finance-payroll/expenses'),
            client.get('/finance-payroll/expense-categories'),
        ]);
        expenses.value = expensesRes.data;
        categories.value = categoriesRes.data;
    } finally {
        loading.value = false;
    }
}
load();

function openCreate() {
    editing.value = null;
    Object.assign(form, {
        expense_category_id: null,
        amount: '',
        date: new Date().toISOString().slice(0, 10),
        paid_to: '',
        status: 'Pending',
        notes: '',
    });
    formOpen.value = true;
}

function openEdit(expense) {
    editing.value = expense;
    Object.assign(form, {
        expense_category_id: expense.expense_category_id,
        amount: Number(expense.amount),
        date: String(expense.date).slice(0, 10),
        paid_to: expense.paid_to || '',
        status: expense.status,
        notes: expense.notes || '',
    });
    formOpen.value = true;
}

async function save() {
    if (!form.expense_category_id) {
        pushToast('Select a category.', 'error');
        return;
    }
    if (!form.paid_to.trim()) {
        pushToast('"Paid to" is required.', 'error');
        return;
    }
    saving.value = true;
    try {
        const payload = { ...form, notes: form.notes || null };
        if (editing.value) {
            await client.put(`/finance-payroll/expenses/${editing.value.id}`, payload);
            pushToast('Expense updated.', 'success');
        } else {
            await client.post('/finance-payroll/expenses', payload);
            pushToast('Expense created.', 'success');
        }
        formOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(expense) {
    if (!window.confirm(`Delete expense ${expense.voucher_no}?`)) return;
    expenses.value = expenses.value.filter((e) => e.id !== expense.id);
    await client.delete(`/finance-payroll/expenses/${expense.id}`);
    pushToast('Expense deleted.', 'success');
}

async function openCategoryTypes() {
    categoryTypesOpen.value = true;
    await loadCategoryTree();
}

async function loadCategoryTree() {
    const { data } = await client.get('/finance-payroll/expense-categories/tree');
    categoryTree.value = data;
}

function toggleExpand(id) {
    if (expanded.value.has(id)) expanded.value.delete(id);
    else expanded.value.add(id);
}

async function addCategoryType() {
    if (!newCategoryName.value.trim()) return;
    try {
        await client.post('/finance-payroll/expense-categories', { name: newCategoryName.value.trim() });
        newCategoryName.value = '';
        pushToast('Category type added.', 'success');
        await loadCategoryTree();
        await load();
    } catch {
        /* toast via interceptor */
    }
}

async function addSubCategory(parent) {
    const name = (newChildName[parent.id] || '').trim();
    if (!name) return;
    try {
        await client.post('/finance-payroll/expense-categories', { name, parent_id: parent.id });
        newChildName[parent.id] = '';
        pushToast('Sub-category added.', 'success');
        await loadCategoryTree();
    } catch {
        /* toast via interceptor */
    }
}

async function toggleCategory(cat) {
    await client.patch(`/finance-payroll/expense-categories/${cat.id}/toggle`);
    await loadCategoryTree();
    await load();
}

watch(perPage, () => { page.value = 1; });
watch(totalPages, (n) => { if (page.value > n) page.value = n; });
watch(() => [filters.search, filters.expense_category_id, filters.status, filters.from, filters.to], () => { page.value = 1; });
</script>
