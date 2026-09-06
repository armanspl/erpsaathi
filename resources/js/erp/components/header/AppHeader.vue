<template>
    <header class="erp-topbar sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b px-3 backdrop-blur sm:px-5 print:hidden">
        <!-- Left -->
        <div class="flex min-w-0 items-center gap-3">
            <button type="button" class="header-icon-btn" title="Toggle sidebar" @click="onToggleSidebar">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>

            <RouterLink to="/" class="flex shrink-0 items-center gap-2">
                <span class="erp-brand-mark" :title="erpStore.school.school_name">
                    <img v-if="erpStore.school.logo_url" :src="erpStore.school.logo_url" :alt="erpStore.school.school_name" class="erp-brand-logo" />
                    <template v-else>{{ schoolMark }}</template>
                </span>
                <span class="hidden min-w-0 flex-col leading-tight lg:flex">
                    <span class="erp-brand-name truncate">{{ erpStore.school.school_name || 'School' }}</span>
                    <span class="erp-brand-tag">ERP System</span>
                </span>
            </RouterLink>

            <div class="hidden md:block">
                <GlobalSearch />
            </div>
        </div>

        <!-- Right -->
        <div class="flex items-center gap-1.5 sm:gap-2">
            <PillSelect v-model="erpStore.currentSession" :options="erpStore.sessions" icon="📅" label="Academic Session" class="hidden sm:block" />
            <PillSelect v-model="erpStore.currentBranch" :options="erpStore.branches" icon="🏫" label="Branch" class="hidden lg:block" />

            <div class="mx-1 hidden h-6 w-px sm:block" style="background: var(--erp-border, rgba(198,167,94,0.22))" />

            <ImportExportMenu />
            <NotificationsMenu />
            <QuickAddMenu />

            <div class="mx-1 hidden h-6 w-px sm:block" style="background: var(--erp-border, rgba(198,167,94,0.22))" />

            <UserMenu />
        </div>
    </header>
</template>

<script setup>
import { computed } from 'vue';
import { erpStore, toggleSidebar, schoolInitials } from '../../store';
import GlobalSearch from './GlobalSearch.vue';
import PillSelect from './PillSelect.vue';
import ImportExportMenu from './ImportExportMenu.vue';
import NotificationsMenu from './NotificationsMenu.vue';
import QuickAddMenu from './QuickAddMenu.vue';
import UserMenu from './UserMenu.vue';

const emit = defineEmits(['toggle-mobile-sidebar']);
const schoolMark = computed(() => schoolInitials());

function onToggleSidebar() {
    if (window.innerWidth < 1024) emit('toggle-mobile-sidebar');
    else toggleSidebar();
}
</script>

<style scoped>
.erp-topbar {
    border-color: var(--erp-border, rgba(198, 167, 94, 0.18));
    background: color-mix(in srgb, var(--erp-surface-solid, #121318) 88%, transparent);
}
.erp-brand-mark {
    display: flex;
    height: 2.25rem;
    width: 2.25rem;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 0.55rem;
    background: linear-gradient(135deg, var(--erp-brand-from, #e2c98a), var(--erp-brand-to, #8a6d2f));
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--erp-brand-ink, #14120e);
    box-shadow: 0 6px 16px color-mix(in srgb, var(--erp-gold, #c6a75e) 25%, transparent);
}
.erp-brand-logo {
    height: 100%;
    width: 100%;
    object-fit: cover;
}
.erp-brand-name {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--erp-cream, #f3efe6);
}
.erp-brand-tag {
    font-size: 0.68rem;
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--erp-muted, #9a958c);
}
</style>
