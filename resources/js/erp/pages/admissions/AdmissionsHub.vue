<template>
    <ModuleTabsHub
        title="Admissions"
        subtitle="Enquiries, registrations, admissions and admission settings — all in one place."
        module-key="admissions"
        :tabs="TABS"
    />
</template>

<script setup>
import ModuleTabsHub from '../../components/common/ModuleTabsHub.vue';

/** Admissions — one sidebar item, the Admissions pages as tabs (see ModuleTabsHub). */
const TABS = [
    { id: 'enquiry', label: 'Enquiry', icon: 'message', items: [
        { key: 'enquiry', label: 'Enquiry', path: '/admissions/enquiry' },
    ] },
    { id: 'registration', label: 'Registration', icon: 'clipboard', items: [
        { key: 'registration', label: 'Registration', path: '/admissions/registration' },
    ] },
    { id: 'admission', label: 'Admission', icon: 'user-check', items: [
        { key: 'admission', label: 'Admission', path: '/admissions/admission' },
    ] },
    { id: 'settings', label: 'Admission Settings', icon: 'settings', items: [
        { key: 'admission-settings', label: 'Admission Settings', path: '/admissions/admission-settings' },
    ] },
];
</script>
