<template>
    <ModuleTabsHub
        title="Salary & Payroll"
        subtitle="Salary sheet, salary slips and salary history — each tab keeps its own records, printing and filters."
        module-key="finance-and-payroll"
        :tabs="TABS"
    />
</template>

<script setup>
import ModuleTabsHub from '../../components/common/ModuleTabsHub.vue';

/** Salary & Payroll — Salary Sheet / Salary Slips / Salary History as tabs (see ModuleTabsHub). */
const TABS = [
    { id: 'sheet', label: 'Salary Sheet', icon: 'table', items: [{ key: 'salary-sheet', label: 'Salary Sheet', path: '/finance-and-payroll/salary-sheet' }] },
    { id: 'slips', label: 'Salary Slips', icon: 'receipt', items: [{ key: 'salary-slips', label: 'Salary Slips', path: '/finance-and-payroll/salary-slips' }] },
    { id: 'history', label: 'Salary History', icon: 'history', items: [{ key: 'salary-history', label: 'Salary History', path: '/finance-and-payroll/salary-history' }] },
];
</script>
