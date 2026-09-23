<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useStatsStore } from '../stores/stats'

const statsStore = useStatsStore()
const router = useRouter()

// One shared fetch also feeds the sidebar badges (events/candidates counts).
onMounted(() => statsStore.load())

const totals = computed(() => statsStore.data || {
  events: 0,
  active_events: 0,
  registrations: 0,
  registrations_this_week: 0,
  joined: 0,
  candidates: 0,
  candidates_this_week: 0,
  recent_events: [],
  recent_activity: [],
})

const activeEvents = computed(() => totals.value.active_events ?? 0)
const checkedIn = computed(() => totals.value.joined ?? 0)
// Weekly delta, mirroring the "+12% this wk" pill in the design.
const weekDelta = computed(() => {
  const total = totals.value.registrations ?? 0
  const week = totals.value.registrations_this_week ?? 0
  if (!total || !week) return 'this week'
  return `+${Math.round((week / Math.max(total - week, 1)) * 100)}% this wk`
})

const candidatesPill = computed(() => {
  const week = totals.value.candidates_this_week ?? 0
  return week > 0 ? `+${week} this week` : 'Listed'
})

// ---------- Recent events: search + status filter + pagination ----------
const search = ref('')
const statusFilter = ref('all')
const page = ref(1)
const perPage = 4

const filteredEvents = computed(() => {
  const list = totals.value.recent_events || []
  const q = search.value.trim().toLowerCase()
  return list.filter((e) => {
    if (statusFilter.value !== 'all' && e.status !== statusFilter.value) return false
    if (!q) return true
    return (e.event_code || '').toLowerCase().includes(q)
      || (e.title || '').toLowerCase().includes(q)
  })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filteredEvents.value.length / perPage)))
const pagedEvents = computed(() =>
  filteredEvents.value.slice((page.value - 1) * perPage, page.value * perPage)
)

// Keep the page in range as the filter shrinks/grows the list.
function onSearch() { page.value = 1 }
function onFilter() { page.value = 1 }

function subtitle(e) {
  const desc = (e.description || '').trim()
  if (desc) return desc.length > 48 ? desc.slice(0, 48) + '…' : desc
  return e.event_code || ''
}

function statusClass(status) {
  return { open: 'is-open', closed: 'is-closed', draft: 'is-draft' }[status] || 'is-draft'
}

// ---------- Recent activity feed ----------
const activity = computed(() => (totals.value.recent_activity || []).slice(0, 4))

function timeAgo(iso) {
  if (!iso) return ''
  const secs = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000)
  if (secs < 60) return 'just now'
  if (secs < 3600) return `${Math.floor(secs / 60)} minutes ago`
  if (secs < 86400) return `${Math.floor(secs / 3600)} hours ago`
  return `${Math.floor(secs / 86400)} days ago`
}

function goScan() {
  // The dedicated check-in screen starts its camera session on mount.
  router.push({ name: 'scan' })
}
</script>

