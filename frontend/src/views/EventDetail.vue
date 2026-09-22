<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import QRCode from 'qrcode'
import ChoiceEditor from '../components/ChoiceEditor.vue'
import CandidateDetailModal from '../components/CandidateDetailModal.vue'
import AddCandidateModal from '../components/AddCandidateModal.vue'
import { api } from '../lib/api'

const route = useRoute()
const event = ref(null)
const candidates = ref([])
const selectedCandidateId = ref(null)
const registrationUrl = ref('')
const error = ref('')
const tab = ref('candidates')

// Server-side pagination for the candidates tab
const candidatePage = ref(1)
const candidateLastPage = ref(1)
const candidateTotal = ref(0)
const candidateSearch = ref('')
let searchTimer = null

// Manager walk-in registration
const showAddCandidate = ref(false)

const questions = ref([])
const savingQuestions = ref(false)
const questionTypes = [
  { value: 'text', label: 'Short text' },
  { value: 'textarea', label: 'Long text' },
  { value: 'radio', label: 'Single choice' },
  { value: 'checkbox', label: 'Multiple choice' },
  { value: 'select', label: 'Dropdown' },
  { value: 'date', label: 'Date' },
]

const fullRegistrationUrl = ref('')
// Absolute HTTPS link baked by the server (public tunnel origin) when one is
// configured; empty otherwise, so the client-side origin logic takes over.
const registrationFullUrl = ref('')

// Public base (scheme + host[:port]) the QR/link should use. Phones cannot
// open "localhost" — that resolves to the phone itself — so when the manager
// is browsing on localhost we swap in a URL other devices can actually reach:
//   1. localStorage.publicOrigin — manual override (always wins)
//   2. /app-info public_origin — cloudflared tunnel URL, works on ANY network
//   3. /app-info public_host — the PC's LAN IP, works on the same Wi-Fi only
async function resolvePublicOrigin() {
  // Manual escape hatch: localStorage.publicOrigin (e.g. a trycloudflare URL)
  // always wins, so a tunnel or domain can be pinned without a rebuild.
  const override = localStorage.getItem('publicOrigin')
  if (override) return override.replace(/\/$/, '')

  try {
    const { data } = await api.get('/app-info')
    // Tunnel URL first: reachable from ANY network (mobile data, other Wi-Fi),
    // even when the panel itself is opened via the LAN IP.
    if (data.public_origin) return data.public_origin
    if (!isLocalOrigin()) {
      // Panel browsed via a LAN IP / domain: that origin already works for
      // devices on the same network, so keep using it.
      return window.location.origin
    }
    // Fallback for localhost browsing: same-Wi-Fi LAN host. In dev mode the
    // SPA is served by Vite (port 5173) and only /api is proxied to Laravel,
    // so replace the API port with the dev-server port — a :8000 link would
    // 404 on the phone (no SPA there), while :5173 serves everything.
    if (data.public_host) {
      const [host, apiPort] = data.public_host.split(':')
      const port = import.meta.env.DEV ? String(import.meta.env.VITE_DEV_SERVER_PORT || 5173) : apiPort
      return `${window.location.protocol}//${host}${port ? `:${port}` : ''}`
    }
  } catch {
    // Endpoint unreachable — fall through.
  }

  // Last resort: keep the current origin so the link at least opens somewhere
  // when the manager is NOT on localhost; localhost managers see a warning.
  return window.location.origin
}

function isLocalOrigin() {
  return /^(http:\/\/(localhost|127\.0\.0\.1|0\.0\.0\.0))/.test(window.location.origin)
}

async function buildUrl() {
  if (!registrationUrl.value) return
  // Server-baked absolute URL wins: it is the HTTPS link that phone camera
  // apps and QR scanners can actually open (plain http://text is often
  // refused, which is the "scan works, link shows nothing" gap).
  fullRegistrationUrl.value = registrationFullUrl.value
    || await resolvePublicOrigin() + registrationUrl.value
  renderQr()
  shareWarning.value = /localhost|127\.0\.0\.1/.test(fullRegistrationUrl.value)
    ? 'This link still points at localhost — phones cannot open it. Run scripts/start-public.bat to get an any-network URL, or serve the app on 0.0.0.0.'
    : ''
}

