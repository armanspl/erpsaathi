<template>
    <!-- Mobile overlay -->
    <transition name="fade">
        <div v-if="erpStore.sidebarMobileOpen" class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden" @click="erpStore.sidebarMobileOpen = false" />
    </transition>

    <aside
        class="erp-sidebar fixed inset-y-0 left-0 z-50 flex flex-col border-r transition-all duration-200 print:hidden lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
        :class="[collapsed ? 'w-[76px]' : 'w-[268px]', erpStore.sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']"
    >
        <div class="erp-sidebar__head flex h-16 shrink-0 items-center gap-2 border-b px-4">
            <span class="erp-sidebar__mark" :title="erpStore.school.school_name">
                <img v-if="erpStore.school.logo_url" :src="erpStore.school.logo_url" :alt="erpStore.school.school_name" class="erp-sidebar__logo" />
                <template v-else>{{ schoolMark }}</template>
            </span>
            <span v-if="!collapsed" class="erp-sidebar__title truncate" :title="erpStore.school.school_name">{{ erpStore.school.school_name || 'School ERP' }}</span>
        </div>

        <nav class="flex-1 overflow-y-auto px-2.5 py-3">
            <ul class="space-y-1">
                <li v-for="group in visibleMenu" :key="group.key">
                    <!-- Direct link (e.g. Dashboard) — no submenu -->
                    <RouterLink
                        v-if="group.path && !group.children.length"
                        :to="group.path"
                        class="erp-nav-item group flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2.5 text-sm font-medium transition"
                        :class="isDirectActive(group) ? 'is-active' : ''"
                        :title="collapsed ? group.label : ''"
                        @click="erpStore.sidebarMobileOpen = false"
                    >
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center text-base">{{ group.icon }}</span>
                        <span v-if="!collapsed" class="flex-1 truncate text-left">{{ group.label }}</span>
                    </RouterLink>

                    <template v-else>
                        <button
                            type="button"
                            class="erp-nav-item group flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2.5 text-sm font-medium transition"
                            :class="isGroupActive(group) ? 'is-active' : ''"
                            :title="collapsed ? group.label : ''"
                            @click="onGroupClick(group)"
                        >
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center text-base">{{ group.icon }}</span>
                            <span v-if="!collapsed" class="flex-1 truncate text-left">{{ group.label }}</span>
                            <svg v-if="!collapsed" class="h-3.5 w-3.5 shrink-0 text-slate-400 transition-transform" :class="expandedKey === group.key && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>

                        <transition name="collapse">
                            <ul v-if="!collapsed && expandedKey === group.key" class="erp-nav-children ml-[27px] mt-1 space-y-0.5 border-l pl-3">
                                <li v-for="child in group.children" :key="child.key">
                                    <button
                                        v-if="child.action === 'logout'"
                                        type="button"
                                        class="erp-nav-child erp-nav-child--danger block w-full rounded-lg px-2.5 py-1.5 text-left text-[13px] font-medium transition"
                                        @click="logout"
                                    >
                                        {{ child.label }}
                                    </button>
                                    <RouterLink
                                        v-else
                                        :to="{ path: child.path, query: child.query || {} }"
                                        class="erp-nav-child block rounded-lg px-2.5 py-1.5 text-[13px] font-medium transition"
                                        :class="isChildActive(child) ? 'is-active' : ''"
                                        @click="erpStore.sidebarMobileOpen = false"
                                    >
                                        {{ child.label }}
                                    </RouterLink>
                                </li>
                            </ul>
                        </transition>
                    </template>
                </li>
            </ul>
        </nav>

        <button type="button" class="erp-sidebar__collapse hidden shrink-0 items-center justify-center gap-2 border-t py-3 text-xs font-medium transition lg:flex" @click="toggleSidebar">
            <svg class="h-4 w-4 transition-transform" :class="collapsed && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14-7-7 7-7" /></svg>
            <span v-if="!collapsed">Collapse</span>
        </button>
    </aside>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { prefetchAcademicsLookups } from '../../api/academics';
import { prefetchAdmissions } from '../../api/admissions';
import { prefetchAttendance } from '../../api/attendance';
import { prefetchFeeManagement } from '../../api/feeManagement';
import { prefetchPeople } from '../../api/people';
import { menu, findMenuItemByPath } from '../../data/menu';
import { erpStore, toggleSidebar, logout, schoolInitials } from '../../store';
import { usePermissions } from '../../composables/usePermissions';

const { can } = usePermissions();
const schoolMark = computed(() => schoolInitials());

const route = useRoute();
const collapsed = computed(() => erpStore.sidebarCollapsed);

