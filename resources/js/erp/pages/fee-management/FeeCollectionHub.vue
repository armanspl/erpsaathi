<template>
    <ModuleTabsHub
        title="Fee Collection"
        subtitle="Dues, payments and receipts — each tab keeps its own tables, filters and actions."
        module-key="fee-management"
        :tabs="TABS"
    />
</template>

<script setup>
import ModuleTabsHub from '../../components/common/ModuleTabsHub.vue';

/** Fee Collection — Fee Due / Fee Paid / Fee Receipt as tabs (see ModuleTabsHub). */
const TABS = [
    { id: 'due', label: 'Fee Due', icon: 'clock', items: [{ key: 'fee-due', label: 'Fee Due', path: '/fee-management/fee-due' }] },
    { id: 'paid', label: 'Fee Paid', icon: 'check-circle', items: [{ key: 'fee-paid', label: 'Fee Paid', path: '/fee-management/fee-paid' }] },
    { id: 'receipt', label: 'Fee Receipt', icon: 'receipt', items: [{ key: 'fee-receipt', label: 'Fee Receipt', path: '/fee-management/fee-receipt' }] },
];
</script>
