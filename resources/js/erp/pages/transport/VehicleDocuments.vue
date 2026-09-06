<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Vehicle Documents</h1>
                <Breadcrumb :items="['Dashboard', 'Transport Management', 'Vehicle Documents']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" :disabled="!vehicleId" @click="openAdd">+ Add Document</button>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <label class="form-label">Vehicle</label>
            <select v-model.number="vehicleId" class="form-input" @change="load">
                <option :value="null">Select a vehicle</option>
                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.vehicle_no }}</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Document No.</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Issued</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Expires</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="!vehicleId">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Select a vehicle to view its documents.</td>
                    </tr>
                    <tr v-else-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!documents.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No documents recorded for this vehicle.</td>
                    </tr>
                    <tr v-for="d in documents" :key="d.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ d.document_type }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ d.document_no || '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ d.issue_date ? formatDate(d.issue_date) : '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(d.expiry_date) }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(expiryStatus(d.expiry_date).badge)">{{ expiryStatus(d.expiry_date).label }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800" @click="openEdit(d)">✏️</button>
                            <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(d)">🗑</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Document' : 'Add Document'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Document Type</label>
                <select v-model="form.document_type" class="form-input">
                    <option>RC</option>
                    <option>Insurance</option>
                    <option>Permit</option>
                    <option>Fitness</option>
                    <option>PUC</option>
                    <option>Other</option>
                </select>
            </div>
            <div>
                <label class="form-label">Document No.</label>
                <input v-model="form.document_no" type="text" class="form-input" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Issue Date</label>
                    <input v-model="form.issue_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Expiry Date</label>
                    <input v-model="form.expiry_date" type="date" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Remarks</label>
                <input v-model="form.remarks" type="text" class="form-input" />
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
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';
import { pushToast } from '../../utils/toast';

const loading = ref(false);
const saving = ref(false);
const vehicles = ref([]);
const documents = ref([]);
const vehicleId = ref(null);
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ document_type: 'Insurance', document_no: '', issue_date: '', expiry_date: '', remarks: '' });

client.get('/transport/vehicles').then(({ data }) => (vehicles.value = data));

async function load() {
    if (!vehicleId.value) {
        documents.value = [];
        return;
    }
    loading.value = true;
    const { data } = await client.get('/transport/documents', { params: { vehicle_id: vehicleId.value } });
    documents.value = data;
    loading.value = false;
}

function openAdd() {
    editing.value = null;
    Object.assign(form, { document_type: 'Insurance', document_no: '', issue_date: '', expiry_date: '', remarks: '' });
    drawerOpen.value = true;
}

function openEdit(document) {
    editing.value = document;
    Object.assign(form, {
        document_type: document.document_type,
        document_no: document.document_no || '',
        issue_date: document.issue_date ? document.issue_date.slice(0, 10) : '',
        expiry_date: document.expiry_date ? document.expiry_date.slice(0, 10) : '',
        remarks: document.remarks || '',
    });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        const payload = { ...form, vehicle_id: vehicleId.value, issue_date: form.issue_date || null };
        if (editing.value) {
            await client.put(`/transport/documents/${editing.value.id}`, payload);
            pushToast('Document updated.', 'success');
        } else {
            await client.post('/transport/documents', payload);
            pushToast('Document added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(document) {
    documents.value = documents.value.filter((d) => d.id !== document.id);
    await client.delete(`/transport/documents/${document.id}`);
    pushToast('Document removed.', 'success');
}

function expiryStatus(expiryDate) {
    const days = (new Date(expiryDate) - new Date()) / (1000 * 60 * 60 * 24);
    if (days < 0) return { label: 'Expired', badge: 'Inactive' };
    if (days <= 30) return { label: 'Expiring Soon', badge: 'Pending' };
    return { label: 'Valid', badge: 'Active' };
}

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