function childViewKey(group, child) {
    if (child.action === 'logout') return null;
    if (group.key === 'dashboard') return 'dashboard.dashboard.view';
    // Account → change-password has edit only in catalog
    if (group.key === 'account' && child.key === 'change-password') {
        return 'account.change-password.edit';
    }
    return `${group.key}.${child.key}.view`;
}

function canSeeChild(group, child) {
    if (child.action === 'logout') return true;
    if (group.key === 'import-and-export' && erpStore.isDemoSchool
        && (erpStore.demoHiddenImportExport || []).includes(child.key)) {
        return false;
    }
    if (!erpStore.permissionsLoaded && erpStore.user?.role === 'admin') return true;
    const key = childViewKey(group, child);
    return !key || can(key);
}

const visibleMenu = computed(() =>
    menu
        .map((group) => {
            if (group.path && !group.children.length) {
                return can('dashboard.dashboard.view') ? group : null;
            }
            const children = group.children.filter((c) => canSeeChild(group, c));
            if (!children.length) return null;
            return { ...group, children };
        })
        .filter(Boolean),
);

const activeGroupKey = computed(() => findMenuItemByPath(route.path)?.group.key || null);
const expandedKey = ref(
    activeGroupKey.value && visibleMenu.value.find((g) => g.key === activeGroupKey.value)?.children?.length
        ? activeGroupKey.value
        : (visibleMenu.value.find((g) => g.children.length)?.key || null),
);

watch(activeGroupKey, (key) => {
    if (key && visibleMenu.value.find((g) => g.key === key)?.children?.length) {
        expandedKey.value = key;
    }
});

function isDirectActive(group) {
    return route.path === group.path;
}
function isGroupActive(group) {
    return activeGroupKey.value === group.key;
}
function isChildActive(child) {
    if (route.path !== child.path) return false;
    if (child.query?.type) return route.query.type === child.query.type;
    return true;
}
function warmGroup(key) {
    if (key === 'academics') prefetchAcademicsLookups();
    if (key === 'admissions') prefetchAdmissions();
    if (key === 'people') prefetchPeople();
    if (key === 'attendance') prefetchAttendance();
    if (key === 'fee-management') prefetchFeeManagement();
}

function onGroupClick(group) {
    if (collapsed.value) {
        erpStore.sidebarCollapsed = false;
        expandedKey.value = group.key;
        warmGroup(group.key);
        return;
    }
    const next = expandedKey.value === group.key ? null : group.key;
    expandedKey.value = next;
    if (next) warmGroup(next);
}
</script>

<style scoped>
.erp-sidebar {
    border-color: var(--erp-border, rgba(198, 167, 94, 0.18));
    background: var(--erp-surface-solid, #121318);
}
.erp-sidebar__head {
    border-color: var(--erp-border, rgba(198, 167, 94, 0.18));
}
.erp-sidebar__mark {
    display: flex;
    height: 2.25rem;
    width: 2.25rem;
    flex-shrink: 0;
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
.erp-sidebar__logo {
    height: 100%;
    width: 100%;
    object-fit: cover;
}
.erp-sidebar__title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--erp-cream, #f3efe6);
}
.erp-nav-item {
    color: var(--erp-muted, #9a958c);
}
.erp-nav-item:hover {
    background: rgba(198, 167, 94, 0.08);
    color: var(--erp-cream, #f3efe6);
}
.erp-nav-item.is-active {
    background: rgba(198, 167, 94, 0.14);
    color: var(--erp-gold-soft, #e2c98a);
}
.erp-nav-children {
    border-color: var(--erp-border, rgba(198, 167, 94, 0.18));
}
.erp-nav-child {
    color: var(--erp-muted, #9a958c);
}
.erp-nav-child:hover {
    background: rgba(255, 255, 255, 0.04);
    color: var(--erp-cream, #f3efe6);
}
.erp-nav-child.is-active {
    background: linear-gradient(135deg, var(--erp-gold, #c6a75e), var(--erp-brand-to, #a8883f));
    color: var(--erp-brand-ink, #14120e);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--erp-gold, #c6a75e) 22%, transparent);
}
.erp-nav-child--danger {
    color: #e8a0a0;
}
.erp-nav-child--danger:hover {
    background: rgba(180, 60, 60, 0.12);
    color: #f6c1c1;
}
.erp-sidebar__collapse {
    border-color: var(--erp-border, rgba(198, 167, 94, 0.18));
    color: var(--erp-muted, #9a958c);
}
.erp-sidebar__collapse:hover {
    background: rgba(198, 167, 94, 0.08);
    color: var(--erp-cream, #f3efe6);
}
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.collapse-enter-active,
.collapse-leave-active {
    transition: all 0.18s ease;
    overflow: hidden;
}
.collapse-enter-from,
.collapse-leave-to {
    opacity: 0;
    max-height: 0;
}
.collapse-enter-to,
.collapse-leave-from {
    max-height: 800px;
}
</style>