<template>
  <div class="dash">
    <!-- Page head -->
    <div class="dash-head">
      <div>
        <h1>Dashboard</h1>
        <p class="dash-sub">Real-time overview of your registrations, events, and candidate check-in counts.</p>
      </div>
      <div class="dash-actions">
        <RouterLink class="btn btn-ghost" :to="{ name: 'candidates' }">⬇ Export Summary</RouterLink>
        <RouterLink class="btn btn-primary" :to="{ name: 'event-create' }">＋ Create Event</RouterLink>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
      <div class="stat-card stat-teal">
        <div class="stat-top">
          <span class="stat-name">Total Events</span>
          <span class="stat-ico">📅</span>
        </div>
        <div class="stat-row">
          <div>
            <div class="stat-value">{{ totals.events }}</div>
            <div class="stat-label">Events created</div>
          </div>
          <span class="pill pill-teal">{{ activeEvents }} active</span>
        </div>
      </div>

      <div class="stat-card stat-green">
        <div class="stat-top">
          <span class="stat-name">Registrations</span>
          <span class="stat-ico">📄</span>
        </div>
        <div class="stat-row">
          <div>
            <div class="stat-value">{{ totals.registrations }}</div>
            <div class="stat-label">Total signups</div>
          </div>
          <span class="pill pill-green">{{ weekDelta }}</span>
        </div>
      </div>

      <div class="stat-card stat-amber">
        <div class="stat-top">
          <span class="stat-name">Checked In</span>
          <span class="stat-ico">✅</span>
        </div>
        <div class="stat-row">
          <div>
            <div class="stat-value">{{ checkedIn }}</div>
            <div class="stat-label">Checked in</div>
          </div>
          <span class="pill pill-amber">Awaiting check-in</span>
        </div>
      </div>

      <div class="stat-card stat-violet">
        <div class="stat-top">
          <span class="stat-name">Attendance</span>
          <span class="stat-ico">👥</span>
        </div>
        <div class="stat-row">
          <div>
            <div class="stat-value">{{ totals.candidates }}</div>
            <div class="stat-label">Attendees listed</div>
          </div>
          <span class="pill pill-violet">{{ candidatesPill }}</span>
        </div>
      </div>
    </div>

    <!-- Main two-column area -->
    <div class="dash-grid">
      <!-- Recent events -->
      <section class="card events-card">
        <div class="card-head">
          <div>
            <h2>Recent events</h2>
            <p class="card-sub">Manage event statuses, registration quotas, and participant lists</p>
          </div>
          <div class="card-tools">
            <input
              v-model="search"
              class="search"
              type="search"
              placeholder="Search event..."
              @input="onSearch"
            />
            <select v-model="statusFilter" class="filter" @change="onFilter">
              <option value="all">All Status</option>
              <option value="open">Open</option>
              <option value="closed">Closed</option>
              <option value="draft">Draft</option>
            </select>
          </div>
        </div>

        <p v-if="statsStore.error" class="error">Could not load events. Please try again.</p>
        <p v-else-if="statsStore.loading && !pagedEvents.length" class="muted">Loading events…</p>

        <div v-else-if="pagedEvents.length" class="table-scroll">
          <table>
            <thead>
              <tr><th>Code</th><th>Title</th><th>Status</th><th>Registrations</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <tr v-for="e in pagedEvents" :key="e.id">
                <td><span class="code-chip">{{ e.event_code }}</span></td>
                <td>
                  <div class="ev-title">{{ e.title }}</div>
                  <div class="ev-sub">{{ subtitle(e) }}</div>
                </td>
                <td>
                  <span class="badge" :class="statusClass(e.status)">
                    <i class="dot"></i>{{ e.status }}
                  </span>
                </td>
                <td><span class="reg-chip">{{ e.registrations_count }}</span></td>
                <td>
                  <div class="row-actions">
                    <RouterLink
                      class="icon-btn"
                      :to="{ name: 'event-detail', params: { id: e.id } }"
                      title="View event"
                    >👁</RouterLink>
                    <RouterLink
                      class="icon-btn"
                      :to="{ name: 'event-detail', params: { id: e.id } }"
                      title="Manage event"
                    >✏️</RouterLink>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else class="muted">No events match. Create your first event.</p>

        <div class="table-foot">
          <span class="muted">Showing {{ pagedEvents.length }} of {{ filteredEvents.length }} events</span>
          <div class="pager" v-if="totalPages > 1">
            <button class="page-btn" :disabled="page <= 1" @click="page--">Prev</button>
            <button
              v-for="p in totalPages"
              :key="p"
              class="page-btn"
              :class="{ 'page-btn-active': p === page }"
              @click="page = p"
            >{{ p }}</button>
            <button class="page-btn" :disabled="page >= totalPages" @click="page++">Next</button>
          </div>
        </div>
      </section>

      <!-- Right rail -->
      <aside class="rail">
        <div class="qr-desk">
          <div class="qr-badge">⛶</div>
          <h3>Fast Check-in Desk</h3>
          <p>Direct camera scanning mode for candidate tickets &amp; QR badges. Supports offline validation cache.</p>
          <button class="btn btn-scan" @click="goScan">📷 Launch QR Scanner</button>
        </div>

        <div class="card activity-card">
          <div class="activity-head">
            <h3>Recent Candidate Activity</h3>
            <span class="live">● Live sync</span>
          </div>
          <ul v-if="activity.length" class="feed">
            <li v-for="(a, i) in activity" :key="i">
              <span class="feed-ico" :class="'feed-' + a.type">{{ i + 1 }}</span>
              <div>
                <div class="feed-title">{{ a.title }}</div>
                <div class="feed-meta">{{ timeAgo(a.at) }} • {{ a.detail }}</div>
              </div>
            </li>
          </ul>
          <p v-else class="muted">No activity yet.</p>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.dash-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 12px; flex-wrap: wrap; }
