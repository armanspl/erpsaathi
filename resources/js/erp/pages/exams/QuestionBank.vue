<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Question Bank</h1>
                <Breadcrumb :items="['Dashboard', 'Exam Management', 'Question Bank']" class="mt-1" />
            </div>
            <button type="button" class="btn-primary" @click="openAdd">+ Add Question</button>
        </div>

        <FilterBar :filters="filters" v-model="filterValues" label="questions" @reset="filterValues = {}" />

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard label="Total Questions" :value="questions.length" color="indigo" icon="❓" />
            <StatCard label="MCQ" :value="questions.filter((q) => q.question_type === 'MCQ').length" color="sky" icon="🔘" />
            <StatCard label="Easy" :value="questions.filter((q) => q.difficulty === 'Easy').length" color="emerald" icon="🟢" />
            <StatCard label="Hard" :value="questions.filter((q) => q.difficulty === 'Hard').length" color="rose" icon="🔴" />
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Question</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Difficulty</th>
                        <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Marks</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">Loading...</td>
                    </tr>
                    <tr v-else-if="!filteredQuestions.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">No questions match your filters.</td>
                    </tr>
                    <tr v-for="q in filteredQuestions" :key="q.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                        <td class="max-w-xs truncate px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ q.question_text }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ q.subject.name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ q.question_type }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ q.difficulty }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ q.marks }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Edit" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800" @click="openEdit(q)">✎</button>
                                <button type="button" title="Delete" class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800" @click="remove(q)">🗑</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SlideOver :open="drawerOpen" :title="editing ? 'Edit Question' : 'Add Question'" @close="drawerOpen = false">
            <div>
                <label class="form-label">Subject</label>
                <select v-model="form.subject_id" class="form-input">
                    <option :value="null">Select subject</option>
                    <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">Question</label>
                <textarea v-model="form.question_text" rows="3" class="form-input" required />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Type</label>
                    <select v-model="form.question_type" class="form-input">
                        <option>MCQ</option>
                        <option>Short Answer</option>
                        <option>Long Answer</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Difficulty</label>
                    <select v-model="form.difficulty" class="form-input">
                        <option>Easy</option>
                        <option>Medium</option>
                        <option>Hard</option>
                    </select>
                </div>
            </div>
            <div v-if="form.question_type === 'MCQ'" class="space-y-2">
                <label class="form-label">Options (one per line)</label>
                <textarea v-model="optionsText" rows="4" class="form-input" placeholder="Option A&#10;Option B&#10;Option C&#10;Option D" />
                <label class="form-label">Correct Answer</label>
                <input v-model="form.correct_answer" type="text" class="form-input" placeholder="Must match one of the options above" />
            </div>
            <div>
                <label class="form-label">Marks</label>
                <input v-model.number="form.marks" type="number" step="0.5" class="form-input" />
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
import FilterBar from '../../components/common/FilterBar.vue';
import StatCard from '../../components/common/StatCard.vue';
import SlideOver from '../../components/common/SlideOver.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const filters = [
    { key: 'search', label: 'Search', type: 'search' },
    { key: 'status', label: 'Difficulty', type: 'select', options: ['Easy', 'Medium', 'Hard'] },
];

const loading = ref(true);
const saving = ref(false);
const questions = ref([]);
const subjects = ref([]);
const filterValues = reactive({});
const drawerOpen = ref(false);
const editing = ref(null);
const optionsText = ref('');

const form = reactive({ subject_id: null, question_text: '', question_type: 'MCQ', options: null, correct_answer: '', marks: 1, difficulty: 'Medium' });

const filteredQuestions = computed(() =>
    questions.value.filter((q) => {
        if (filterValues.search && !q.question_text.toLowerCase().includes(filterValues.search.toLowerCase())) return false;
        if (filterValues.status && q.difficulty !== filterValues.status) return false;
        return true;
    }),
);

async function load() {
    loading.value = true;
    const [questionsRes, subjectsRes] = await Promise.all([client.get('/exams/questions'), client.get('/academics/subjects')]);
    questions.value = questionsRes.data;
    subjects.value = subjectsRes.data;
    loading.value = false;
}
load();

function openAdd() {
    editing.value = null;
    Object.assign(form, { subject_id: null, question_text: '', question_type: 'MCQ', options: null, correct_answer: '', marks: 1, difficulty: 'Medium' });
    optionsText.value = '';
    drawerOpen.value = true;
}

function openEdit(question) {
    editing.value = question;
    Object.assign(form, {
        subject_id: question.subject.id,
        question_text: question.question_text,
        question_type: question.question_type,
        options: question.options,
        correct_answer: question.correct_answer || '',
        marks: Number(question.marks),
        difficulty: question.difficulty,
    });
    optionsText.value = (question.options || []).join('\n');
    drawerOpen.value = true;
}

async function save() {
    saving.value = true;
    try {
        const payload = { ...form, options: form.question_type === 'MCQ' ? optionsText.value.split('\n').map((o) => o.trim()).filter(Boolean) : null };
        if (editing.value) {
            await client.put(`/exams/questions/${editing.value.id}`, payload);
            pushToast('Question updated.', 'success');
        } else {
            await client.post('/exams/questions', payload);
            pushToast('Question added.', 'success');
        }
        drawerOpen.value = false;
        await load();
    } finally {
        saving.value = false;
    }
}

async function remove(question) {
    questions.value = questions.value.filter((q) => q.id !== question.id);
    await client.delete(`/exams/questions/${question.id}`);
    pushToast('Question deleted.', 'success');
}
</script>
