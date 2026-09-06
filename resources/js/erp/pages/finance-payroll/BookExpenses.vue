<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Book Expenses</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Issue books to students and download expense receipts.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
                    <span class="text-lg leading-none">+</span> Create book expense
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
                <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="filters.branch_id" class="form-input">
                            <option value="">All branches</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="filters.school_class_id" class="form-input" :disabled="!filters.branch_id" @change="filters.section_id = ''">
                            <option value="">{{ filters.branch_id ? 'All classes' : 'Select branch first' }}</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <select v-model="filters.section_id" class="form-input" :disabled="!filters.school_class_id">
                            <option value="">{{ filters.school_class_id ? 'All sections' : 'Select class first' }}</option>
                            <option v-for="s in sectionsForClass(filters.school_class_id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">From</label>
                        <input v-model="filters.from" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">To</label>
                        <input v-model="filters.to" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Search</label>
                        <input v-model="filters.search" type="search" class="form-input" placeholder="Search expense or student..." />
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
            <div v-else-if="!paged.length" class="px-6 py-20 text-center text-sm text-slate-400">No book expenses found.</div>

            <div v-else class="overflow-x-auto">
                <table v-if="viewMode === 'table'" class="w-full min-w-[920px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('expense_no')">Expense # {{ sortArrow('expense_no') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Student</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Class / Section</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('total_amount')">Amount {{ sortArrow('total_amount') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('date')">Date {{ sortArrow('date') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="e in paged" :key="e.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ e.expense_no }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800 dark:text-slate-100">{{ e.student?.name || '—' }}</div>
                                <div class="text-xs text-slate-400">{{ e.student?.admission_no }}</div>
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ e.school_class?.name || '—' }}{{ e.section ? ` - ${e.section.name}` : '' }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ inr(e.total_amount) }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ String(e.date).slice(0, 10) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" title="Download receipt" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="downloadPdf(e)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                    </button>
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
                            <div class="font-medium text-slate-800 dark:text-slate-100">{{ e.student?.name }} <span class="text-xs font-normal text-slate-400">· {{ e.expense_no }}</span></div>
                            <div class="text-xs text-slate-400">{{ e.school_class?.name }}{{ e.section ? ` - ${e.section.name}` : '' }} · {{ String(e.date).slice(0, 10) }}</div>
                        </div>
                        <span class="font-semibold text-slate-800 dark:text-slate-100">{{ inr(e.total_amount) }}</span>
                    </div>
                </div>

                <!-- Grid / cards -->
                <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="e in paged" :key="e.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="font-semibold text-slate-800 dark:text-slate-100">{{ e.student?.name }}</div>
                        <p class="mt-2 text-xs text-slate-400">{{ e.expense_no }} · {{ e.school_class?.name }}{{ e.section ? ` - ${e.section.name}` : '' }}</p>
                        <p class="text-xs text-slate-400">{{ String(e.date).slice(0, 10) }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ inr(e.total_amount) }}</p>
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

        <!-- Create / Edit Book Expense modal -->
        <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="formOpen = false" />
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit book expense' : 'Create book expense' }}</h2>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="formOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="form-label">Branch</label>
                            <select v-model="picker.branch_id" class="form-input" @change="picker.school_class_id = ''; picker.section_id = ''">
                                <option value="">Select branch</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Class</label>
                            <select v-model="picker.school_class_id" class="form-input" :disabled="!picker.branch_id" @change="picker.section_id = ''">
                                <option value="">Select class</option>
                                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Section</label>
                            <select v-model="picker.section_id" class="form-input" :disabled="!picker.school_class_id">
                                <option value="">Select section</option>
                                <option v-for="s in sectionsForClass(picker.school_class_id)" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="!form.student">
                        <label class="form-label">Search student</label>
                        <input v-model="studentSearch" type="search" class="form-input" placeholder="Name, admission no, or roll no" />
                        <div class="mt-2 max-h-40 overflow-y-auto rounded-lg border border-slate-200 dark:border-slate-700">
                            <button
                                v-for="s in studentResults"
                                :key="s.id"
                                type="button"
                                class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
                                @click="selectStudent(s)"
                            >
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ s.name }}</span>
                                <span class="text-xs text-slate-400">{{ s.admission_no }}{{ s.roll_no ? ` · Roll ${s.roll_no}` : '' }}</span>
                            </button>
                            <p v-if="!studentResults.length" class="px-3 py-4 text-center text-sm text-slate-400">No students found.</p>
                        </div>
                    </div>
                    <div v-else class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800/60">
                        <span>
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ form.student.name }}</span>
                            <span class="ml-1 text-xs text-slate-400">{{ form.student.admission_no }}</span>
                        </span>
                        <button type="button" class="text-xs font-medium text-primary-600 hover:underline" @click="clearStudent">Change</button>
                    </div>

                    <div v-if="form.student">
                        <label class="form-label">Books ({{ bookOptions.length }} available for this class)</label>
                        <div class="max-h-44 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2 dark:border-slate-700">
                            <label v-for="b in bookOptions" :key="b.id" class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                                <span class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" :value="b.id" v-model="form.item_ids" />
                                    {{ b.title }}
                                </span>
                                <span class="text-slate-500">{{ inr(b.price) }}</span>
                            </label>
                            <p v-if="!bookOptions.length" class="px-2 py-3 text-center text-xs text-slate-400">No books in the catalog for this student's branch/class.</p>
                        </div>
                        <p class="mt-2 text-xs font-semibold text-slate-600 dark:text-slate-300">Total: {{ inr(totalAmount) }}</p>
                    </div>

                    <div>
                        <label class="form-label">Expense date</label>
                        <input v-model="form.date" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Notes</label>
                        <textarea v-model="form.notes" rows="3" class="form-input" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="formOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : editing ? 'Save' : 'Create expense' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { fetchAcademicsLookups } from '../../api/academics';
