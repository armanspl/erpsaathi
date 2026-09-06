<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Admission Settings</h1>
                <Breadcrumb :items="['Dashboard', 'Admissions', 'Admission Settings']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Field</button>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">More fields</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Custom fields appear below previous school on the admission form and on student create/edit.
            </p>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Label</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Placeholder</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!fields.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No custom fields yet. Click Add Field to create one.</td>
                    </tr>
                    <tr v-for="field in fields" :key="field.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ field.label }}</td>
                        <td class="px-4 py-3 capitalize text-slate-500 dark:text-slate-400">{{ field.type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ field.placeholder || '—' }}</td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors"
                                :class="field.is_active ? 'bg-primary-600' : 'bg-slate-200 dark:bg-slate-700'"
                                role="switch"
                                :aria-checked="field.is_active"
                                @click="toggleActive(field)"
                            >
                                <span
                                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
                                    :class="field.is_active ? 'translate-x-5' : 'translate-x-0'"
                                />
                            </button>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 dark:text-slate-300 dark:ring-slate-600" @click="openEdit(field)">Edit</button>
                                <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-rose-600 ring-1 ring-inset ring-rose-200 hover:bg-rose-50 dark:text-rose-400 dark:ring-rose-500/30" @click="remove(field)">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Field' : 'Add Field'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Label</label>
                <input v-model="form.label" type="text" class="form-input" required />
            </div>
            <div>
                <label class="form-label">Type</label>
                <select v-model="form.type" class="form-input">
                    <option value="text">Text</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="textarea">Textarea</option>
                </select>
            </div>
            <div>
                <label class="form-label">Placeholder</label>
                <input v-model="form.placeholder" type="text" class="form-input" />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                Active
            </label>
            <template #footer>
                <button type="button" class="btn-outline" @click="drawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import { invalidateAdmissionsLookups } from '../../api/admissions';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const fields = ref([]);
const drawerOpen = ref(false);
const editing = ref(null);
const form = reactive({ label: '', type: 'text', placeholder: '', is_active: true });

async function load() {
    loading.value = true;
    const { data } = await client.get('/admissions/custom-fields');
    fields.value = data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { label: '', type: 'text', placeholder: '', is_active: true });
    drawerOpen.value = true;
}

function openEdit(field) {
    editing.value = field;
    Object.assign(form, {
        label: field.label,
        type: field.type,
        placeholder: field.placeholder || '',
        is_active: !!field.is_active,
    });
    drawerOpen.value = true;
}

async function save() {
    if (!form.label.trim()) {
        pushToast('Label is required.', 'error');
        return;
    }
    saving.value = true;
    try {
        if (editing.value) {
            const { data } = await client.put(`/admissions/custom-fields/${editing.value.id}`, { ...form });
            const idx = fields.value.findIndex((f) => f.id === data.id);
            if (idx !== -1) fields.value[idx] = data;
            pushToast('Field updated.', 'success');
        } else {
            await client.post('/admissions/custom-fields', { ...form });
            pushToast('Field added.', 'success');
            await load();
        }
        invalidateAdmissionsLookups();
        drawerOpen.value = false;
    } catch (err) {
        pushToast(err?.response?.data?.message || 'Save failed.', 'error');
    } finally {
        saving.value = false;
    }
}

async function toggleActive(field) {
    const next = !field.is_active;
    field.is_active = next;
    try {
        const { data } = await client.put(`/admissions/custom-fields/${field.id}`, { is_active: next });
        Object.assign(field, data);
        invalidateAdmissionsLookups();
    } catch {
        field.is_active = !next;
        pushToast('Could not update status.', 'error');
    }
}

async function remove(field) {
    if (!confirm(`Delete field "${field.label}"?`)) return;
    await client.delete(`/admissions/custom-fields/${field.id}`);
    fields.value = fields.value.filter((f) => f.id !== field.id);
    invalidateAdmissionsLookups();
    pushToast('Field deleted.', 'success');
}
</script>
