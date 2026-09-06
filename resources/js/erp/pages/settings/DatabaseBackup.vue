<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Database Backup</h1>
                <Breadcrumb :items="['Dashboard', 'Settings', 'Database Backup']" class="mt-1" />
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Create and download SQL dumps of
                    <span v-if="database" class="font-medium text-slate-700 dark:text-slate-200">{{ database }}</span>
                    <span v-else>the connected database</span>.
                </p>
            </div>
            <button type="button" class="btn-primary" :disabled="creating" @click="createBackup">
                {{ creating ? 'Creating...' : '+ Create Backup' }}
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatCard label="Backups" :value="backups.length" color="indigo" icon="💾" />
            <StatCard label="Latest size" :value="latestSize" color="sky" icon="📦" />
            <StatCard label="Database" :value="database || '—'" color="emerald" icon="🗄️" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">File</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Size</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Created</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-400">Loading backups...</td>
                    </tr>
                    <tr v-else-if="!backups.length">
                        <td colspan="4" class="px-4 py-10 text-center text-slate-400">No backups yet. Create one to get started.</td>
                    </tr>
                    <tr v-for="b in backups" :key="b.name" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ b.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ b.size_human }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ formatDate(b.created_at) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="downloading === b.name" @click="download(b)">
                                    {{ downloading === b.name ? 'Downloading...' : 'Download' }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50 disabled:opacity-40 dark:border-slate-700 dark:hover:bg-rose-500/10"
                                    :disabled="deleting === b.name"
                                    @click="remove(b)"
                                >
                                    {{ deleting === b.name ? 'Deleting...' : 'Delete' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const creating = ref(false);
const downloading = ref(null);
const deleting = ref(null);
const database = ref('');
const backups = ref([]);

const latestSize = computed(() => backups.value[0]?.size_human || '—');

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return value;
    }
}

async function load() {
    loading.value = true;
    try {
        const { data } = await client.get('/settings/database-backups');
        database.value = data.database || '';
        backups.value = data.backups || [];
    } finally {
        loading.value = false;
    }
}

async function createBackup() {
    creating.value = true;
    try {
        await client.post('/settings/database-backups');
        pushToast('Database backup created.', 'success');
        await load();
    } finally {
        creating.value = false;
    }
}

async function download(backup) {
    downloading.value = backup.name;
    try {
        const { data } = await client.get(`/settings/database-backups/${encodeURIComponent(backup.name)}/download`, {
            responseType: 'blob',
        });
        const url = URL.createObjectURL(data);
        const a = document.createElement('a');
        a.href = url;
        a.download = backup.name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        pushToast('Download started.', 'success');
    } finally {
        downloading.value = null;
    }
}

async function remove(backup) {
    if (!window.confirm(`Delete backup "${backup.name}"? This cannot be undone.`)) return;
    deleting.value = backup.name;
    try {
        await client.delete(`/settings/database-backups/${encodeURIComponent(backup.name)}`);
        pushToast('Backup deleted.', 'success');
        backups.value = backups.value.filter((b) => b.name !== backup.name);
    } finally {
        deleting.value = null;
    }
}

load();
</script>
