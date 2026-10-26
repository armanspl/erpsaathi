<template>
    <ModuleTabsHub
        title="People Directory"
        subtitle="Students, parents, teachers, staff and drivers — each tab keeps its own list, search, filters and actions."
        module-key="people"
        :tabs="TABS"
    />
</template>

<script setup>
import ModuleTabsHub from '../../components/common/ModuleTabsHub.vue';

/** People Directory — Students / Parents / Teachers / Staff / Drivers as tabs (see ModuleTabsHub). */
const TABS = [
    { id: 'students', label: 'Students', icon: 'graduation', items: [{ key: 'students', label: 'Students', path: '/people/students' }] },
    { id: 'parents', label: 'Parents', icon: 'users', items: [{ key: 'parents', label: 'Parents', path: '/people/parents' }] },
    { id: 'teachers', label: 'Teachers', icon: 'presentation', items: [{ key: 'teachers', label: 'Teachers', path: '/people/teachers' }] },
    { id: 'staff', label: 'Staff', icon: 'briefcase', items: [{ key: 'staff', label: 'Staff', path: '/people/staff' }] },
    { id: 'drivers', label: 'Drivers', icon: 'bus', items: [{ key: 'drivers', label: 'Drivers', path: '/people/drivers' }] },
];
</script>
