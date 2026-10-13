<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">WhatsApp</h1>
            <Breadcrumb :items="['Dashboard', 'Settings', 'WhatsApp']" class="mt-1" />
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Connect your school's own WhatsApp Business account through Meta. Messages are sent from your number, and delivery updates come back here.
            </p>
        </div>

        <div v-if="loading" class="rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center text-sm text-slate-400 dark:border-slate-800 dark:bg-slate-900">Loading...</div>

        <template v-else>
            <div v-if="!configured" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-200">
                WhatsApp onboarding is not configured on the server yet. Please contact ERPSaathi support.
            </div>

            <!-- Not connected -->
            <div v-if="!connection" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-base font-semibold text-slate-800 dark:text-slate-100">No WhatsApp number connected</div>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            You'll sign in with the Facebook account that manages your WhatsApp Business account, then pick the business, account and phone number.
                        </p>
                    </div>
                    <button type="button" class="btn-primary whitespace-nowrap" :disabled="!configured || connecting" @click="connect">
                        {{ connecting ? 'Waiting for Meta…' : 'Connect WhatsApp' }}
                    </button>
                </div>
            </div>

            <!-- Connected -->
            <div v-else class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-6 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-lg dark:bg-emerald-900/40">✓</span>
                        <div>
                            <div class="font-semibold text-slate-800 dark:text-slate-100">{{ connection.verified_name || 'WhatsApp Business' }}</div>
                            <div class="text-sm text-slate-500">{{ connection.display_phone_number }}</div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-outline" :disabled="busy" @click="refresh">Refresh status</button>
                        <button type="button" class="btn-outline" :disabled="busy || connecting" @click="connect">Reconnect</button>
                        <button type="button" class="btn-outline !text-rose-600" :disabled="busy" @click="disconnect">Disconnect</button>
                    </div>
                </div>
                <dl class="grid gap-x-6 gap-y-3 px-6 py-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">WhatsApp Business Account</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ connection.waba_name || '—' }} <span class="text-xs text-slate-400">({{ connection.waba_id }})</span></dd></div>
                    <div><dt class="text-slate-500">Phone number ID</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ connection.phone_number_id }}</dd></div>
                    <div><dt class="text-slate-500">Cloud API registration</dt>
                        <dd class="font-medium" :class="connection.registered ? 'text-emerald-600' : 'text-amber-600'">{{ connection.registered ? 'Registered' : (connection.platform_type || 'Not registered') }}</dd></div>
                    <div><dt class="text-slate-500">Quality rating</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ connection.quality_rating || '—' }}</dd></div>
                    <div><dt class="text-slate-500">Connected</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ fmtDate(connection.connected_at) }}</dd></div>
                    <div v-if="connection.token_expires_at"><dt class="text-slate-500">Authorisation expires</dt><dd class="font-medium text-slate-800 dark:text-slate-100">{{ fmtDate(connection.token_expires_at) }}</dd></div>
                </dl>
                <div v-if="connection.last_error" class="mx-6 mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900/50 dark:bg-rose-900/20 dark:text-rose-200">{{ connection.last_error }}</div>

                <div v-if="!connection.registered" class="border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                    <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">Register this number for sending</div>
                    <p class="mt-1 text-sm text-slate-500">
                        Meta needs the number registered on the Cloud API once. Enter a 6-digit PIN — if the number already has two-step verification, use that existing PIN.
                    </p>
                    <div class="mt-3 flex max-w-sm gap-2">
                        <input v-model="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" class="form-input" placeholder="6-digit PIN" />
                        <button type="button" class="btn-primary whitespace-nowrap" :disabled="busy || !/^\d{6}$/.test(pin)" @click="register">Register</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onBeforeUnmount, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import client from '../../api/client';
import { pushToast } from '../../utils/toast';

const loading = ref(true);
const configured = ref(false);
const connection = ref(null);
const connecting = ref(false);
const busy = ref(false);
const pin = ref('');
let popup = null;
let popupOrigin = '';
let popupTimer = null;

const errMsg = (e, fallback) => e?.response?.data?.message || e?.message || fallback;
const fmtDate = (iso) => (iso ? new Date(iso).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—');

async function load() {
    try {
        const { data } = await client.get('/settings/whatsapp');
        configured.value = !!data.configured;
        connection.value = data.connection;
    } catch (e) {
        pushToast(errMsg(e, 'Could not load WhatsApp settings.'), 'error');
    } finally {
        loading.value = false;
    }
}

function onMessage(event) {
    if (!popupOrigin || event.origin !== popupOrigin) return;
    if (event.data?.type !== 'erpsaathi-whatsapp-connected') return;
    stopWatching();
    pushToast('WhatsApp connected.', 'success');
    load();
}

function stopWatching() {
    connecting.value = false;
    if (popupTimer) clearInterval(popupTimer);
    popupTimer = null;
}

async function connect() {
    // Open synchronously on the click so the browser doesn't block the popup, then point it at
    // the signed connect URL once the server hands it back.
    popup = window.open('', 'erpsaathi-whatsapp-connect', 'width=640,height=760');
    connecting.value = true;
    try {
        const { data } = await client.post('/settings/whatsapp/connect');
        popupOrigin = data.origin;
        if (!popup || popup.closed) {
            popup = window.open(data.url, 'erpsaathi-whatsapp-connect', 'width=640,height=760');
        } else {
            popup.location.href = data.url;
        }
        if (!popup) {
            stopWatching();
            pushToast('Please allow pop-ups for this site, then click Connect again.', 'error');
            return;
        }
        popupTimer = setInterval(() => {
            if (popup && popup.closed) {
                stopWatching();
                load();
            }
        }, 800);
    } catch (e) {
        if (popup && !popup.closed) popup.close();
        stopWatching();
        pushToast(errMsg(e, 'Could not start WhatsApp connection.'), 'error');
    }
}

async function call(fn, okText) {
    busy.value = true;
    try {
        const { data } = await fn();
        if (data?.connection) connection.value = data.connection;
        if (okText) pushToast(okText, 'success');
    } catch (e) {
        pushToast(errMsg(e, 'Request failed.'), 'error');
        await load();
    } finally {
        busy.value = false;
    }
}

const refresh = () => call(() => client.post('/settings/whatsapp/refresh'), 'Status refreshed.');
const register = () => call(() => client.post('/settings/whatsapp/register', { pin: pin.value }), 'Number registered.').then(() => { pin.value = ''; });

async function disconnect() {
    if (!window.confirm('Disconnect this WhatsApp number from the ERP? Messages will stop sending until you connect again.')) return;
    await call(() => client.delete('/settings/whatsapp'), 'WhatsApp disconnected.');
    connection.value = null;
}

window.addEventListener('message', onMessage);
onBeforeUnmount(() => {
    window.removeEventListener('message', onMessage);
    stopWatching();
});
load();
</script>
