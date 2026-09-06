<template>
    <div class="space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100 sm:text-2xl">School Settings</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage school profile, layout preferences, and document assets.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-slate-100/80 p-1 dark:border-slate-800 dark:bg-slate-900/80">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                class="rounded-lg px-3.5 py-2 text-sm font-medium transition"
                :class="tab === t.key ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-slate-100' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
                @click="tab = t.key"
            >
                {{ t.label }}
            </button>
        </div>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white px-6 py-16 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">
            Loading school settings...
        </div>

        <template v-else>
            <!-- GENERAL -->
            <form v-show="tab === 'general'" class="space-y-5" @submit.prevent="saveGeneral">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="form-label">School name</label>
                            <input v-model="form.school_name" type="text" class="form-input" required />
                        </div>
                        <div>
                            <label class="form-label">School code</label>
                            <input v-model="form.school_code" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Registration / Affiliation no.</label>
                            <input v-model="form.registration_no" type="text" class="form-input" placeholder="Shown on Transfer Certificate" />
                        </div>
                        <div>
                            <label class="form-label">U-DISE code</label>
                            <input v-model="form.udise_code" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Phone</label>
                            <input v-model="form.phone" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Email</label>
                            <input v-model="form.email" type="email" class="form-input" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label">Website</label>
                            <input v-model="form.website" type="text" class="form-input" placeholder="https://" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Site appearance</h2>
                    <p class="mt-1 text-xs text-slate-500">Controls the browser tab title, description, and favicon. Leave blank to fall back to school name, logo, or environment defaults.</p>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="form-label">Browser tab title</label>
                            <input v-model="form.browser_title" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Meta description</label>
                            <textarea v-model="form.meta_description" rows="3" class="form-input" placeholder="Short description for search engines and link previews." />
                        </div>
                        <div>
                            <label class="form-label">Favicon</label>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                                    <img v-if="form.favicon_url || form.logo_url" :src="form.favicon_url || form.logo_url" alt="Favicon" class="h-full w-full object-cover" />
                                    <span v-else class="text-[10px] text-slate-400">None</span>
                                </div>
                                <button type="button" class="btn-outline inline-flex items-center gap-1.5 !py-1.5 !text-xs" :disabled="uploading === 'favicon'" @click="pickFile('favicon')">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    {{ uploading === 'favicon' ? 'Uploading...' : 'Upload favicon' }}
                                </button>
                            </div>
                            <p class="mt-1.5 text-[11px] text-slate-400">Square image recommended. If no favicon is uploaded, the school logo or /favicon.svg is used.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Logo</label>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 dark:border-slate-700">
                                    <img v-if="form.logo_url" :src="form.logo_url" alt="Logo" class="h-full w-full object-cover" />
                                    <span v-else class="text-[10px] text-slate-400">No logo</span>
                                </div>
                                <button type="button" class="btn-outline inline-flex items-center gap-1.5 !py-1.5 !text-xs" :disabled="uploading === 'logo'" @click="pickFile('logo')">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    {{ uploading === 'logo' ? 'Uploading...' : 'Upload logo' }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Banner</label>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex h-14 w-28 items-center justify-center overflow-hidden rounded-lg border border-dashed border-slate-200 bg-slate-50 text-[10px] text-slate-400 dark:border-slate-700">
                                    <img v-if="form.banner_url" :src="form.banner_url" alt="Banner" class="h-full w-full object-cover" />
                                    <span v-else>No banner</span>
                                </div>
                                <button type="button" class="btn-outline inline-flex items-center gap-1.5 !py-1.5 !text-xs" :disabled="uploading === 'banner'" @click="pickFile('banner')">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    {{ uploading === 'banner' ? 'Uploading...' : 'Upload banner' }}
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Address line 1</label>
                            <input v-model="form.address_line_1" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Address line 2</label>
                            <input v-model="form.address_line_2" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">City</label>
                            <input v-model="form.city" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">State</label>
                            <input v-model="form.state" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">District</label>
                            <input v-model="form.district" type="text" class="form-input" placeholder="Used on UDISE+ S03 form" />
                        </div>
                        <div>
                            <label class="form-label">Block</label>
                            <input v-model="form.block" type="text" class="form-input" placeholder="Used on UDISE+ S03 form" />
                        </div>
                        <div>
                            <label class="form-label">Pincode</label>
                            <input v-model="form.pincode" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Current branch</label>
                            <input v-model="form.current_branch" type="text" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label">Instagram</label>
                            <input v-model="form.instagram" type="text" class="form-input" placeholder="https://instagram.com/..." />
                        </div>
                        <div>
                            <label class="form-label">Facebook</label>
                            <input v-model="form.facebook" type="text" class="form-input" placeholder="https://facebook.com/..." />
                        </div>
                        <div>
                            <label class="form-label">Youtube</label>
                            <input v-model="form.youtube" type="text" class="form-input" placeholder="https://youtube.com/..." />
                        </div>
                        <div>
                            <label class="form-label">Linkedin</label>
                            <input v-model="form.linkedin" type="text" class="form-input" placeholder="https://linkedin.com/..." />
                        </div>
                        <div>
                            <label class="form-label">Twitter</label>
                            <input v-model="form.twitter" type="text" class="form-input" placeholder="https://twitter.com/..." />
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save general settings' }}</button>
            </form>

            <!-- LAYOUT -->
            <form v-show="tab === 'layout'" class="space-y-5" @submit.prevent="saveLayout">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Page watermark</h2>
                    <p class="mt-1 text-xs text-slate-500">Shown faintly behind printable pages and some admin screens.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="form-label">Watermark text</label>
                            <input v-model="form.watermark_text" type="text" class="form-input" placeholder="e.g. GAS" maxlength="50" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2 pb-2.5 text-sm text-slate-600 dark:text-slate-300">
                                <input v-model="form.show_watermark" type="checkbox" class="rounded border-slate-300" />
                                Show watermark
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save layout settings' }}</button>
            </form>

            <!-- NAVIGATION -->
            <form v-show="tab === 'navigation'" class="space-y-5" @submit.prevent="saveNavigation">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Sidebar</h2>
                    <p class="mt-1 text-xs text-slate-500">Default sidebar behaviour for ERP users on this school.</p>
                    <label class="mt-4 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input v-model="form.compact_sidebar" type="checkbox" class="rounded border-slate-300" />
                        Start with sidebar collapsed
                    </label>
                </div>
                <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save navigation settings' }}</button>
            </form>

            <!-- TEMPLATES & SIGNATURES -->
            <div v-show="tab === 'templates'" class="space-y-5">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Signatures</h2>
                        <button type="button" class="btn-outline !py-1 !text-xs" @click="addSignature">+ Add</button>
                    </div>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="(sig, idx) in form.signatures"
                            :key="sig.id"
                            class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-100 p-3 dark:border-slate-800"
                        >
                            <div class="min-w-[160px] flex-1">
                                <label class="form-label">Label</label>
                                <input v-model="sig.label" type="text" class="form-input" placeholder="e.g. Principal" />
                            </div>
                            <div class="flex h-12 w-28 items-center justify-center overflow-hidden rounded-md border border-dashed border-slate-200 bg-slate-50 text-[10px] text-slate-400 dark:border-slate-700">
                                <img v-if="sig.image_url" :src="sig.image_url" alt="" class="h-full w-full object-contain" />
                                <span v-else>No image</span>
                            </div>
                            <button type="button" class="btn-outline inline-flex items-center gap-1 !py-1.5 !text-xs" :disabled="uploading === sig.id" @click="pickFile('signature', sig)">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                Upload
                            </button>
                            <button type="button" class="rounded-md p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete" :disabled="saving" @click="removeSignature(idx)">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Stamps</h2>
                        <button type="button" class="btn-outline !py-1 !text-xs" @click="addStamp">+ Add</button>
                    </div>
                    <div v-if="!form.stamps.length" class="mt-6 py-8 text-center text-sm text-slate-400">No stamps yet.</div>
                    <div v-else class="mt-4 space-y-3">
                        <div
                            v-for="(stamp, idx) in form.stamps"
                            :key="stamp.id"
                            class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-100 p-3 dark:border-slate-800"
                        >
                            <div class="min-w-[160px] flex-1">
                                <label class="form-label">Label</label>
                                <input v-model="stamp.label" type="text" class="form-input" placeholder="e.g. School seal" />
                            </div>
                            <div class="flex h-12 w-28 items-center justify-center overflow-hidden rounded-md border border-dashed border-slate-200 bg-slate-50 text-[10px] text-slate-400 dark:border-slate-700">
                                <img v-if="stamp.image_url" :src="stamp.image_url" alt="" class="h-full w-full object-contain" />
                                <span v-else>No image</span>
                            </div>
                            <button type="button" class="btn-outline inline-flex items-center gap-1 !py-1.5 !text-xs" :disabled="uploading === stamp.id" @click="pickFile('stamp', stamp)">
                                Upload
                            </button>
                            <button type="button" class="rounded-md p-2 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete" :disabled="saving" @click="removeStamp(idx)">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <p class="text-xs text-slate-500">
                    These assets are used by
                    <RouterLink to="/documents/template-builder" class="font-medium text-primary-600 hover:underline">Template Builder</RouterLink>
                    when printing certificates, admit cards, report cards, and fee receipts.
                </p>

                <button type="button" class="btn-primary" :disabled="saving" @click="saveTemplates">{{ saving ? 'Saving...' : 'Save templates & signatures' }}</button>
            </div>
        </template>

        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFilePicked" />
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';
import { applySchoolSettings, erpStore } from '../../store';

const tabs = [
    { key: 'general', label: 'General' },
    { key: 'layout', label: 'Layout' },
    { key: 'navigation', label: 'Navigation' },
    { key: 'templates', label: 'Templates & Signatures' },
];

const tab = ref('general');
const loading = ref(true);
const saving = ref(false);
const uploading = ref('');
const fileInput = ref(null);
const uploadTarget = ref({ kind: null, item: null });

const form = reactive({
    school_name: '',
    school_code: '',
    registration_no: '',
    udise_code: '',
    phone: '',
    email: '',
    website: '',
    browser_title: '',
    meta_description: '',
    logo_path: null,
    logo_url: null,
    banner_path: null,
    banner_url: null,
    favicon_path: null,
    favicon_url: null,
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    district: '',
    block: '',
    pincode: '',
    current_branch: '',
    instagram: '',
    facebook: '',
    youtube: '',
    linkedin: '',
    twitter: '',
    watermark_text: '',
    show_watermark: true,
    compact_sidebar: false,
    signatures: [],
    stamps: [],
});

function applyData(data) {
    Object.assign(form, {
        ...data,
        signatures: (data.signatures || []).map((s) => ({ ...s })),
        stamps: (data.stamps || []).map((s) => ({ ...s })),
        show_watermark: data.show_watermark !== false,
        compact_sidebar: !!data.compact_sidebar,
    });
    if (data.current_branch) erpStore.currentBranch = data.current_branch;
    applySchoolSettings(data);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await client.get('/settings/school');
        applyData(data);
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function uid() {
    return crypto.randomUUID ? crypto.randomUUID() : `id-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function addSignature() {
    form.signatures.push({ id: uid(), label: '', image_path: null, image_url: null });
}
function addStamp() {
    form.stamps.push({ id: uid(), label: '', image_path: null, image_url: null });
}

async function removeSignature(idx) {
    const item = form.signatures[idx];
    if (!item) return;
    if (!window.confirm(`Delete signature "${item.label || 'Untitled'}"?`)) return;
    form.signatures.splice(idx, 1);
    await saveTemplates({ deleted: 'Signature deleted.' });
}

async function removeStamp(idx) {
    const item = form.stamps[idx];
    if (!item) return;
    if (!window.confirm(`Delete stamp "${item.label || 'Untitled'}"?`)) return;
    form.stamps.splice(idx, 1);
    await saveTemplates({ deleted: 'Stamp deleted.' });
}

function pickFile(kind, item = null) {
    uploadTarget.value = { kind, item };
    fileInput.value?.click();
}

async function onFilePicked(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    const { kind, item } = uploadTarget.value;
    const formData = new FormData();
    formData.append('file', file);
    formData.append('kind', kind);
    if (item?.id) formData.append('item_id', item.id);

    uploading.value = item?.id || kind;
    try {
        const { data } = await client.post('/settings/school/upload', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        applyData(data);
        pushToast('Image uploaded.', 'success');
    } catch (e) {
        pushToast(e.response?.data?.message || 'Upload failed.', 'error');
    } finally {
        uploading.value = '';
    }
}

async function putPayload(payload, options = {}) {
    saving.value = true;
    try {
        const { data } = await client.put('/settings/school', payload);
        applyData(data);
        pushToast(options.deleted || 'Settings saved.', 'success');
        return true;
    } catch (e) {
        pushToast(e.response?.data?.message || 'Could not save settings.', 'error');
        await load();
        return false;
    } finally {
        saving.value = false;
    }
}

function saveGeneral() {
    return putPayload({
        school_name: form.school_name,
        school_code: form.school_code,
        registration_no: form.registration_no,
        udise_code: form.udise_code,
        phone: form.phone,
        email: form.email,
        website: form.website,
        browser_title: form.browser_title,
        meta_description: form.meta_description,
        address_line_1: form.address_line_1,
        address_line_2: form.address_line_2,
        city: form.city,
        state: form.state,
        district: form.district,
        block: form.block,
        pincode: form.pincode,
        current_branch: form.current_branch,
        instagram: form.instagram,
        facebook: form.facebook,
        youtube: form.youtube,
        linkedin: form.linkedin,
        twitter: form.twitter,
    });
}

function saveLayout() {
    return putPayload({
        school_name: form.school_name,
        watermark_text: form.watermark_text,
        show_watermark: form.show_watermark,
    });
}

function saveNavigation() {
    return putPayload({
        school_name: form.school_name,
        compact_sidebar: form.compact_sidebar,
    });
}

function saveTemplates(options = {}) {
    return putPayload({
        school_name: form.school_name,
        signatures: form.signatures.map(({ id, label, image_path }) => ({
            id,
            label: (label || '').trim() || 'Signature',
            image_path: image_path || null,
        })),
        stamps: form.stamps.map(({ id, label, image_path }) => ({
            id,
            label: (label || '').trim() || 'Stamp',
            image_path: image_path || null,
        })),
    }, options);
}
</script>
