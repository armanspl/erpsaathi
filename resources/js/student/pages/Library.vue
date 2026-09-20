<template>
    <div class="space-y-5">
        <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Library</h1>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">Loading…</div>

        <div v-else-if="!data?.is_member" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">You are not registered as a library member yet.</div>

        <template v-else>
            <p class="text-sm text-slate-500 dark:text-slate-400">Library Card No.: <span class="font-medium text-slate-700 dark:text-slate-200">{{ data.library_card_no || '—' }}</span></p>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr><th class="px-4 py-3">Book</th><th class="px-4 py-3">Author</th><th class="px-4 py-3">Issued</th><th class="px-4 py-3">Due</th><th class="px-4 py-3">Returned</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Fine</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="i in data.issues" :key="i.id">
                            <td class="px-4 py-2.5">{{ i.book_title }}</td>
                            <td class="px-4 py-2.5">{{ i.author || '—' }}</td>
                            <td class="px-4 py-2.5">{{ i.issue_date }}</td>
                            <td class="px-4 py-2.5">{{ i.due_date }}</td>
                            <td class="px-4 py-2.5">{{ i.return_date || '—' }}</td>
                            <td class="px-4 py-2.5">{{ i.status }}</td>
                            <td class="px-4 py-2.5 text-right" :class="i.fine_amount > 0 ? 'text-rose-600 font-medium' : ''">₹{{ Number(i.fine_amount || 0).toLocaleString('en-IN') }}</td>
                        </tr>
                        <tr v-if="!data.issues.length"><td colspan="7" class="px-4 py-6 text-center text-slate-400">No books issued yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import client from '../api/client';

const loading = ref(true);
const data = ref(null);

onMounted(async () => {
    try {
        const { data: res } = await client.get('/library');
        data.value = res;
    } finally {
        loading.value = false;
    }
});
</script>
