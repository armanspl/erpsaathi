<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Grade System</h1>
                <Breadcrumb :items="['Dashboard', 'Exam Management', 'Grade System']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Grade Band</button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <StatCard label="Total Grade Bands" :value="grades.length" color="indigo" icon="🎓" />
            <StatCard label="Coverage" :value="`${coverage}%`" color="emerald" icon="📏" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Grade</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Min %</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Max %</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!grades.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No grade bands defined yet.</td>
                    </tr>
                    <tr v-for="g in grades" :key="g.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-semibold text-slate-800 dark:text-slate-100">{{ g.grade }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ g.min_percentage }}%</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ g.max_percentage }}%</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ g.remarks || '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(g)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(g)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Grade Band' : 'Add Grade Band'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Grade</label>
                <input v-model="form.grade" type="text" class="form-input" placeholder="A+, A, B..." required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Min %</label>
                    <input v-model.number="form.min_percentage" type="number" step="0.01" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Max %</label>
                    <input v-model.number="form.max_percentage" type="number" step="0.01" class="form-input" />
                </div>
            </div>
            <div>
                <label class="form-label">Remarks</label>
                <input v-model="form.remarks" type="text" class="form-input" placeholder="Outstanding, Excellent..." />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const grades = ref([]);
const drawerOpen = ref(false);
const editing = ref(null);

const form = reactive({ grade: '', min_percentage: null, max_percentage: null, remarks: '' });

const coverage = computed(() => {
    if (!grades.value.length) return 0;
    const min = Math.min(...grades.value.map((g) => Number(g.min_percentage)));
    const max = Math.max(...grades.value.map((g) => Number(g.max_percentage)));
    return Math.round(max - min + 1 >= 100 ? 100 : max - min);
});

async function load() {
    loading.value = true;
    const { data } = await client.get('/exams/grade-system');
    grades.value = data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { grade: '', min_percentage: null, max_percentage: null, remarks: '' });
    drawerOpen.value = true;
}

function openEdit(grade) {
    editing.value = grade;
    Object.assign(form, { grade: grade.grade, min_percentage: Number(grade.min_percentage), max_percentage: Number(grade.max_percentage), remarks: grade.remarks || '' });
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/exams/grade-system/${editing.value.id}`, form);
            pushToast('Grade band updated.', 'success');
        } else {
            await client.post('/exams/grade-system', form);
            pushToast('Grade band added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(grade) {
    grades.value = grades.value.filter((g) => g.id !== grade.id);
    await client.delete(`/exams/grade-system/${grade.id}`);
    pushToast(`Grade "${grade.grade}" deleted.`, 'success');
}
</script>
