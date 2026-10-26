<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ subtitle }}</p>
        </div>

        <!-- Main tabs: each one is a real URL, so refresh / back / forward keep the selected tab. -->
        <nav class="grid grid-cols-2 gap-2 sm:grid-cols-3" :class="LG_COLS[visibleTabs.length] || 'lg:grid-cols-6'" :aria-label="`${title} sections`">
            <RouterLink
                v-for="tab in visibleTabs"
                :key="tab.id"
                :to="tabTarget(tab)"
                class="erp-tab-btn flex items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 active:scale-[0.98] dark:focus-visible:ring-offset-slate-900"
                :class="tab.id === activeTab?.id
                    ? 'is-active border-primary-600 bg-primary-600 text-white shadow-md hover:bg-primary-700'
                    : 'border-slate-200 bg-white text-slate-700 hover:-translate-y-0.5 hover:border-primary-300 hover:bg-primary-50 hover:text-primary-700 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-primary-500/50 dark:hover:bg-primary-500/10 dark:hover:text-primary-300'"
                :aria-current="tab.id === activeTab?.id ? 'page' : undefined"
            >
                <AppIcon :name="tab.icon" class="erp-tab-btn__icon h-[18px] w-[18px] shrink-0 text-lg leading-none" />
                <span class="min-w-0 text-center leading-tight">{{ tab.label }}</span>
            </RouterLink>
        </nav>

        <!-- Sub-tabs, only for tabs that group more than one page. -->
        <div v-if="activeTab && activeTab.items.length > 1" class="inline-flex flex-wrap rounded-lg bg-slate-100 p-0.5 dark:bg-slate-800">
            <RouterLink
                v-for="item in activeTab.items"
                :key="item.key"
                :to="item.path"
                class="rounded-md px-3.5 py-1.5 text-sm font-medium transition"
                :class="item.path === route.path
                    ? 'bg-white text-primary-700 shadow-sm dark:bg-slate-700 dark:text-primary-300'
                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
            >
                {{ item.label }}
            </RouterLink>
        </div>

        <!-- The existing page for this URL, unchanged. -->
        <component :is="page" v-if="page" :key="route.path" />
        <div v-else-if="!visibleTabs.length" class="rounded-2xl border border-dashed border-slate-200 px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-700">
            You don't have access to any {{ title }} pages.
        </div>
    </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { usePermissions } from '../../composables/usePermissions';
import AppIcon from './AppIcon.vue';
import { erpStore } from '../../store';

/**
 * A sidebar section shown as one page with button tabs (Academics, Admissions, …).
 * Every tab / sub-tab is the page's existing URL; the router renders the hub for those URLs and
 * passes the original page component in route.meta.hubPage, so each page keeps its own lists,
 * forms, filters and permissions.
 */
const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    moduleKey: { type: String, required: true }, // permission prefix, e.g. 'academics' → academics.<item>.view
    // [{ id, label, icon, items: [{ key, label, path }] }]
    tabs: { type: Array, required: true },
});

const LG_COLS = { 1: 'lg:grid-cols-1', 2: 'lg:grid-cols-2', 3: 'lg:grid-cols-3', 4: 'lg:grid-cols-4', 5: 'lg:grid-cols-5', 6: 'lg:grid-cols-6' };

const route = useRoute();
const router = useRouter();
const { can } = usePermissions();

// Same rule as the sidebar: a page is shown when the user can view it.
const visibleTabs = computed(() => props.tabs
    .map((tab) => ({ ...tab, items: tab.items.filter((i) => (!erpStore.permissionsLoaded && erpStore.user?.role === 'admin') || can(`${props.moduleKey}.${i.key}.view`)) }))
    .filter((tab) => tab.items.length));

const activeTab = computed(() => visibleTabs.value.find((t) => t.items.some((i) => i.path === route.path)) || null);
const page = computed(() => route.meta.hubPage || null);

// Clicking the tab you're on stays put; another tab opens its first page.
function tabTarget(tab) {
    return tab.id === activeTab.value?.id ? route.path : tab.items[0].path;
}

// Bare section URL (e.g. /academics) → first page this user may open.
watch(() => [route.path, visibleTabs.value.length], () => {
    if (!page.value && visibleTabs.value.length) router.replace(visibleTabs.value[0].items[0].path);
}, { immediate: true });
</script>