import { fetchStudentsLite } from '../../api/people';
import client from '../../api/client';
import { downloadPdf as fetchAndOpenPdf } from '../../utils/documentPdf';
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
const branches = ref([]);
const classes = ref([]);
const sections = ref([]);
const allStudents = ref([]);
const books = ref([]);
const filters = reactive({ branch_id: '', school_class_id: '', section_id: '', from: '', to: '', search: '' });
const page = ref(1);
const perPage = ref(20);
const sortKey = ref('date');
const sortDir = ref('desc');

const formOpen = ref(false);
const editing = ref(null);
const picker = reactive({ branch_id: '', school_class_id: '', section_id: '' });
const studentSearch = ref('');
const form = reactive({
    student: null,
    date: new Date().toISOString().slice(0, 10),
    notes: '',
    item_ids: [],
});

function sectionsForClass(classId) {
    return sections.value.filter((s) => s.school_class_id === classId);
}

const studentResults = computed(() => {
    let rows = allStudents.value;
    if (picker.branch_id) rows = rows.filter((s) => s.branch_id === picker.branch_id);
    if (picker.school_class_id) rows = rows.filter((s) => s.school_class_id === picker.school_class_id);
    if (picker.section_id) rows = rows.filter((s) => s.section_id === picker.section_id);
    const term = studentSearch.value.trim().toLowerCase();
    if (term) {
        rows = rows.filter((s) => `${s.name} ${s.admission_no || ''} ${s.roll_no || ''}`.toLowerCase().includes(term));
    }
    return rows.slice(0, 20);
});

const bookOptions = computed(() => {
    if (!form.student) return [];
    return books.value.filter((b) => b.branch_id === form.student.branch_id && b.school_class_id === form.student.school_class_id && b.status === 'Active');
});

const totalAmount = computed(() => bookOptions.value.filter((b) => form.item_ids.includes(b.id)).reduce((sum, b) => sum + Number(b.price), 0));

