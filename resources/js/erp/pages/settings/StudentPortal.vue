<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 sm:text-2xl">Student Portal</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose which sections students can see when they sign in at /student/login. Disabling a section here also blocks it on the student's side, not just hides it.</p>
        </div>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
            Loading...
        </div>

        <form v-else class="space-y-5" @submit.prevent="save">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Visible Sections</h2>
                    <div class="flex gap-2">
                        <button type="button" class="btn-outline !py-1 !text-xs" @click="setAll(true)">Enable all</button>
                        <button type="button" class="btn-outline !py-1 !text-xs" @click="setAll(false)">Disable all</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-x-6 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                    <label v-for="(label, key) in modules" :key="key" class="flex items-center gap-2 rounded-lg px-2 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <input v-model="visibility[key]" type="checkbox" class="rounded border-slate-300" />
                        <span class="text-sm text-slate-700 dark:text-slate-200">{{ label }}</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save Changes' }}</button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const modules = ref({});
const visibility = reactive({});

function setAll(value) {
    for (const key of Object.keys(modules.value)) visibility[key] = value;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await client.get('/settings/student-portal');
        modules.value = data.modules;
        Object.assign(visibility, data.visibility);
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    try {
        await client.put('/settings/student-portal', { visibility: { ...visibility } });
        pushToast('Student Portal settings saved.', 'success');
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>
