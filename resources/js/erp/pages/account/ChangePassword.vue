<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Change Password</h1>
            <Breadcrumb :items="['Dashboard', 'Account', 'Change Password']" class="mt-1" />
        </div>

        <div class="max-w-lg rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="space-y-3">
                <div>
                    <label class="form-label">Current Password</label>
                    <input v-model="form.current_password" type="password" class="form-input" />
                </div>
                <div>
                    <label class="form-label">New Password</label>
                    <input v-model="form.password" type="password" class="form-input" />
                </div>
                <div>
                    <label class="form-label">Confirm New Password</label>
                    <input v-model="form.password_confirmation" type="password" class="form-input" />
                </div>
            </div>

            <button type="button" class="btn-primary mt-4" :disabled="saving" @click="save">{{ saving ? 'Updating...' : 'Update Password' }}</button>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const saving = ref(false);
const form = reactive({ current_password: '', password: '', password_confirmation: '' });

async function save() {
    saving.value = true;
    try {
        const { data } = await client.put('/account/password', form);
        pushToast(data.message, 'success');
        form.current_password = '';
        form.password = '';
        form.password_confirmation = '';
    } finally {
        saving.value = false;
    }
}
</script>