const filtered = computed(() => {
    let rows = expenses.value;
    if (filters.branch_id) rows = rows.filter((e) => e.branch_id === filters.branch_id);
    if (filters.school_class_id) rows = rows.filter((e) => e.school_class_id === filters.school_class_id);
    if (filters.section_id) rows = rows.filter((e) => e.section_id === filters.section_id);
    if (filters.from) rows = rows.filter((e) => String(e.date).slice(0, 10) >= filters.from);
    if (filters.to) rows = rows.filter((e) => String(e.date).slice(0, 10) <= filters.to);
    if (filters.search.trim()) {
        const term = filters.search.trim().toLowerCase();
        rows = rows.filter((e) => `${e.expense_no || ''} ${e.student?.name || ''} ${e.student?.admission_no || ''}`.toLowerCase().includes(term));
    }

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
        const [expensesRes, academics, studentsRes, booksRes] = await Promise.all([
            client.get('/finance-payroll/book-expenses'),
            fetchAcademicsLookups(),
            fetchStudentsLite(),
            client.get('/finance-payroll/book-store'),
        ]);
        expenses.value = expensesRes.data;
        branches.value = academics.branches || [];
        classes.value = academics.classes || [];
        sections.value = academics.sections || [];
        allStudents.value = studentsRes || [];
        books.value = booksRes.data;
    } finally {
        loading.value = false;
    }
}
load();

function openCreate() {
    editing.value = null;
    Object.assign(picker, { branch_id: '', school_class_id: '', section_id: '' });
    studentSearch.value = '';
    Object.assign(form, { student: null, date: new Date().toISOString().slice(0, 10), notes: '', item_ids: [] });
    formOpen.value = true;
}

function openEdit(expense) {
    editing.value = expense;
    Object.assign(picker, { branch_id: expense.branch_id || '', school_class_id: expense.school_class_id || '', section_id: expense.section_id || '' });
    studentSearch.value = '';
    Object.assign(form, {
        student: expense.student
            ? { id: expense.student_id, name: expense.student.name, admission_no: expense.student.admission_no, branch_id: expense.branch_id, school_class_id: expense.school_class_id, section_id: expense.section_id }
            : null,
        date: String(expense.date).slice(0, 10),
        notes: expense.notes || '',
        item_ids: (expense.items || []).map((i) => i.store_book_id).filter(Boolean),
    });
    formOpen.value = true;
}

function selectStudent(student) {
    form.student = student;
    form.item_ids = [];
}

function clearStudent() {
    form.student = null;
    form.item_ids = [];
}

async function save() {
    if (!form.student) {
        pushToast('Select a student.', 'error');
        return;
    }
    if (!form.item_ids.length) {
        pushToast('Select at least one book.', 'error');
        return;
    }
    saving.value = true;
    try {
        const payload = {
            student_id: form.student.id,
            date: form.date,
            notes: form.notes || null,
            item_ids: form.item_ids,
        };
        if (editing.value) {
            await client.put(`/finance-payroll/book-expenses/${editing.value.id}`, payload);
            pushToast('Book expense updated.', 'success');
        } else {
            await client.post('/finance-payroll/book-expenses', payload);
            pushToast('Book expense created.', 'success');
        }
        formOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(expense) {
    if (!window.confirm(`Delete book expense ${expense.expense_no}?`)) return;
    expenses.value = expenses.value.filter((e) => e.id !== expense.id);
    await client.delete(`/finance-payroll/book-expenses/${expense.id}`);
    pushToast('Book expense deleted.', 'success');
}

async function downloadPdf(expense) {
    try {
        await fetchAndOpenPdf(
            `/finance-payroll/book-expenses/${expense.id}/pdf`,
            `${expense.expense_no || 'book-expense'}.pdf`,
        );
    } catch {
        pushToast('Could not open book expense PDF.', 'error');
    }
}

watch(perPage, () => { page.value = 1; });
watch(totalPages, (n) => { if (page.value > n) page.value = n; });
watch(() => [filters.branch_id, filters.school_class_id, filters.section_id, filters.from, filters.to, filters.search], () => { page.value = 1; });
</script>
