<template>
    <div class="space-y-6">
        <div>
            <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Uploaded Documents</h4>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="doc in STUDENT_DOCUMENT_TYPES" :key="doc.key" class="flex items-center justify-between rounded-lg border border-slate-100 p-3.5 dark:border-slate-800">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg" :class="uploadedPath(doc) ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-800'">📄</span>
                        <div>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ doc.label }}</p>
                            <p class="text-xs text-slate-400">{{ uploadedPath(doc) ? 'Uploaded' : 'Not uploaded' }}</p>
                        </div>
                    </div>
                    <button
                        v-if="uploadedPath(doc)"
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-primary-600 dark:hover:bg-slate-800 dark:hover:text-primary-400"
                        title="Download"
                        @click="download(doc)"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="detail">
            <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Document Status</h4>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="d in statusFlags" :key="d.label" class="flex items-center justify-between rounded-lg border border-slate-100 p-3.5 dark:border-slate-800">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg" :class="d.received ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-800'">📄</span>
                        <div>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ d.label }}</p>
                            <p class="text-xs text-slate-400">{{ d.received ? 'Received' : 'Not Received' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { STUDENT_DOCUMENT_TYPES } from '../../data/studentDocumentTypes';
import { downloadStudentDocument } from '../../utils/downloadExport';

const props = defineProps({ student: { type: Object, required: true } });

const detail = computed(() => props.student.additional_detail || null);

const statusFlags = computed(() => {
    const d = detail.value;
    return [
        { label: 'Report Card', received: !!d.report_card_received },
        { label: 'Character Certificate (CC)', received: !!d.cc_received },
        { label: 'Transfer Certificate (TC)', received: !!d.tc_received },
        { label: 'DOB Certificate', received: !!d.dob_certificate_received },
    ];
});

function uploadedPath(doc) {
    return props.student.documents?.[doc.column] || null;
}

function download(doc) {
    downloadStudentDocument(props.student.id, doc.key, `${props.student.admission_no} ${props.student.name} ${doc.label}`);
}
</script>