const joinedCount = computed(() => candidates.value.filter((c) => c.attendance_status === 'joined').length)

onMounted(load)

async function loadCandidates() {
  const { data } = await api.get(`/events/${route.params.id}`, {
    params: {
      page: candidatePage.value,
      per_page: 20,
      search: candidateSearch.value || undefined,
    },
  })
  event.value = data.event
  candidates.value = data.candidates
  registrationUrl.value = data.registration_url
  registrationFullUrl.value = data.registration_full_url || ''
  candidatePage.value = data.pagination.current_page
  candidateLastPage.value = data.pagination.last_page
  candidateTotal.value = data.pagination.total
  questions.value = (data.event.questions || []).map((q) => ({ ...q }))
}

function onCandidateSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    candidatePage.value = 1
    loadCandidates()
  }, 300)
}

function gotoCandidatePage(p) {
  if (p < 1 || p > candidateLastPage.value) return
  candidatePage.value = p
  loadCandidates()
}

async function load() {
  error.value = ''
  try {
    await loadCandidates()
  } catch (e) {
    error.value = 'Failed to load event'
  }
  buildUrl()
}

const shareWarning = ref('')
const qrDataUrl = ref('')
async function renderQr() {
  if (!fullRegistrationUrl.value) return
  qrDataUrl.value = await QRCode.toDataURL(fullRegistrationUrl.value, { width: 220, margin: 1 })
}

function addQuestion() {
  questions.value.push({ question: '', type: 'text', required: false, options: [], order: questions.value.length + 1 })
}

function removeQuestion(index) {
  questions.value.splice(index, 1)
}

async function saveQuestions() {
  savingQuestions.value = true
  try {
    const payload = {
      questions: questions.value.map((q, i) => ({
        id: q.id,
        question: q.question,
        type: q.type,
        required: !!q.required,
        options: ['radio', 'checkbox', 'select'].includes(q.type)
          ? (q.options || []).map((s) => s.trim()).filter(Boolean)
          : null,
        order: i + 1,
      })),
    }
    const { data } = await api.put(`/events/${route.params.id}/questions`, payload)
    questions.value = data.map((q) => ({ ...q }))
  } finally {
    savingQuestions.value = false
  }
}

