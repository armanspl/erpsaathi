<template>
    <transition name="fade">
        <div
            v-if="studentStore.sidebarMobileOpen"
            class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"
            @click="studentStore.sidebarMobileOpen = false"
        />
    </transition>

    <!-- Desktop spacer: stable rail width; expands when pinned -->
    <div
        class="erp-rail-spacer hidden shrink-0 transition-[width] duration-200 ease-out lg:block"
        :class="studentStore.sidebarPinned ? 'w-[268px]' : 'w-16'"
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
            <span class="erp-sidebar__mark" :title="studentStore.school.school_name">
                <img
                    v-if="studentStore.school.logo_url"
                    :src="studentStore.school.logo_url"
                    :alt="studentStore.school.school_name"
                    class="erp-sidebar__logo"
                />
                <template v-else>{{ schoolInitials() }}</template>
            </span>
            <span
                v-show="showLabels"
                class="erp-sidebar__title min-w-0 flex-1 truncate"
                :title="studentStore.school.school_name"
            >{{ studentStore.school.school_name || 'School' }}</span>

            <button
                v-show="isDesktop && (showLabels || studentStore.sidebarPinned)"
                type="button"
                class="erp-sidebar__pin shrink-0 rounded-lg p-1.5 transition"
                :class="studentStore.sidebarPinned ? 'is-pinned' : ''"
                :title="studentStore.sidebarPinned ? 'Unpin sidebar' : 'Pin sidebar open'"
                :aria-pressed="studentStore.sidebarPinned"
                @click.stop="onTogglePin"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path v-if="studentStore.sidebarPinned" d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z" />
                    <path v-else d="M14 4v7.17l2.59 2.58c.08.09.12.21.12.33V15c0 .28-.22.5-.5.5H13v6h-2v-6H7.5c-.28 0-.5-.22-.5-.5v-.92c0-.12.04-.24.12-.33L10 11.17V4h4m2-2H8v2h1v6.17L6.41 12.76A1.98 1.98 0 0 0 6 14.08V15c0 1.1.9 2 2 2h3v6h2v-6h3c1.1 0 2-.9 2-2v-.92c0-.4-.12-.78-.35-1.1L15 10.17V4h1V2z" />
                </svg>
            </button>
        </div>

        <nav
            class="flex-1 overflow-y-auto overflow-x-hidden py-3 transition-[padding] duration-200"
            :class="showLabels ? 'px-2.5' : 'px-1.5'"
        >
            <ul class="space-y-1">
                <li v-for="item in visibleItems" :key="item.path">
                    <RouterLink
                        :to="item.path"
                        class="erp-nav-item group flex w-full items-center rounded-lg text-sm font-medium transition-[padding,gap,background-color,color] duration-200"
                        active-class="is-active"
                        :class="showLabels ? 'gap-2.5 px-2.5 py-2.5' : 'justify-center px-0 py-2.5'"
                        :title="showLabels ? '' : item.label"
                        @click="onNavClick"
                    >
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center text-base">{{ item.icon }}</span>
                        <span v-show="showLabels" class="min-w-0 flex-1 truncate text-left">{{ item.label }}</span>
                    </RouterLink>
                </li>
            </ul>
        </nav>
    </aside>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { studentStore, isModuleVisible, schoolInitials, toggleSidebarPin } from '../store';
import client from '../api/client';

const RAIL_MQ = '(min-width: 1024px)';
const route = useRoute();

const items = [
    { path: '/', label: 'Dashboard', icon: '🏠' },
    { path: '/profile', label: 'My Profile', icon: '🧑‍🎓', keys: ['profile'] },
    { path: '/attendance', label: 'Attendance', icon: '🗓️', keys: ['attendance'] },
    { path: '/examination', label: 'Examination', icon: '📝', keys: ['exam_schedule', 'admit_card', 'marks', 'report_card', 'subject_performance', 'previous_results'] },
    { path: '/fees', label: 'Fees', icon: '💳', keys: ['fee_summary', 'paid_fees', 'pending_fees', 'fee_history', 'receipts'] },
    { path: '/transport', label: 'Transport', icon: '🚌', keys: ['transport'], requiresTransport: true },
    { path: '/events-calendar', label: 'Events & Calendar', icon: '📅', keys: ['events_calendar'] },
    { path: '/homework', label: 'Homework', icon: '📚', keys: ['homework'] },
    { path: '/library', label: 'Library', icon: '📖', keys: ['library'] },
    { path: '/documents', label: 'My Documents', icon: '📄', keys: ['documents'] },
];

