<template>
    <ModuleTabsHub
        title="UDISE+"
        subtitle="UDISE+ student forms — S02 and S03."
        module-key="people"
        :tabs="TABS"
    />
</template>

<script setup>
import ModuleTabsHub from '../../components/common/ModuleTabsHub.vue';

/** UDISE+ — the S02 and S03 screens as tabs (see ModuleTabsHub). */
const TABS = [
    { id: 's02', label: 'S02', icon: 'file', items: [{ key: 'udiseplus-s02', label: 'UDISE+ S02', path: '/people/udiseplus-s02' }] },
    { id: 's03', label: 'S03', icon: 'file', items: [{ key: 'udiseplus-s03', label: 'UDISE+ S03', path: '/people/udiseplus-s03' }] },
];
</script>
