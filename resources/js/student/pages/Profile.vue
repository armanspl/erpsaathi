<template>
    <div class="space-y-5">
        <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">My Profile</h1>

        <div v-if="loading" class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">Loading…</div>

        <template v-else-if="p">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Student Details</h2>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Field label="Name" :value="p.name" />
                    <Field label="Admission No." :value="p.admission_no" />
                    <Field label="Roll No." :value="p.roll_no" />
                    <Field label="Class" :value="p.school_class" />
                    <Field label="Section" :value="p.section" />
                    <Field label="Gender" :value="p.gender" />
                    <Field label="Date of Birth" :value="p.dob" />
                    <Field label="Age" :value="p.age" />
                    <Field label="Blood Group" :value="p.blood_group" />
                    <Field label="Category" :value="p.category" />
                    <Field label="Religion" :value="p.religion" />
                    <Field label="Nationality" :value="p.nationality" />
                    <Field label="Admission Date" :value="p.admission_date" />
                    <Field label="Status" :value="p.status" />
                </dl>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Contact</h2>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Field label="Mobile" :value="p.mobile" />
                    <Field label="Email" :value="p.email" />
                    <Field label="Address" :value="p.address" />
                    <Field label="City" :value="p.city" />
                    <Field label="State" :value="p.state" />
                    <Field label="Pincode" :value="p.pincode" />
                </dl>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div v-for="(person, key) in { Father: p.father, Mother: p.mother, Guardian: p.guardian }" :key="key" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ key }}</h2>
                    <dl v-if="person" class="space-y-2">
                        <Field label="Name" :value="person.name" />
                        <Field label="Phone" :value="person.phone" />
                        <Field label="Email" :value="person.email" />
                        <Field label="Occupation" :value="person.occupation" />
                    </dl>
                    <p v-else class="text-sm text-slate-400">Not on record</p>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { h, onMounted, ref } from 'vue';
import client from '../api/client';

const loading = ref(true);
const p = ref(null);

const Field = (props) =>
    h('div', [
        h('dt', { class: 'text-xs font-medium uppercase tracking-wide text-slate-400' }, props.label),
        h('dd', { class: 'mt-0.5 text-sm text-slate-700 dark:text-slate-200' }, props.value || '—'),
    ]);
Field.props = ['label', 'value'];

onMounted(async () => {
    try {
        const { data } = await client.get('/profile');
        p.value = data;
    } finally {
        loading.value = false;
    }
});
</script>
