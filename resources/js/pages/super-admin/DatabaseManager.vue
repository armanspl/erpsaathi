<template>
  <div class="dbm">
    <div v-if="note" class="dbm-alert ok" @click="note = null">{{ note }}</div>
    <div v-if="err" class="dbm-alert error" @click="err = null">{{ err }}</div>

    <!-- ── School databases list ───────────────────────────────────────── -->
    <section v-if="!school" class="dbm-panel">
      <div class="dbm-panel-head">
        <h2>School databases</h2>
        <button type="button" class="dbm-btn ghost sm" :disabled="listLoading" @click="loadList">Refresh</button>
      </div>
      <div class="dbm-scroll">
        <table class="dbm-table">
          <thead>
            <tr><th>School</th><th>Domain</th><th>Database</th><th>Status</th><th>Tables</th><th>Size</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-if="listLoading"><td colspan="7" class="dbm-empty">Loading…</td></tr>
            <tr v-else-if="!schools.length"><td colspan="7" class="dbm-empty">No schools registered.</td></tr>
            <tr v-for="s in schools" :key="s.id">
              <td><div class="dbm-strong">{{ s.name }}</div><div class="dbm-muted">{{ s.slug }}</div></td>
              <td class="dbm-muted">{{ s.domain || '—' }}</td>
              <td><code>{{ s.db_name }}</code></td>
              <td>
                <span class="dbm-pill" :class="statusClass(s.status)">{{ s.status }}</span>
                <div class="dbm-tiny" :class="s.database?.exists ? 'ok' : 'danger'">
                  {{ s.database?.error ? 'cannot read status' : (s.database?.exists ? 'database found' : 'database missing') }}
                </div>
              </td>
              <td>{{ s.database?.tables ?? '—' }}</td>
              <td>{{ s.database?.size_bytes != null ? bytes(s.database.size_bytes) : '—' }}</td>
              <td>
                <button
                  type="button"
                  class="dbm-btn primary sm"
                  :disabled="!!s.blocked_reason || !s.database?.exists"
                  :title="s.blocked_reason || (!s.database?.exists ? 'Database not found' : '')"
                  @click="openSchool(s.id)"
                >Manage database</button>
                <div v-if="s.blocked_reason" class="dbm-tiny danger">{{ s.blocked_reason }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ── One school's database ───────────────────────────────────────── -->
    <template v-else>
      <section class="dbm-panel dbm-head">
        <div class="dbm-head-main">
          <button type="button" class="dbm-btn ghost sm" @click="closeSchool">← All databases</button>
          <div>
            <div class="dbm-title">{{ school.name }} <span class="dbm-pill" :class="statusClass(school.status)">{{ school.status }}</span></div>
            <div class="dbm-muted">
              <code class="dbm-db">{{ overview?.db_name }}</code>
              <span v-if="school.domain"> · {{ school.domain }}</span>
              <span v-if="overview"> · {{ overview.tables.length }} tables · {{ bytes(overview.size_bytes) }} · MySQL {{ overview.server_version }}</span>
            </div>
          </div>
        </div>
        <div class="dbm-head-actions">
          <button type="button" class="dbm-btn ghost sm" :class="{ active: tab === 'activity' && !table }" @click="showActivity">Activity log</button>
          <a class="dbm-btn ghost sm" :href="`${base}/backup`">Backup database (.sql)</a>
          <button type="button" class="dbm-btn ghost sm" :disabled="overviewLoading" @click="loadOverview">Refresh</button>
        </div>
      </section>

      <div class="dbm-layout">
        <!-- Tables list -->
        <aside class="dbm-panel dbm-tables">
          <input v-model="tableFilter" class="dbm-input" placeholder="Filter tables…" />
          <div v-if="overviewLoading" class="dbm-empty">Loading tables…</div>
          <button
            v-for="t in filteredTables"
            :key="t.name"
            type="button"
            class="dbm-table-item"
            :class="{ active: table === t.name }"
            @click="selectTable(t.name)"
          >
            <span class="dbm-table-name">{{ t.name }} <span v-if="t.is_view" class="dbm-pill muted">view</span></span>
            <span class="dbm-muted">{{ t.rows == null ? '—' : (t.rows_exact ? '' : '≈') + num(t.rows) }}</span>
          </button>
          <div v-if="overview && !filteredTables.length" class="dbm-empty">No tables match.</div>
        </aside>

        <!-- Table workspace -->
        <section class="dbm-panel dbm-work">
          <template v-if="tab === 'activity' && !table">
            <div class="dbm-panel-head"><h2>Activity log — {{ overview?.db_name }}</h2></div>
            <div class="dbm-scroll">
              <table class="dbm-table">
                <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Table</th><th>Rows</th><th>Details</th></tr></thead>
                <tbody>
                  <tr v-if="!audits.length"><td colspan="6" class="dbm-empty">No database operations recorded yet.</td></tr>
                  <tr v-for="a in audits" :key="a.id">
                    <td class="dbm-nowrap">{{ dateTime(a.created_at) }}</td>
                    <td>{{ a.super_admin_email || '—' }}</td>
                    <td><span class="dbm-pill" :class="auditClass(a.action)">{{ a.action.replace(/_/g, ' ') }}</span></td>
                    <td><code v-if="a.table_name">{{ a.table_name }}</code></td>
                    <td>{{ a.affected_rows ?? '' }}</td>
                    <td class="dbm-detail">{{ auditDetail(a) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <div v-else-if="!table" class="dbm-empty big">Select a table on the left to browse its records and structure.</div>

          <template v-else>
            <div class="dbm-panel-head">
              <h2><code>{{ table }}</code></h2>
              <div class="dbm-tabs">
                <button type="button" :class="{ active: tab === 'browse' }" @click="setTab('browse')">Browse</button>
                <button type="button" :class="{ active: tab === 'structure' }" @click="setTab('structure')">Structure</button>
                <button v-if="!currentTable?.is_view" type="button" :class="{ active: tab === 'operations' }" @click="setTab('operations')">Operations</button>
              </div>
            </div>

            <!-- Browse -->
            <div v-if="tab === 'browse'" class="dbm-body">
              <div class="dbm-toolbar">
                <input v-model="search" class="dbm-input grow" placeholder="Search all columns…" @keyup.enter="reloadRows(1)" />
                <button type="button" class="dbm-btn ghost sm" @click="reloadRows(1)">Search</button>
                <button type="button" class="dbm-btn ghost sm" @click="addFilter">+ Filter</button>
                <select v-model.number="perPage" class="dbm-input" @change="reloadRows(1)">
                  <option v-for="n in [10, 25, 50, 100, 250]" :key="n" :value="n">{{ n }} / page</option>
                </select>
                <button v-if="browse?.editable" type="button" class="dbm-btn primary sm" @click="openInsert">Insert record</button>
                <button
                  v-if="browse?.editable"
                  type="button"
                  class="dbm-btn danger sm"
                  :disabled="!selected.length"
                  @click="askDeleteRecords(selectedKeys())"
                >Delete selected ({{ selected.length }})</button>
              </div>

              <div v-for="(f, i) in filters" :key="i" class="dbm-toolbar filter">
                <select v-model="f.column" class="dbm-input">
                  <option value="">column…</option>
                  <option v-for="c in browse?.columns || []" :key="c.name" :value="c.name">{{ c.name }}</option>
                </select>
                <select v-model="f.op" class="dbm-input">
                  <option v-for="o in operators" :key="o.v" :value="o.v">{{ o.l }}</option>
                </select>
                <input v-if="!['is_null', 'not_null'].includes(f.op)" v-model="f.value" class="dbm-input grow" placeholder="value" @keyup.enter="reloadRows(1)" />
                <button type="button" class="dbm-btn ghost sm" @click="filters.splice(i, 1); reloadRows(1)">Remove</button>
              </div>

              <div v-if="browse && !browse.editable && !currentTable?.is_view" class="dbm-note">
                This table has no primary key, so records can be browsed but not edited or deleted individually.
              </div>
              <div v-if="currentTable?.is_view" class="dbm-note">This is a view; it can only be browsed.</div>

              <div class="dbm-scroll dbm-grid-wrap">
                <table class="dbm-table dbm-grid">
                  <thead>
                    <tr>
                      <th v-if="browse?.editable" class="dbm-check"><input type="checkbox" :checked="allSelected" @change="toggleAll" /></th>
                      <th v-if="browse?.editable"></th>
                      <th v-for="c in browse?.columns || []" :key="c.name" class="dbm-sortable" @click="sortBy(c.name)">
                        {{ c.name }}
                        <span v-if="browse.primary_key.includes(c.name)" class="dbm-key" title="Primary key">PK</span>
                        <span v-if="sort === c.name">{{ dir === 'asc' ? '▲' : '▼' }}</span>
                        <div class="dbm-coltype">{{ c.column_type }}</div>
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="rowsLoading"><td :colspan="colspan" class="dbm-empty">Loading…</td></tr>
                    <tr v-else-if="!browse?.rows.length"><td :colspan="colspan" class="dbm-empty">No records{{ search || filters.length ? ' match.' : '.' }}</td></tr>
                    <template v-else>
                    <tr v-for="r in browse.rows" :key="keyId(r.key) || JSON.stringify(r.values)" :class="{ sel: isSelected(r) }">
                      <td v-if="browse.editable" class="dbm-check"><input type="checkbox" :checked="isSelected(r)" @change="toggle(r)" /></td>
                      <td v-if="browse.editable" class="dbm-nowrap">
                        <button type="button" class="dbm-link" @click="openRecord(r, 'view')">View</button>
                        <button type="button" class="dbm-link" @click="openRecord(r, 'edit')">Edit</button>
                        <button type="button" class="dbm-link danger" @click="askDeleteRecords([r.key])">Delete</button>
                      </td>
                      <td v-for="c in browse.columns" :key="c.name" :class="cellClass(r, c)">
                        <template v-if="r.values[c.name] === null">NULL</template>
                        <template v-else>{{ r.values[c.name] }}<span v-if="r.meta[c.name] === 'truncated'">…</span></template>
                      </td>
                    </tr>
                    </template>
                  </tbody>
                </table>
              </div>

              <div v-if="browse" class="dbm-pager">
                <span class="dbm-muted">{{ num(browse.total) }} record(s) · page {{ browse.page }} of {{ browse.last_page }}</span>
                <div class="dbm-pager-btns">
                  <button type="button" class="dbm-btn ghost sm" :disabled="browse.page <= 1" @click="reloadRows(1)">« First</button>
                  <button type="button" class="dbm-btn ghost sm" :disabled="browse.page <= 1" @click="reloadRows(browse.page - 1)">‹ Prev</button>
                  <input v-model.number="pageInput" class="dbm-input page" type="number" min="1" :max="browse.last_page" @keyup.enter="reloadRows(pageInput)" />
                  <button type="button" class="dbm-btn ghost sm" :disabled="browse.page >= browse.last_page" @click="reloadRows(browse.page + 1)">Next ›</button>
                  <button type="button" class="dbm-btn ghost sm" :disabled="browse.page >= browse.last_page" @click="reloadRows(browse.last_page)">Last »</button>
                </div>
              </div>
            </div>

            <!-- Structure -->
            <div v-else-if="tab === 'structure'" class="dbm-body">
              <div v-if="!structure" class="dbm-empty">Loading structure…</div>
              <template v-else>
                <h3 class="dbm-h3">Columns</h3>
                <div class="dbm-scroll">
                  <table class="dbm-table">
                    <thead><tr><th>#</th><th>Name</th><th>Type</th><th>Null</th><th>Default</th><th>Key</th><th>Extra</th><th>Comment</th></tr></thead>
                    <tbody>
                      <tr v-for="(c, i) in structure.columns" :key="c.name">
                        <td class="dbm-muted">{{ i + 1 }}</td>
                        <td class="dbm-strong">{{ c.name }}</td>
                        <td><code>{{ c.column_type }}</code></td>
                        <td>{{ c.nullable ? 'Yes' : 'No' }}</td>
                        <td><span v-if="c.default === null" class="dbm-null">{{ c.nullable ? 'NULL' : 'none' }}</span><code v-else>{{ c.default }}</code></td>
                        <td>{{ c.key }}</td>
                        <td>{{ c.extra }}</td>
                        <td class="dbm-muted">{{ c.comment }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <h3 class="dbm-h3">Indexes</h3>
                <div class="dbm-scroll">
                  <table class="dbm-table">
                    <thead><tr><th>Name</th><th>Columns</th><th>Unique</th><th>Type</th></tr></thead>
                    <tbody>
                      <tr v-if="!structure.indexes.length"><td colspan="4" class="dbm-empty">No indexes.</td></tr>
                      <tr v-for="ix in structure.indexes" :key="ix.name">
                        <td class="dbm-strong">{{ ix.name }}</td>
                        <td><code>{{ ix.columns.join(', ') }}</code></td>
                        <td>{{ ix.primary ? 'Primary' : (ix.unique ? 'Yes' : 'No') }}</td>
                        <td>{{ ix.type }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <h3 class="dbm-h3">Relationships</h3>
                <div class="dbm-scroll">
                  <table class="dbm-table">
                    <thead><tr><th>Direction</th><th>Constraint</th><th>From</th><th>To</th><th>On delete</th><th>On update</th></tr></thead>
                    <tbody>
                      <tr v-if="!structure.foreign_keys.length && !structure.referenced_by.length"><td colspan="6" class="dbm-empty">No foreign keys.</td></tr>
                      <tr v-for="fk in structure.foreign_keys" :key="'o' + fk.name">
                        <td>references</td>
                        <td class="dbm-muted">{{ fk.name }}</td>
                        <td><code>{{ table }}.{{ fk.columns.join(', ') }}</code></td>
                        <td><button type="button" class="dbm-link" @click="selectTable(fk.ref_table)">{{ fk.ref_table }}</button><code>.{{ fk.ref_columns.join(', ') }}</code></td>
                        <td>{{ fk.on_delete }}</td>
                        <td>{{ fk.on_update }}</td>
                      </tr>
                      <tr v-for="fk in structure.referenced_by" :key="'i' + fk.table + fk.name">
                        <td>referenced by</td>
                        <td class="dbm-muted">{{ fk.name }}</td>
                        <td><button type="button" class="dbm-link" @click="selectTable(fk.table)">{{ fk.table }}</button><code>.{{ fk.columns.join(', ') }}</code></td>
                        <td><code>{{ table }}.{{ fk.ref_columns.join(', ') }}</code></td>
                        <td>{{ fk.on_delete }}</td>
                        <td>{{ fk.on_update }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <h3 class="dbm-h3">CREATE statement</h3>
                <pre class="dbm-pre">{{ structure.create_sql }}</pre>
              </template>
            </div>

            <!-- Operations -->
            <div v-else-if="tab === 'operations'" class="dbm-body">
              <div v-if="!structure" class="dbm-empty">Loading…</div>
              <template v-else>
                <div class="dbm-note">
                  These operations change <strong>{{ school.name }}</strong>'s live data in <code>{{ overview?.db_name }}</code>.
                  Download a backup first: <a :href="`${base}/tables/${table}/backup`">Backup this table (.sql)</a> ·
                  <a :href="`${base}/backup`">Backup whole database (.sql)</a>
                </div>

                <div v-if="structure.referenced_by.length" class="dbm-note warn">
                  Other tables reference <code>{{ table }}</code> with foreign keys:
                  <ul>
                    <li v-for="fk in structure.referenced_by" :key="fk.table + fk.name">
                      <code>{{ fk.table }}.{{ fk.columns.join(', ') }}</code> — ON DELETE <strong>{{ fk.on_delete }}</strong>
                      <span class="dbm-muted">({{ deleteRuleText(fk.on_delete) }})</span>
                    </li>
                  </ul>
                </div>

                <div class="dbm-ops">
                  <div class="dbm-op">
                    <h3>Empty table</h3>
                    <p>Deletes <strong>every row</strong> (<code>DELETE FROM</code>). The table, its columns, indexes and AUTO_INCREMENT counter are kept. Runs in a transaction and follows foreign-key rules: if any row is still referenced with RESTRICT / NO ACTION, nothing is deleted.</p>
                    <button type="button" class="dbm-btn warn" @click="askTableOp('empty')">Empty table…</button>
                  </div>
                  <div class="dbm-op">
                    <h3>Truncate table</h3>
                    <p>Removes all rows instantly and <strong>resets AUTO_INCREMENT</strong> to 1. Cannot be rolled back. MySQL refuses it when any other table has a foreign key to this table<span v-if="structure.referenced_by.length"> — <strong>which is the case here, so use Empty instead</strong></span>.</p>
                    <button type="button" class="dbm-btn warn" @click="askTableOp('truncate')">Truncate table…</button>
                  </div>
                  <div class="dbm-op danger">
                    <h3>Drop table</h3>
                    <p>Permanently deletes the table <strong>structure and all its data</strong>. ERP pages that use this table will stop working, and <code>erp:deploy</code> will <strong>not</strong> recreate it (its migration is already recorded). Refused while other tables reference it.</p>
                    <button type="button" class="dbm-btn danger" @click="askTableOp('drop')">Drop table…</button>
                  </div>
                </div>
              </template>
            </div>
          </template>
        </section>
      </div>
    </template>

    <!-- ── Record view / edit / insert ─────────────────────────────────── -->
    <div v-if="record" class="dbm-backdrop" @click.self="record = null">
      <div class="dbm-modal wide">
        <h2>
          {{ record.mode === 'insert' ? 'Insert record into' : record.mode === 'edit' ? 'Edit record in' : 'Record in' }}
          <code>{{ table }}</code>
        </h2>
        <div class="dbm-muted dbm-modal-sub">{{ overview?.db_name }} · {{ school?.name }}<span v-if="record.key"> · key {{ keyText(record.key) }}</span></div>
        <div v-if="record.loading" class="dbm-empty">Loading…</div>

        <div v-else-if="record.mode === 'view'" class="dbm-record">
          <div v-for="c in record.columns" :key="c.name" class="dbm-record-row">
            <div class="dbm-record-label">{{ c.name }} <span class="dbm-coltype">{{ c.column_type }}</span></div>
            <div class="dbm-record-value" :class="{ 'dbm-null': record.values[c.name] === null }">{{ record.values[c.name] === null ? 'NULL' : record.values[c.name] }}</div>
          </div>
          <div class="dbm-modal-actions">
            <button type="button" class="dbm-btn ghost" @click="record = null">Close</button>
            <button v-if="browse?.editable" type="button" class="dbm-btn primary" @click="record.mode = 'edit'; fillForm(record.values)">Edit</button>
          </div>
        </div>

        <form v-else class="dbm-record" @submit.prevent="saveRecord">
          <div v-for="c in record.columns" :key="c.name" class="dbm-record-row">
            <label class="dbm-record-label" :for="'f_' + c.name">
              {{ c.name }}
              <span class="dbm-coltype">{{ c.column_type }}<template v-if="!c.nullable"> · required</template></span>
            </label>
            <div class="dbm-record-value">
              <div v-if="c.binary || c.generated" class="dbm-muted">{{ c.binary ? 'Binary column — not editable here.' : 'Generated column — computed by MySQL.' }}</div>
              <template v-else>
                <select v-if="c.data_type === 'enum'" :id="'f_' + c.name" v-model="form[c.name]" class="dbm-input full" :disabled="nulls[c.name]">
                  <option v-if="record.mode === 'insert' && c.has_default" value="">(default)</option>
                  <option v-for="o in c.options" :key="o" :value="o">{{ o }}</option>
                </select>
                <textarea
                  v-else-if="isLongText(c)"
                  :id="'f_' + c.name"
                  v-model="form[c.name]"
                  class="dbm-input full"
                  rows="3"
                  :disabled="nulls[c.name]"
                ></textarea>
                <input
                  v-else
                  :id="'f_' + c.name"
                  v-model="form[c.name]"
                  class="dbm-input full"
                  :type="c.data_type === 'date' ? 'date' : 'text'"
                  :placeholder="placeholder(c)"
                  :disabled="nulls[c.name]"
                />
                <label v-if="c.nullable" class="dbm-nullbox"><input v-model="nulls[c.name]" type="checkbox" /> NULL</label>
              </template>
              <div v-if="formErrors[c.name]" class="dbm-field-error">{{ formErrors[c.name] }}</div>
            </div>
          </div>
          <div v-if="record.mode === 'insert'" class="dbm-muted dbm-tiny">Leave AUTO_INCREMENT and defaulted fields empty to let MySQL fill them.</div>
          <div class="dbm-modal-actions">
            <button type="button" class="dbm-btn ghost" @click="record = null">Cancel</button>
            <button type="submit" class="dbm-btn primary" :disabled="record.saving">{{ record.saving ? 'Saving…' : (record.mode === 'insert' ? 'Insert' : 'Save changes') }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Confirm destructive action ──────────────────────────────────── -->
    <div v-if="confirmBox" class="dbm-backdrop" @click.self="!confirmBox.busy && (confirmBox = null)">
      <div class="dbm-modal danger">
        <h2>{{ confirmBox.title }}</h2>
        <p class="dbm-help" v-html="confirmBox.html"></p>
        <form @submit.prevent="runConfirm">
          <div v-if="confirmBox.phrase" class="dbm-field">
            <label>Type <code>{{ confirmBox.phrase }}</code> to confirm</label>
            <input v-model="confirmBox.typed" class="dbm-input full" autocomplete="off" />
          </div>
          <div class="dbm-modal-actions">
            <button type="button" class="dbm-btn ghost" :disabled="confirmBox.busy" @click="confirmBox = null">Cancel</button>
            <button
              type="submit"
              class="dbm-btn danger"
              :disabled="confirmBox.busy || (confirmBox.phrase && confirmBox.typed.trim() !== confirmBox.phrase)"
            >{{ confirmBox.busy ? 'Working…' : confirmBox.button }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
  /** Parent's JSON fetch helper (adds CSRF, throws Error(message) on failure). */
  api: { type: Function, required: true },
  /** Open this school's database straight away (from the Schools table). */
  openSchoolId: { type: Number, default: null },
});
const emit = defineEmits(['opened']);

const operators = [
  { v: '=', l: '=' }, { v: '!=', l: '≠' }, { v: 'contains', l: 'contains' }, { v: 'not_contains', l: 'does not contain' },
  { v: 'starts_with', l: 'starts with' }, { v: '>', l: '>' }, { v: '>=', l: '≥' }, { v: '<', l: '<' }, { v: '<=', l: '≤' },
  { v: 'is_null', l: 'IS NULL' }, { v: 'not_null', l: 'IS NOT NULL' },
];

const note = ref(null);
const err = ref(null);
const schools = ref([]);
const listLoading = ref(false);

const school = ref(null);
const overview = ref(null);
const overviewLoading = ref(false);
const tableFilter = ref('');
const table = ref(null);
const tab = ref('browse');
const audits = ref([]);

const browse = ref(null);
const rowsLoading = ref(false);
const search = ref('');
const filters = ref([]);
const sort = ref('');
const dir = ref('asc');
const perPage = ref(25);
const pageInput = ref(1);
const selected = ref([]);

const structure = ref(null);
const record = ref(null);
const form = reactive({});
const nulls = reactive({});
const formErrors = ref({});
const confirmBox = ref(null);

const base = computed(() => (school.value ? `/super-admin/api/schools/${school.value.id}/database` : ''));
const currentTable = computed(() => overview.value?.tables.find((t) => t.name === table.value) || null);
const filteredTables = computed(() => {
  const q = tableFilter.value.trim().toLowerCase();
  return (overview.value?.tables || []).filter((t) => !q || t.name.toLowerCase().includes(q));
});
const colspan = computed(() => (browse.value?.columns.length || 1) + (browse.value?.editable ? 2 : 0));
const allSelected = computed(() => !!browse.value?.rows.length && browse.value.rows.every((r) => isSelected(r)));

function flash(message) { note.value = message; err.value = null; }
function fail(e) { err.value = e?.message || String(e); note.value = null; }

async function loadList() {
  listLoading.value = true;
  try {
    schools.value = (await props.api('/super-admin/api/databases')).schools || [];
  } catch (e) { fail(e); } finally { listLoading.value = false; }
}

async function openSchool(id) {
  err.value = null;
  note.value = null;
  school.value = { id, name: '…' };
  table.value = null;
  tab.value = 'browse';
  await loadOverview();
  emit('opened', id);
}

function closeSchool() {
  school.value = null;
  overview.value = null;
  table.value = null;
  loadList();
}

async function loadOverview() {
  overviewLoading.value = true;
  try {
    const data = await props.api(base.value);
    overview.value = data;
    school.value = data.school;
    if (table.value && !data.tables.some((t) => t.name === table.value)) table.value = null;
  } catch (e) {
    fail(e);
    if (!overview.value) school.value = null;
  } finally { overviewLoading.value = false; }
}

async function showActivity() {
  table.value = null;
  tab.value = 'activity';
  try {
    audits.value = (await props.api(`${base.value}/audits`)).audits || [];
  } catch (e) { fail(e); }
}

function selectTable(name) {
  if (!overview.value?.tables.some((t) => t.name === name)) return;
  table.value = name;
  search.value = '';
  filters.value = [];
  sort.value = '';
  dir.value = 'asc';
  selected.value = [];
  browse.value = null;
  structure.value = null;
  setTab('browse');
  loadStructure(); // also feeds the ON DELETE CASCADE warning when deleting records
}

function setTab(next) {
  tab.value = next;
  if (next === 'browse') reloadRows(1);
  if (next === 'structure' || next === 'operations') loadStructure();
}

async function loadStructure() {
  const name = table.value;
  if (structure.value?.table?.name !== name) structure.value = null;
  try {
    const data = await props.api(`${base.value}/tables/${encodeURIComponent(name)}/structure`);
    if (table.value === name) structure.value = data;
  } catch (e) { fail(e); }
}

async function reloadRows(page = 1) {
  if (!table.value) return;
  rowsLoading.value = true;
  const params = new URLSearchParams({
    page: String(Math.max(1, Number(page) || 1)),
    per_page: String(perPage.value),
    search: search.value,
    sort: sort.value,
    dir: dir.value,
    filters: JSON.stringify(filters.value.filter((f) => f.column)),
  });
  try {
    browse.value = await props.api(`${base.value}/tables/${encodeURIComponent(table.value)}/rows?${params}`);
    pageInput.value = browse.value.page;
    selected.value = selected.value.filter((id) => browse.value.rows.some((r) => keyId(r.key) === id));
  } catch (e) { fail(e); } finally { rowsLoading.value = false; }
}

function addFilter() { filters.value.push({ column: '', op: '=', value: '' }); }

function sortBy(col) {
  if (sort.value === col) dir.value = dir.value === 'asc' ? 'desc' : 'asc';
  else { sort.value = col; dir.value = 'asc'; }
  reloadRows(1);
}

const keyId = (key) => (key ? JSON.stringify(key) : '');
const keyText = (key) => Object.entries(key || {}).map(([k, v]) => `${k}=${v}`).join(', ');
const isSelected = (r) => selected.value.includes(keyId(r.key));
function toggle(r) {
  const id = keyId(r.key);
  selected.value = isSelected(r) ? selected.value.filter((x) => x !== id) : [...selected.value, id];
}
function toggleAll() {
  selected.value = allSelected.value ? [] : (browse.value?.rows || []).map((r) => keyId(r.key));
}
const selectedKeys = () => selected.value.map((id) => JSON.parse(id));

function cellClass(r, c) {
  const v = r.values[c.name];
  return {
    'dbm-null': v === null,
    'dbm-binary': r.meta[c.name] === 'binary',
    'dbm-num': ['int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'].includes(c.data_type),
  };
}

// ── Records ──────────────────────────────────────────────────────────────
async function openRecord(r, mode) {
  formErrors.value = {};
  record.value = { mode, key: r.key, columns: browse.value.columns, values: {}, loading: true, saving: false };
  try {
    const params = new URLSearchParams();
    Object.entries(r.key).forEach(([k, v]) => params.append(`key[${k}]`, v));
    const data = await props.api(`${base.value}/tables/${encodeURIComponent(table.value)}/record?${params}`);
    record.value = { ...record.value, columns: data.columns, values: data.record.values, loading: false };
    if (mode === 'edit') fillForm(data.record.values);
  } catch (e) { record.value = null; fail(e); }
}

function fillForm(values) {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.keys(nulls).forEach((k) => delete nulls[k]);
  record.value.columns.forEach((c) => {
    const v = values[c.name];
    form[c.name] = v === null || v === undefined ? '' : String(v);
    nulls[c.name] = v === null;
  });
}

const isExpressionDefault = (c) => /current_timestamp|now\(\)|uuid\(\)/i.test(String(c.default ?? ''));

function openInsert() {
  formErrors.value = {};
  record.value = { mode: 'insert', key: null, columns: browse.value.columns, values: {}, loading: false, saving: false };
  Object.keys(form).forEach((k) => delete form[k]);
  Object.keys(nulls).forEach((k) => delete nulls[k]);
  browse.value.columns.forEach((c) => {
    form[c.name] = c.default !== null && !isExpressionDefault(c) && !c.auto_increment ? String(c.default) : '';
    nulls[c.name] = c.nullable && c.default === null;
  });
}

function placeholder(c) {
  if (c.auto_increment) return 'auto (AUTO_INCREMENT)';
  if (isExpressionDefault(c)) return `default: ${c.default}`;
  if (c.data_type === 'datetime' || c.data_type === 'timestamp') return 'YYYY-MM-DD HH:MM:SS';
  if (c.data_type === 'time') return 'HH:MM:SS';
  return '';
}

const isLongText = (c) => ['text', 'tinytext', 'mediumtext', 'longtext', 'json'].includes(c.data_type) || (c.max_length || 0) > 255;

async function saveRecord() {
  const r = record.value;
  const values = {};
  r.columns.forEach((c) => {
    if (c.binary || c.generated) return;
    if (nulls[c.name]) { values[c.name] = null; return; }
    const v = form[c.name] ?? '';
    if (r.mode === 'insert' && v === '' && (c.auto_increment || c.has_default)) return;
    values[c.name] = v;
  });

  r.saving = true;
  formErrors.value = {};
  try {
    const url = `${base.value}/tables/${encodeURIComponent(table.value)}/rows`;
    const data = r.mode === 'insert'
      ? await rawApi(url, 'POST', { values })
      : await rawApi(url, 'PUT', { key: r.key, values });
    record.value = null;
    flash(data.message);
    await Promise.all([reloadRows(browse.value?.page || 1), refreshCounts()]);
  } catch (e) {
    if (e.errors) formErrors.value = Object.fromEntries(Object.entries(e.errors).map(([k, v]) => [k, [].concat(v)[0]]));
    fail(e);
  } finally {
    if (record.value) record.value.saving = false;
  }
}

/** Like props.api but keeps per-field validation errors for the record form. */
async function rawApi(url, method, body) {
  const res = await fetch(url, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const e = new Error(data.errors ? Object.values(data.errors).flat()[0] : (data.message || `Request failed (${res.status})`));
    e.errors = data.errors || null;
    throw e;
  }
  return data;
}

async function refreshCounts() {
  try {
    const data = await props.api(base.value);
    overview.value = data;
  } catch { /* counts are cosmetic */ }
}

// ── Destructive confirmations ────────────────────────────────────────────
const esc = (s) => String(s).replace(/[&<>"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));

function askDeleteRecords(keys) {
  if (!keys.length) return;
  const cascade = (structure.value?.referenced_by || []).filter((fk) => fk.on_delete === 'CASCADE').map((fk) => fk.table);
  confirmBox.value = {
    title: `Delete ${keys.length} record(s)?`,
    html: `Permanently deletes ${keys.length} record(s) from <code>${esc(table.value)}</code> in <code>${esc(overview.value.db_name)}</code> (${esc(school.value.name)}). `
      + 'All selected records are deleted together, or none if MySQL refuses (e.g. a foreign key still references one). '
      + (cascade.length ? `Related rows in ${cascade.map((t) => `<code>${esc(t)}</code>`).join(', ')} are deleted too (ON DELETE CASCADE).` : ''),
    button: 'Delete records',
    phrase: null,
    typed: '',
    busy: false,
    run: async () => {
      const data = await rawApi(`${base.value}/tables/${encodeURIComponent(table.value)}/rows`, 'DELETE', { keys });
      selected.value = [];
      flash(data.message);
      return () => Promise.all([reloadRows(browse.value?.page || 1), refreshCounts()]);
    },
  };
}

function askTableOp(kind) {
  const phrase = `${overview.value.db_name}.${table.value}`;
  const rows = currentTable.value?.rows;
  const texts = {
    empty: ['Empty table', `Deletes all ${rows ?? ''} row(s) from <code>${esc(table.value)}</code>. The table structure and AUTO_INCREMENT are kept. Referencing rows follow their ON DELETE rule (CASCADE deletes them too).`, 'Empty table', 'POST', 'empty'],
    truncate: ['Truncate table', `Removes all ${rows ?? ''} row(s) from <code>${esc(table.value)}</code> and resets AUTO_INCREMENT. This cannot be rolled back. MySQL refuses if other tables reference this table.`, 'Truncate table', 'POST', 'truncate'],
    drop: ['Drop table', `Permanently deletes <code>${esc(table.value)}</code> — its structure and all ${rows ?? ''} row(s). ERP features using this table will break and migrations will not recreate it.`, 'Drop table forever', 'DELETE', ''],
  }[kind];
  confirmBox.value = {
    title: `${texts[0]} — ${school.value.name}`,
    html: `${texts[1]}<br><br><strong>Download a backup first</strong> if you may need this data: <a href="${base.value}/tables/${encodeURIComponent(table.value)}/backup">table backup (.sql)</a>.`,
    button: texts[2],
    phrase,
    typed: '',
    busy: false,
    run: async () => {
      const url = `${base.value}/tables/${encodeURIComponent(table.value)}${texts[4] ? `/${texts[4]}` : ''}`;
      const data = await rawApi(url, texts[3], { confirmation: phrase });
      flash(data.message);
      if (kind === 'drop') {
        table.value = null;
        return loadOverview;
      }
      return () => { refreshCounts(); setTab('operations'); };
    },
  };
}

async function runConfirm() {
  const box = confirmBox.value;
  box.busy = true;
  try {
    const after = await box.run();
    confirmBox.value = null; // close as soon as MySQL confirmed; refresh the view afterwards
    if (after) after();
  } catch (e) {
    box.busy = false;
    confirmBox.value = null;
    fail(e);
  }
}

// ── Formatting ───────────────────────────────────────────────────────────
const num = (n) => Number(n || 0).toLocaleString('en-IN');
function bytes(n) {
  const v = Number(n || 0);
  if (v < 1024) return `${v} B`;
  if (v < 1048576) return `${(v / 1024).toFixed(1)} KB`;
  if (v < 1073741824) return `${(v / 1048576).toFixed(1)} MB`;
  return `${(v / 1073741824).toFixed(2)} GB`;
}
const dateTime = (s) => (s ? new Date(s).toLocaleString() : '');
const statusClass = (s) => ({ active: 'ok', inactive: 'muted', provisioning: 'warn', failed: 'danger' }[s] || 'muted');
const auditClass = (a) => (/drop|truncate|empty|delete/.test(a) ? 'danger' : /backup/.test(a) ? 'muted' : 'ok');
function auditDetail(a) {
  const parts = [];
  if (a.record_key) parts.push(`key ${JSON.stringify(a.record_key)}`);
  if (a.details?.after) parts.push(`changed ${Object.keys(a.details.after).join(', ')}`);
  if (a.details?.values) parts.push(`values ${Object.keys(a.details.values).length} column(s)`);
  if (a.ip) parts.push(`ip ${a.ip}`);
  return parts.join(' · ');
}
const deleteRuleText = (rule) => ({
  CASCADE: 'their rows are deleted too',
  'SET NULL': 'their link is cleared',
  RESTRICT: 'blocks deleting referenced rows',
  'NO ACTION': 'blocks deleting referenced rows',
  'SET DEFAULT': 'link reset to default',
}[rule] || rule);

watch(() => props.openSchoolId, (id) => { if (id) openSchool(id); });
onMounted(() => {
  if (props.openSchoolId) openSchool(props.openSchoolId);
  else loadList();
});
</script>

<style scoped>
.dbm { display: flex; flex-direction: column; gap: 16px; font-size: 13px; }
.dbm-panel { background: var(--sa-panel, #fff); border: 1px solid var(--sa-line, #e2e8f0); border-radius: 18px; overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03); min-width: 0; }
.dbm-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 18px; border-bottom: 1px solid var(--sa-line, #e2e8f0); }
.dbm-panel-head h2 { margin: 0; font-size: 15px; font-weight: 700; }
.dbm-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 14px 18px; }
.dbm-head-main { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
.dbm-head-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.dbm-title { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.dbm-db { background: #f0fdfa; color: #0f766e; padding: 1px 6px; border-radius: 6px; font-weight: 700; }
.dbm-layout { display: grid; grid-template-columns: 270px minmax(0, 1fr); gap: 16px; align-items: start; }
.dbm-tables { padding: 10px; display: flex; flex-direction: column; gap: 2px; max-height: calc(100vh - 230px); overflow-y: auto; position: sticky; top: 90px; }
.dbm-tables .dbm-input { margin-bottom: 8px; }
.dbm-table-item { display: flex; justify-content: space-between; gap: 8px; align-items: center; border: 0; background: none; text-align: left; padding: 7px 9px; border-radius: 8px; cursor: pointer; font-size: 12.5px; color: var(--sa-ink, #0f172a); }
.dbm-table-item:hover { background: #f1f5f9; }
.dbm-table-item.active { background: rgba(15, 118, 110, 0.12); color: #0d5f59; font-weight: 700; }
.dbm-table-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dbm-work { min-height: 420px; }
.dbm-body { padding: 14px 18px 18px; display: flex; flex-direction: column; gap: 10px; }
.dbm-tabs { display: flex; gap: 4px; background: #f1f5f9; padding: 3px; border-radius: 10px; }
.dbm-tabs button { border: 0; background: none; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-size: 12px; color: var(--sa-muted, #64748b); cursor: pointer; }
.dbm-tabs button.active { background: #fff; color: var(--sa-ink, #0f172a); box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08); }
.dbm-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.dbm-toolbar.filter { background: #f8fafc; padding: 6px; border-radius: 10px; }
.dbm-input { border: 1px solid var(--sa-line, #e2e8f0); border-radius: 9px; padding: 7px 10px; font-size: 13px; outline: none; background: #fff; color: inherit; font-family: inherit; }
.dbm-input:focus { border-color: #5eead4; box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.12); }
.dbm-input.grow { flex: 1; min-width: 160px; }
.dbm-input.full { width: 100%; box-sizing: border-box; }
.dbm-input.page { width: 70px; }
.dbm-btn { display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; padding: 9px 14px; font-size: 13px; font-weight: 700; border: 1px solid transparent; cursor: pointer; text-decoration: none; white-space: nowrap; }
.dbm-btn.sm { padding: 6px 11px; font-size: 12px; }
.dbm-btn.primary { background: var(--sa-accent, #0f766e); color: #fff; }
.dbm-btn.primary:hover { background: var(--sa-accent-deep, #0d5f59); }
.dbm-btn.ghost { background: #fff; border-color: var(--sa-line, #e2e8f0); color: var(--sa-ink, #0f172a); }
.dbm-btn.ghost:hover, .dbm-btn.ghost.active { background: #f1f5f9; }
.dbm-btn.danger { background: #dc2626; color: #fff; }
.dbm-btn.danger:hover { background: #b91c1c; }
.dbm-btn.warn { background: #d97706; color: #fff; }
.dbm-btn.warn:hover { background: #b45309; }
.dbm-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.dbm-link { border: 0; background: none; padding: 0 4px; cursor: pointer; color: var(--sa-accent, #0f766e); font-weight: 700; font-size: 12px; }
.dbm-link.danger { color: #b91c1c; }
.dbm-scroll { overflow-x: auto; }
.dbm-grid-wrap { max-height: 60vh; overflow: auto; border: 1px solid var(--sa-line, #e2e8f0); border-radius: 10px; }
.dbm-table { width: 100%; border-collapse: collapse; }
.dbm-table th { text-align: left; padding: 9px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--sa-muted, #64748b); background: #f8fafc; border-bottom: 1px solid var(--sa-line, #e2e8f0); white-space: nowrap; }
.dbm-table td { padding: 9px 12px; border-top: 1px solid #f1f5f9; vertical-align: top; }
.dbm-grid th { position: sticky; top: 0; z-index: 1; text-transform: none; letter-spacing: 0; font-size: 12px; color: var(--sa-ink, #0f172a); }
.dbm-grid td { max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12.5px; }
.dbm-grid tr.sel td { background: #f0fdfa; }
.dbm-grid tbody tr:hover td { background: #f8fafc; }
.dbm-sortable { cursor: pointer; user-select: none; }
.dbm-coltype { font-size: 10.5px; color: #94a3b8; font-weight: 500; text-transform: none; letter-spacing: 0; }
.dbm-key { font-size: 9px; background: #fef3c7; color: #92400e; border-radius: 4px; padding: 0 4px; margin-left: 2px; }
.dbm-check { width: 28px; }
.dbm-null { color: #94a3b8; font-style: italic; }
.dbm-binary { color: #7c3aed; font-style: italic; }
.dbm-num { text-align: right; font-variant-numeric: tabular-nums; }
.dbm-nowrap { white-space: nowrap; }
.dbm-detail { font-size: 12px; color: var(--sa-muted, #64748b); max-width: 420px; word-break: break-word; }
.dbm-pager { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
.dbm-pager-btns { display: flex; gap: 6px; align-items: center; }
.dbm-strong { font-weight: 600; }
.dbm-muted { color: var(--sa-muted, #64748b); font-size: 12px; }
.dbm-tiny { font-size: 11px; margin-top: 4px; }
.dbm-tiny.ok { color: #047857; }
.dbm-tiny.danger { color: #b91c1c; }
.dbm-empty { text-align: center; color: var(--sa-muted, #64748b); padding: 28px 12px !important; }
.dbm-empty.big { padding: 90px 20px !important; }
.dbm-pill { display: inline-flex; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.dbm-pill.ok { background: #ecfdf5; color: #047857; }
.dbm-pill.warn { background: #fffbeb; color: #b45309; }
.dbm-pill.danger { background: #fef2f2; color: #b91c1c; }
.dbm-pill.muted { background: #f1f5f9; color: #475569; }
.dbm-alert { border-radius: 12px; padding: 11px 14px; font-size: 13px; cursor: pointer; white-space: pre-wrap; }
.dbm-alert.ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; }
.dbm-alert.error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
.dbm-note { background: #f8fafc; border: 1px solid var(--sa-line, #e2e8f0); border-radius: 10px; padding: 10px 12px; color: #334155; line-height: 1.5; }
.dbm-note.warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
.dbm-note ul { margin: 6px 0 0; padding-left: 18px; }
.dbm-note a { color: var(--sa-accent, #0f766e); font-weight: 700; }
.dbm-h3 { margin: 8px 0 0; font-size: 13px; font-weight: 700; }
.dbm-pre { background: #0f172a; color: #e2e8f0; border-radius: 10px; padding: 12px; font-size: 12px; overflow: auto; max-height: 360px; margin: 0; }
.dbm-ops { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
.dbm-op { border: 1px solid #fde68a; background: #fffdf5; border-radius: 14px; padding: 14px; display: flex; flex-direction: column; gap: 8px; }
.dbm-op.danger { border-color: #fecaca; background: #fff8f8; }
.dbm-op h3 { margin: 0; font-size: 14px; }
.dbm-op p { margin: 0; color: #475569; line-height: 1.5; flex: 1; }
.dbm-op .dbm-btn { align-self: flex-start; }
.dbm-backdrop { position: fixed; inset: 0; z-index: 60; background: rgba(15, 23, 42, 0.5); display: flex; align-items: center; justify-content: center; padding: 16px; }
.dbm-modal { width: min(560px, 100%); max-height: 92vh; overflow-y: auto; background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2); }
.dbm-modal.wide { width: min(860px, 100%); }
.dbm-modal h2 { margin: 0 0 4px; font-size: 1.05rem; }
.dbm-modal.danger h2 { color: #b91c1c; }
.dbm-modal-sub { margin-bottom: 14px; }
.dbm-help { margin: 8px 0 14px; color: #475569; line-height: 1.55; }
.dbm-help :deep(a) { color: var(--sa-accent, #0f766e); font-weight: 700; }
.dbm-field label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 700; color: var(--sa-muted, #64748b); }
.dbm-record { display: flex; flex-direction: column; gap: 2px; }
.dbm-record-row { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 12px; padding: 7px 0; border-bottom: 1px solid #f1f5f9; align-items: start; }
.dbm-record-label { font-weight: 700; font-size: 12.5px; word-break: break-word; }
.dbm-record-label .dbm-coltype { display: block; }
.dbm-record-value { white-space: pre-wrap; word-break: break-word; min-width: 0; }
.dbm-nullbox { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; color: var(--sa-muted, #64748b); margin-top: 4px; }
.dbm-field-error { color: #b91c1c; font-size: 12px; margin-top: 3px; }
.dbm-modal-actions { display: flex; justify-content: flex-end; gap: 8px; padding-top: 14px; }
code { font-size: 12px; }
@media (max-width: 1000px) {
  .dbm-layout { grid-template-columns: 1fr; }
  .dbm-tables { position: static; max-height: 260px; }
  .dbm-record-row { grid-template-columns: 1fr; gap: 4px; }
}
</style>
