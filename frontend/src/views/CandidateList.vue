<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { api } from '../lib/api'

const candidates = ref([])
const search = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = 20

// Debounced search so typing doesn't fire a request per keystroke.
let debounceTimer = null
onUnmounted(() => clearTimeout(debounceTimer))

async function load() {
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
}

function onSearch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
}

function goto(p) {
  if (p < 1 || p > lastPage.value) return
  page.value = p
  load()
}

onMounted(load)
</script>

<template>
  <div>
    <h1>Candidates</h1>
    <div class="card">
      <div class="toolbar">
        <input
          v-model="search"
          placeholder="Search name, code, phone, email or institution…"
          @input="onSearch"
        />
        <span class="muted total">{{ total.toLocaleString() }} candidates</span>
      </div>
      <table v-if="candidates.length">
        <thead>
          <tr><th>Code</th><th>Name</th><th>Email</th><th>Phone</th><th>Telegram</th><th>Institution</th><th>Role</th><th>Events</th></tr>
        </thead>
        <tbody>
          <tr v-for="c in candidates" :key="c.id">
            <td>{{ c.candidate_code }}</td>
            <td>{{ c.name }}</td>
            <td>{{ c.email || '—' }}</td>
            <td>{{ c.phone || '—' }}</td>
            <td>{{ c.telegram_username || '—' }}</td>
            <td>{{ c.institution || '—' }}</td>
            <td>{{ c.role || '—' }}</td>
            <td>
              <span v-for="r in c.registrations" :key="r.id" class="pill">
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
  </div>
</template>

<style scoped>
h1 { margin-top: 0; font-size: 22px; }
.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.toolbar { margin-bottom: 12px; display: flex; align-items: center; gap: 14px; }
.toolbar input { width: 360px; max-width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
.total { font-size: 13px; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th { text-align: left; color: #64748b; font-weight: 600; padding: 8px; border-bottom: 1px solid #e2e8f0; }
td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.muted { color: #94a3b8; }
.pill { display: inline-block; background: #f0fdfa; color: #0f766e; border-radius: 999px; padding: 2px 10px; font-size: 12px; margin: 2px 4px 2px 0; }
.pager { display: flex; align-items: center; gap: 14px; margin-top: 14px; }
.pager button:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
