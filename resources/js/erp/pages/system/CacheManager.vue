<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Cache Manager</h1>
            <Breadcrumb :items="['Dashboard', 'System', 'Cache Manager']" class="mt-1" />
        </div>

        <div v-if="info" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Active Cache Driver</p>
                    <p class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ info.driver }}</p>
                </div>
                <button type="button" class="btn-primary" :disabled="clearing" @click="clearCache">{{ clearing ? 'Clearing...' : '🧹 Clear All Caches' }}</button>
            </div>
            <p class="mt-3 text-xs text-slate-400">Clears the application, config, route and view caches via the real Artisan commands.</p>
        </div>

        <div v-if="lastResult" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400">
            {{ lastResult }}
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const info = ref(null);
const clearing = ref(false);
const lastResult = ref('');

client.get('/system/cache-manager').then(({ data }) => (info.value = data));

async function clearCache() {
    clearing.value = true;
    try {
        const { data } = await client.post('/system/cache-manager/clear');
        lastResult.value = data.message;
        pushToast(data.message, 'success');
    } finally {
        clearing.value = false;
    }
}
</script>
