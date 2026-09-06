<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Notifications</h1>
                <Breadcrumb :items="['Dashboard', 'Account', 'Notifications']" class="mt-1" />
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Live operational alerts for the selected academic session.
                </p>
            </div>
            <button type="button" class="btn-outline" :disabled="loading" @click="load">
                {{ loading ? 'Refreshing...' : 'Refresh' }}
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatCard label="Active alerts" :value="totalCount" color="rose" icon="🔔" />
            <StatCard label="Categories" :value="items.length" color="indigo" icon="📋" />
            <StatCard label="Clear" :value="clearCount" color="emerald" icon="✅" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <li v-if="loading && !items.length" class="px-5 py-12 text-center text-sm text-slate-400">Loading notifications...</li>
                <li v-else-if="!items.length" class="px-5 py-12 text-center text-sm text-slate-400">No notification categories available.</li>
                <li
                    v-for="n in items"
                    :key="n.key || n.label"
                    class="flex cursor-pointer items-start gap-4 px-5 py-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/40"
                    @click="go(n)"
                >
                    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xl dark:bg-slate-800">{{ n.icon }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-slate-800 dark:text-slate-100">{{ n.label }}</p>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="Number(n.count) > 0
                                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300'
                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                            >
                                {{ Number(n.count) > 0 ? n.count : 'None' }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ n.hint }}</p>
                    </div>
                    <span class="mt-2 text-slate-300 dark:text-slate-600">→</span>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';
import { erpStore } from '../../store';

const router = useRouter();

const loading = ref(false);
const items = ref([]);
const totalCount = ref(0);

const clearCount = computed(() => items.value.filter((n) => Number(n.count) === 0).length);

async function load() {
    loading.value = true;
    try {
        const { data } = await client.get('/notifications');
        items.value = Array.isArray(data?.items) ? data.items : [];
        totalCount.value = Number(data?.total ?? items.value.reduce((s, n) => s + Number(n.count || 0), 0));
    } catch {
        items.value = [];
        totalCount.value = 0;
    } finally {
        loading.value = false;
    }
}

function go(n) {
    if (n?.path) router.push(n.path);
}

onMounted(load);
watch(() => erpStore.currentSession, load);
</script>
