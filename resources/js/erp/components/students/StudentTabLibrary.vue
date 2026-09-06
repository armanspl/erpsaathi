<template>
    <div v-if="loading" class="py-10 text-center text-sm text-slate-400">Loading...</div>
    <div v-else-if="!issues.length" class="py-10 text-center text-sm text-slate-400">No book issue records for this student.</div>
    <table v-else class="w-full text-left text-sm">
        <thead>
            <tr class="border-b border-slate-100 text-xs text-slate-400 dark:border-slate-800">
                <th class="pb-2 font-medium">Book</th>
                <th class="pb-2 font-medium">Issue Date</th>
                <th class="pb-2 font-medium">Due Date</th>
                <th class="pb-2 font-medium">Return Date</th>
                <th class="pb-2 font-medium">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="r in issues" :key="r.id">
                <td class="py-2.5 font-medium text-slate-700 dark:text-slate-200">{{ r.book?.title }}</td>
                <td class="py-2.5 text-slate-500 dark:text-slate-400">{{ formatDate(r.issue_date) }}</td>
                <td class="py-2.5 text-slate-500 dark:text-slate-400">{{ formatDate(r.due_date) }}</td>
                <td class="py-2.5 text-slate-500 dark:text-slate-400">{{ r.return_date ? formatDate(r.return_date) : '—' }}</td>
                <td class="py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(r.is_overdue ? 'Overdue' : r.status)">{{ r.is_overdue ? 'Overdue' : r.status }}</span></td>
            </tr>
        </tbody>
    </table>
</template>

<script setup>
import { ref, watch } from 'vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';

const props = defineProps({ student: { type: Object, required: true } });

const loading = ref(true);
const issues = ref([]);

async function load() {
    loading.value = true;
    const { data } = await client.get('/library/issues', { params: { student_id: props.student.id } });
    issues.value = data;
    loading.value = false;
}
watch(() => props.student.id, load, { immediate: true });

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
