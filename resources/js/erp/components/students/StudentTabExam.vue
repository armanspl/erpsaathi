<template>
    <div v-if="loading" class="py-10 text-center text-sm text-slate-400">Loading...</div>
    <div v-else-if="!results.length" class="py-10 text-center text-sm text-slate-400">No exam results recorded for this student yet.</div>
    <table v-else class="w-full text-left text-sm">
        <thead>
            <tr class="border-b border-slate-100 text-xs text-slate-400 dark:border-slate-800">
                <th class="pb-2 font-medium">Exam</th>
                <th class="pb-2 font-medium">Marks Obtained</th>
                <th class="pb-2 font-medium">Total Marks</th>
                <th class="pb-2 font-medium">Percentage</th>
                <th class="pb-2 font-medium">Grade</th>
                <th class="pb-2 font-medium">Rank</th>
                <th class="pb-2 font-medium">Result</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="r in results" :key="r.exam_id">
                <td class="py-2.5 font-medium text-slate-700 dark:text-slate-200">{{ r.exam_name }}</td>
                <td class="py-2.5 text-slate-500 dark:text-slate-400">{{ r.obtained }}</td>
                <td class="py-2.5 text-slate-500 dark:text-slate-400">{{ r.max_total }}</td>
                <td class="py-2.5 font-medium text-primary-600 dark:text-primary-400">{{ r.percentage }}%</td>
                <td class="py-2.5"><span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400">{{ r.grade || '—' }}</span></td>
                <td class="py-2.5 text-slate-500 dark:text-slate-400">#{{ r.rank }}</td>
                <td class="py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(r.result === 'Pass' ? 'Active' : 'Inactive')">{{ r.result }}</span></td>
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
const results = ref([]);

async function load() {
    loading.value = true;
    const { data } = await client.get(`/exams/students/${props.student.id}/results`);
    results.value = data;
    loading.value = false;
}
watch(() => props.student.id, load, { immediate: true });
</script>
