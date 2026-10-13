<template>
  <div class="wac">
    <div v-if="note" class="wac-alert ok" @click="note = null">{{ note }}</div>
    <div v-if="err" class="wac-alert error" @click="err = null">{{ err }}</div>

    <div v-if="loading" class="wac-panel wac-empty">Loading…</div>

    <template v-else-if="data">
      <div v-if="data.problems.length" class="wac-alert warn">
        <strong>Schools cannot connect WhatsApp yet:</strong>
        <ul><li v-for="p in data.problems" :key="p">{{ p }}</li></ul>
      </div>

      <!-- Settings -->
      <section class="wac-panel">
        <div class="wac-head">
          <h2>Meta app settings</h2>
          <span class="wac-muted">{{ data.connected_schools }} school{{ data.connected_schools === 1 ? '' : 's' }} connected</span>
        </div>

        <form class="wac-form" @submit.prevent="save">
          <label class="wac-switch">
            <input v-model="form.embedded_signup_enabled" type="checkbox" />
            <span>Allow schools to connect WhatsApp (Embedded Signup)</span>
          </label>

          <div class="wac-grid">
            <div class="wac-field">
              <label for="wac-app-id">Meta App ID</label>
              <input id="wac-app-id" v-model.trim="form.app_id" inputmode="numeric" autocomplete="off" placeholder="e.g. 1234567890123456" />
              <small>App settings → Basic → App ID. <SourceTag :source="data.sources.app_id" /></small>
              <small v-if="fieldErr.app_id" class="bad">{{ fieldErr.app_id }}</small>
            </div>
            <div class="wac-field">
              <label for="wac-config">Facebook Login for Business Configuration ID</label>
              <input id="wac-config" v-model.trim="form.embedded_signup_config_id" inputmode="numeric" autocomplete="off" placeholder="e.g. 987654321098765" />
              <small>Facebook Login for Business → Configurations. <SourceTag :source="data.sources.embedded_signup_config_id" /></small>
              <small v-if="fieldErr.embedded_signup_config_id" class="bad">{{ fieldErr.embedded_signup_config_id }}</small>
            </div>
            <div class="wac-field">
              <label for="wac-ver">Graph API version</label>
              <input id="wac-ver" v-model.trim="form.graph_version" autocomplete="off" placeholder="v23.0" />
              <small>As shown on your App Dashboard. <SourceTag :source="data.sources.graph_version" /></small>
              <small v-if="fieldErr.graph_version" class="bad">{{ fieldErr.graph_version }}</small>
            </div>
            <div class="wac-field">
              <label for="wac-ttl">Connect link lifetime (minutes)</label>
              <input id="wac-ttl" v-model.number="form.signup_ticket_ttl" type="number" min="5" max="60" />
              <small>How long a school's "Connect WhatsApp" popup link stays valid (single use).</small>
              <small v-if="fieldErr.signup_ticket_ttl" class="bad">{{ fieldErr.signup_ticket_ttl }}</small>
            </div>
          </div>

          <h3>Secrets <span class="wac-muted">— write-only, stored encrypted; never shown again after saving</span></h3>
          <div class="wac-grid">
            <div class="wac-field">
              <label for="wac-secret">Meta App Secret</label>
              <input id="wac-secret" v-model="form.app_secret" type="password" autocomplete="new-password" :placeholder="data.settings.app_secret_set ? '•••••••• set — leave blank to keep' : 'Not set'" />
              <small>
                App settings → Basic → App secret.
                <template v-if="data.settings.app_secret_set">Last changed {{ fmt(data.settings.app_secret_updated_at) }}.</template>
                <SourceTag :source="data.sources.app_secret" />
              </small>
              <label v-if="data.settings.app_secret_set" class="wac-inline"><input v-model="form.clear_app_secret" type="checkbox" :disabled="!!form.app_secret" /> Remove the saved secret</label>
              <small v-if="fieldErr.app_secret" class="bad">{{ fieldErr.app_secret }}</small>
            </div>
            <div class="wac-field">
              <label for="wac-verify">Webhook Verify Token</label>
              <div class="wac-row">
                <input id="wac-verify" v-model="form.webhook_verify_token" :type="showToken ? 'text' : 'password'" autocomplete="new-password" :placeholder="data.settings.webhook_verify_token_set ? '•••••••• set — leave blank to keep' : 'Not set'" />
                <button type="button" class="wac-btn ghost" @click="generateToken">Generate</button>
              </div>
              <small>
                Paste the same value in Meta → WhatsApp → Configuration → Verify token.
                <template v-if="data.settings.webhook_verify_token_set">Last changed {{ fmt(data.settings.webhook_verify_token_updated_at) }}.</template>
                <SourceTag :source="data.sources.webhook_verify_token" />
              </small>
              <div v-if="showToken && form.webhook_verify_token" class="wac-tokenbox">
                Copy this into Meta <b>before</b> saving — it won't be shown again.
                <button type="button" class="wac-btn ghost sm" @click="copy(form.webhook_verify_token)">Copy</button>
              </div>
              <label v-if="data.settings.webhook_verify_token_set" class="wac-inline"><input v-model="form.clear_webhook_verify_token" type="checkbox" :disabled="!!form.webhook_verify_token" /> Remove the saved token</label>
              <small v-if="fieldErr.webhook_verify_token" class="bad">{{ fieldErr.webhook_verify_token }}</small>
            </div>
          </div>

          <div class="wac-actions">
            <span class="wac-muted" v-if="data.settings.updated_at">Last saved {{ fmt(data.settings.updated_at) }}{{ data.settings.updated_by_email ? ' by ' + data.settings.updated_by_email : '' }}</span>
            <button type="button" class="wac-btn ghost" :disabled="checking" @click="check">{{ checking ? 'Checking…' : 'Check configuration' }}</button>
            <button type="submit" class="wac-btn primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </section>

      <!-- Check results -->
      <section v-if="checkResult" class="wac-panel">
        <div class="wac-head">
          <h2>Configuration check</h2>
          <span class="wac-pill" :class="checkResult.ok ? 'ok' : 'danger'">{{ checkResult.ok ? 'Ready' : 'Needs attention' }}</span>
        </div>
        <table class="wac-table">
          <tbody>
            <tr v-for="r in checkResult.results" :key="r.label">
              <td class="wac-status"><span class="wac-pill" :class="{ ok: r.status === 'ok', warn: r.status === 'warn', danger: r.status === 'fail' }">{{ r.status === 'fail' ? 'FAIL' : r.status.toUpperCase() }}</span></td>
              <td class="wac-strong">{{ r.label }}</td>
              <td>{{ r.message }}</td>
            </tr>
          </tbody>
        </table>
        <div v-if="checkResult.at" class="wac-muted wac-pad">Checked {{ fmt(checkResult.at) }}</div>
      </section>

      <!-- Values for Meta -->
      <section class="wac-panel">
        <div class="wac-head"><h2>Values to enter in Meta</h2><span class="wac-muted">Fixed by this server's domain</span></div>
        <table class="wac-table">
          <tbody>
            <tr v-for="row in metaRows" :key="row.label">
              <td class="wac-strong">{{ row.label }}</td>
              <td><code>{{ row.value }}</code></td>
              <td class="wac-right"><button type="button" class="wac-btn ghost sm" @click="copy(row.value)">Copy</button></td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, h, reactive, ref } from 'vue';

