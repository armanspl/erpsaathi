<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Working Days</h1>
            <Breadcrumb :items="['Dashboard', 'Attendance', 'Working Days']" class="mt-1" />
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Weekly Off Days</h3>
                <div class="grid grid-cols-2 gap-2">
                    <label v-for="day in weekdays" :key="day" class="flex items-center gap-2 rounded-lg border border-slate-100 p-2.5 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300">
                        <input v-model="selectedOffDays" type="checkbox" :value="day" class="h-3.5 w-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                        {{ day }}
                    </label>
                </div>
                <button type="button" class="btn-primary mt-4 w-full" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save Configuration' }}</button>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Working Days Calculator</h3>
                <label class="form-label">Month</label>
                <input v-model="month" type="month" class="form-input mb-4" @change="loadCalc" />
                <div v-if="calc" class="grid grid-cols-3 gap-3">
                    <StatCard label="Days in Month" :value="calc.days_in_month" color="indigo" icon="📆" />
                    <StatCard label="Working Days" :value="calc.working_days" color="emerald" icon="✅" />
                    <StatCard label="Off Days" :value="calc.off_days" color="rose" icon="🚫" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const selectedOffDays = ref([]);
const month = ref(new Date().toISOString().slice(0, 7));
const calc = ref(null);
const saving = ref(false);

async function loadCalc() {
    const { data } = await client.get('/attendance/working-days', { params: { month: month.value } });
    calc.value = data;
    selectedOffDays.value = data.weekly_off_days;
}
loadCalc();

async function save() {
    saving.value = true;
    try {
        await client.put('/attendance/working-days', { weekly_off_days: selectedOffDays.value });
        pushToast('Working day configuration saved.', 'success');
        await loadCalc();
    } finally {
        saving.value = false;
    }
}
</script>
