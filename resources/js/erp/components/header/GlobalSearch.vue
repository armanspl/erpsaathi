<template>
    <div ref="root" class="relative w-full max-w-md">
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" /><path stroke-linecap="round" d="m21 21-4.3-4.3" />
            </svg>
            <input
                v-model="query"
                type="text"
                placeholder="Search student, admission no, receipt, teacher, vehicle..."
                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:placeholder:text-slate-500 dark:focus:ring-primary-500/20"
                @focus="open = true"
            />
        </div>
        <transition name="dropdown">
            <div v-if="open" class="absolute left-0 top-full z-40 mt-2 w-full min-w-[280px] rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                <p class="px-2 pb-1.5 pt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Search in</p>
                <button
                    v-for="cat in filteredCategories"
                    :key="cat.label"
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm text-slate-600 transition hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                    @click="select(cat)"
                >
                    <span class="text-base">{{ cat.icon }}</span>
                    <span>{{ cat.label }}</span>
                </button>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { pushToast } from '../../utils/toast';

const categories = [
    { label: 'Student Name', icon: '🎓', path: '/people/students' },
    { label: 'Admission No', icon: '📝', path: '/admissions/admission' },
    { label: 'Receipt No', icon: '🧾', path: '/fee-management/fee-receipt' },
    { label: 'Teacher', icon: '👩‍🏫', path: '/people/teachers' },
    { label: 'Driver', icon: '🚗', path: '/people/drivers' },
    { label: 'Vehicle', icon: '🚌', path: '/transport-management/vehicles' },
    { label: 'Fee Receipt', icon: '💳', path: '/fee-management/fee-receipt' },
    { label: 'Certificate', icon: '📜', path: '/documents/certificates' },
    { label: 'Center', icon: '🏫', path: '/academics/branches' },
];

const root = ref(null);
const open = ref(false);
const query = ref('');
const router = useRouter();

const filteredCategories = computed(() => categories);

function select(cat) {
    if (query.value.trim()) {
        pushToast(`Searching "${query.value}" in ${cat.label}...`, 'info');
    }
    open.value = false;
    router.push(cat.path);
}

function onClickOutside(e) {
    if (root.value && !root.value.contains(e.target)) open.value = false;
}
onMounted(() => document.addEventListener('click', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside));
</script>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
