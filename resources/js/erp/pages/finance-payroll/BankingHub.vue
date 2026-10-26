<template>
    <ModuleTabsHub
        title="Banking"
        subtitle="Bank accounts and bank transactions — each tab keeps its own records, filters and actions."
        module-key="finance-and-payroll"
        :tabs="TABS"
    />
</template>

<script setup>
import ModuleTabsHub from '../../components/common/ModuleTabsHub.vue';

/** Banking — Bank Accounts / Bank Transactions as tabs (see ModuleTabsHub). */
const TABS = [
    { id: 'accounts', label: 'Bank Accounts', icon: 'bank', items: [{ key: 'bank-accounts', label: 'Bank Accounts', path: '/finance-and-payroll/bank-accounts' }] },
    { id: 'transactions', label: 'Bank Transactions', icon: 'transfer', items: [{ key: 'bank-transactions', label: 'Bank Transactions', path: '/finance-and-payroll/bank-transactions' }] },
];
</script>
