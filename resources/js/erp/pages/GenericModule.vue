<template>
    <ModulePage :config="config" :seed="route.path" />
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import ModulePage from '../components/common/ModulePage.vue';
import { getModuleConfig } from '../data/moduleConfigs';

const route = useRoute();
const config = computed(() => getModuleConfig(route.path, route.meta.label, route.meta.group));
</script>