const visibleItems = computed(() =>
    items.filter((item) => {
        if (item.requiresTransport && studentStore.hasTransport === false) return false;
        if (!item.keys) return true;
        return item.keys.some((k) => isModuleVisible(k));
    }),
);

const isDesktop = ref(false);
const railHovered = ref(false);
let leaveTimer = null;

const isExpanded = computed(() => {
    if (!isDesktop.value) return true;
    return studentStore.sidebarPinned || railHovered.value;
});

const showLabels = computed(() => {
    if (!isDesktop.value) return true;
    return isExpanded.value;
});

const sidebarClasses = computed(() => {
    if (!isDesktop.value) {
        return [
            'w-[268px] transition-transform duration-200',
            studentStore.sidebarMobileOpen ? 'translate-x-0' : '-translate-x-full',
        ];
    }
    return [
        'lg:translate-x-0 transition-[width] duration-200 ease-out',
        isExpanded.value
            ? (studentStore.sidebarPinned ? 'erp-sidebar--expanded erp-sidebar--pinned w-[268px]' : 'erp-sidebar--expanded w-[268px] shadow-2xl')
            : 'erp-sidebar--rail w-16',
    ];
});

watch(() => route.fullPath, () => {
    studentStore.sidebarMobileOpen = false;
});

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
    if (!isDesktop.value || studentStore.sidebarPinned) return;
    clearLeaveTimer();
    leaveTimer = setTimeout(() => {
        railHovered.value = false;
    }, 120);
}

function onTogglePin() {
    toggleSidebarPin();
    if (studentStore.sidebarPinned) {
        clearLeaveTimer();
        railHovered.value = true;
    }
}

function onNavClick() {
    studentStore.sidebarMobileOpen = false;
}

function syncDesktop() {
    const desktop = window.matchMedia(RAIL_MQ).matches;
    isDesktop.value = desktop;
    if (!desktop) {
        railHovered.value = false;
        clearLeaveTimer();
    }
}

onMounted(async () => {
    syncDesktop();
    window.addEventListener('resize', syncDesktop);

    if (studentStore.hasTransport !== null) return;
    if (!isModuleVisible('transport')) {
        studentStore.hasTransport = false;
        return;
    }
    try {
        const { data } = await client.get('/transport');
        studentStore.hasTransport = !!data.enrolled;
    } catch {
        studentStore.hasTransport = false;
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', syncDesktop);
    clearLeaveTimer();
});
</script>

<style scoped>
.erp-sidebar {
    background: var(--erp-surface);
    border-color: var(--erp-border);
    backdrop-filter: blur(16px);
    will-change: width;
}
.erp-sidebar--rail,
.erp-sidebar--expanded {
    overflow: hidden;
}
.erp-sidebar__head { border-color: var(--erp-border); }
.erp-sidebar__mark {
    display: flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 10px; overflow: hidden; flex-shrink: 0;
    background: linear-gradient(135deg, var(--erp-brand-from), var(--erp-brand-to));
    color: var(--erp-brand-ink); font-weight: 700; font-size: 13px;
}
.erp-sidebar__logo { width: 100%; height: 100%; object-fit: cover; }
.erp-sidebar__title { color: var(--erp-cream); font-weight: 700; font-size: 14px; }
.erp-sidebar__pin { color: var(--erp-muted); }
.erp-sidebar__pin:hover {
    background: color-mix(in srgb, var(--erp-gold) 12%, transparent);
    color: var(--erp-cream);
}
.erp-sidebar__pin.is-pinned {
    background: color-mix(in srgb, var(--erp-gold) 18%, transparent);
    color: var(--erp-gold-soft, var(--erp-gold));
}
.erp-nav-item { color: var(--erp-muted); }
.erp-nav-item:hover { color: var(--erp-cream); background: color-mix(in srgb, var(--erp-gold) 8%, transparent); }
.erp-nav-item.is-active { color: var(--erp-brand-ink); background: linear-gradient(135deg, var(--erp-brand-from), var(--erp-brand-to)); }
.fade-enter-active, .fade-leave-active { transition: opacity .15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
