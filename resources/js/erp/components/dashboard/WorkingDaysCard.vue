<template>
    <DashCard title="Total Working Days" icon="calendar-check">
        <div class="wd-head">
            <p class="wd-total">
                <span class="wd-total__num">{{ total }}</span>
                <span class="wd-total__label">{{ sessionLabel ? `days · session ${sessionLabel}` : 'days' }}</span>
            </p>
            <RouterLink :to="{ path: '/attendance/student-attendance', query: { tab: 'working-days' } }" class="wd-add">+ Add</RouterLink>
        </div>
        <p v-if="loading" class="wd-empty">Loading…</p>
        <p v-else-if="message" class="wd-empty">{{ message }}</p>
        <p v-else-if="!filled.length" class="wd-empty">No working days added yet — use “Add” to enter them month by month.</p>
        <ul v-else class="wd-list">
            <li v-for="m in filled" :key="`${m.year}-${m.month}`">
                <span>{{ m.label }}</span>
                <strong>{{ m.working_days }}</strong>
            </li>
        </ul>
    </DashCard>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import client from '../../api/client';
import DashCard from './DashCard.vue';

// School working days per month (Attendance › Student Attendance › Working Days).
const months = ref([]);
const total = ref(0);
const sessionLabel = ref('');
const message = ref('');
const loading = ref(true);
const filled = computed(() => months.value.filter((m) => m.working_days != null));

onMounted(async () => {
    try {
        const { data } = await client.get('/attendance/school-working-days');
        months.value = data.months || [];
        total.value = data.total || 0;
        sessionLabel.value = data.session_label || '';
        message.value = data.ready === false ? data.message : '';
    } catch {
        message.value = 'Working days could not be loaded.';
    } finally {
        loading.value = false;
    }
});
</script>

<style scoped>
.wd-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}
.wd-total {
    margin: 0;
    display: flex;
    align-items: baseline;
    gap: 0.45rem;
}
.wd-total__num {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1;
    color: var(--erp-heading, var(--erp-cream, #f3efe6));
}
.wd-total__label {
    font-size: 0.75rem;
    color: var(--erp-muted, #9a958c);
}
.wd-add {
    flex-shrink: 0;
    padding: 0.35rem 0.7rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--erp-gold, #c6a75e);
    border: 1px solid var(--erp-border, rgba(198, 167, 94, 0.3));
    text-decoration: none;
}
.wd-add:hover {
    background: var(--erp-tint-strong, rgba(198, 167, 94, 0.1));
}
.wd-list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(7.5rem, 1fr));
    gap: 0.4rem;
}
.wd-list li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.45rem 0.65rem;
    border-radius: 9px;
    background: var(--erp-tint, rgba(255, 255, 255, 0.03));
    border: 1px solid var(--erp-line, rgba(255, 255, 255, 0.05));
    font-size: 0.82rem;
    color: var(--erp-muted, #9a958c);
}
.wd-list strong {
    color: var(--erp-heading, var(--erp-cream, #f3efe6));
}
.wd-empty {
    margin: 0;
    font-size: 0.82rem;
    color: var(--erp-muted, #9a958c);
}
</style>
