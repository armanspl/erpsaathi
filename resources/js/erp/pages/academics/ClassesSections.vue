<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Classes &amp; Sections</h1>
                <Breadcrumb :items="['Dashboard', 'Academics', 'Classes & Sections']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAddClass">+ Add Class</button>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <StatCard label="Total Classes" :value="classes.length" color="indigo" icon="🏷️" />
            <StatCard label="Total Sections" :value="totalSections" color="sky" icon="🧩" />
            <StatCard label="Total Capacity" :value="totalCapacity" color="emerald" icon="👥" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Class</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Sections</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Subjects</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Capacity</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!classes.length">
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No classes yet.</td>
                    </tr>
                    <tr v-for="c in classes" :key="c.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ c.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            <span v-if="(c.sections || []).length">{{ c.sections.map((s) => s.name).join(', ') }}</span>
                            <span v-else class="text-slate-300">None</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ (c.subjects || []).length }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ c.capacity ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Manage Sections" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openSections(c)">🧩</button>
                                <button type="button" title="Manage Subjects" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openSubjects(c)">📖</button>
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEditClass(c)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="removeClass(c)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Add / Edit Class -->
        <SlideOver :open="classDrawerOpen" :title="editingClass ? 'Edit Class' : 'Add Class'" @close="classDrawerOpen = false">
            <div>
                <label class="form-label">Class Name</label>
                <input v-model="classForm.name" type="text" class="form-input" placeholder="e.g. 11 or Nursery" required />
            </div>
            <div>
                <label class="form-label">Capacity</label>
                <input v-model.number="classForm.capacity" type="number" class="form-input" />
            </div>
            <div>
                <label class="form-label">Sort Order</label>
                <input v-model.number="classForm.sort_order" type="number" class="form-input" />
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="classDrawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="saveClass">{{ saving ? 'Saving...' : 'Save' }}</button>
            </template>
        </SlideOver>

        <!-- Manage Sections -->
        <SlideOver :open="sectionsDrawerOpen" :title="`Sections — Class ${activeClass?.name}`" @close="sectionsDrawerOpen = false">
            <div class="space-y-2">
                <div v-for="s in activeClass?.sections" :key="s.id" class="flex items-center gap-2 rounded-lg border border-slate-100 p-2.5 dark:border-slate-800">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Section {{ s.name }}</p>
                        <p class="text-xs text-slate-400">Capacity: {{ s.capacity ?? '—' }} • Teacher: {{ s.class_teacher || '—' }}</p>
                    </div>
                    <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEditSection(s)">✎</button>
                    <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="removeSection(s)">🗑</button>
                </div>
                <p v-if="!activeClass?.sections.length" class="py-4 text-center text-sm text-slate-400">No sections yet.</p>
            </div>

            <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                <p class="form-label">{{ editingSection ? 'Edit Section' : 'Add New Section' }}</p>
                <div class="space-y-3">
                    <input v-model="sectionForm.name" type="text" class="form-input" placeholder="Section name, e.g. A" />
                    <input v-model.number="sectionForm.capacity" type="number" class="form-input" placeholder="Capacity" />
                    <input v-model="sectionForm.class_teacher" type="text" class="form-input" placeholder="Class Teacher (optional)" />
                    <div class="flex gap-2">
                        <button v-if="editingSection" type="button" class="btn-outline flex-1" @click="cancelEditSection">Cancel</button>
                        <button type="button" class="btn-primary flex-1" :disabled="saving" @click="saveSection">{{ editingSection ? 'Update Section' : '+ Add Section' }}</button>
                    </div>
                </div>
            </div>
            <template #footer>
                <button type="button" class="btn-outline" @click="sectionsDrawerOpen = false">Close</button>
            </template>
        </SlideOver>

        <!-- Manage Subjects -->
        <SlideOver :open="subjectsDrawerOpen" :title="`Subjects — Class ${activeClass?.name}`" @close="subjectsDrawerOpen = false">
            <div class="grid grid-cols-2 gap-2">
                <label v-for="subject in allSubjects" :key="subject.id" class="flex items-center gap-2 rounded-lg border border-slate-100 p-2.5 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300">
                    <input v-model="selectedSubjectIds" type="checkbox" :value="subject.id" class="h-3.5 w-3.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500" />
                    {{ subject.name }} <span class="text-xs text-slate-400">({{ subject.code }})</span>
                </label>
            </div>
            <p v-if="!allSubjects.length" class="py-4 text-center text-sm text-slate-400">No subjects created yet. Add subjects first.</p>
            <template #footer>
                <button type="button" class="btn-outline" @click="subjectsDrawerOpen = false">Cancel</button>
                <button type="button" class="btn-primary" :disabled="saving" @click="saveSubjectAssignment">{{ saving ? 'Saving...' : 'Save Assignment' }}</button>
            </template>
        </SlideOver>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import { fetchClassesFull, invalidateAcademicsLookups } from '../../api/academics';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const saving = ref(false);
