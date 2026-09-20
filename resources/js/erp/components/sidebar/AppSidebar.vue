<template>
    <!-- Mobile overlay -->
    <transition name="fade">
        <div
            v-if="erpStore.sidebarMobileOpen"
            class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"
            @click="erpStore.sidebarMobileOpen = false"
        />
    </transition>

    <!-- Desktop spacer: rail width, or full width when pinned so content doesn't sit under sidebar -->
    <div
        v-if="!hideDesktopSpacer"
        class="erp-rail-spacer hidden shrink-0 transition-[width] duration-200 ease-out lg:block"
        :class="erpStore.sidebarPinned ? 'w-[268px]' : 'w-16'"
        aria-hidden="true"
    />

    <aside
        class="erp-sidebar fixed inset-y-0 left-0 z-50 flex flex-col border-r print:hidden"
        :class="sidebarClasses"
        @mouseenter="onRailHoverEnter"
        @mouseleave="onRailHoverLeave"
    >
        <div
            class="erp-sidebar__head flex h-16 shrink-0 items-center border-b transition-[padding,gap] duration-200"
            :class="showLabels ? 'gap-2 px-3' : 'justify-center px-2'"
        >
            <span class="erp-sidebar__mark" :title="erpStore.school.school_name">
                <img
                    v-if="erpStore.school.logo_url"
                    :src="erpStore.school.logo_url"
                    :alt="erpStore.school.school_name"
                    class="erp-sidebar__logo"
                />
                <template v-else>{{ schoolMark }}</template>
            </span>
            <span
                v-show="showLabels"
                class="erp-sidebar__title min-w-0 flex-1 truncate"
                :title="erpStore.school.school_name"
            >{{ erpStore.school.school_name || 'School ERP' }}</span>

            <!-- Pin: keep sidebar expanded (desktop only) -->
            <button
                v-show="isDesktop && (showLabels || erpStore.sidebarPinned)"
                type="button"
                class="erp-sidebar__pin shrink-0 rounded-lg p-1.5 transition"
                :class="erpStore.sidebarPinned ? 'is-pinned' : ''"
                :title="erpStore.sidebarPinned ? 'Unpin sidebar' : 'Pin sidebar open'"
                :aria-pressed="erpStore.sidebarPinned"
                @click.stop="onTogglePin"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path v-if="erpStore.sidebarPinned" d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z" />
                    <path v-else d="M14 4v7.17l2.59 2.58c.08.09.12.21.12.33V15c0 .28-.22.5-.5.5H13v6h-2v-6H7.5c-.28 0-.5-.22-.5-.5v-.92c0-.12.04-.24.12-.33L10 11.17V4h4m2-2H8v2h1v6.17L6.41 12.76A1.98 1.98 0 0 0 6 14.08V15c0 1.1.9 2 2 2h3v6h2v-6h3c1.1 0 2-.9 2-2v-.92c0-.4-.12-.78-.35-1.1L15 10.17V4h1V2z" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 transition-[padding] duration-200" :class="showLabels ? 'px-2.5' : 'px-1.5'">
            <ul class="space-y-1">
                <li v-for="group in visibleMenu" :key="group.key">
                    <!-- Direct link (e.g. Dashboard) -->
                    <RouterLink
                        v-if="group.path && !group.children.length"
                        :to="group.path"
                        class="erp-nav-item group flex w-full items-center rounded-lg text-sm font-medium transition-[padding,gap,background-color,color] duration-200"
                        :class="[
                            showLabels ? 'gap-2.5 px-2.5 py-2.5' : 'justify-center px-0 py-2.5',
                            isDirectActive(group) ? 'is-active' : '',
                        ]"
                        :title="showLabels ? '' : group.label"
                        @click="onNavClick"
                    >
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center text-base">{{ group.icon }}</span>
                        <span v-show="showLabels" class="min-w-0 flex-1 truncate text-left">{{ group.label }}</span>
                    </RouterLink>

                    <template v-else>
                        <button
                            type="button"
                            class="erp-nav-item group flex w-full items-center rounded-lg text-sm font-medium transition-[padding,gap,background-color,color] duration-200"
                            :class="[
                                showLabels ? 'gap-2.5 px-2.5 py-2.5' : 'justify-center px-0 py-2.5',
                                isGroupActive(group) ? 'is-active' : '',
                            ]"
                            :title="showLabels ? '' : group.label"
                            :aria-expanded="expandedKey === group.key"
                            @click="onGroupClick(group)"
                        >
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center text-base">{{ group.icon }}</span>
                            <span v-show="showLabels" class="min-w-0 flex-1 truncate text-left">{{ group.label }}</span>
                            <svg
                                v-show="showLabels"
                                class="h-3.5 w-3.5 shrink-0 text-slate-400 transition-transform duration-200"
                                :class="expandedKey === group.key && 'rotate-180'"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>

                        <transition name="collapse">
                            <ul
                                v-if="showLabels && expandedKey === group.key"
                                class="erp-nav-children ml-[27px] mt-1 space-y-0.5 border-l pl-3"
                            >
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
                                        @click="onNavClick"
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
    </aside>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { prefetchAcademicsLookups } from '../../api/academics';
