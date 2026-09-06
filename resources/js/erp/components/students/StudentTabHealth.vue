<template>
    <div v-if="!detail" class="py-10 text-center text-sm text-slate-400">No additional details on record for this student.</div>
    <div v-else class="space-y-6">
        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Health & Physical</h4>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                <div v-for="item in healthItems" :key="item.label">
                    <dt class="text-xs font-medium text-slate-400">{{ item.label }}</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">{{ item.value ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Previous School</h4>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                <div v-for="item in schoolItems" :key="item.label">
                    <dt class="text-xs font-medium text-slate-400">{{ item.label }}</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">{{ item.value ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div>
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Identifiers & Other</h4>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                <div v-for="item in idItems" :key="item.label">
                    <dt class="text-xs font-medium text-slate-400">{{ item.label }}</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">{{ item.value ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div v-if="detail.remarks_1 || detail.remarks_2">
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Remarks</h4>
            <p v-if="detail.remarks_1" class="text-sm text-slate-600 dark:text-slate-300">{{ detail.remarks_1 }}</p>
            <p v-if="detail.remarks_2" class="text-sm text-slate-600 dark:text-slate-300">{{ detail.remarks_2 }}</p>
        </div>

        <div v-if="additionalFields.length">
            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Additional Fields</h4>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                <div v-for="item in additionalFields" :key="item.label">
                    <dt class="text-xs font-medium text-slate-400">{{ item.label }}</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">{{ item.value }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ student: { type: Object, required: true } });

const detail = computed(() => props.student.additional_detail || null);

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : null;
}

const healthItems = computed(() => {
    const d = detail.value;
    return [
        { label: 'Height', value: d.height != null ? `${d.height} cm` : null },
        { label: 'Weight', value: d.weight != null ? `${d.weight} kg` : null },
        { label: 'Vision (Left)', value: d.vision_left },
        { label: 'Vision (Right)', value: d.vision_right },
        { label: 'Dental Hygiene', value: d.dental_hygiene },
        { label: 'House', value: d.house },
        { label: 'Family', value: d.family },
    ];
});

const schoolItems = computed(() => {
    const d = detail.value;
    return [
        { label: 'Previous School', value: d.last_school_name },
        { label: 'Previous Exam', value: d.last_exam },
        { label: 'Exam Year', value: d.last_exam_year },
        { label: 'Exam Status', value: d.last_exam_status },
        { label: 'Marks', value: d.last_exam_marks },
        { label: 'Board', value: d.last_exam_board },
    ];
});

const idItems = computed(() => {
    const d = detail.value;
    return [
        { label: 'GR No.', value: d.gr_no },
        { label: 'PEN No.', value: d.pen_no || props.student.udise_detail?.student_pen },
        { label: 'Student Ref ID', value: d.student_ref_id },
        { label: 'Biometric Card No.', value: d.biometric_card_no },
        { label: 'Child UID', value: d.child_uid },
        { label: 'Form No.', value: d.form_no },
        { label: 'Scholarship No.', value: d.scholarship_no },
        { label: 'Opening Balance', value: d.opening_balance != null ? `₹${Number(d.opening_balance).toLocaleString('en-IN')}` : null },
        { label: 'Discontinue Date', value: formatDate(d.discontinue_date) },
        { label: 'Parents Anniversary', value: formatDate(d.parents_anniversary_date) },
        { label: 'Report Card Received', value: d.report_card_received ? 'Yes' : 'No' },
        { label: 'CC Received', value: d.cc_received ? 'Yes' : 'No' },
        { label: 'TC Received', value: d.tc_received ? 'Yes' : 'No' },
        { label: 'DOB Certificate Received', value: d.dob_certificate_received ? 'Yes' : 'No' },
    ];
});

const additionalFields = computed(() => {
    const d = detail.value;
    return Array.from({ length: 10 }, (_, i) => i + 1)
        .map((n) => ({ label: `Additional Field ${n}`, value: d[`additional_field_${n}`] }))
        .filter((item) => item.value);
});
</script>
