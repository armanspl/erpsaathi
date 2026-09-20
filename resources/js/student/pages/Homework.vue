<template>
    <div class="space-y-5">
        <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Homework</h1>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">Loading…</div>

        <div v-else class="space-y-3">
            <div v-for="h in rows" :key="h.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-400">{{ h.assigned_date }}</span>
                    <span v-if="h.teacher" class="text-xs text-slate-400">By {{ h.teacher }}</span>
                </div>
                <p v-if="h.description" class="mb-2 text-sm text-slate-600 dark:text-slate-300">{{ h.description }}</p>
                <div class="space-y-1.5">
                    <div v-for="item in h.items" :key="item.id" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800/50">
                        <div>
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ item.subject }}</span>
                            <span class="ml-2 text-slate-500 dark:text-slate-400">{{ item.content }}</span>
                        </div>
                        <button v-if="item.has_attachment" type="button" class="btn-outline !py-1 !text-xs shrink-0" @click="downloadFile(`/homework/${item.id}/download`, item.attachment_name || 'attachment')">Download</button>
                    </div>
                </div>
            </div>
            <p v-if="!rows.length" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">No homework assigned yet.</p>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import client from '../api/client';
import { downloadFile } from '../utils/download';

const loading = ref(true);
const rows = ref([]);

onMounted(async () => {
    try {
        const { data } = await client.get('/homework');
        rows.value = data;
    } finally {
        loading.value = false;
    }
});
</script>