const props = defineProps({
  /** Parent's JSON fetch helper (adds CSRF, throws Error(message) on failure). */
  api: { type: Function, required: true },
});

const SourceTag = (p) => {
  const map = { database: ['saved here', 'ok'], env: ['from .env fallback', 'warn'], missing: ['not set', 'danger'] };
  const [text, cls] = map[p.source] || ['', ''];
  return text ? h('span', { class: `wac-src ${cls}` }, text) : null;
};
SourceTag.props = ['source'];

const loading = ref(true);
const saving = ref(false);
const checking = ref(false);
const data = ref(null);
const note = ref(null);
const err = ref(null);
const showToken = ref(false);
const checkResult = ref(null);
const fieldErr = reactive({});
const form = reactive({});

const metaRows = computed(() => {
  const m = data.value?.meta_values || {};
  return [
    { label: 'Webhook Callback URL', value: m.callback_url },
    { label: 'Embedded Signup page', value: m.signup_page_url },
    { label: 'Valid OAuth Redirect URI', value: m.oauth_redirect_uri },
    { label: 'Allowed Domain for the JavaScript SDK', value: m.allowed_domain },
  ];
});

const fmt = (iso) => (iso ? new Date(iso).toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—');

function hydrate(payload) {
  data.value = payload;
  const s = payload.settings;
  Object.assign(form, {
    embedded_signup_enabled: !!s.embedded_signup_enabled,
    app_id: s.app_id || '',
    embedded_signup_config_id: s.embedded_signup_config_id || '',
    graph_version: s.graph_version || 'v23.0',
    signup_ticket_ttl: s.signup_ticket_ttl || 15,
    app_secret: '',
    webhook_verify_token: '',
    clear_app_secret: false,
    clear_webhook_verify_token: false,
  });
  showToken.value = false;
  if (s.last_check && !checkResult.value) checkResult.value = { ...s.last_check, at: s.last_check.at };
}

async function load() {
  loading.value = true;
  try {
    hydrate(await props.api('/super-admin/api/whatsapp-config'));
  } catch (e) {
    err.value = e.message;
  } finally {
    loading.value = false;
  }
}

function clientValidate() {
  Object.keys(fieldErr).forEach((k) => delete fieldErr[k]);
  if (form.app_id && !/^\d{10,20}$/.test(form.app_id)) fieldErr.app_id = 'Must be the numeric App ID (10–20 digits).';
  if (form.embedded_signup_config_id && !/^\d{10,20}$/.test(form.embedded_signup_config_id)) fieldErr.embedded_signup_config_id = 'Must be the numeric Configuration ID (10–20 digits).';
  if (!/^v\d{1,2}\.\d{1,2}$/.test(form.graph_version || '')) fieldErr.graph_version = 'Must look like v23.0.';
  if (!(form.signup_ticket_ttl >= 5 && form.signup_ticket_ttl <= 60)) fieldErr.signup_ticket_ttl = 'Between 5 and 60 minutes.';
  if (form.app_secret && !/^[A-Za-z0-9]{16,128}$/.test(form.app_secret)) fieldErr.app_secret = 'Letters and digits only (Meta shows 32 characters).';
  if (form.webhook_verify_token && !/^[A-Za-z0-9._~-]{16,255}$/.test(form.webhook_verify_token)) fieldErr.webhook_verify_token = '16–255 characters: letters, digits, . - _ ~';
  return Object.keys(fieldErr).length === 0;
}

async function save() {
  err.value = null;
  note.value = null;
  if (!clientValidate()) return;
  if (form.clear_app_secret && !window.confirm('Remove the saved Meta App Secret? Webhooks will be refused and schools cannot connect until a new one is saved.')) return;
  if (form.webhook_verify_token && data.value.settings.webhook_verify_token_set
      && !window.confirm('Replace the webhook verify token? Update it in Meta → WhatsApp → Configuration too, or Meta will fail to re-verify.')) return;
  saving.value = true;
  try {
    const res = await props.api('/super-admin/api/whatsapp-config', { method: 'PUT', body: JSON.stringify({ ...form, app_id: form.app_id || null, embedded_signup_config_id: form.embedded_signup_config_id || null, app_secret: form.app_secret || null, webhook_verify_token: form.webhook_verify_token || null }) });
    hydrate(res);
    note.value = res.message || 'Saved.';
  } catch (e) {
    err.value = e.message;
  } finally {
    saving.value = false;
  }
}

async function check() {
  checking.value = true;
  err.value = null;
  try {
    const res = await props.api('/super-admin/api/whatsapp-config/check', { method: 'POST' });
    checkResult.value = { ...res, at: new Date().toISOString() };
  } catch (e) {
    err.value = e.message;
  } finally {
    checking.value = false;
  }
}

function generateToken() {
  const bytes = new Uint8Array(32);
  crypto.getRandomValues(bytes);
  form.webhook_verify_token = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  form.clear_webhook_verify_token = false;
  showToken.value = true;
}

async function copy(text) {
  try {
    await navigator.clipboard.writeText(text);
    note.value = 'Copied.';
  } catch {
    err.value = 'Could not copy — select the text and copy it manually.';
  }
}

load();
</script>

<style scoped>
.wac { display: grid; gap: 18px; }
.wac-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }
.wac-head { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid #f1f5f9; }
.wac-head h2 { margin: 0; font-size: 15px; font-weight: 700; color: #0f172a; }
.wac-form { padding: 18px; display: grid; gap: 16px; }
.wac-form h3 { margin: 6px 0 0; font-size: 14px; color: #0f172a; }
.wac-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
.wac-field { display: grid; gap: 5px; min-width: 0; }
.wac-field label { font-size: 13px; font-weight: 600; color: #334155; }
.wac-field input:not([type='checkbox']) { width: 100%; padding: 9px 11px; border: 1px solid #cbd5e1; border-radius: 9px; font-size: 14px; background: #fff; color: #0f172a; }
.wac-field input:focus { outline: 2px solid #6366f1; outline-offset: 0; border-color: #6366f1; }
.wac-field small { font-size: 12px; color: #64748b; }
.wac-field small.bad { color: #dc2626; }
.wac-row { display: flex; gap: 8px; }
.wac-row input { flex: 1; min-width: 0; }
.wac-switch, .wac-inline { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #0f172a; }
.wac-inline { font-size: 12px; color: #475569; font-weight: 400 !important; }
.wac-tokenbox { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; border-radius: 8px; background: #fef3c7; color: #92400e; font-size: 12px; }
.wac-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 10px; border-top: 1px solid #f1f5f9; padding-top: 14px; }
.wac-actions .wac-muted { margin-right: auto; }
.wac-btn { border: 0; border-radius: 9px; padding: 9px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
.wac-btn.sm { padding: 5px 10px; font-size: 12px; }
.wac-btn.primary { background: #4f46e5; color: #fff; }
.wac-btn.ghost { background: #f1f5f9; color: #0f172a; }
.wac-btn:disabled { opacity: .6; cursor: default; }
.wac-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.wac-table td { padding: 11px 18px; border-top: 1px solid #f1f5f9; vertical-align: top; word-break: break-word; }
.wac-table code { font-size: 12.5px; background: #f8fafc; padding: 2px 6px; border-radius: 6px; }
.wac-status { width: 70px; }
.wac-right { text-align: right; width: 80px; }
.wac-strong { font-weight: 600; color: #0f172a; white-space: nowrap; }
.wac-muted { color: #64748b; font-size: 12.5px; font-weight: 400; }
.wac-pad { padding: 10px 18px 14px; }
.wac-empty { padding: 40px; text-align: center; color: #94a3b8; }
.wac-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.wac-pill.ok { background: #dcfce7; color: #166534; }
.wac-pill.warn { background: #fef3c7; color: #92400e; }
.wac-pill.danger { background: #fee2e2; color: #991b1b; }
.wac-src { margin-left: 4px; padding: 1px 6px; border-radius: 6px; font-size: 11px; }
.wac-src.ok { background: #dcfce7; color: #166534; }
.wac-src.warn { background: #fef3c7; color: #92400e; }
.wac-src.danger { background: #fee2e2; color: #991b1b; }
.wac-alert { padding: 11px 14px; border-radius: 10px; font-size: 13px; cursor: pointer; }
.wac-alert ul { margin: 6px 0 0 18px; padding: 0; }
.wac-alert.ok { background: #dcfce7; color: #166534; }
.wac-alert.error { background: #fee2e2; color: #991b1b; }
.wac-alert.warn { background: #fef3c7; color: #92400e; cursor: default; }
@media (max-width: 640px) {
  .wac-table td { padding: 9px 12px; }
  .wac-strong { white-space: normal; }
}
</style>
