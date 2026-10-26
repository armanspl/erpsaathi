<template>
    <ModuleTabsHub
        title="Fee Reports"
        subtitle="Fee history, month-wise collection and demand slips — each tab keeps its own filters, print and export."
        module-key="fee-management"
        :tabs="TABS"
    />
</template>

<script setup>
import ModuleTabsHub from '../../components/common/ModuleTabsHub.vue';

/** Fee Reports — Fee History / Month-wise Fee Collection / Demand Slip as tabs (see ModuleTabsHub). */
const TABS = [
    { id: 'history', label: 'Fee History', icon: 'history', items: [{ key: 'fee-history', label: 'Fee History', path: '/fee-management/fee-history' }] },
    { id: 'month-wise', label: 'Month-wise Fee Collection', icon: 'chart', items: [{ key: 'month-wise-fee-collection', label: 'Month-wise Fee Collection', path: '/fee-management/month-wise-fee-collection' }] },
    { id: 'demand-slip', label: 'Demand Slip', icon: 'file', items: [{ key: 'demand-slip', label: 'Demand Slip', path: '/fee-management/demand-slip' }] },
];
</script>
