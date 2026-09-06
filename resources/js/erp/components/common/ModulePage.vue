<template>
    <div class="space-y-5">
        <!-- Title + Breadcrumb + Actions -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ config.title }}</h1>
                <Breadcrumb :items="config.breadcrumb" class="mt-1" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button v-if="config.actions.includes('import')" type="button" class="btn-outline" @click="simulate('Import')">
                    <IconUpload /> Import
                </button>
                <button v-if="config.actions.includes('export')" type="button" class="btn-outline" @click="simulate('Export')">
                    <IconDownload /> Export
                </button>
                <button v-if="config.actions.includes('template')" type="button" class="btn-outline hidden sm:inline-flex" @click="simulate('Template downloaded')">
                    <IconFile /> Download Template
                </button>
                <button v-if="config.actions.includes('print')" type="button" class="btn-outline" @click="simulate('Sent to printer')">
                    <IconPrint /> Print
                </button>
                <button v-if="config.actions.includes('add') && !config.hideAddButton" type="button" class="btn-primary" @click="openAdd">
                    <IconPlus /> Add {{ singular }}
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <FilterBar :filters="config.filters" v-model="filterValues" :label="config.title" @reset="resetFilters" />

        <!-- Stat Cards -->
        <div v-if="config.stats.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <StatCard v-for="(s, i) in config.stats" :key="s.label" :label="s.label" :value="statDisplay(s, i)" :color="s.color" icon="●" />
        </div>

        <!-- Data Table -->
        <DataTable :columns="config.columns" :rows="pagedRows" :actions="rowActions" @action="onRowAction" />

        <!-- Pagination -->
        <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <Pagination v-model="page" :per-page="perPage" :total="filteredRows.length" />
        </div>

        <DrawerForm :open="drawerOpen" :title="`Add ${singular}`" :columns="config.columns" @close="drawerOpen = false" @save="onSave" />
    </div>
</template>

<script setup>
import { computed, h, reactive, ref, watch } from 'vue';
import Breadcrumb from './Breadcrumb.vue';
import FilterBar from './FilterBar.vue';
import StatCard from './StatCard.vue';
import DataTable from './DataTable.vue';
import Pagination from './Pagination.vue';
import DrawerForm from './DrawerForm.vue';
import { generateRows, seededRandom } from '../../utils/mock';
import { pushToast } from '../../utils/toast';
import { useRoute } from 'vue-router';

const props = defineProps({
    config: { type: Object, required: true },
    seed: { type: [Number, String], default: 1 },
    rowCount: { type: Number, default: 46 },
});

const singular = computed(() => props.config.title.replace(/s$/, ''));
const rowActions = ['view', 'edit', 'delete', 'print'];

const route = useRoute();

function hashSeed(str) {
    let h = 0;
    for (let i = 0; i < str.length; i++) h = (h * 31 + str.charCodeAt(i)) >>> 0;
    return h || 1;
}

const baseRows = ref(generateRows(props.config.columns, props.rowCount, hashSeed(String(props.seed))));

const filterValues = reactive({});
const page = ref(1);
const perPage = 10;
const drawerOpen = ref(route.query.add === '1');

function resetFilters() {
    Object.keys(filterValues).forEach((k) => delete filterValues[k]);
}

const filteredRows = computed(() => {
    return baseRows.value.filter((row) => {
        if (filterValues.search) {
            const q = filterValues.search.toLowerCase();
            const hay = Object.values(row).join(' ').toLowerCase();
            if (!hay.includes(q)) return false;
        }
        if (filterValues.status && row.status && row.status !== filterValues.status) return false;
        if (filterValues.class && row.class && row.class !== filterValues.class) return false;
        if (filterValues.section && row.section && row.section !== filterValues.section) return false;
        return true;
    });
});

watch(filteredRows, () => (page.value = 1));

const pagedRows = computed(() => {
    const start = (page.value - 1) * perPage;
    return filteredRows.value.slice(start, start + perPage);
});

function statDisplay(stat, idx) {
    const rand = seededRandom(hashSeed(props.config.title + stat.label + idx));
    const label = stat.label.toLowerCase();
    if (label.includes('%')) return `${Math.floor(60 + rand() * 39)}%`;
    if (['income', 'expense', 'balance', 'cash', 'bank', 'fine', 'collection', 'fee', 'value', 'due', 'discount'].some((k) => label.includes(k))) {
        return `₹${Math.floor(5000 + rand() * 495000).toLocaleString('en-IN')}`;
    }
    if (label === 'total') return baseRows.value.length;
    if (label === 'active') return baseRows.value.filter((r) => r.status === 'Active').length;
    if (label === 'inactive') return baseRows.value.filter((r) => r.status === 'Inactive').length;
    return Math.floor(3 + rand() * 240);
}

function simulate(msg) {
    pushToast(`${msg} — demo simulation for ${props.config.title}.`, 'info');
}

function openAdd() {
    drawerOpen.value = true;
}

function onSave(data) {
    baseRows.value = [{ id: Date.now(), __name: data.name || data.code, ...data }, ...baseRows.value];
    drawerOpen.value = false;
    pushToast(`${singular.value} added successfully.`, 'success');
}

function onRowAction({ type, row }) {
    if (type === 'delete') {
        baseRows.value = baseRows.value.filter((r) => r.id !== row.id);
        pushToast(`${singular.value} deleted.`, 'success');
        return;
    }
    pushToast(`${type[0].toUpperCase()}${type.slice(1)} — demo action on "${row.__name || row.name || row.code}".`, 'info');
}

// tiny inline icon helpers (kept local to avoid a global icon registry for a handful of glyphs)
const IconUpload = () => h('svg', { class: 'h-4 w-4', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M12 16V4m0 0 4 4m-4-4L8 8M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2' })]);
const IconDownload = () => h('svg', { class: 'h-4 w-4', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M12 4v12m0 0 4-4m-4 4-4-4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2' })]);
const IconFile = () => h('svg', { class: 'h-4 w-4', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z' }), h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M14 2v6h6' })]);
const IconPrint = () => h('svg', { class: 'h-4 w-4', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M6 9V2h12v7M6 18H4a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2M6 14h12v8H6Z' })]);
const IconPlus = () => h('svg', { class: 'h-4 w-4', viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': '2.5' }, [h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M12 5v14M5 12h14' })]);
</script>
