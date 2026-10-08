<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Salary History</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Every salary change — imported sheets, slips created or edited, payments (and their bank debit), deletes — with slip number, month and a Rollback.
                </p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="form-label">Month</label>
                    <select v-model="month" class="form-input !py-1.5">
                        <option value="">All months</option>
                        <option v-for="(m, i) in MONTHS" :key="i" :value="String(i + 1).padStart(2, '0')">{{ m }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Year</label>
                    <select v-model="year" class="form-input !py-1.5" :disabled="!month">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <SalaryHistoryPanel :filters="filters" />
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import SalaryHistoryPanel from '../../components/finance/SalaryHistoryPanel.vue';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const currentYear = new Date().getFullYear();
const years = Array.from({ length: currentYear + 2 - 2020 }, (_, i) => currentYear + 1 - i);

const month = ref('');
const year = ref(currentYear);

const filters = computed(() => (month.value ? { period: `${year.value}-${month.value}` } : {}));
</script>
