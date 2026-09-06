<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <div class="flex items-center gap-3">
          <img src="/assets/img/logo/erpsaathi.png" alt="ERPSaathi" class="h-10 object-contain" />
          <div>
            <div class="text-sm font-semibold">Super Admin</div>
            <div class="text-xs text-slate-500">Multi-school control plane</div>
          </div>
        </div>
        <form method="post" action="/super-admin/logout">
          <input type="hidden" name="_token" :value="csrf" />
          <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-medium hover:bg-slate-50">Logout</button>
        </form>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8">
      <div class="mb-6 grid gap-3 sm:grid-cols-4">
        <div v-for="card in statCards" :key="card.label" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</div>
          <div class="mt-1 text-2xl font-bold">{{ card.value }}</div>
        </div>
      </div>

      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Schools</h1>
        <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500" @click="showCreate = true">Add school</button>
      </div>

      <div v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>
      <div v-if="flash" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 whitespace-pre-wrap">{{ flash }}</div>

      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-4 py-3">School</th>
              <th class="px-4 py-3">Slug / DB</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Domains</th>
              <th class="px-4 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="5" class="px-4 py-8 text-center text-slate-500">Loading…</td></tr>
            <tr v-else-if="!schools.length"><td colspan="5" class="px-4 py-8 text-center text-slate-500">No schools yet. Run <code>php artisan tenancy:bootstrap-first-school</code>.</td></tr>
            <tr v-for="s in schools" :key="s.id" class="border-t border-slate-100">
              <td class="px-4 py-3">
                <div class="font-medium">{{ s.name }}</div>
                <div class="text-xs text-slate-500">{{ s.admin_email }}</div>
              </td>
              <td class="px-4 py-3">
                <div><code>{{ s.slug }}</code></div>
                <div class="text-xs text-slate-500">{{ s.db_name }}</div>
              </td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass(s.status)">{{ s.status }}</span>
                <div v-if="s.is_first_school" class="mt-1 text-[11px] text-slate-400">first school</div>
              </td>
              <td class="px-4 py-3 text-xs text-slate-600">
                <div v-for="d in s.domains || []" :key="d.id">{{ d.domain }}</div>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-2">
                  <button v-if="s.status === 'active'" type="button" class="text-xs font-semibold text-amber-700" @click="setStatus(s, 'inactive')">Deactivate</button>
                  <button v-else-if="s.status !== 'provisioning'" type="button" class="text-xs font-semibold text-emerald-700" @click="setStatus(s, 'active')">Activate</button>
                  <button type="button" class="text-xs font-semibold text-indigo-700" @click="resetAdmin(s)">Reset admin</button>
                  <button v-if="!s.is_first_school" type="button" class="text-xs font-semibold text-red-600" @click="disableSchool(s)">Disable</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </main>

    <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="showCreate = false">
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
        <h2 class="mb-4 text-lg font-semibold">Provision new school</h2>
        <form class="space-y-3" @submit.prevent="createSchool">
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">School name</label>
            <input v-model="form.name" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Slug (optional)</label>
            <input v-model="form.slug" placeholder="auto from name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">School admin email</label>
            <input v-model="form.admin_email" type="email" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Admin name</label>
            <input v-model="form.admin_name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Admin password (optional)</label>
            <input v-model="form.admin_password" type="text" minlength="8" placeholder="auto-generated if left blank" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" autocomplete="new-password" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-semibold uppercase text-slate-500">Custom domain (optional)</label>
            <input v-model="form.custom_domain" placeholder="school.example.com" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm" @click="showCreate = false">Cancel</button>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white" :disabled="saving">{{ saving ? 'Provisioning…' : 'Create' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SuperAdminApp',
  data() {
    return {
      csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
      loading: true,
      saving: false,
      schools: [],
      stats: {},
      error: null,
      flash: null,
      showCreate: false,
      form: {
        name: '',
        slug: '',
        admin_email: '',
        admin_name: 'School Admin',
        admin_password: '',
        custom_domain: '',
      },
    };
  },
  computed: {
    statCards() {
      return [
        { label: 'Total', value: this.stats.schools_total ?? '—' },
        { label: 'Active', value: this.stats.schools_active ?? '—' },
        { label: 'Inactive', value: this.stats.schools_inactive ?? '—' },
        { label: 'Failed', value: this.stats.schools_failed ?? '—' },
      ];
    },
  },
  mounted() {
    this.refresh();
  },
  methods: {
    statusClass(status) {
      return {
        active: 'bg-emerald-50 text-emerald-700',
        inactive: 'bg-slate-100 text-slate-600',
        provisioning: 'bg-amber-50 text-amber-700',
        failed: 'bg-red-50 text-red-700',
      }[status] || 'bg-slate-100 text-slate-600';
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
        const data = await this.api('/super-admin/api/schools', {
          method: 'POST',
          body: JSON.stringify(payload),
        });
        this.flash = `School created.\nAdmin: ${data.school.admin_email}\nPassword: ${data.admin_password}\nOpen: /erp/login?school=${data.school.slug}`;
        this.showCreate = false;
        this.form = { name: '', slug: '', admin_email: '', admin_name: 'School Admin', admin_password: '', custom_domain: '' };
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
    async disableSchool(school) {
      if (!confirm(`Disable ${school.name}? The database will not be dropped.`)) return;
      try {
        await this.api(`/super-admin/api/schools/${school.id}`, { method: 'DELETE' });
        await this.refresh();
      } catch (e) {
        this.error = e.message;
      }
    },
  },
};
</script>
