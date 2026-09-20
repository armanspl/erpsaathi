<template>
    <div class="erp-shell flex min-h-screen" :class="studentStore.darkMode ? 'erp-shell-dark' : 'erp-shell-light'" data-template="midnight-gold">
        <StudentSidebar />

        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            <StudentHeader @toggle-mobile-sidebar="studentStore.sidebarMobileOpen = !studentStore.sidebarMobileOpen" />

            <main class="relative flex-1 px-3 py-5 sm:px-5 lg:px-6">
                <router-view />
            </main>
        </div>

        <ToastHost />
    </div>
</template>

<script setup>
import StudentSidebar from '../components/StudentSidebar.vue';
import StudentHeader from '../components/StudentHeader.vue';
import ToastHost from '../../erp/components/common/ToastHost.vue';
import { studentStore } from '../store';
import '../../../css/erp-theme-shell.css';
</script>