import { prefetchAdmissions } from '../../api/admissions';
import { prefetchAttendance } from '../../api/attendance';
import { prefetchFeeManagement } from '../../api/feeManagement';
import { prefetchPeople } from '../../api/people';
import { menu, findMenuItemByPath } from '../../data/menu';
import { erpStore, logout, schoolInitials, toggleSidebarPin } from '../../store';
import { usePermissions } from '../../composables/usePermissions';

defineProps({
    /** When true, parent layout already reserved rail space (or print). */
    hideDesktopSpacer: { type: Boolean, default: false },
});

const RAIL_MQ = '(min-width: 1024px)';

const { can } = usePermissions();
const schoolMark = computed(() => schoolInitials());
const route = useRoute();

const isDesktop = ref(false);
const railHovered = ref(false);
let leaveTimer = null;

const isExpanded = computed(() => {
    if (!isDesktop.value) return true;
    return erpStore.sidebarPinned || railHovered.value;
});

const showLabels = computed(() => {
    if (!isDesktop.value) return true;
    return isExpanded.value;
});

const sidebarClasses = computed(() => {
    if (!isDesktop.value) {
        return [
            'w-[268px] transition-transform duration-200',
            erpStore.sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full',
        ];
    }
    return [
        'lg:translate-x-0 transition-[width] duration-200 ease-out',
        isExpanded.value
            ? (erpStore.sidebarPinned ? 'erp-sidebar--expanded erp-sidebar--pinned w-[268px]' : 'erp-sidebar--expanded w-[268px] shadow-2xl')
            : 'erp-sidebar--rail w-16',
    ];
});

function childViewKey(group, child) {
    if (child.action === 'logout') return null;
    if (group.key === 'dashboard') return 'dashboard.dashboard.view';
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
        : (visibleMenu.value.find((g) => g.children?.length)?.key || null),
);

watch(activeGroupKey, (key) => {
    if (key && visibleMenu.value.find((g) => g.key === key)?.children?.length) {
        expandedKey.value = key;
    }
});

watch(() => route.fullPath, () => {
    erpStore.sidebarMobileOpen = false;
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

function clearLeaveTimer() {
    if (leaveTimer) {
        clearTimeout(leaveTimer);
        leaveTimer = null;
    }
}

function onRailHoverEnter() {
    if (!isDesktop.value) return;
    clearLeaveTimer();
    railHovered.value = true;
}

function onRailHoverLeave() {
    if (!isDesktop.value || erpStore.sidebarPinned) return;
    clearLeaveTimer();
    leaveTimer = setTimeout(() => {
        railHovered.value = false;
    }, 120);
}

function onTogglePin() {
    toggleSidebarPin();
    if (erpStore.sidebarPinned) {
        clearLeaveTimer();
        railHovered.value = true;
    }
}

function onGroupClick(group) {
    if (isDesktop.value && !showLabels.value) {
        railHovered.value = true;
        expandedKey.value = group.key;
        warmGroup(group.key);
        return;
    }
    const next = expandedKey.value === group.key ? null : group.key;
    expandedKey.value = next;
    if (next) warmGroup(next);
}

function onNavClick() {
    erpStore.sidebarMobileOpen = false;
}

function syncDesktop() {
    const desktop = window.matchMedia(RAIL_MQ).matches;
    isDesktop.value = desktop;
    if (!desktop) {
        railHovered.value = false;
        clearLeaveTimer();
    }
}

onMounted(() => {
    syncDesktop();
    window.addEventListener('resize', syncDesktop);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', syncDesktop);
    clearLeaveTimer();
});
</script>

<style scoped>
.erp-sidebar {
    border-color: var(--erp-border, rgba(198, 167, 94, 0.18));
    background: var(--erp-surface-solid, #121318);
    will-change: width;
}
.erp-sidebar--rail {
    overflow: hidden;
}
.erp-sidebar--expanded {
    overflow: hidden;
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
.erp-sidebar__pin {
    color: var(--erp-muted, #9a958c);
}
.erp-sidebar__pin:hover {
    background: rgba(198, 167, 94, 0.12);
    color: var(--erp-cream, #f3efe6);
}
.erp-sidebar__pin.is-pinned {
    background: rgba(198, 167, 94, 0.18);
    color: var(--erp-gold-soft, #e2c98a);
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