const classes = ref([]);
const allSubjects = ref([]);

const totalSections = computed(() => classes.value.reduce((sum, c) => sum + (c.sections || []).length, 0));
const totalCapacity = computed(() => classes.value.reduce((sum, c) => sum + (c.capacity || 0), 0));

async function load() {
    loading.value = true;
    const [fullClasses, subjectsRes] = await Promise.all([
        fetchClassesFull(),
        client.get('/academics/subjects'),
    ]);
    classes.value = fullClasses;
    allSubjects.value = subjectsRes.data;
    loading.value = false;

    if (activeClass.value) {
        activeClass.value = classes.value.find((c) => c.id === activeClass.value.id) || null;
    }
}
load();

// --- Class CRUD ---
const classDrawerOpen = ref(false);
const editingClass = ref(null);
const classForm = reactive({ name: '', capacity: null, sort_order: 0 });

function openAddClass() {
    editingClass.value = null;
    Object.assign(classForm, { name: '', capacity: null, sort_order: 0 });
    classDrawerOpen.value = true;
}
function openEditClass(schoolClass) {
    editingClass.value = schoolClass;
    Object.assign(classForm, { name: schoolClass.name, capacity: schoolClass.capacity, sort_order: schoolClass.sort_order });
    classDrawerOpen.value = true;
}
async function saveClass() {
    saving.value = true;
    try {
        if (editingClass.value) {
            await client.put(`/academics/classes/${editingClass.value.id}`, classForm);
            pushToast('Class updated.', 'success');
        } else {
            await client.post('/academics/classes', classForm);
            pushToast('Class added.', 'success');
        }
        classDrawerOpen.value = false;
        invalidateAcademicsLookups();
        await load();
    } finally {
        saving.value = false;
    }
}
async function removeClass(schoolClass) {
    classes.value = classes.value.filter((c) => c.id !== schoolClass.id);
    await client.delete(`/academics/classes/${schoolClass.id}`);
    invalidateAcademicsLookups();
    pushToast(`Class "${schoolClass.name}" deleted.`, 'success');
}

// --- Sections (class-section mapping) ---
const sectionsDrawerOpen = ref(false);
const activeClass = ref(null);
const editingSection = ref(null);
const sectionForm = reactive({ name: '', capacity: null, class_teacher: '' });

function openSections(schoolClass) {
    activeClass.value = schoolClass;
    cancelEditSection();
    sectionsDrawerOpen.value = true;
}
function openEditSection(section) {
    editingSection.value = section;
    Object.assign(sectionForm, { name: section.name, capacity: section.capacity, class_teacher: section.class_teacher || '' });
}
function cancelEditSection() {
    editingSection.value = null;
    Object.assign(sectionForm, { name: '', capacity: null, class_teacher: '' });
}
async function saveSection() {
    saving.value = true;
    try {
        const payload = { ...sectionForm, school_class_id: activeClass.value.id };
        if (editingSection.value) {
            await client.put(`/academics/sections/${editingSection.value.id}`, payload);
            pushToast('Section updated.', 'success');
        } else {
            await client.post('/academics/sections', payload);
            pushToast('Section added.', 'success');
        }
        cancelEditSection();
        invalidateAcademicsLookups();
        await load();
    } finally {
        saving.value = false;
    }
}
async function removeSection(section) {
    activeClass.value.sections = activeClass.value.sections.filter((s) => s.id !== section.id);
    await client.delete(`/academics/sections/${section.id}`);
    invalidateAcademicsLookups();
    pushToast(`Section "${section.name}" deleted.`, 'success');
    await load();
}

// --- Class-Subject assignment ---
const subjectsDrawerOpen = ref(false);
const selectedSubjectIds = ref([]);

function openSubjects(schoolClass) {
    activeClass.value = schoolClass;
    selectedSubjectIds.value = (schoolClass.subjects || []).map((s) => s.id);
    subjectsDrawerOpen.value = true;
}
async function saveSubjectAssignment() {
    saving.value = true;
    try {
        await client.put(`/academics/classes/${activeClass.value.id}/subjects`, { subject_ids: selectedSubjectIds.value });
        pushToast('Subject assignment saved.', 'success');
        subjectsDrawerOpen.value = false;
        invalidateAcademicsLookups();
        await load();
    } finally {
        saving.value = false;
    }
}
</script>
