<template>
    <dl class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
        <div v-for="item in items" :key="item.label">
            <dt class="text-xs font-medium text-slate-400">{{ item.label }}</dt>
            <dd class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-200">{{ item.value || '—' }}</dd>
        </div>
    </dl>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({ student: { type: Object, required: true } });

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : null;
}

// Computed client-side from DOB rather than stored/fetched — same "computed, not stored"
// convention as the backend's Student::age() accessor.
function formatAge(dob) {
    if (!dob) return null;
    const birth = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const beforeBirthdayThisYear = today.getMonth() < birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate());
    if (beforeBirthdayThisYear) age--;
    return `${age} years`;
}

const items = computed(() => [
    { label: 'Date of Birth', value: formatDate(props.student.dob) },
    { label: 'Age', value: formatAge(props.student.dob) },
    { label: 'Gender', value: props.student.gender },
    { label: 'Blood Group', value: props.student.blood_group },
    { label: 'Category', value: props.student.category },
    { label: 'Religion', value: props.student.religion },
    { label: 'Nationality', value: props.student.nationality },
    { label: 'Aadhar No', value: props.student.aadhar_no },
    { label: 'Name As per Aadhaar', value: props.student.udise_detail?.name_as_per_aadhaar },
    { label: 'Student PEN', value: props.student.udise_detail?.student_pen || props.student.additional_detail?.pen_no },
    { label: 'In UDISE', value: props.student.udise_detail?.is_in_udise ? 'Yes' : 'No' },
    { label: 'Mobile', value: props.student.mobile },
    { label: 'Email', value: props.student.email },
    { label: 'Address', value: props.student.address },
    { label: 'City / State', value: [props.student.city, props.student.state].filter(Boolean).join(', ') },
    { label: 'Pincode', value: props.student.pincode },
    { label: 'Vehicle', value: props.student.udise_detail?.vehicle },
    { label: 'Previous Year Schooling Status', value: props.student.latest_session_history?.previous_year_schooling_status },
]);
</script>
