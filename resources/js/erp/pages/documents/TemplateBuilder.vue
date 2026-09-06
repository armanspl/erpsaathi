<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Template Builder</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Design print layouts once. Every Print, Preview, and Download across Fees, Exams, Payroll, and Documents uses the <strong class="font-medium text-slate-700 dark:text-slate-200">default</strong> template for that document type.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900">
                <button
                    v-for="c in categories"
                    :key="c.key"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="activeCategory === c.key ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                    @click="activeCategory = c.key"
                >
                    {{ c.label }}
                </button>
            </div>
            <div class="flex items-center gap-2">
                <input ref="importInput" type="file" accept="application/json" class="hidden" @change="runImport" />
                <button type="button" class="btn-outline inline-flex items-center gap-1.5" @click="importInput.click()">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 3v12m0 0l-4-4m4 4l4-4"/></svg>
                    Import
                </button>
                <button type="button" class="btn-outline inline-flex items-center gap-1.5 !text-purple-600" @click="pushToast('Generate with AI coming soon.', 'info')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    Generate with AI
                </button>
                <button type="button" class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
                    <span class="text-base leading-none">+</span> Create
                </button>
            </div>
        </div>

        <div v-if="activeCategory === 'certificate'" class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Certificate type → Template</p>
            <p class="mt-0.5 text-xs text-slate-400">Pick which template is used when a certificate type is printed or downloaded. Types left on "Default template" use the Certificate category's default template above.</p>
            <div v-if="certificateTypes.length" class="mt-3 grid gap-2 sm:grid-cols-2">
                <div v-for="ct in certificateTypes" :key="ct.id" class="flex items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2 dark:border-slate-700">
                    <span class="truncate text-sm text-slate-700 dark:text-slate-200">{{ ct.label }}</span>
                    <select
                        class="form-input !h-8 w-48 !py-1 !text-xs"
                        :value="ct.template_id || ''"
                        :disabled="assigningTypeId === ct.id"
                        @change="assignCertificateTemplate(ct, $event.target.value)"
                    >
                        <option value="">Default template</option>
                        <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading...</div>
        <div v-else-if="!templates.length" class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
            No {{ categoryLabel(activeCategory) }} templates yet. Click "+ Create" to design one.
        </div>

        <div v-else class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="t in templates"
                :key="t.id"
                class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:bg-slate-900"
                :class="t.is_default ? 'border-emerald-400 dark:border-emerald-500/60' : 'border-slate-200 dark:border-slate-800'"
            >
                <div class="flex justify-center bg-slate-50 p-4 dark:bg-slate-800/40">
                    <DesignPreviewFrame
                        v-if="t.render_mode === 'html' && t.raw_html"
                        :html="htmlPreview(t)"
                        :width-mm="Number(t.page_width_mm) || 210"
                        :height-mm="Number(t.page_height_mm) || 297"
                        :box-height="150"
                    />
                    <TemplateThumbnail v-else :template="t" :sample="sample" :width="240" />
                </div>
                <div class="p-4">
                    <div class="flex items-center gap-2">
                        <p class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ t.name }}</p>
                        <span v-if="t.is_default" class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">Default</span>
                        <button type="button" class="ml-auto shrink-0 text-amber-400 hover:text-amber-500" :title="t.is_favorite ? 'Unfavorite' : 'Favorite'" @click="toggleFavorite(t)">
                            <svg class="h-4 w-4" :fill="t.is_favorite ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5l2.19 4.44 4.9.71-3.55 3.46.84 4.88L11.48 15l-4.38 2.3.84-4.88L4.4 8.66l4.9-.71 2.19-4.44z"/></svg>
                        </button>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-400">
                        {{ t.page_width_mm }} × {{ t.page_height_mm }} mm
                        · {{ t.render_mode === 'html' ? 'HTML design' : `${(t.elements || []).length} elements` }}
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button type="button" class="btn-outline !py-1 !text-xs" :disabled="previewingId === t.id" @click="previewPdf(t)">{{ previewingId === t.id ? 'Opening...' : 'Preview' }}</button>
                        <button type="button" class="btn-primary !py-1 !text-xs" @click="openEditor(t)">Open</button>
                        <button
                            v-if="!t.is_default"
                            type="button"
                            class="btn-outline !py-1 !text-xs"
                            title="Use this template for all Print / Download actions of this type"
                            :disabled="settingDefaultId === t.id"
                            @click="setDefault(t)"
                        >
                            {{ settingDefaultId === t.id ? 'Setting...' : 'Set default' }}
                        </button>
                        <span
                            v-else
                            class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"
                        >
                            Default
                        </span>
                        <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Download JSON" @click="downloadJson(t)">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                        </button>
                        <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" title="Duplicate" @click="duplicate(t)">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                        <button type="button" class="ml-auto rounded-md p-1.5 text-rose-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" title="Delete" @click="remove(t)">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create a template -->
        <div v-if="createOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40" @click="createOpen = false" />
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-4xl flex-col rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between px-5 pt-5">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Create a template</h2>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Pick a category, then a starting design. You can view/edit its code or the canvas afterward.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="createOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <button
                            v-for="c in categories"
                            :key="c.key"
                            type="button"
                            class="rounded-xl border p-3 text-left transition"
                            :class="createForm.category === c.key ? 'border-primary-500 ring-2 ring-primary-200 dark:ring-primary-500/30' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'"
                            @click="selectCreateCategory(c.key)"
                        >
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ c.label }}</p>
                        </button>
                    </div>

                    <div v-if="createForm.category" class="mt-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Choose a design</p>
                        <div v-if="designsLoading" class="mt-2 rounded-xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-400 dark:border-slate-700">Loading designs...</div>
                        <div v-else class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <button
                                type="button"
                                class="overflow-hidden rounded-xl border-2 text-left transition"
                                :class="createForm.design_key === 'blank' ? 'border-primary-500 ring-2 ring-primary-200 dark:ring-primary-500/30' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'"
                                @click="createForm.design_key = 'blank'"
                            >
                                <div class="flex h-36 items-center justify-center bg-slate-50 text-slate-300 dark:bg-slate-800/60">
                                    <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                </div>
                                <div class="border-t border-slate-100 p-2.5 dark:border-slate-800">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Blank canvas</p>
                                    <p class="text-xs text-slate-400">Build it yourself, drag-and-drop</p>
                                </div>
                            </button>

                            <button
                                v-for="d in designs"
                                :key="d.key"
                                type="button"
                                class="overflow-hidden rounded-xl border-2 text-left transition"
                                :class="createForm.design_key === d.key ? 'border-primary-500 ring-2 ring-primary-200 dark:ring-primary-500/30' : 'border-slate-200 hover:border-slate-300 dark:border-slate-700'"
                                @click="createForm.design_key = d.key"
                            >
                                <div class="h-36 overflow-hidden bg-slate-100 dark:bg-slate-950">
                                    <DesignPreviewFrame
                                        :html="d.preview_html"
                                        :width-mm="d.page_width_mm || designPageSize.width"
                                        :height-mm="d.page_height_mm || designPageSize.height"
                                        :box-height="144"
                                    />
                                </div>
                                <div class="border-t border-slate-100 p-2.5 dark:border-slate-800">
                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ d.label }}</p>
                                    <p class="text-xs text-slate-400">{{ d.page_width_mm || designPageSize.width }} × {{ d.page_height_mm || designPageSize.height }} mm</p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label class="form-label">Template name</label>
                        <input v-model="createForm.name" type="text" class="form-input" placeholder="e.g. Annual Exam Admit Card" />
                    </div>
                    <label class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input v-model="createForm.set_as_default" type="checkbox" class="rounded border-slate-300" />
                        Set as default for Print / Download in this document type
                    </label>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button type="button" class="btn-outline" @click="createOpen = false">Cancel</button>
                    <button type="button" class="btn-primary" :disabled="!createForm.category || !createForm.name || creating" @click="createTemplate">{{ creating ? 'Creating...' : 'Create & Open Editor' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import TemplateThumbnail from '../../components/templates/TemplateThumbnail.vue';
import DesignPreviewFrame from '../../components/templates/DesignPreviewFrame.vue';
import client from '../../api/client';
import { downloadPdf, triggerBlobDownload } from '../../utils/documentPdf';
import { substituteTokens } from '../../utils/templateRender';
import { pushToast } from '../../utils/toast';

const router = useRouter();

const categories = ref([]);
const activeCategory = ref('admit_card');
const templates = ref([]);
const sample = ref({});
const loading = ref(false);
const previewingId = ref(null);
const settingDefaultId = ref(null);
const importInput = ref(null);

function normalizeTemplate(t) {
    return {
        ...t,
        is_default: !!t.is_default,
        is_favorite: !!t.is_favorite,
        elements: Array.isArray(t.elements) ? t.elements : [],
    };
}

/** Fill {{tokens}} in an HTML-mode template so the list card shows the real design. */
function htmlPreview(t) {
    return substituteTokens(t.raw_html || '', sample.value);
}

function categoryLabel(key) {
    return categories.value.find((c) => c.key === key)?.label.toLowerCase() || key;
}

async function loadCategories() {
    const { data } = await client.get('/documents/templates/categories');
    categories.value = data;
}
loadCategories();

async function load() {
    loading.value = true;
    try {
        const [templatesRes, sampleRes] = await Promise.all([
            client.get('/documents/templates', { params: { category: activeCategory.value } }),
            client.get('/documents/templates/sample-data', { params: { category: activeCategory.value } }),
        ]);
        templates.value = (templatesRes.data || []).map(normalizeTemplate);
        sample.value = sampleRes.data;
        if (activeCategory.value === 'certificate') {
            await loadCertificateTypes();
        }
    } finally {
        loading.value = false;
    }
}
watch(activeCategory, load, { immediate: true });

// --- Certificate type -> template mapping (Certificate category only) ---
const certificateTypes = ref([]);
const assigningTypeId = ref(null);

async function loadCertificateTypes() {
    const { data } = await client.get('/documents/certificate-types');
    certificateTypes.value = data;
}

async function assignCertificateTemplate(ct, templateId) {
    assigningTypeId.value = ct.id;
    try {
        const { data } = await client.patch(`/documents/certificate-types/${ct.id}/template`, {
            template_id: templateId || null,
        });
        const idx = certificateTypes.value.findIndex((x) => x.id === ct.id);
        if (idx !== -1) certificateTypes.value[idx] = data;
        pushToast(`"${ct.label}" now uses ${data.template_id ? 'a custom' : 'the default'} template.`, 'success');
    } finally {
        assigningTypeId.value = null;
    }
}

function openEditor(t) {
    router.push(`/documents/template-builder/${t.id}`);
}

async function toggleFavorite(t) {
    const { data } = await client.patch(`/documents/templates/${t.id}/favorite`);
    t.is_favorite = data.is_favorite;
}

async function setDefault(t) {
    settingDefaultId.value = t.id;
    try {
        const { data } = await client.patch(`/documents/templates/${t.id}/default`);
        const defaultId = data?.id ?? t.id;
        templates.value = templates.value.map((x) => normalizeTemplate({
            ...x,
            is_default: x.id === defaultId,
        }));
        pushToast(`"${t.name}" is now the default for ${categoryLabel(t.category)}.`, 'success');
    } finally {
        settingDefaultId.value = null;
    }
}

async function duplicate(t) {
    await client.post(`/documents/templates/${t.id}/duplicate`);
    pushToast('Template duplicated.', 'success');
    await load();
}

async function remove(t) {
    if (!window.confirm(`Delete template "${t.name}"?`)) return;
    templates.value = templates.value.filter((x) => x.id !== t.id);
    await client.delete(`/documents/templates/${t.id}`);
    pushToast(`Template "${t.name}" deleted.`, 'success');
}

async function previewPdf(t) {
    previewingId.value = t.id;
    try {
        await downloadPdf(`/documents/templates/${t.id}/preview-pdf`, `${t.name || 'template'}-preview.pdf`);
    } finally {
        previewingId.value = null;
    }
}

async function downloadJson(t) {
    const response = await client.get(`/documents/templates/${t.id}/download-json`, { responseType: 'blob' });
    triggerBlobDownload(new Blob([response.data], { type: 'application/json' }), `${t.name}.json`);
}

async function runImport(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    try {
        await client.post('/documents/templates/import', formData, { headers: { 'Content-Type': 'multipart/form-data' } });
        pushToast('Template imported.', 'success');
        await load();
    } catch (e) {
        pushToast(e.response?.data?.message || 'Could not import this file.', 'error');
    } finally {
        event.target.value = '';
    }
}

// --- Create a template ---
const createOpen = ref(false);
const creating = ref(false);
const createForm = reactive({ category: null, name: '', design_key: 'blank', set_as_default: true });
const designs = ref([]);
const designsLoading = ref(false);
const designPageSize = reactive({ width: 210, height: 297 });

function openCreate() {
    Object.assign(createForm, { category: activeCategory.value, name: '', design_key: 'blank', set_as_default: true });
    createOpen.value = true;
    loadDesigns();
}
function selectCreateCategory(key) {
    createForm.category = key;
    createForm.design_key = 'blank';
    loadDesigns();
}
async function loadDesigns() {
    if (!createForm.category) return;
    designsLoading.value = true;
    designs.value = [];
    try {
        const { data } = await client.get('/documents/templates/designs', { params: { category: createForm.category } });
        designs.value = data.designs;
        designPageSize.width = data.page_width_mm;
        designPageSize.height = data.page_height_mm;
    } finally {
        designsLoading.value = false;
    }
}
async function createTemplate() {
    creating.value = true;
    try {
        const { data } = await client.post('/documents/templates', {
            category: createForm.category,
            name: createForm.name,
            design_key: createForm.design_key,
            set_as_default: createForm.set_as_default,
        });
        createOpen.value = false;
        pushToast('Template created.', 'success');
        router.push(`/documents/template-builder/${data.id}`);
    } finally {
        creating.value = false;
    }
}
</script>
