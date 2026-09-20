<template>
    <div
        class="erp-shell flex min-h-screen"
        :class="[isPrintPage && 'print-page-shell', erpStore.darkMode ? 'erp-shell-dark' : 'erp-shell-light']"
        :data-template="erpStore.uiTemplate"
    >
        <AppSidebar v-if="!isPrintPage" />

        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            <AppHeader v-if="!isPrintPage" @toggle-mobile-sidebar="erpStore.sidebarMobileOpen = !erpStore.sidebarMobileOpen" />

            <main class="relative flex-1" :class="isPrintPage ? 'p-0' : 'px-3 py-5 sm:px-5 lg:px-6'">
                <router-view v-slot="{ Component, route }">
                    <!-- Remount on session change so every module reloads with X-Academic-Session -->
                    <component :is="Component" :key="`${route.fullPath}::${erpStore.currentSession}`" />
                </router-view>
            </main>

            <AppFooter v-if="!isPrintPage" />
        </div>

        <ToastHost />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppHeader from '../components/header/AppHeader.vue';
import AppSidebar from '../components/sidebar/AppSidebar.vue';
import AppFooter from '../components/AppFooter.vue';
import ToastHost from '../components/common/ToastHost.vue';
import { erpStore } from '../store';
import '../../../css/erp-theme-shell.css';

const route = useRoute();
const isPrintPage = computed(() => !!route.meta.printPage);
</script>
