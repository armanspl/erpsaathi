<template>
    <footer class="erp-footer border-t px-4 py-3 print:hidden sm:px-6">
        <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-center text-xs">
            <span>© {{ year }} Global Access School ERP</span>
            <span class="hidden sm:inline">•</span>
            <span>Session: <strong>{{ erpStore.currentSession }}</strong></span>
            <span class="hidden sm:inline">•</span>
            <span>Version 1.0.0</span>
            <span class="hidden sm:inline">•</span>
            <span>Last Sync: <strong>Today, 12:30 PM</strong></span>
        </div>
    </footer>
</template>

<script setup>
import { erpStore } from '../store';
const year = new Date().getFullYear();
</script>

<style scoped>
.erp-footer {
    border-color: var(--erp-border, rgba(198, 167, 94, 0.18));
    background: var(--erp-surface-solid, #121318);
    color: var(--erp-muted, #9a958c);
}
.erp-footer strong {
    font-weight: 600;
    color: var(--erp-cream, #f3efe6);
}
</style>
