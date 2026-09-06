<template>
    <ErpLayout />
</template>

<script setup>
import ErpLayout from './layouts/ErpLayout.vue';
import { loadSessions, loadSchoolSettings, loadBranches, loadPermissions } from './store';

loadSessions();
loadSchoolSettings();
loadBranches();
loadPermissions();
</script>
