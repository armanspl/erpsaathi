<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Terms</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Configure academic terms for this session, then assign assessments under Exams. Session: {{ currentSessionName || '\u2014' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-outline" :disabled="setupping" @click="setupSession">
                    {{ setupping ? 'Setting up...' : 'Setup Term-1 / Term-2' }}
                </button>
                <button type="button" class="btn-primary" @click="openCreate">Add term</button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div v-if="loading" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
            <div v-else-if="!terms.length" class="px-6 py-16 text-center text-sm text-slate-400">
                No terms yet. Use &quot;Setup Term-1 / Term-2&quot; or add terms manually.
            </div>
            <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                <div v-for="term in terms" :key="term.id" class="px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ term.name }}</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Sort {{ term.sort_order }} · Max {{ term.max_marks }} · {{ (term.exams || []).length }} assessment(s)</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn-outline text-xs" @click="openEdit(term)">Edit</button>
                            <button type="button" class="btn-outline text-xs text-rose-600" @click="remove(term)">Delete</button>
                        </div>
                    </div>
                    <ul v-if="term.exams?.length" class="mt-3 flex flex-wrap gap-2">
                        <li
                            v-for="e in term.exams"
                            :key="e.id"
                            class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        >
                            {{ e.name }}
                            <span v-if="e.is_internal_component" class="text-slate-400">(internal)</span>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-xs text-slate-400">No exams assigned — open Exams and pick a term.</p>
                </div>
            </div>
        </div>

        <div v-if="formOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="formOpen = false" />
            <div class="relative z-10 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit term' : 'Add term' }}</h2>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="form-label">Name</label>
                        <input v-model="form.name" type="text" class="form-input" placeholder="e.g. Term-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Sort order</label>
                            <input v-model.number="form.sort_order" type="number" min="0" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Max marks</label>
                            <input v-model.number="form.max_marks" type="number" min="0" class="form-input" />
                        </div>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="formOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import client from '../../api/client';
import { erpStore } from '../../store';
import { pushToast } from '../../utils/toast';

const terms = ref([]);
const loading = ref(true);
const setupping = ref(false);
const currentSessionName = ref(erpStore.currentSession || '');
const formOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const form = reactive({ name: '', sort_order: 1, max_marks: 100 });

async function load() {
    loading.value = true;
    try {
        const [{ data }, sessionsRes] = await Promise.all([
            client.get('/exams/terms'),
            client.get('/settings/academic-sessions'),
        ]);
        terms.value = data || [];
        currentSessionName.value = erpStore.currentSession || sessionsRes.data.find((s) => s.is_current)?.name || '';
    } catch (e) {
        pushToast(e?.response?.data?.message || e.message || 'Failed to load terms', 'error');
    } finally {
        loading.value = false;
    }
}
load();

function openCreate() {
    editing.value = null;
    Object.assign(form, { name: '', sort_order: (terms.value.length || 0) + 1, max_marks: 100 });
    formOpen.value = true;
}
function openEdit(term) {
    editing.value = term;
    Object.assign(form, { name: term.name, sort_order: term.sort_order, max_marks: Number(term.max_marks) });
    formOpen.value = true;
}

async function save() {
    if (!form.name.trim()) {
        pushToast('Name is required.', 'error');
        return;
    }
    saving.value = true;
    try {
        if (editing.value) {
            await client.put(`/exams/terms/${editing.value.id}`, { ...form });
            pushToast('Term updated');
        } else {
            await client.post('/exams/terms', { ...form });
            pushToast('Term created');
        }
        formOpen.value = false;
        await load();
    } catch (e) {
        pushToast(e?.response?.data?.message || e.message || 'Save failed', 'error');
    } finally {
        saving.value = false;
    }
}

async function remove(term) {
    if (!window.confirm(`Delete "${term.name}"? Exams will be unassigned from this term.`)) return;
    await client.delete(`/exams/terms/${term.id}`);
    pushToast('Term deleted');
    await load();
}

async function setupSession() {
    setupping.value = true;
    try {
        const { data } = await client.post('/exams/terms/setup-session');
        terms.value = data.terms || [];
        pushToast(data.message || 'Terms set up');
    } catch (e) {
        pushToast(e?.response?.data?.message || e.message || 'Setup failed', 'error');
    } finally {
        setupping.value = false;
    }
}
</script>
