<template>
  <div class="sa-shell">
    <!-- Mobile sidebar overlay -->
    <div v-if="sidebarOpen" class="sa-overlay" @click="sidebarOpen = false"></div>

    <aside class="sa-sidebar" :class="{ open: sidebarOpen }">
      <div class="sa-brand">
        <img src="/assets/img/logo/erpsaathi.png" alt="ERPSaathi" />
        <div>
          <div class="sa-brand-title">ERPSaathi</div>
          <div class="sa-brand-sub">Control plane</div>
        </div>
      </div>

      <nav class="sa-nav">
        <button type="button" class="sa-nav-item" :class="{ active: page === 'dashboard' }" @click="go('dashboard')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
          Dashboard
        </button>
        <button type="button" class="sa-nav-item" :class="{ active: page === 'schools' }" @click="go('schools')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>
          Schools
        </button>
      </nav>

      <div class="sa-sidebar-foot">
        <div class="sa-user-chip">
          <div class="sa-avatar">{{ userInitial }}</div>
          <div class="sa-user-meta">
            <div class="sa-user-name">{{ user?.name || 'Super Admin' }}</div>
            <div class="sa-user-email">{{ user?.email || '' }}</div>
          </div>
        </div>
      </div>
    </aside>

    <div class="sa-main">
      <header class="sa-header">
        <div class="sa-header-left">
          <button type="button" class="sa-icon-btn sa-menu" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>
          <div>
            <h1 class="sa-page-title">{{ pageTitle }}</h1>
            <p class="sa-page-sub">{{ pageSubtitle }}</p>
          </div>
        </div>
        <div class="sa-header-actions">
          <button v-if="page === 'schools'" type="button" class="sa-btn sa-btn-primary" @click="showCreate = true">Add school</button>
          <form method="post" action="/super-admin/logout">
            <input type="hidden" name="_token" :value="csrf" />
            <button type="submit" class="sa-btn sa-btn-ghost">Logout</button>
          </form>
        </div>
      </header>

      <main class="sa-content">
        <div v-if="error" class="sa-alert sa-alert-error">{{ error }}</div>
        <div v-if="flash" class="sa-alert sa-alert-ok whitespace-pre-wrap">{{ flash }}</div>

        <!-- Dashboard -->
        <section v-if="page === 'dashboard'" class="sa-stack">
          <div class="sa-stat-grid">
            <div v-for="card in dashboardCards" :key="card.label" class="sa-stat">
              <div class="sa-stat-label">{{ card.label }}</div>
              <div class="sa-stat-value">{{ card.value }}</div>
              <div v-if="card.hint" class="sa-stat-hint">{{ card.hint }}</div>
            </div>
          </div>

          <div class="sa-panel">
            <div class="sa-panel-head">
              <h2>Schools overview</h2>
              <button type="button" class="sa-link" @click="go('schools')">View all</button>
            </div>
            <div class="sa-table-wrap">
              <table class="sa-table">
                <thead>
                  <tr>
                    <th>School</th>
                    <th>Status</th>
                    <th>Setup price</th>
                    <th>Renewal</th>
                    <th>Domain</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td colspan="5" class="sa-empty">Loading…</td></tr>
                  <tr v-else-if="!schools.length"><td colspan="5" class="sa-empty">No schools yet.</td></tr>
                  <tr v-for="s in schools.slice(0, 8)" :key="s.id">
                    <td>
                      <div class="sa-strong">{{ s.name }}</div>
                      <div class="sa-muted"><code>{{ s.slug }}</code></div>
                    </td>
                    <td><span class="sa-pill" :class="statusClass(s.status)">{{ s.status }}</span></td>
                    <td>{{ money(s.price, s.billing_currency) }}</td>
                    <td>{{ money(s.renewal_charge, s.billing_currency) }}</td>
                    <td class="sa-muted">{{ primaryDomain(s) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Schools -->
        <section v-else class="sa-stack">
          <div class="sa-panel">
            <div class="sa-panel-head">
              <h2>All schools</h2>
              <button type="button" class="sa-btn sa-btn-primary sa-btn-sm" @click="showCreate = true">Add school</button>
            </div>
            <div class="sa-table-wrap">
              <table class="sa-table">
                <thead>
                  <tr>
                    <th>School</th>
                    <th>Slug / DB</th>
                    <th>Billing</th>
                    <th>Status</th>
                    <th>Domains</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td colspan="6" class="sa-empty">Loading…</td></tr>
                  <tr v-else-if="!schools.length"><td colspan="6" class="sa-empty">No schools yet.</td></tr>
                  <tr v-for="s in schools" :key="s.id">
                    <td>
                      <div class="sa-strong">{{ s.name }}</div>
                      <div class="sa-muted">{{ s.admin_email }}</div>
                    </td>
                    <td>
                      <div><code>{{ s.slug }}</code></div>
                      <div class="sa-muted">{{ s.db_name }}</div>
                    </td>
                    <td>
                      <div>Setup: {{ money(s.price, s.billing_currency) }}</div>
                      <div class="sa-muted">Renewal: {{ money(s.renewal_charge, s.billing_currency) }}</div>
                    </td>
                    <td>
                      <span class="sa-pill" :class="statusClass(s.status)">{{ s.status }}</span>
                      <div v-if="s.is_first_school" class="sa-muted sa-tiny">first school</div>
                    </td>
                    <td class="sa-muted">
                      <div v-for="d in s.domains || []" :key="d.id">{{ d.domain }}</div>
                    </td>
                    <td>
                      <div class="sa-actions">
                        <button v-if="s.status === 'active'" type="button" class="sa-text-btn warn" @click="setStatus(s, 'inactive')">Deactivate</button>
                        <button v-else-if="s.status !== 'provisioning'" type="button" class="sa-text-btn ok" @click="setStatus(s, 'active')">Activate</button>
                        <button type="button" class="sa-text-btn" @click="openEdit(s)">Edit billing</button>
                        <button type="button" class="sa-text-btn" @click="resetAdmin(s)">Reset admin</button>
                        <button type="button" class="sa-text-btn danger" @click="openDelete(s)">Delete</button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </main>

      <footer class="sa-footer">
        <span>© {{ year }} ERPSaathi Super Admin</span>
        <span>{{ tenancy.base_domain || 'erpsaathi.com' }}</span>
      </footer>
    </div>

    <!-- Create modal -->
    <div v-if="showCreate" class="sa-modal-backdrop" @click.self="showCreate = false">
      <div class="sa-modal">
        <h2>Provision new school</h2>
        <form class="sa-form" @submit.prevent="createSchool">
          <div class="sa-field">
            <label>School name</label>
            <input v-model="form.name" required />
          </div>
          <div class="sa-grid-2">
            <div class="sa-field">
              <label>Slug (optional)</label>
              <input v-model="form.slug" placeholder="auto from name" />
            </div>
            <div class="sa-field">
              <label>Currency</label>
              <input v-model="form.billing_currency" placeholder="INR" maxlength="8" />
            </div>
          </div>
          <div class="sa-grid-2">
            <div class="sa-field">
              <label>Setup / charge price</label>
              <input v-model="form.price" type="number" min="0" step="0.01" placeholder="0.00" />
            </div>
            <div class="sa-field">
              <label>Renewal charge</label>
              <input v-model="form.renewal_charge" type="number" min="0" step="0.01" placeholder="0.00" />
            </div>
          </div>
          <div class="sa-field">
            <label>School admin email</label>
            <input v-model="form.admin_email" type="email" required />
          </div>
          <div class="sa-grid-2">
            <div class="sa-field">
              <label>Admin name</label>
              <input v-model="form.admin_name" />
            </div>
            <div class="sa-field">
              <label>Admin password (optional)</label>
              <input v-model="form.admin_password" type="text" minlength="8" placeholder="auto if blank" autocomplete="new-password" />
            </div>
          </div>
          <div class="sa-field">
            <label>Custom domain (optional)</label>
            <input v-model="form.custom_domain" placeholder="school.example.com" />
          </div>
          <div class="sa-modal-actions">
            <button type="button" class="sa-btn sa-btn-ghost" @click="showCreate = false">Cancel</button>
            <button type="submit" class="sa-btn sa-btn-primary" :disabled="saving">{{ saving ? 'Provisioning…' : 'Create' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit billing modal -->
    <div v-if="editTarget" class="sa-modal-backdrop" @click.self="editTarget = null">
      <div class="sa-modal">
        <h2>Edit billing — {{ editTarget.name }}</h2>
        <form class="sa-form" @submit.prevent="saveBilling">
          <div class="sa-grid-2">
            <div class="sa-field">
              <label>Setup / charge price</label>
              <input v-model="editForm.price" type="number" min="0" step="0.01" />
            </div>
            <div class="sa-field">
              <label>Renewal charge</label>
              <input v-model="editForm.renewal_charge" type="number" min="0" step="0.01" />
            </div>
          </div>
          <div class="sa-field">
            <label>Currency</label>
            <input v-model="editForm.billing_currency" maxlength="8" />
          </div>
          <div class="sa-modal-actions">
            <button type="button" class="sa-btn sa-btn-ghost" @click="editTarget = null">Cancel</button>
            <button type="submit" class="sa-btn sa-btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete modal -->
    <div v-if="deleteTarget" class="sa-modal-backdrop" @click.self="closeDelete">
      <div class="sa-modal sa-modal-danger">
        <h2>Delete school permanently</h2>
        <p class="sa-help">
          Drops database <code>{{ deleteTarget.db_name }}</code>, removes domains, and deletes tenant files. Cannot be undone.
        </p>
        <p v-if="deleteTarget.is_first_school" class="sa-help" style="color:#b91c1c;font-weight:600">
          Warning: this is the first / original school tenant. Deleting it will permanently destroy that ERP database and all its data.
        </p>
        <ul class="sa-help-list">
          <li>School: <strong>{{ deleteTarget.name }}</strong></li>
          <li>Slug: <code>{{ deleteTarget.slug }}</code></li>
          <li v-for="d in deleteTarget.domains || []" :key="d.id">Domain: <code>{{ d.domain }}</code></li>
        </ul>
        <form class="sa-form" @submit.prevent="confirmDelete">
          <div class="sa-field">
            <label>Type school slug, name, or domain to confirm</label>
            <input v-model="deleteConfirmation" required :placeholder="deleteConfirmPlaceholder" autocomplete="off" />
          </div>
          <div class="sa-modal-actions">
            <button type="button" class="sa-btn sa-btn-ghost" @click="closeDelete">Cancel</button>
            <button type="submit" class="sa-btn sa-btn-danger" :disabled="deleting || !deleteConfirmation.trim()">
              {{ deleting ? 'Deleting…' : 'Delete forever' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
const emptyForm = () => ({
  name: '',
  slug: '',
  admin_email: '',
  admin_name: 'School Admin',
  admin_password: '',
  custom_domain: '',
  price: '',
  renewal_charge: '',
  billing_currency: 'INR',
});

export default {
  name: 'SuperAdminApp',
  data() {
    return {
      csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
      page: 'dashboard',
      sidebarOpen: false,
      loading: true,
      saving: false,
      schools: [],
      stats: {},
      user: null,
      tenancy: {},
      error: null,
      flash: null,
      showCreate: false,
      form: emptyForm(),
      editTarget: null,
      editForm: { price: '', renewal_charge: '', billing_currency: 'INR' },
      deleteTarget: null,
      deleteConfirmation: '',
      deleting: false,
      year: new Date().getFullYear(),
    };
  },
  computed: {
    pageTitle() {
      return this.page === 'dashboard' ? 'Dashboard' : 'Schools';
    },
    pageSubtitle() {
      return this.page === 'dashboard'
        ? 'Multi-school SaaS overview and billing totals'
        : 'Provision, bill, activate, or permanently remove schools';
    },
    userInitial() {
      const n = this.user?.name || 'S';
      return String(n).charAt(0).toUpperCase();
    },
    dashboardCards() {
      return [
        { label: 'Total schools', value: this.stats.schools_total ?? '—' },
        { label: 'Active', value: this.stats.schools_active ?? '—' },
        { label: 'Inactive', value: this.stats.schools_inactive ?? '—' },
        { label: 'Failed', value: this.stats.schools_failed ?? '—' },
        {
          label: 'Setup charges (active)',
          value: this.money(this.stats.billing_setup_active, 'INR'),
          hint: `All schools: ${this.money(this.stats.billing_setup_total, 'INR')}`,
        },
        {
          label: 'Renewal charges (active)',
          value: this.money(this.stats.billing_renewal_active, 'INR'),
          hint: `All schools: ${this.money(this.stats.billing_renewal_total, 'INR')}`,
        },
      ];
    },
    deleteConfirmPlaceholder() {
      if (!this.deleteTarget) return '';
      const primary = (this.deleteTarget.domains || []).find((d) => d.is_primary);
      return primary?.domain || this.deleteTarget.slug || this.deleteTarget.name;
    },
  },
  mounted() {
    this.refresh();
  },
  methods: {
    go(page) {
      this.page = page;
      this.sidebarOpen = false;
      this.error = null;
    },
    money(amount, currency = 'INR') {
      if (amount === null || amount === undefined || amount === '') return '—';
      const n = Number(amount);
      if (Number.isNaN(n)) return '—';
      try {
        return new Intl.NumberFormat('en-IN', {
          style: 'currency',
          currency: currency || 'INR',
          maximumFractionDigits: 2,
        }).format(n);
      } catch {
        return `${currency || 'INR'} ${n.toFixed(2)}`;
      }
    },
    primaryDomain(school) {
      const domains = school.domains || [];
      return (domains.find((d) => d.is_primary) || domains[0])?.domain || '—';
    },
    statusClass(status) {
      return {
        active: 'ok',
        inactive: 'muted',
        provisioning: 'warn',
        failed: 'danger',
      }[status] || 'muted';
    },
    async api(url, options = {}) {
      const res = await fetch(url, {
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': this.csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        ...options,
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        const firstValidation = data.errors ? Object.values(data.errors).flat()[0] : null;
        throw new Error(firstValidation || data.message || data.error || `Request failed (${res.status})`);
      }
      return data;
    },
    async refresh() {
      this.loading = true;
      this.error = null;
      try {
        const [summary, list] = await Promise.all([
          this.api('/super-admin/api/summary'),
          this.api('/super-admin/api/schools'),
        ]);
        this.stats = summary.stats || {};
        this.user = summary.user || null;
        this.tenancy = summary.tenancy || {};
        this.schools = list.schools || [];
      } catch (e) {
        this.error = e.message;
      } finally {
        this.loading = false;
      }
    },
    async createSchool() {
      this.saving = true;
      this.error = null;
      this.flash = null;
      try {
        const payload = { ...this.form };
        if (!payload.slug) delete payload.slug;
        if (!payload.custom_domain) delete payload.custom_domain;
        if (!payload.admin_password) delete payload.admin_password;
        if (payload.price === '') delete payload.price;
        if (payload.renewal_charge === '') delete payload.renewal_charge;
        const data = await this.api('/super-admin/api/schools', {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        this.flash = `School created.\nAdmin: ${data.school.admin_email}\nPassword: ${data.admin_password}\nOpen: https://${data.school.slug}.${this.tenancy.base_domain || 'erpsaathi.com'}/erp/login`;
        this.showCreate = false;
        this.form = emptyForm();
        this.page = 'schools';
        await this.refresh();
      } catch (e) {
        this.error = e.message;
      } finally {
        this.saving = false;
      }
    },
    openEdit(school) {
      this.editTarget = school;
      this.editForm = {
        price: school.price ?? '',
        renewal_charge: school.renewal_charge ?? '',
        billing_currency: school.billing_currency || 'INR',
      };
    },
    async saveBilling() {
      if (!this.editTarget) return;
      this.saving = true;
      this.error = null;
      try {
        await this.api(`/super-admin/api/schools/${this.editTarget.id}`, {
          method: 'PUT',
          body: JSON.stringify({
            price: this.editForm.price === '' ? null : this.editForm.price,
            renewal_charge: this.editForm.renewal_charge === '' ? null : this.editForm.renewal_charge,
            billing_currency: this.editForm.billing_currency || 'INR',
          }),
        });
        this.flash = `Billing updated for ${this.editTarget.name}.`;
        this.editTarget = null;
        await this.refresh();
      } catch (e) {
        this.error = e.message;
      } finally {
        this.saving = false;
      }
    },
    async setStatus(school, status) {
      this.flash = null;
      try {
        await this.api(`/super-admin/api/schools/${school.id}/status`, {
          method: 'PATCH',
          body: JSON.stringify({ status }),
        });
        await this.refresh();
      } catch (e) {
        this.error = e.message;
      }
    },
    async resetAdmin(school) {
      if (!confirm(`Reset admin password for ${school.name}?`)) return;
      try {
        const data = await this.api(`/super-admin/api/schools/${school.id}/reset-admin`, { method: 'POST', body: '{}' });
        this.flash = `Admin reset.\nEmail: ${data.admin_email}\nPassword: ${data.admin_password}`;
      } catch (e) {
        this.error = e.message;
      }
    },
    openDelete(school) {
      this.error = null;
      this.flash = null;
      this.deleteTarget = school;
      this.deleteConfirmation = '';
    },
    closeDelete() {
      if (this.deleting) return;
      this.deleteTarget = null;
      this.deleteConfirmation = '';
    },
    async confirmDelete() {
      if (!this.deleteTarget) return;
      this.deleting = true;
      this.error = null;
      this.flash = null;
      try {
        const name = this.deleteTarget.name;
        const data = await this.api(`/super-admin/api/schools/${this.deleteTarget.id}`, {
          method: 'DELETE',
          body: JSON.stringify({ confirmation: this.deleteConfirmation.trim() }),
        });
        this.flash = data.message || `School "${name}" permanently deleted.`;
        this.deleteTarget = null;
        this.deleteConfirmation = '';
        await this.refresh();
      } catch (e) {
        this.error = e.message;
      } finally {
        this.deleting = false;
      }
    },
  },
};
</script>

<style scoped>
.sa-shell {
  --sa-bg: #f4f6f8;
  --sa-panel: #ffffff;
  --sa-ink: #0f172a;
  --sa-muted: #64748b;
  --sa-line: #e2e8f0;
  --sa-accent: #0f766e;
  --sa-accent-deep: #0d5f59;
  --sa-sidebar: #0b1220;
  --sa-sidebar-text: #cbd5e1;
  min-height: 100vh;
  display: flex;
  background: var(--sa-bg);
  color: var(--sa-ink);
  font-family: "DM Sans", ui-sans-serif, system-ui, sans-serif;
}

.sa-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  z-index: 30;
}

.sa-sidebar {
  width: 260px;
  background: linear-gradient(180deg, #0b1220 0%, #111827 100%);
  color: var(--sa-sidebar-text);
  display: flex;
  flex-direction: column;
  padding: 20px 14px;
  flex-shrink: 0;
  z-index: 40;
}

.sa-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 10px 20px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.15);
  margin-bottom: 16px;
}
.sa-brand img { height: 40px; width: auto; object-fit: contain; }
.sa-brand-title { color: #fff; font-weight: 700; font-size: 15px; }
.sa-brand-sub { font-size: 11px; color: #94a3b8; }

.sa-nav { display: flex; flex-direction: column; gap: 4px; flex: 1; }
.sa-nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  border: 0;
  background: transparent;
  color: #cbd5e1;
  text-align: left;
  padding: 11px 12px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
.sa-nav-item:hover { background: rgba(148, 163, 184, 0.12); color: #fff; }
.sa-nav-item.active { background: rgba(15, 118, 110, 0.28); color: #fff; }

.sa-sidebar-foot { padding-top: 14px; border-top: 1px solid rgba(148, 163, 184, 0.15); }
.sa-user-chip { display: flex; align-items: center; gap: 10px; padding: 8px; }
.sa-avatar {
  width: 36px; height: 36px; border-radius: 10px;
  background: var(--sa-accent); color: #fff;
  display: grid; place-items: center; font-weight: 700;
}
.sa-user-name { color: #fff; font-size: 13px; font-weight: 600; }
.sa-user-email { font-size: 11px; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; }

.sa-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.sa-header {
  display: flex; align-items: center; justify-content: space-between; gap: 16px;
  padding: 18px 24px; background: var(--sa-panel);
  border-bottom: 1px solid var(--sa-line); position: sticky; top: 0; z-index: 20;
}
.sa-header-left { display: flex; align-items: center; gap: 12px; }
.sa-page-title { margin: 0; font-size: 1.25rem; font-weight: 700; letter-spacing: -0.02em; }
.sa-page-sub { margin: 2px 0 0; font-size: 13px; color: var(--sa-muted); }
.sa-header-actions { display: flex; align-items: center; gap: 8px; }
.sa-menu { display: none; }

.sa-content { flex: 1; padding: 24px; }
.sa-stack { display: flex; flex-direction: column; gap: 20px; }
.sa-stat-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
@media (min-width: 900px) {
  .sa-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (min-width: 1200px) {
  .sa-stat-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); }
}

.sa-stat {
  background: var(--sa-panel);
  border: 1px solid var(--sa-line);
  border-radius: 16px;
  padding: 16px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.sa-stat-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--sa-muted); }
.sa-stat-value { margin-top: 8px; font-size: 1.35rem; font-weight: 700; letter-spacing: -0.02em; }
.sa-stat-hint { margin-top: 6px; font-size: 11px; color: #94a3b8; }

.sa-panel {
  background: var(--sa-panel);
  border: 1px solid var(--sa-line);
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}
.sa-panel-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 18px; border-bottom: 1px solid var(--sa-line);
}
.sa-panel-head h2 { margin: 0; font-size: 15px; font-weight: 700; }
.sa-link { border: 0; background: none; color: var(--sa-accent); font-weight: 600; cursor: pointer; font-size: 13px; }

.sa-table-wrap { overflow-x: auto; }
.sa-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.sa-table th {
  text-align: left; padding: 12px 16px; font-size: 11px; text-transform: uppercase;
  letter-spacing: 0.05em; color: var(--sa-muted); background: #f8fafc; border-bottom: 1px solid var(--sa-line);
}
.sa-table td { padding: 14px 16px; border-top: 1px solid #f1f5f9; vertical-align: top; }
.sa-strong { font-weight: 600; }
.sa-muted { color: var(--sa-muted); font-size: 12px; }
.sa-tiny { font-size: 11px; margin-top: 4px; }
.sa-empty { text-align: center; color: var(--sa-muted); padding: 36px 16px !important; }

.sa-pill {
  display: inline-flex; padding: 2px 8px; border-radius: 999px;
  font-size: 11px; font-weight: 700; text-transform: lowercase;
}
.sa-pill.ok { background: #ecfdf5; color: #047857; }
.sa-pill.warn { background: #fffbeb; color: #b45309; }
.sa-pill.danger { background: #fef2f2; color: #b91c1c; }
.sa-pill.muted { background: #f1f5f9; color: #475569; }

.sa-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.sa-text-btn {
  border: 0; background: none; padding: 0; cursor: pointer;
  font-size: 12px; font-weight: 700; color: var(--sa-accent);
}
.sa-text-btn.warn { color: #b45309; }
.sa-text-btn.ok { color: #047857; }
.sa-text-btn.danger { color: #b91c1c; }

.sa-btn {
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: 10px; padding: 9px 14px; font-size: 13px; font-weight: 700;
  border: 1px solid transparent; cursor: pointer; text-decoration: none;
}
.sa-btn-sm { padding: 7px 12px; font-size: 12px; }
.sa-btn-primary { background: var(--sa-accent); color: #fff; }
.sa-btn-primary:hover { background: var(--sa-accent-deep); }
.sa-btn-ghost { background: #fff; border-color: var(--sa-line); color: var(--sa-ink); }
.sa-btn-ghost:hover { background: #f8fafc; }
.sa-btn-danger { background: #dc2626; color: #fff; }
.sa-btn-danger:hover { background: #b91c1c; }
.sa-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.sa-icon-btn {
  border: 1px solid var(--sa-line); background: #fff; border-radius: 10px;
  width: 38px; height: 38px; display: grid; place-items: center; cursor: pointer;
}

.sa-alert { border-radius: 12px; padding: 12px 14px; font-size: 13px; margin-bottom: 16px; }
.sa-alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
.sa-alert-ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; }

.sa-footer {
  display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap;
  padding: 14px 24px; border-top: 1px solid var(--sa-line); color: #94a3b8; font-size: 12px;
  background: #fff;
}

.sa-modal-backdrop {
  position: fixed; inset: 0; z-index: 50;
  background: rgba(15, 23, 42, 0.5);
  display: flex; align-items: center; justify-content: center; padding: 16px;
}
.sa-modal {
  width: min(560px, 100%);
  background: #fff; border-radius: 18px; padding: 22px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
}
.sa-modal h2 { margin: 0 0 14px; font-size: 1.1rem; }
.sa-modal-danger h2 { color: #b91c1c; }
.sa-help { margin: 0 0 10px; font-size: 13px; color: var(--sa-muted); line-height: 1.5; }
.sa-help-list { margin: 0 0 14px; padding-left: 18px; font-size: 12px; color: var(--sa-muted); }
.sa-form { display: flex; flex-direction: column; gap: 12px; }
.sa-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.sa-field label {
  display: block; margin-bottom: 6px; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.04em; color: var(--sa-muted);
}
.sa-field input {
  width: 100%; border: 1px solid var(--sa-line); border-radius: 10px;
  padding: 10px 12px; font-size: 14px; outline: none;
}
.sa-field input:focus { border-color: #5eead4; box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12); }
.sa-modal-actions { display: flex; justify-content: flex-end; gap: 8px; padding-top: 6px; }

@media (max-width: 900px) {
  .sa-sidebar {
    position: fixed; inset: 0 auto 0 0; transform: translateX(-105%);
    transition: transform .2s ease;
  }
  .sa-sidebar.open { transform: translateX(0); }
  .sa-menu { display: grid; }
  .sa-grid-2 { grid-template-columns: 1fr; }
}
</style>
