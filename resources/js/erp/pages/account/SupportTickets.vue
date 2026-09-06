<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Support Tickets</h1>
                <Breadcrumb :items="['Dashboard', 'Account', 'Support Tickets']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ New Ticket</button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Subject</th>
                        <th class="px-4 py-3">Priority</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Replies</th>
                        <th class="px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!tickets.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No support tickets raised yet.</td>
                    </tr>
                    <tr
                        v-for="t in tickets"
                        :key="t.id"
                        class="cursor-pointer border-t border-slate-100 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"
                        @click="openView(t)"
                    >
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ t.subject }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="priorityBadgeClass(t.priority)">{{ t.priority }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(t.status === 'Open' || t.status === 'In Progress' ? 'Active' : 'Inactive')">{{ t.status }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ t.replies_count }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ formatDate(t.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="addOpen" title="New Support Ticket" @close="addOpen = false">
            <div>
                <label class="form-label">Subject</label>
                <input v-model="form.subject" type="text" class="form-input" />
            </div>
            <div>
                <label class="form-label">Priority</label>
                <select v-model="form.priority" class="form-input">
                    <option>Low</option>
                    <option>Medium</option>
                    <option>High</option>
                </select>
            </div>
            <div>
                <label class="form-label">Description</label>
                <textarea v-model="form.description" rows="5" class="form-input" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="addOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Submitting...' : 'Submit Ticket' }}</button>
            </template>
        </SlideOver>

        <SlideOver :open="viewOpen" :title="active?.subject || 'Ticket'" @close="viewOpen = false">
            <template v-if="active">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="priorityBadgeClass(active.priority)">{{ active.priority }}</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(active.status === 'Open' || active.status === 'In Progress' ? 'Active' : 'Inactive')">{{ active.status }}</span>
                </div>
                <p class="whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">{{ active.description }}</p>

                <div class="space-y-3 border-t border-slate-200 pt-3 dark:border-slate-800">
                    <div v-for="r in active.replies" :key="r.id" class="rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800/50">
                        <p class="mb-1 text-xs font-semibold text-slate-500">{{ r.erp_user?.name }} · {{ formatDate(r.created_at) }}</p>
                        <p class="whitespace-pre-wrap text-slate-700 dark:text-slate-200">{{ r.message }}</p>
                    </div>
                    <p v-if="!active.replies?.length" class="text-xs text-slate-400">No replies yet.</p>
                </div>

                <div>
                    <label class="form-label">Add Reply</label>
                    <textarea v-model="replyMessage" rows="3" class="form-input" />
                </div>
            </template>
            <template #footer>
                <button type="button" class="text-xs font-medium text-rose-600 hover:underline" @click="remove">Delete Ticket</button>
                <button type="button" class="btn-primary" :disabled="replying || !replyMessage" @click="reply">{{ replying ? 'Sending...' : 'Send Reply' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const tickets = ref([]);
const loading = ref(true);
const saving = ref(false);
const replying = ref(false);
const addOpen = ref(false);
const viewOpen = ref(false);
const active = ref(null);
const replyMessage = ref('');
const form = reactive({ subject: '', priority: 'Medium', description: '' });

function priorityBadgeClass(priority) {
    if (priority === 'High') return 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/30';
    if (priority === 'Medium') return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30';
    return 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-500/10 dark:text-slate-300 dark:ring-slate-500/30';
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

async function load() {
    loading.value = true;
    const { data } = await client.get('/account/support-tickets');
    tickets.value = data;
    loading.value = false;
}
load();

function openAdd() {
    Object.assign(form, { subject: '', priority: 'Medium', description: '' });
    addOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        await client.post('/account/support-tickets', form);
        addOpen.value = false;
        pushToast('Support ticket submitted.', 'success');
        await load();
    } finally {
        saving.value = false;
    }
}

async function openView(ticket) {
    replyMessage.value = '';
    const { data } = await client.get(`/account/support-tickets/${ticket.id}`);
    active.value = data;
    viewOpen.value = true;
}

async function reply() {
    replying.value = true;
    try {
        const { data } = await client.post(`/account/support-tickets/${active.value.id}/reply`, { message: replyMessage.value });
        active.value = data;
        replyMessage.value = '';
        await load();
    } finally {
        replying.value = false;
    }
}

async function remove() {
    if (!confirm(`Delete ticket "${active.value.subject}"?`)) return;
    await client.delete(`/account/support-tickets/${active.value.id}`);
    viewOpen.value = false;
    pushToast('Ticket deleted.', 'success');
    await load();
}
</script>
