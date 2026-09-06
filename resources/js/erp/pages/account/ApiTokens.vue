<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">API Tokens</h1>
                <Breadcrumb :items="['Dashboard', 'Account', 'API Tokens']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="creating = true">+ Generate Token</button>
        </div>

        <div v-if="newToken" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-500/30 dark:bg-amber-500/10">
            <p class="font-semibold text-amber-800 dark:text-amber-300">Copy this token now — it will not be shown again.</p>
            <code class="mt-2 block break-all rounded bg-white px-3 py-2 font-mono text-xs dark:bg-slate-900">{{ newToken }}</code>
            <button type="button" class="mt-2 text-xs font-medium text-amber-700 underline dark:text-amber-300" @click="newToken = ''">Dismiss</button>
        </div>

        <div v-if="creating" class="max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <label class="form-label">Token Name</label>
            <input v-model="name" type="text" class="form-input" placeholder="e.g. My Laptop" />
            <div class="mt-3 flex gap-2">
                <button type="button" class="btn-primary" :disabled="saving || !name" @click="generate">{{ saving ? 'Generating...' : 'Generate' }}</button>
                <button type="button" class="btn-outline" @click="creating = false">Cancel</button>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Last Used</th>
                        <th class="px-4 py-3">Created</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="token in tokens" :key="token.id" class="border-t border-slate-100 dark:border-slate-800">
                        <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">{{ token.name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ token.last_used_at ? formatDate(token.last_used_at) : 'Never' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ formatDate(token.created_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="text-xs font-medium text-rose-600 hover:underline" @click="revoke(token)">Revoke</button>
                        </td>
                    </tr>
                    <tr v-if="!tokens.length">
                        <td colspan="4" class="px-4 py-6 text-center text-slate-400">No API tokens generated yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const tokens = ref([]);
const creating = ref(false);
const saving = ref(false);
const name = ref('');
const newToken = ref('');

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '—';
}

function load() {
    client.get('/account/api-tokens').then(({ data }) => {
        tokens.value = data;
    });
}

async function generate() {
    saving.value = true;
    try {
        const { data } = await client.post('/account/api-tokens', { name: name.value });
        newToken.value = data.plain_text_token;
        name.value = '';
        creating.value = false;
        load();
        pushToast('API token generated.', 'success');
    } finally {
        saving.value = false;
    }
}

async function revoke(token) {
    if (!confirm(`Revoke token "${token.name}"? This cannot be undone.`)) return;
    await client.delete(`/account/api-tokens/${token.id}`);
    tokens.value = tokens.value.filter((t) => t.id !== token.id);
    pushToast('Token revoked.', 'success');
}

load();
</script>
