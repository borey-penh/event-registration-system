<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { api } from '../lib/api'

const events = ref([])
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(false)
const error = ref('')
let searchTimer = null

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/events', { params: { page: page.value, per_page: 20, search: search.value || undefined } })
    events.value = data.data
    page.value = data.current_page
    lastPage.value = data.last_page
    total.value = data.total
  } catch {
    error.value = 'Could not load events. Please try again.'
  } finally {
    loading.value = false
  }
}

function onSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; load() }, 300)
}

function goto(nextPage) {
  if (nextPage < 1 || nextPage > lastPage.value || loading.value) return
  page.value = nextPage
  load()
}

onMounted(load)
onUnmounted(() => clearTimeout(searchTimer))
</script>

<template>
  <div>
    <div class="page-head">
      <h1>Events</h1>
      <RouterLink class="btn btn-primary" :to="{ name: 'event-create' }">+ Create Event</RouterLink>
    </div>
    <div class="card">
      <div class="toolbar">
        <input v-model="search" placeholder="Search event name or code…" @input="onSearch" />
        <span class="muted">{{ total.toLocaleString() }} events</span>
      </div>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-else-if="loading && !events.length" class="muted">Loading events…</p>
      <div v-else-if="events.length" class="table-wrap">
        <table>
          <thead><tr><th>Code</th><th>Title</th><th>Date</th><th>Status</th><th>Regs</th><th></th></tr></thead>
          <tbody>
            <tr v-for="e in events" :key="e.id">
              <td>{{ e.event_code }}</td>
              <td>{{ e.title }}</td>
              <td>{{ e.start_date }}<template v-if="e.start_time"> · {{ e.start_time.slice(0, 5) }}</template></td>
              <td><span class="badge" :class="'badge-' + e.status">{{ e.status }}</span></td>
              <td>{{ e.registrations_count }}</td>
              <td><RouterLink :to="{ name: 'event-detail', params: { id: e.id } }" class="link">Manage →</RouterLink></td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="muted">No events found.</p>
      <div v-if="lastPage > 1" class="pager">
        <button class="btn btn-ghost" :disabled="page <= 1 || loading" @click="goto(page - 1)">← Previous</button>
        <span class="muted">Page {{ page }} of {{ lastPage }}</span>
        <button class="btn btn-ghost" :disabled="page >= lastPage || loading" @click="goto(page + 1)">Next →</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
h1 { margin: 0; font-size: 22px; }
.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.toolbar { display: flex; align-items: center; gap: 14px; margin-bottom: 12px; }
.toolbar input { width: 360px; max-width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th { text-align: left; color: #64748b; font-weight: 600; padding: 8px; border-bottom: 1px solid #e2e8f0; }
td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; }
.muted { color: #94a3b8; }.error { color: #b91c1c; }
.link { color: #0f766e; font-weight: 600; text-decoration: none; }
.badge { padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.badge-open { background: #d1fae5; color: #065f46; }.badge-draft { background: #fef3c7; color: #92400e; }.badge-closed { background: #fee2e2; color: #991b1b; }
.pager { display: flex; align-items: center; gap: 14px; margin-top: 14px; }.pager button:disabled { opacity: .5; cursor: not-allowed; }

/* ---------- Mobile ---------- */
@media (max-width: 640px) {
  .card { padding: 14px 16px; }
  .toolbar { flex-direction: column; align-items: stretch; gap: 8px; }
  .toolbar .muted { align-self: flex-end; font-size: 13px; }
  .toolbar input { width: 100%; }
  .pager { justify-content: space-between; gap: 8px; }
  .pager .btn { padding: 10px 14px; }
}
</style>
