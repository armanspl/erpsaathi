<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Books</h1>
                <Breadcrumb :items="['Dashboard', 'Library', 'Books']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Book</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="books" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Titles" :value="books.length" color="indigo" icon="📚" />
            <StatCard label="Total Copies" :value="totalCopies" color="sky" icon="📦" />
            <StatCard label="Available Now" :value="totalAvailable" color="emerald" icon="✅" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Title</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Author</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Category</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Rack</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Available</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredBooks.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No books match your filters.</td>
                    </tr>
                    <tr v-for="b in filteredBooks" :key="b.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ b.title }}</p>
                            <p class="text-xs text-slate-400">{{ b.isbn || '—' }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ b.author.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ b.category.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ b.rack_no || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium" :class="b.available_copies > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">{{ b.available_copies }}/{{ b.total_copies }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(b)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(b)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Book' : 'Add Book'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Title</label>
                <input v-model="form.title" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">ISBN</label>
                <input v-model="form.isbn" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Category</label>
                <select v-model="form.book_category_id" class="form-input">
                    <option :value="null">Select category</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Author</label>
                <select v-model="form.author_id" class="form-input">
                    <option :value="null">Select author</option>
                    <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Publisher</label>
                <select v-model="form.publisher_id" class="form-input">
                    <option :value="null">Select publisher</option>
                    <option v-for="p in publishers" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Total Copies</label>
                    <input v-model.number="form.total_copies" type="number" min="1" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Rack No.</label>
                    <input v-model="form.rack_no" type="text" class="form-input" />
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
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const filters = [{ key: 'search', label: 'Search', type: 'search' }];

const loading = ref(true);
const saving = ref(false);
const books = ref([]);
const categories = ref([]);
const authors = ref([]);
const publishers = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ title: '', isbn: '', book_category_id: null, author_id: null, publisher_id: null, total_copies: 1, rack_no: '' });

const totalCopies = computed(() => books.value.reduce((sum, b) => sum + b.total_copies, 0));
const totalAvailable = computed(() => books.value.reduce((sum, b) => sum + b.available_copies, 0));

const filteredBooks = computed(() =>
    books.value.filter((b) => {
        if (filterValues.search && !`${b.title} ${b.isbn} ${b.author.name}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [booksRes, categoriesRes, authorsRes, publishersRes] = await Promise.all([
        client.get('/library/books'),
        client.get('/library/categories'),
        client.get('/library/authors'),
        client.get('/library/publishers'),
    ]);
    books.value = booksRes.data;
    categories.value = categoriesRes.data;
    authors.value = authorsRes.data;
    publishers.value = publishersRes.data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { title: '', isbn: '', book_category_id: null, author_id: null, publisher_id: null, total_copies: 1, rack_no: '' });
    drawerOpen.value = true;
}

function openEdit(book) {
    editing.value = book;
    Object.assign(form, {
        title: book.title,
        isbn: book.isbn || '',
        book_category_id: book.book_category_id,
        author_id: book.author_id,
        publisher_id: book.publisher_id,
        total_copies: book.total_copies,
        rack_no: book.rack_no || '',
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/library/books/${editing.value.id}`, form);
            pushToast('Book updated.', 'success');
        } else {
            await client.post('/library/books', form);
            pushToast('Book added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(book) {
    books.value = books.value.filter((b) => b.id !== book.id);
    await client.delete(`/library/books/${book.id}`);
    pushToast(`Book "${book.title}" deleted.`, 'success');
}
</script>