h1 { margin: 0; font-size: 24px; }
.dash-sub { margin: 6px 0 0; color: #64748b; font-size: 13px; }
.dash-actions { display: flex; gap: 10px; }

/* ---------- Stat cards ---------- */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin: 20px 0 22px; }
.stat-card {
  position: relative;
  background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
  padding: 16px 16px 18px; overflow: hidden;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.stat-card::after { content: ''; position: absolute; left: 0; right: 0; bottom: 0; height: 4px; }
.stat-teal::after { background: linear-gradient(90deg, #10b981, #0d9488); }
.stat-green::after { background: linear-gradient(90deg, #34d399, #10b981); }
.stat-amber::after { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.stat-violet::after { background: linear-gradient(90deg, #818cf8, #6366f1); }
.stat-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.stat-name { font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; }
.stat-ico {
  width: 34px; height: 34px; display: grid; place-items: center;
  border-radius: 10px; background: #f0fdfa; font-size: 15px;
}
.stat-row { display: flex; justify-content: space-between; align-items: flex-end; gap: 8px; }
.stat-value { font-size: 30px; font-weight: 800; color: #0f172a; line-height: 1; }
.stat-label { color: #64748b; font-size: 12px; margin-top: 6px; }
.pill { border-radius: 999px; font-size: 11px; font-weight: 700; padding: 4px 10px; white-space: nowrap; }
.pill-teal { background: #d1fae5; color: #047857; }
.pill-green { background: #d1fae5; color: #047857; }
.pill-amber { background: #fef3c7; color: #b45309; }
.pill-violet { background: #e0e7ff; color: #4338ca; }

/* ---------- Layout ---------- */
.dash-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 20px; align-items: start; }
.rail { display: flex; flex-direction: column; gap: 20px; }

/* ---------- Cards ---------- */
.card {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 14px;
  padding: 20px; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; }
h2 { margin: 0; font-size: 17px; }
.card-sub { margin: 4px 0 0; color: #64748b; font-size: 12px; max-width: 260px; }
.card-tools { display: flex; gap: 8px; }
.search, .filter {
  padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 10px;
  font-size: 13px; color: #0f172a; background: #fff;
}
.search { width: 180px; }

/* ---------- Table ---------- */
.table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 560px; }
th {
  text-align: left; color: #64748b; font-weight: 700; font-size: 11px;
  letter-spacing: 0.06em; text-transform: uppercase;
  padding: 10px 10px; border-bottom: 1px solid #e2e8f0; background: #f8fafc;
}
td { padding: 12px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
tr:last-child td { border-bottom: 0; }
.code-chip {
  background: #f1f5f9; border-radius: 8px; padding: 5px 8px;
  font-weight: 700; font-size: 12px; color: #334155; white-space: nowrap;
}
.ev-title { font-weight: 600; color: #0f172a; }
.ev-sub { color: #94a3b8; font-size: 12px; margin-top: 2px; }
.badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600;
}
.badge .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.is-open { background: #d1fae5; color: #047857; }
.is-closed { background: #e2e8f0; color: #475569; }
.is-draft { background: #fef3c7; color: #b45309; }
.reg-chip {
  display: inline-grid; place-items: center; min-width: 30px; height: 30px;
  padding: 0 8px; border-radius: 999px; background: #f1f5f9; font-weight: 700; color: #334155;
}
.row-actions { display: flex; gap: 6px; }
.icon-btn {
  width: 30px; height: 30px; display: grid; place-items: center;
  border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0;
  font-size: 13px; text-decoration: none;
}
.icon-btn:hover { background: #f0fdfa; border-color: #99f6e4; }

/* ---------- Table footer ---------- */
.table-foot { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
.pager { display: flex; gap: 6px; }
.page-btn {
  min-width: 30px; height: 30px; padding: 0 10px;
  border: 1px solid #e2e8f0; background: #fff; border-radius: 8px;
  font-size: 12px; font-weight: 600; color: #475569; cursor: pointer;
}
.page-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.page-btn-active { background: #0f766e; border-color: #0f766e; color: #fff; }

/* ---------- QR desk ---------- */
.qr-desk {
  background: linear-gradient(160deg, #064e3b, #022c22);
  color: #fff; border-radius: 16px; padding: 22px;
  box-shadow: 0 10px 26px rgba(4, 47, 46, 0.35);
}
.qr-badge {
  width: 44px; height: 44px; display: grid; place-items: center;
  background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 12px; font-size: 19px; margin-bottom: 14px;
}
.qr-desk h3 { margin: 0 0 8px; font-size: 16px; }
.qr-desk p { margin: 0 0 16px; color: #a7f3d0; font-size: 12.5px; line-height: 1.6; }
.btn-scan {
  width: 100%; background: #10b981; color: #fff; border: 0;
  border-radius: 10px; padding: 11px; font-weight: 700; font-size: 13px; cursor: pointer;
}
.btn-scan:hover { background: #059669; }

/* ---------- Activity feed ---------- */
.activity-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 12px; }
.activity-head h3 { margin: 0; font-size: 14px; }
.live { color: #059669; font-size: 10px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; }
.feed { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 14px; }
.feed li { display: flex; gap: 10px; align-items: flex-start; }
.feed-ico {
  flex: 0 0 auto; width: 26px; height: 26px; display: grid; place-items: center;
  border-radius: 50%; background: #d1fae5; color: #047857; font-size: 12px; font-weight: 700;
}
.feed-status { background: #e2e8f0; color: #475569; }
.feed-title { font-size: 13px; font-weight: 600; color: #0f172a; }
.feed-meta { font-size: 11.5px; color: #94a3b8; margin-top: 2px; }

.muted { color: #94a3b8; font-size: 13px; }
.error { color: #b91c1c; font-size: 13px; }

/* ---------- Responsive ---------- */
@media (max-width: 1100px) {
  .stat-grid { grid-template-columns: repeat(2, 1fr); }
  .dash-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  h1 { font-size: 20px; }
  .dash-actions { width: 100%; }
  .dash-actions .btn { flex: 1; }
  .stat-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 14px 0 16px; }
  .stat-value { font-size: 24px; }
  .pill { font-size: 10px; padding: 3px 8px; }
  .card { padding: 14px 16px; }
  .card-tools { width: 100%; }
  .search { flex: 1; width: auto; }
}
@media (max-width: 360px) {
  .stat-grid { grid-template-columns: 1fr; }
  .stat-value { font-size: 21px; }
}
</style>
