<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">System Health</h1>
            <Breadcrumb :items="['Dashboard', 'System', 'System Health']" class="mt-1" />
        </div>

        <template v-if="health">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Database" :value="health.database.status" :color="health.database.status === 'Connected' ? 'emerald' : 'rose'" icon="🗄️" />
                <StatCard label="Storage" :value="health.storage_writable ? 'Writable' : 'Read-only'" :color="health.storage_writable ? 'emerald' : 'rose'" icon="💾" />
                <StatCard label="Environment" :value="health.environment" color="indigo" icon="⚙️" />
                <StatCard label="Debug Mode" :value="health.debug_mode ? 'On' : 'Off'" :color="health.debug_mode ? 'amber' : 'emerald'" icon="🐞" />
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">PHP Version</td>
                            <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ health.php_version }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">Laravel Version</td>
                            <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ health.laravel_version }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">Database Connection</td>
                            <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ health.database.connection }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">Cache Driver</td>
                            <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ health.cache_driver }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">Queue Driver</td>
                            <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ health.queue_driver }}</td>
                        </tr>
                        <tr v-if="health.disk_free_space_gb !== null">
                            <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">Free Disk Space</td>
                            <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ health.disk_free_space_gb }} GB</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-200">Server Time</td>
                            <td class="px-4 py-3 text-right text-slate-500 dark:text-slate-400">{{ health.server_time }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import client from '../../api/client';

const health = ref(null);

client.get('/system/health').then(({ data }) => (health.value = data));
</script>
