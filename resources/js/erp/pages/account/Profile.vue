<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Profile</h1>
            <Breadcrumb :items="['Dashboard', 'Account', 'Profile']" class="mt-1" />
        </div>

        <div class="max-w-lg rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-5 flex items-center gap-3">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-100 text-lg font-bold text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">
                    {{ initials }}
                </div>
                <div>
                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ form.name }}</p>
                    <p class="text-xs capitalize text-slate-400">{{ user?.role }}</p>
                </div>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="form-label">Name</label>
                    <input v-model="form.name" type="text" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input v-model="form.email" type="email" class="form-input" />
                </div>
            </div>

            <button type="button" class="btn-primary mt-4" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save Changes' }}</button>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import client from '../../api/client';
import { erpStore } from '../../store';
import { pushToast } from '../../utils/toast';

const saving = ref(false);
const user = ref(null);
const form = reactive({ name: '', email: '' });

const initials = computed(() =>
    (form.name || '')
        .split(' ')
        .map((p) => p[0])
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);

client.get('/account/profile').then(({ data }) => {
    user.value = data;
    form.name = data.name;
    form.email = data.email;
});

async function save() {
    saving.value = true;
    try {
        const { data } = await client.put('/account/profile', form);
        Object.assign(erpStore.user, { name: data.name, email: data.email });
        pushToast('Profile updated.', 'success');
    } finally {
        saving.value = false;
    }
}
</script>
