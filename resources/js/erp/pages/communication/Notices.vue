<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ pageTitle }}</h1>
                <Breadcrumb :items="['Dashboard', 'Communication', pageTitle]" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ New {{ pageTitle === 'Circulars' ? 'Circular' : 'Notice' }}</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="notices" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Total" :value="filteredNotices.length" color="indigo" icon="📢" />
            <StatCard label="Published" :value="filteredNotices.filter((n) => n.status === 'Published').length" color="emerald" icon="✅" />
            <StatCard label="Draft" :value="filteredNotices.filter((n) => n.status === 'Draft').length" color="amber" icon="📝" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Title</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Audience</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Publish Date</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredNotices.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Nothing matches your filters.</td>
                    </tr>
                    <tr v-for="n in filteredNotices" :key="n.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ n.title }}</p>
                            <p class="max-w-md truncate text-xs text-slate-400">{{ n.content }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ n.audience }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(n.publish_date) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(n.status === 'Published' ? 'Active' : 'Pending')">{{ n.status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(n)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(n)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit' : `New ${pageTitle === 'Circulars' ? 'Circular' : 'Notice'}`" @close="drawerOpen = false">
            <div>
                <label class="form-label">Title</label>
                <input v-model="form.title" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Content</label>
                <textarea v-model="form.content" rows="4" class="form-input" required />
            </div>
            <div v-if="!routeType">
                <label class="form-label">Type</label>
                <select v-model="form.type" class="form-input">
                    <option>Notice</option>
                    <option>Circular</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Audience</label>
                    <select v-model="form.audience" class="form-input">
                        <option>All</option>
                        <option>Students</option>
                        <option>Teachers</option>
                        <option>Staff</option>
                        <option>Parents</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select v-model="form.status" class="form-input">
                        <option>Published</option>
                        <option>Draft</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Publish Date</label>
                    <input v-model="form.publish_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Expiry Date</label>
                    <input v-model="form.expiry_date" type="date" class="form-input" />
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
import { useRoute } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const route = useRoute();
const routeType = computed(() => (route.path === '/communication/circulars' ? 'Circular' : route.path === '/communication/notices' ? 'Notice' : null));
const pageTitle = computed(() => (routeType.value === 'Circular' ? 'Circulars' : 'Notices'));

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Status', type: 'select', options: ['Published', 'Draft'] },
];

const loading = ref(true);
const saving = ref(false);
const notices = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(route.query.add === '1');
const editing = ref(null);

const form = reactive({ title: '', content: '', type: routeType.value || 'Notice', audience: 'All', publish_date: new Date().toISOString().slice(0, 10), expiry_date: '', status: 'Published' });

const filteredNotices = computed(() =>
    notices.value.filter((n) => {
        if (routeType.value && n.type !== routeType.value) return false;
        if (filterValues.search && !`${n.title} ${n.content}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && n.status !== filterValues.status) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const { data } = await client.get('/communication/notices');
    notices.value = data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { title: '', content: '', type: routeType.value || 'Notice', audience: 'All', publish_date: new Date().toISOString().slice(0, 10), expiry_date: '', status: 'Published' });
    drawerOpen.value = true;
}

function openEdit(notice) {
    editing.value = notice;
    Object.assign(form, {
        title: notice.title,
        content: notice.content,
        type: notice.type,
        audience: notice.audience,
        publish_date: notice.publish_date.slice(0, 10),
        expiry_date: notice.expiry_date ? notice.expiry_date.slice(0, 10) : '',
        status: notice.status,
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        const payload = { ...form, expiry_date: form.expiry_date || null };
        if (editing.value) {
            await client.put(`/communication/notices/${editing.value.id}`, payload);
            pushToast('Updated.', 'success');
        } else {
            await client.post('/communication/notices', payload);
            pushToast(`${form.type} published.`, 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(notice) {
    notices.value = notices.value.filter((n) => n.id !== notice.id);
    await client.delete(`/communication/notices/${notice.id}`);
    pushToast(`"${notice.title}" deleted.`, 'success');
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
