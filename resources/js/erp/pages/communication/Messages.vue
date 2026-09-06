<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ pageTitle }}</h1>
                <Breadcrumb :items="['Dashboard', 'Communication', pageTitle]" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Compose</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="messages" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <StatCard label="Messages Sent" :value="filteredMessages.length" color="indigo" icon="✉️" />
            <StatCard label="Recipients Reached" :value="totalRecipients" color="emerald" icon="🧑‍🤝‍🧑" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th v-if="!routeChannel" class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Channel</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Subject / Body</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Audience</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Recipients</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Sent At</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredMessages.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No messages match your filters.</td>
                    </tr>
                    <tr v-for="m in filteredMessages" :key="m.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td v-if="!routeChannel" class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ m.channel }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ m.subject || '—' }}</p>
                            <p class="max-w-md truncate text-xs text-slate-400">{{ m.body }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ m.audience }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ m.recipient_count }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ m.sent_at ? formatDateTime(m.sent_at) : '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(m.status === 'Sent' ? 'Active' : 'Inactive')">{{ m.status }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="`Compose ${routeChannel || 'Message'}`" @close="drawerOpen = false">
            <div v-if="!routeChannel">
                <label class="form-label">Channel</label>
                <select v-model="form.channel" class="form-input">
                    <option>SMS</option>
                    <option>Email</option>
                    <option>WhatsApp</option>
                    <option>Push</option>
                </select>
            </div>
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
            <div v-if="form.channel === 'Email'">
                <label class="form-label">Subject</label>
                <input v-model="form.subject" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Message</label>
                <textarea v-model="form.body" rows="4" class="form-input" required />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Sending...' : 'Send' }}</button>
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
const routeChannelMap = {
    '/communication/sms': 'SMS',
    '/communication/email': 'Email',
    '/communication/whatsapp': 'WhatsApp',
    '/communication/push-notifications': 'Push',
};
const routeChannel = computed(() => routeChannelMap[route.path] || null);
const pageTitle = computed(() => (route.path === '/communication/push-notifications' ? 'Push Notifications' : routeChannel.value || 'Communication'));

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'audience', label: 'Audience', type: 'select', options: ['All', 'Students', 'Teachers', 'Staff', 'Parents'] },
];

const loading = ref(true);
const saving = ref(false);
const messages = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);

const form = reactive({ channel: routeChannel.value || 'SMS', audience: 'All', subject: '', body: '' });

const filteredMessages = computed(() =>
    messages.value.filter((m) => {
        if (routeChannel.value && m.channel !== routeChannel.value) return false;
        if (filterValues.search && !`${m.subject} ${m.body}`.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.audience && m.audience !== filterValues.audience) return false;
        return true;
    }),
);

const totalRecipients = computed(() => filteredMessages.value.reduce((sum, m) => sum + m.recipient_count, 0));

async function load() {
    loading.value = true;
    const { data } = await client.get('/communication/messages');
    messages.value = data;
    loading.value = false;
}
load();

function openAdd() {
    Object.assign(form, { channel: routeChannel.value || 'SMS', audience: 'All', subject: '', body: '' });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        const { data } = await client.post('/communication/messages', form);
        pushToast(`${data.channel} sent to ${data.recipient_count} recipient(s).`, 'success');
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

function formatDateTime(value) {
    return new Date(value).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
