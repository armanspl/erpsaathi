<template>
    <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Class</p>
                <select v-model="schoolClassId" class="form-input" @change="sectionId = null">
                    <option :value="null">Select class</option>
                    <option value="all">All Classes</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>
            <div>
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Section</p>
                <select v-model.number="sectionId" class="form-input" :disabled="!schoolClassId || schoolClassId === 'all'">
                    <option :value="null">All sections</option>
                    <option v-for="s in sectionsForClass" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div>
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Term</p>
                <select v-model.number="academicTermId" class="form-input">
                    <option :value="null">{{ terms.length ? 'Select term' : 'Loading...' }}</option>
                    <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
            </div>
        </div>

        <p class="text-[11px] text-slate-400">
            One row per student: Adm No., Name, Roll No., subject-wise final marks, Total Marks, %age, Rank, Attendance and Attdn %age — same layout as the school's own Exam Cross List workbook.
        </p>

        <div>
            <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="exporting || !canExport" @click="emitExport">
                {{ exporting ? 'Exporting...' : '📊 Excel (.xlsx)' }}
            </button>
            <p v-if="!canExport" class="mt-1 text-[11px] text-rose-500">Select a class (or All Classes) and a term to export.</p>
            <p v-else-if="schoolClassId === 'all'" class="mt-1 text-[11px] text-slate-400">Exports one workbook with a separate sheet per class.</p>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import client from '../../api/client';
import { fetchAcademicsLookups } from '../../api/academics';

defineProps({
    exporting: { type: Boolean, default: false },
});

const emit = defineEmits(['export']);

const classes = ref([]);
const sections = ref([]);
const terms = ref([]);

const schoolClassId = ref(null);
const sectionId = ref(null);
const academicTermId = ref(null);

const sectionsForClass = computed(() => {
    if (!schoolClassId.value || schoolClassId.value === 'all') return [];
    return sections.value.filter((s) => s.school_class_id === schoolClassId.value);
});

const canExport = computed(() => !!schoolClassId.value && !!academicTermId.value);

async function loadLookups() {
    const [academics, termsRes] = await Promise.all([
        fetchAcademicsLookups(),
        client.get('/exams/terms').catch(() => ({ data: [] })),
    ]);
    classes.value = academics.classes || [];
    sections.value = academics.sections || [];
    terms.value = termsRes.data || [];
}
onMounted(loadLookups);

function emitExport() {
    if (!canExport.value) return;
    emit('export', {
        school_class_id: schoolClassId.value === 'all' ? null : schoolClassId.value,
        section_id: sectionId.value,
        academic_term_id: academicTermId.value,
    });
}
</script>
