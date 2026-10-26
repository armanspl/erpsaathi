<template>
    <ModuleTabsHub
        title="Fee Setup"
        subtitle="Fee structure and fee settings — each tab keeps its own configuration and actions."
        module-key="fee-management"
        :tabs="TABS"
    />
</template>

<script setup>
import ModuleTabsHub from '../../components/common/ModuleTabsHub.vue';

/** Fee Setup — Fee Structure / Fee Settings as tabs (see ModuleTabsHub). */
const TABS = [
    { id: 'structure', label: 'Fee Structure', icon: 'layers', items: [{ key: 'fee-structure', label: 'Fee Structure', path: '/fee-management/fee-structure' }] },
    { id: 'settings', label: 'Fee Settings', icon: 'settings', items: [{ key: 'fee-settings', label: 'Fee Settings', path: '/fee-management/fee-settings' }] },
];
</script>
