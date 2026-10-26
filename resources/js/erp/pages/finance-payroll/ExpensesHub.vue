<template>
    <ModuleTabsHub
        title="Expenses"
        subtitle="Office expenses and book expenses — kept as separate records, each with its own actions."
        module-key="finance-and-payroll"
        :tabs="TABS"
    />
</template>

<script setup>
import ModuleTabsHub from '../../components/common/ModuleTabsHub.vue';

/** Expenses — Office Expenses / Book Expenses as tabs (see ModuleTabsHub). */
const TABS = [
    { id: 'office', label: 'Office Expenses', icon: 'building', items: [{ key: 'office-expenses', label: 'Office Expenses', path: '/finance-and-payroll/office-expenses' }] },
    { id: 'book', label: 'Book Expenses', icon: 'book', items: [{ key: 'book-expenses', label: 'Book Expenses', path: '/finance-and-payroll/book-expenses' }] },
];
</script>
