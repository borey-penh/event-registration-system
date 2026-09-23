<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { api } from '../lib/api'
import CandidateDetailModal from '../components/CandidateDetailModal.vue'

const candidates = ref([])
const selectedCandidateId = ref(null)
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = 20
const loading = ref(false)
const error = ref('')

// Debounced search so typing doesn't fire a request per keystroke.
let debounceTimer = null
onUnmounted(() => clearTimeout(debounceTimer))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/candidates', {
      params: {
        search: search.value || undefined,
        page: page.value,
        per_page: perPage,
      },
    })
    candidates.value = data.data
    page.value = data.current_page
    lastPage.value = data.last_page
    total.value = data.total
  } catch {
    error.value = 'Could not load candidates. Please try again.'
  } finally {
    loading.value = false
  }
}

function onSearch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
}

function goto(p) {
  if (p < 1 || p > lastPage.value || loading.value) return
  page.value = p
  load()
}

onMounted(load)
</script>

<template>
  <div>
    <h1>Attendance</h1>
    <div class="card">
      <div class="toolbar">
        <input
          v-model="search"
          placeholder="Search name, code, phone, email, institution, role or event…"
          @input="onSearch"
        />
        <span class="muted total">{{ total.toLocaleString() }} candidates</span>
      </div>

      <table v-if="candidates.length">
        <thead>
          <tr><th>Code</th><th>Name</th><th>Email</th><th>Phone</th><th>Institution</th><th>Role</th><th>Events</th></tr>
        </thead>
        <tbody>
          <tr
            v-for="c in candidates"
            :key="c.id"
            class="candidate-row"
            tabindex="0"
            @click="selectedCandidateId = c.id"
            @keydown.enter="selectedCandidateId = c.id"
          >
            <td data-label="Code">{{ c.candidate_code }}</td>
            <td data-label="Name">{{ c.name }}</td>
            <td data-label="Email">{{ c.email || '—' }}</td>
            <td data-label="Phone">{{ c.phone || '—' }}</td>
            <td data-label="Institution">{{ c.institution || '—' }}</td>
            <td data-label="Role">{{ c.role || '—' }}</td>
            <td data-label="Events" class="pill-cell">
              <span v-for="r in c.all_registrations" :key="r.id" class="pill">
                {{ r.event?.title || 'Event #' + r.event_id }} · {{ r.attendance_status }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else class="muted">No candidates found.</p>

      <div v-if="lastPage > 1" class="pager">
        <button class="btn btn-ghost" :disabled="page <= 1" @click="goto(page - 1)">← Prev</button>
        <span class="muted">Page {{ page }} / {{ lastPage }}</span>
        <button class="btn btn-ghost" :disabled="page >= lastPage" @click="goto(page + 1)">Next →</button>
      </div>
    </div>

    <CandidateDetailModal
      :candidate-id="selectedCandidateId"
      @close="selectedCandidateId = null"
    />
  </div>
</template>

<style scoped>
h1 { margin-top: 0; font-size: 22px; }
.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.toolbar { margin-bottom: 12px; display: flex; align-items: center; gap: 14px; }
.toolbar input { width: 480px; max-width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
.total { font-size: 13px; }

table { width: 100%; border-collapse: collapse; font-size: 14px; }
th { text-align: left; color: #64748b; font-weight: 600; padding: 8px; border-bottom: 1px solid #e2e8f0; }
td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.candidate-row { cursor: pointer; }
.candidate-row:hover td { background: #f8fafc; }
.candidate-row:focus-visible { outline: 2px solid #14b8a6; outline-offset: -2px; }
/* Codes like C-0017 must stay on one line */
.candidate-row td:first-child { white-space: nowrap; }
.muted { color: #94a3b8; }
.pill { display: inline-block; background: #f0fdfa; color: #0f766e; border-radius: 999px; padding: 2px 10px; font-size: 12px; margin: 2px 4px 2px 0; }
.pager { display: flex; align-items: center; gap: 14px; margin-top: 14px; }
.pager button:disabled { opacity: 0.5; cursor: not-allowed; }

/* ---------- Mobile ---------- */
@media (max-width: 640px) {
  .card { padding: 14px 16px; }
  .toolbar { flex-direction: column; align-items: stretch; gap: 8px; }
  .toolbar .total { align-self: flex-end; }
  .toolbar input { width: 100%; }
  .pager { justify-content: space-between; gap: 8px; }
  .pager .btn { padding: 10px 14px; }
  /* Cards instead of an 8-column table on phones */
  table { display: block; }
  thead { display: none; }
  tbody { display: block; }
  tr {
    display: block;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 10px 12px;
    margin-bottom: 10px;
  }
  td { display: flex; gap: 8px; border-bottom: 0; padding: 3px 0; }
  td::before {
    content: attr(data-label);
    flex: 0 0 92px;
    font-weight: 600;
    color: #64748b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding-top: 2px;
  }
  td.pill-cell { display: block; }
  td.pill-cell::before { margin-bottom: 4px; }
}
</style>
