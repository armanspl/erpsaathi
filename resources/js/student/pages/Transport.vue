<template>
    <div class="space-y-5">
        <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Transport</h1>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">Loading…</div>

        <div v-else-if="!data?.enrolled" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
            You are not currently enrolled in school transport.
        </div>

        <div v-else class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Route</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-400">Route Name</dt><dd class="text-slate-700 dark:text-slate-200">{{ data.route?.name || '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Route Code</dt><dd class="text-slate-700 dark:text-slate-200">{{ data.route?.route_code || '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">From</dt><dd class="text-slate-700 dark:text-slate-200">{{ data.route?.start_point || '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">To</dt><dd class="text-slate-700 dark:text-slate-200">{{ data.route?.end_point || '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Vehicle / Bus Details</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-400">Vehicle No.</dt><dd class="text-slate-700 dark:text-slate-200">{{ data.vehicle?.vehicle_no || '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Type</dt><dd class="text-slate-700 dark:text-slate-200">{{ data.vehicle?.type || '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Driver</dt><dd class="text-slate-700 dark:text-slate-200">{{ data.vehicle?.driver_name || '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Driver Contact</dt><dd class="text-slate-700 dark:text-slate-200">{{ data.vehicle?.driver_mobile || '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Pickup / Drop Point</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-400">Stop</dt><dd class="text-slate-700 dark:text-slate-200">{{ data.pickup_point?.stop_name || '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Pickup Time</dt><dd class="text-slate-700 dark:text-slate-200">{{ data.pickup_point?.pickup_time || '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Drop Time</dt><dd class="text-slate-700 dark:text-slate-200">{{ data.pickup_point?.drop_time || '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Transport Fee</h2>
                <p class="text-2xl font-semibold text-slate-800 dark:text-slate-100">₹{{ Number(data.fee?.monthly_fare || 0).toLocaleString('en-IN') }}<span class="text-sm font-normal text-slate-400"> / month</span></p>
                <RouterLink to="/fees" class="mt-2 inline-block text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">View in Fees →</RouterLink>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import client from '../api/client';
import { studentStore } from '../store';

const loading = ref(true);
const data = ref(null);

onMounted(async () => {
    try {
        const { data: res } = await client.get('/transport');
        data.value = res;
        studentStore.hasTransport = !!res.enrolled;
    } finally {
        loading.value = false;
    }
});
</script>