const copied = ref(false)
async function copyLink() {
  try {
    await navigator.clipboard.writeText(fullRegistrationUrl.value)
  } catch {
    // Clipboard API unavailable (e.g. non-HTTPS) — fallback
    const textarea = document.createElement('textarea')
    textarea.value = fullRegistrationUrl.value
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    textarea.remove()
  }
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

async function changeStatus(status) {
  const { data } = await api.put(`/events/${route.params.id}`, { status })
  event.value.status = data.status
}

// ---------- Candidate list export (Excel) ----------
const exporting = ref(false) // true while the export runs
const exportError = ref('')

// One button, one job: always download the printable Attendance List.
async function downloadAttendance() {
  exportError.value = ''
  exporting.value = true
  try {
    await downloadFile(
      `/events/${route.params.id}/attendance-sheet`,
      `attendance-${event.value?.event_code ?? route.params.id}.xlsx`,
    )
  } catch {
    exportError.value = 'Could not download the file. Please try again.'
  } finally {
    exporting.value = false
  }
}

async function downloadFile(path, fallbackName) {
  // Blob request instead of a plain <a href> so the Authorization header
  // is sent — a direct link would hit the route without a token and 401.
  const res = await api.get(path, { responseType: 'blob' })
  const match = (res.headers?.['content-disposition'] || '').match(/filename=("?)([^";]+)\1/)
  const filename = match ? match[2] : fallbackName

  const url = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div>
    <p v-if="error" class="error">{{ error }}</p>
    <template v-else-if="event">
      <div class="page-head">
        <div>
          <h1>{{ event.title }}</h1>
          <p class="muted">{{ event.event_code }} · {{ event.location }} · {{ event.start_date }}<template v-if="event.start_time"> · {{ event.start_time.slice(0, 5) }}</template></p>
        </div>
        <span class="badge" :class="'badge-' + event.status">{{ event.status }}</span>
      </div>

      <div class="status-actions card">
        <span>Set status:</span>
        <button v-for="s in ['draft', 'open', 'closed']" :key="s" class="btn btn-ghost" :class="{ active: event.status === s }" @click="changeStatus(s)">
          {{ s }}
        </button>
      </div>

      <div class="tabs">
        <button :class="{ active: tab === 'candidates' }" @click="tab = 'candidates'">Candidates ({{ candidates.length }})</button>
        <button :class="{ active: tab === 'form' }" @click="tab = 'form'">Registration Form ({{ questions.length }})</button>
        <button :class="{ active: tab === 'share' }" @click="tab = 'share'">Registration QR</button>
      </div>

      <!-- Candidates tab -->
      <div v-if="tab === 'candidates'" class="card">
        <div class="cand-toolbar">
          <input
            v-model="candidateSearch"
            placeholder="Search name, code, phone, email…"
            @input="onCandidateSearch"
          />
          <span class="muted">{{ candidateTotal.toLocaleString() }} registered</span>
          <!-- Single download button: always exports the printable Attendance List.
               Disabled while the export runs or the list is empty. -->
          <button
            class="btn btn-primary export-btn"
            type="button"
            :disabled="exporting || candidateTotal === 0"
            @click="downloadAttendance"
          >
            <svg class="export-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
              <polyline points="7 10 12 15 17 10" />
              <line x1="12" y1="3" x2="12" y2="15" />
            </svg>
            {{ exporting ? 'Preparing…' : 'Attendance List' }}
            <span v-if="exporting" class="export-spinner" aria-hidden="true"></span>
          </button>
          <button class="btn btn-ghost" type="button" @click="showAddCandidate = !showAddCandidate">
            {{ showAddCandidate ? '✕ Close' : '+ Add candidate' }}
          </button>
        </div>
        <p v-if="exportError" class="export-error">{{ exportError }}</p>

        <AddCandidateModal
          v-if="showAddCandidate"
          :event-id="route.params.id"
          :questions="questions"
          @added="loadCandidates"
          @close="showAddCandidate = false"
        />

        <table v-if="candidates.length" class="regs-table">
          <thead>
            <tr><th>No</th><th>Code</th><th>Name</th><th>Email</th><th>Phone</th><th>Institution</th><th>Role</th><th>Status</th><th>Registered</th><th>Checked in</th></tr>
          </thead>
          <template v-for="(c, index) in candidates" :key="c.registration_id">
            <tr
              class="candidate-row"
              tabindex="0"
              @click="selectedCandidateId = c.candidate_id"
              @keydown.enter="selectedCandidateId = c.candidate_id"
            >
              <td data-label="No">{{ (candidatePage - 1) * 20 + index + 1 }}</td>
              <td data-label="Code">{{ c.candidate_code }}</td>
              <td data-label="Name">{{ c.name }}</td>
              <td data-label="Email">{{ c.email || '—' }}</td>
              <td data-label="Phone">{{ c.phone }}</td>
              <td data-label="Institution">{{ c.institution || '—' }}</td>
              <td data-label="Role">{{ c.role || '—' }}</td>
              <td data-label="Status">
                <span class="badge" :class="c.attendance_status === 'joined' ? 'badge-open' : 'badge-draft'">
                  {{ c.attendance_status }}
                </span>
              </td>
              <td data-label="Registered">{{ c.registered_at }}</td>
              <td data-label="Checked in">{{ c.joined_at || '—' }}</td>
            </tr>
          </template>
        </table>
        <p v-else class="muted">No registrations yet. Share the registration QR/link below.</p>

        <div v-if="candidateLastPage > 1" class="pager">
          <button class="btn btn-ghost" :disabled="candidatePage <= 1" @click="gotoCandidatePage(candidatePage - 1)">← Prev</button>
          <span class="muted">Page {{ candidatePage }} / {{ candidateLastPage }}</span>
          <button class="btn btn-ghost" :disabled="candidatePage >= candidateLastPage" @click="gotoCandidatePage(candidatePage + 1)">Next →</button>
        </div>
      </div>
      <!-- Form builder tab -->
      <div v-if="tab === 'form'" class="card">
        <p class="muted">Build the registration form candidates will fill in.</p>
        <div v-for="(q, i) in questions" :key="q.id ?? 'new' + i" class="question-row">
          <div class="question-main">
            <input v-model="q.question" placeholder="Question text" class="q-input" />
            <select v-model="q.type" class="q-type">
              <option v-for="t in questionTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
            <label class="q-required"><input v-model="q.required" type="checkbox" /> Required</label>
            <button class="btn btn-ghost danger" @click="removeQuestion(i)">✕</button>
          </div>
          <ChoiceEditor
            v-if="['radio', 'checkbox', 'select'].includes(q.type)"
            v-model="q.options"
            :multiple="q.type === 'checkbox'"
            class="q-choices"
          />
        </div>
        <div class="form-actions">
          <button class="btn btn-ghost" @click="addQuestion">+ Add question</button>
          <button class="btn btn-primary" :disabled="savingQuestions" @click="saveQuestions">
            Save form
          </button>
        </div>
      </div>

      <!-- QR / share tab -->
      <div v-if="tab === 'share'" class="card share">
        <img v-if="qrDataUrl" :src="qrDataUrl" alt="Registration QR" class="qr-img" />
        <div class="share-info">
          <p class="muted">Candidates scan this QR or click the link to open the registration form:</p>
          <p v-if="shareWarning" class="share-warning">⚠ {{ shareWarning }}</p>

          <input v-model="fullRegistrationUrl" class="link-input" spellcheck="false" />
          <div class="form-actions">
            <button class="btn" :class="copied ? 'btn-copied' : 'btn-primary'" @click="copyLink">
              {{ copied ? '✓ Copied!' : 'Copy link' }}
            </button>
            <a class="btn btn-ghost" :href="fullRegistrationUrl" target="_blank">Open registration page</a>
          </div>
        </div>
      </div>
    </template>

    <CandidateDetailModal
      :candidate-id="selectedCandidateId"
      @close="selectedCandidateId = null"
    />
  </div>
</template>

<style scoped>
.page-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
h1 { margin: 0; font-size: 22px; }
.muted { color: #64748b; font-size: 14px; }
.error { color: #dc2626; }
.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 16px; }
.status-actions { display: flex; gap: 8px; align-items: center; font-size: 14px; color: #475569; }
.status-actions .active { background: #0f766e; color: #fff; }
.tabs { display: flex; gap: 4px; margin-bottom: 12px; }
.tabs button {
  padding: 8px 16px; border: 1px solid #e2e8f0; background: #fff; border-radius: 8px 8px 0 0;
  font-size: 14px; cursor: pointer; color: #475569;
}
.tabs button.active { background: #0f766e; color: #fff; border-color: #0f766e; font-weight: 600; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th { text-align: left; color: #64748b; font-weight: 600; padding: 8px; border-bottom: 1px solid #e2e8f0; }
td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; }
.candidate-row { cursor: pointer; }
.candidate-row:hover td { background: #f8fafc; }
.candidate-row:focus-visible { outline: 2px solid #14b8a6; outline-offset: -2px; }
.cand-toolbar { display: flex; align-items: center; gap: 14px; margin-bottom: 12px; }
.cand-toolbar input { width: 360px; max-width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }


/* ---------- Attendance List download button ---------- */
.export-btn { margin-left: auto; display: inline-flex; align-items: center; gap: 8px; }
.export-icon { width: 16px; height: 16px; flex: 0 0 auto; }
.export-spinner {
  width: 14px; height: 14px; border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.4);
  border-top-color: #fff;
  animation: export-spin 0.7s linear infinite;
}
@keyframes export-spin { to { transform: rotate(360deg); } }
.export-error { color: #dc2626; font-size: 13px; margin: 0 0 10px; }
.pager { display: flex; align-items: center; gap: 14px; margin-top: 14px; }
.pager button:disabled { opacity: 0.5; cursor: not-allowed; }
.badge { padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.badge-open { background: #d1fae5; color: #065f46; }
.badge-draft { background: #fef3c7; color: #92400e; }
.badge-closed { background: #fee2e2; color: #991b1b; }
.question-row { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; margin-bottom: 10px; }
.question-main { display: flex; gap: 8px; align-items: center; }
.q-input { flex: 1; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
.q-type { padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; }
.q-required { display: flex; gap: 6px; align-items: center; font-size: 13px; white-space: nowrap; }
.q-choices { margin-top: 10px; padding-left: 4px; }
.danger { color: #dc2626; }
.form-actions { display: flex; gap: 10px; margin-top: 12px; }
.share { display: flex; gap: 24px; align-items: center; flex-wrap: wrap; }
.qr-img { border: 1px solid #e2e8f0; border-radius: 8px; }
.link-input {
  width: 100%; background: #f1f5f9; padding: 12px; border-radius: 8px; font-size: 13px;
  border: 1px solid #cbd5e1; color: #0f766e; font-weight: 600; font-family: monospace;
}
.link-input:focus { outline: 2px solid #14b8a6; background: #fff; }
.share-warning {
  color: #92400e; font-size: 13px; line-height: 1.5; margin: 0 0 10px;
  background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 8px 12px;
}
.btn-copied {
  background: #059669; color: #fff;
  animation: pop 0.3s ease;
}
@keyframes pop {
  0% { transform: scale(1); }
  50% { transform: scale(1.06); }
  100% { transform: scale(1); }
}

/* ---------- Mobile ---------- */
@media (max-width: 640px) {
  .card { padding: 14px 16px; }
  .page-head { flex-direction: column; align-items: flex-start; gap: 8px; }
  h1 { font-size: 19px; }

  /* Status buttons wrap comfortably instead of squeezing */
  .status-actions { flex-wrap: wrap; }
  .status-actions .btn { padding: 9px 18px; }

  /* Tabs become even, tappable pills that scroll sideways if needed */
  .tabs { gap: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
  .tabs::-webkit-scrollbar { display: none; }
  .tabs button {
    flex: 1 0 auto;
    padding: 10px 14px;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    white-space: nowrap;
  }
  .tabs button.active { border-color: #0f766e; }

  .cand-toolbar { flex-direction: column; align-items: stretch; gap: 8px; }
  .cand-toolbar .muted { align-self: flex-end; }
  .cand-toolbar input { width: 100%; }

  /* Registrations become expandable cards; the form builder stacks */
  table { display: block; overflow-x: auto; }
  .question-main { flex-wrap: wrap; }
  .q-input { min-width: 0; }
  .form-actions { flex-wrap: wrap; }
  .form-actions .btn { flex: 1 1 auto; }

  .share { flex-direction: column; align-items: stretch; }
  .share-info { width: 100%; }
  .qr-img { align-self: center; }
}

/* Registration rows: card layout with inline labels on phones */
@media (max-width: 640px) {
  .regs-table thead { display: none; }
  .regs-table, .regs-table tbody { display: block; }
  .regs-table tr {
    display: block;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 8px 12px;
    margin-bottom: 10px;
  }
  .regs-table td { display: flex; gap: 8px; border-bottom: 0; padding: 3px 0; }
  .regs-table td:first-child { justify-content: flex-end; padding: 2px 0 6px; }
  .regs-table td[data-label]::before {
    content: attr(data-label);
    flex: 0 0 92px;
    font-weight: 600;
    color: #64748b;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding-top: 2px;
  }
}
</style>
