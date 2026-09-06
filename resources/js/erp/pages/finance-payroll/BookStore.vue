<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Book Store</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage branch and class-wise book catalog with prices.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
                    <span class="text-lg leading-none">+</span> Add book
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
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="filters.branch_id" class="form-input">
                            <option value="">All branches</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="filters.school_class_id" class="form-input" :disabled="!filters.branch_id">
                            <option value="">{{ filters.branch_id ? 'All classes' : 'Select branch first' }}</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select v-model="filters.status" class="form-input">
                            <option value="">All statuses</option>
                            <option>Active</option>
                            <option>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Search</label>
                        <input v-model="filters.search" type="search" class="form-input" placeholder="Search books..." />
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
            <div v-else-if="!paged.length" class="px-6 py-20 text-center text-sm text-slate-400">No books found.</div>

            <div v-else class="overflow-x-auto">
                <table v-if="viewMode === 'table'" class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                        <tr>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('title')">Title {{ sortArrow('title') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Branch</th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Class</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('price')">Price {{ sortArrow('price') }}</th>
                            <th class="cursor-pointer whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500" @click="toggleSort('status')">Status {{ sortArrow('status') }}</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="b in paged" :key="b.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ b.title }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ b.branch?.name || '—' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ b.school_class?.name || '—' }}</td>
                            <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ inr(b.price) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(b.status)">{{ b.status }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(b)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                    </button>
                                    <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(b)">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- List view -->
                <div v-else-if="viewMode === 'list'" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="b in paged" :key="b.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <div>
                            <div class="font-medium text-slate-800 dark:text-slate-100">{{ b.title }}</div>
                            <div class="text-xs text-slate-400">{{ b.branch?.name }} · {{ b.school_class?.name }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ inr(b.price) }}</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(b.status)">{{ b.status }}</span>
                        </div>
                    </div>
                </div>

                <!-- Grid / cards -->
                <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="b in paged" :key="b.id" class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-semibold text-slate-800 dark:text-slate-100">{{ b.title }}</div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(b.status)">{{ b.status }}</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-400">{{ b.branch?.name }} · {{ b.school_class?.name }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ inr(b.price) }}</p>
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

        <!-- Add / Edit book modal -->
        <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="formOpen = false" />
            <div class="relative z-10 w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit book' : 'Add book' }}</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Books are listed by branch and class for book expense assignment.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" @click="formOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="form-label">Title</label>
                        <input v-model="form.title" type="text" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Branch</label>
                        <select v-model="form.branch_id" class="form-input" @change="form.school_class_id = null">
                            <option :value="null">Select branch</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Class</label>
                        <select v-model="form.school_class_id" class="form-input" :disabled="!form.branch_id">
                            <option :value="null">Select class</option>
                            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Price</label>
                        <input v-model.number="form.price" type="number" min="0" step="0.01" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select v-model="form.status" class="form-input">
                            <option>Active</option>
                            <option>Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="formOpen = false">Cancel</button>
                    <button type="button" class="btn-primary inline-flex items-center gap-1.5" :disabled="saving" @click="save">
                        <svg v-if="saving" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 3a9 9 0 100 18"/></svg>
                        {{ saving ? 'Saving...' : editing ? 'Save' : 'Create book' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { fetchAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
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
const books = ref([]);
const branches = ref([]);
const classes = ref([]);
const filters = reactive({ branch_id: '', school_class_id: '', status: '', search: '' });
const page = ref(1);
const perPage = ref(20);
const sortKey = ref('title');
const sortDir = ref('asc');

const formOpen = ref(false);
const editing = ref(null);
const form = reactive({ title: '', branch_id: null, school_class_id: null, price: '', status: 'Active' });

const filtered = computed(() => {
    let rows = books.value;
    if (filters.branch_id) rows = rows.filter((b) => b.branch_id === filters.branch_id);
    if (filters.school_class_id) rows = rows.filter((b) => b.school_class_id === filters.school_class_id);
    if (filters.status) rows = rows.filter((b) => b.status === filters.status);
    if (filters.search.trim()) {
        const term = filters.search.trim().toLowerCase();
        rows = rows.filter((b) => b.title.toLowerCase().includes(term));
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
        const [booksRes, academics] = await Promise.all([
            client.get('/finance-payroll/book-store'),
            fetchAcademicsLookups(),
        ]);
        books.value = booksRes.data;
        branches.value = academics.branches || [];
        classes.value = academics.classes || [];
    } finally {
        loading.value = false;
    }
}
load();

function openCreate() {
    editing.value = null;
    Object.assign(form, { title: '', branch_id: null, school_class_id: null, price: '', status: 'Active' });
    formOpen.value = true;
}

function openEdit(book) {
    editing.value = book;
    Object.assign(form, {
        title: book.title,
        branch_id: book.branch_id,
        school_class_id: book.school_class_id,
        price: Number(book.price),
        status: book.status,
    });
    formOpen.value = true;
}

async function save() {
    if (!form.title.trim()) {
        pushToast('Title is required.', 'error');
        return;
    }
    if (!form.branch_id || !form.school_class_id) {
        pushToast('Select a branch and class.', 'error');
        return;
    }
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/finance-payroll/book-store/${editing.value.id}`, form);
            pushToast('Book updated.', 'success');
        } else {
            await client.post('/finance-payroll/book-store', form);
            pushToast('Book created.', 'success');
        }
        formOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(book) {
    if (!window.confirm(`Delete "${book.title}"?`)) return;
    books.value = books.value.filter((b) => b.id !== book.id);
    await client.delete(`/finance-payroll/book-store/${book.id}`);
    pushToast('Book deleted.', 'success');
}

watch(perPage, () => { page.value = 1; });
watch(totalPages, (n) => { if (page.value > n) page.value = n; });
watch(() => [filters.branch_id, filters.school_class_id, filters.status, filters.search], () => { page.value = 1; });
</script>
