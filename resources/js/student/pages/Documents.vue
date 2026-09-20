<template>
    <div class="space-y-5">
        <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">My Documents</h1>

        <div v-if="isModuleVisible('id_card')" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">ID Card</h2>
                <p class="text-xs text-slate-400">Download your school identity card.</p>
            </div>
            <button type="button" class="btn-outline !py-1.5 !text-xs" @click="downloadFile('/id-card', 'id-card.pdf')">Download</button>
        </div>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">Loading…</div>

        <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-for="d in rows" :key="d.type" class="flex items-center justify-between px-4 py-3">
                    <span class="text-sm text-slate-700 dark:text-slate-200">{{ d.label }}</span>
                    <span v-if="!d.uploaded" class="text-xs text-slate-400">Not uploaded</span>
                    <button v-else type="button" class="btn-outline !py-1 !text-xs" @click="downloadFile(`/documents/${d.type}`, d.label)">Download</button>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import client from '../api/client';
import { isModuleVisible } from '../store';
import { downloadFile } from '../utils/download';

const loading = ref(true);
const rows = ref([]);

onMounted(async () => {
    try {
        const { data } = await client.get('/documents');
        rows.value = data;
    } finally {
        loading.value = false;
    }
});
</script>
