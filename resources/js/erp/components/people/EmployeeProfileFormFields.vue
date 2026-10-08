<template>
    <!-- Same columns as the SALARY DETAILS / STAFF DETAILS Excel sheets; saved as profile fields. -->
    <div class="space-y-4">
        <section>
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Job details <span class="normal-case tracking-normal text-slate-300 dark:text-slate-500">(SALARY DETAILS)</span></h3>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="form-label">Post / Designation</label>
                    <input v-model="profile.designation" type="text" class="form-input" :list="`${uid}-posts`" :placeholder="postPlaceholder" />
                    <datalist :id="`${uid}-posts`">
                        <option v-for="p in POSTS[type]" :key="p" :value="p" />
                    </datalist>
                </div>
                <div>
                    <label class="form-label">Grade</label>
                    <input v-model="profile.grade" type="text" class="form-input" :list="`${uid}-grades`" placeholder="e.g. GRADE I" />
                    <datalist :id="`${uid}-grades`">
                        <option v-for="g in GRADES" :key="g" :value="g" />
                    </datalist>
                </div>
                <template v-if="type === 'teacher'">
                    <div>
                        <label class="form-label">Subject</label>
                        <input v-model="profile.subject" type="text" class="form-input" placeholder="e.g. MATHS, EVS, ENGLISH" />
                    </div>
                    <div>
                        <label class="form-label">Section</label>
                        <input v-model="profile.section" type="text" class="form-input" :list="`${uid}-sections`" placeholder="e.g. PRIMARY / MIDDLE" />
                        <datalist :id="`${uid}-sections`">
                            <option v-for="s in SECTIONS" :key="s" :value="s" />
                        </datalist>
                    </div>
                </template>
                <div>
                    <label class="form-label">Basic salary at the time of joining</label>
                    <input v-model="profile.joining_salary" type="number" min="0" step="1" class="form-input" placeholder="₹" />
                </div>
            </div>
        </section>

        <section>
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Personal details <span class="normal-case tracking-normal text-slate-300 dark:text-slate-500">(STAFF DETAILS)</span></h3>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div v-for="d in DATE_FIELDS" :key="d.key">
                    <label class="form-label">{{ d.label }}</label>
                    <input
                        v-if="isDateLike(profile[d.key])"
                        type="date"
                        class="form-input"
                        :value="toIso(profile[d.key])"
                        @input="profile[d.key] = fromIso($event.target.value)"
                    />
                    <!-- Free-text value from an older import that isn't a DD-MM-YYYY date — keep it editable as text. -->
                    <input v-else v-model="profile[d.key]" type="text" class="form-input" placeholder="DD-MM-YYYY" />
                </div>
                <div>
                    <label class="form-label">Category</label>
                    <input v-model="profile.category" type="text" class="form-input" :list="`${uid}-categories`" placeholder="GEN / OBC / SC / ST" />
                    <datalist :id="`${uid}-categories`">
                        <option v-for="c in CATEGORIES" :key="c" :value="c" />
                    </datalist>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div v-for="y in YEAR_FIELDS" :key="y.key">
                        <label class="form-label">{{ y.label }}</label>
                        <input v-model="profile[y.key]" type="text" inputmode="numeric" maxlength="4" class="form-input" placeholder="YYYY" />
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Address</label>
                    <input v-model="profile.address" type="text" class="form-input" />
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label">Qualification / Remarks</label>
                    <input v-model="profile.remarks" type="text" class="form-input" placeholder="e.g. M.A. 2017, B.Ed." />
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Reactive object from emptyProfile() / profileFromFields() — edited in place. */
    profile: { type: Object, required: true },
    /** teacher | staff | driver */
    type: { type: String, required: true },
});

const uid = `epf-${Math.random().toString(36).slice(2, 8)}`;

const POSTS = {
    teacher: ['ASST. TEACHER', 'COMPUTER TEACHER', 'TEACHER'],
    staff: ['ACCOUNTANT', 'OFFICE ASST', 'COMP OPERATOR', 'ADMIN', 'GUARD', 'BOOA'],
    driver: ['DRIVER'],
};
const GRADES = ['GRADE I', 'GRADE II', 'GRADE III', 'GRADE IV', 'TRANSPORT'];
const SECTIONS = ['PRIMARY', 'MIDDLE', 'SECONDARY'];
const CATEGORIES = ['GEN', 'OBC', 'SC', 'ST', 'EWS'];
const DATE_FIELDS = [
    { key: 'dob', label: 'Date of birth' },
    { key: 'join_date', label: 'Join date' },
];
const YEAR_FIELDS = [
    { key: 'hs_year', label: 'HS year' },
    { key: 'inter_year', label: 'Inter year' },
    { key: 'grad_year', label: 'Grad year' },
];

const postPlaceholder = computed(() => `e.g. ${POSTS[props.type]?.[0] || ''}`);

const DMY = /^(\d{1,2})-(\d{1,2})-(\d{4})$/;

function isDateLike(value) {
    return !value || DMY.test(String(value).trim());
}
/** "02-12-1972" (how profiles store dates, same as the Excel export) -> "1972-12-02" for <input type=date>. */
function toIso(value) {
    const m = String(value || '').trim().match(DMY);
    return m ? `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}` : '';
}
function fromIso(value) {
    const m = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return m ? `${m[3]}-${m[2]}-${m[1]}` : '';
}
</script>
