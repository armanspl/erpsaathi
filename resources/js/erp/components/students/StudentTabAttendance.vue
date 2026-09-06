<template>
    <div v-if="loading" class="py-10 text-center text-sm text-slate-400">Loading...</div>
    <div v-else-if="!records.length" class="py-10 text-center text-sm text-slate-400">No attendance has been marked for this student yet.</div>
    <div v-else class="space-y-5">
        <div class="grid grid-cols-3 gap-3 sm:w-96 sm:grid-cols-5">
            <div class="rounded-lg bg-emerald-50 p-3 text-center dark:bg-emerald-500/10">
                <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ summary.percentage }}%</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Overall</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 text-center dark:bg-slate-800">
                <p class="text-lg font-bold text-slate-700 dark:text-slate-200">{{ summary.present }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Present</p>
            </div>
            <div class="rounded-lg bg-rose-50 p-3 text-center dark:bg-rose-500/10">
                <p class="text-lg font-bold text-rose-600 dark:text-rose-400">{{ summary.absent }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Absent</p>
            </div>
            <div class="rounded-lg bg-amber-50 p-3 text-center dark:bg-amber-500/10">
                <p class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ summary.leave }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Leave</p>
            </div>
            <div class="rounded-lg bg-slate-50 p-3 text-center dark:bg-slate-800">
                <p class="text-lg font-bold text-slate-700 dark:text-slate-200">{{ summary.late + summary.half_day }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Late/Half</p>
            </div>
        </div>

        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-xs text-slate-400 dark:border-slate-800">
                    <th class="pb-2 font-medium">Date</th>
                    <th class="pb-2 font-medium">Status</th>
                    <th class="pb-2 font-medium" title="Running total of Present days up to this date, in chronological order — holidays don't count.">Present to date</th>
                    <th class="pb-2 font-medium">Remarks</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="r in visibleRecords" :key="r.date">
                    <td class="py-2.5 text-slate-500 dark:text-slate-400">{{ formatDate(r.date) }}</td>
                    <td class="py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="statusBadgeClass(r.status)">{{ r.status }}</span></td>
                    <td class="py-2.5 font-medium text-slate-700 dark:text-slate-200">{{ r.present_to_date }}</td>
                    <td class="py-2.5 text-slate-500 dark:text-slate-400">{{ r.remarks || '—' }}</td>
                </tr>
            </tbody>
        </table>
        <button v-if="records.length > 20 && visibleRecords.length < records.length" type="button" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400" @click="showAll = true">Show all {{ records.length }} records</button>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import client from '../../api/client';
import { statusBadgeClass } from '../../utils/colors';

const props = defineProps({ student: { type: Object, required: true } });

const loading = ref(true);
const records = ref([]);
const summary = ref({ present: 0, absent: 0, leave: 0, late: 0, half_day: 0, total_marked: 0, percentage: 0 });
const showAll = ref(false);

const visibleRecords = computed(() => (showAll.value ? records.value : records.value.slice(0, 20)));

async function load() {
    loading.value = true;
    showAll.value = false;
    const { data } = await client.get(`/attendance/student/${props.student.id}/history`);
    records.value = data.records;
    summary.value = data.summary;
    loading.value = false;
}
watch(() => props.student.id, load, { immediate: true });

function formatDate(value) {
    return new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
