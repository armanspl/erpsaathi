<template>
    <div v-if="loading" class="py-10 text-center text-sm text-slate-400">Loading...</div>
    <div v-else-if="!assignment" class="py-10 text-center text-sm text-slate-400">No transport assignment on record for this student.</div>
    <dl v-else class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
        <div v-for="item in items" :key="item.label">
            <dt class="text-xs font-medium text-slate-400">{{ item.label }}</dt>
            <dd class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">{{ item.value || '—' }}</dd>
        </div>
    </dl>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import client from '../../api/client';

const props = defineProps({ student: { type: Object, required: true } });

const loading = ref(true);
const assignment = ref(null);

async function load() {
    loading.value = true;
    const { data } = await client.get('/transport/student-transport', { params: { student_id: props.student.id } });
    assignment.value = data[0] || null;
    loading.value = false;
}
watch(() => props.student.id, load, { immediate: true });

const items = computed(() => {
    const a = assignment.value;
    if (!a) return [];
    const vehicle = a.route?.vehicle;
    return [
        { label: 'Route', value: a.route?.name },
        { label: 'Vehicle', value: vehicle ? `${vehicle.vehicle_no}` : null },
        { label: 'Driver', value: vehicle?.driver?.name },
        { label: 'Driver Phone', value: vehicle?.driver?.phone },
        { label: 'Pickup Point', value: a.route_stop?.stop_name },
        { label: 'Monthly Fare', value: a.route_stop?.fare != null ? `₹${Number(a.route_stop.fare).toLocaleString('en-IN')}` : null },
        { label: 'Start Date', value: a.start_date ? new Date(a.start_date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : null },
        { label: 'Status', value: a.status },
    ];
});
</script>
