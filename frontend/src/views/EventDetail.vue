<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import QRCode from 'qrcode'
import ChoiceEditor from '../components/ChoiceEditor.vue'
import { api } from '../lib/api'

const route = useRoute()
const event = ref(null)
const candidates = ref([])
const registrationUrl = ref('')
const error = ref('')
const tab = ref('candidates')

// Server-side pagination for the candidates tab
const candidatePage = ref(1)
const candidateLastPage = ref(1)
const candidateTotal = ref(0)
const candidateSearch = ref('')
let searchTimer = null

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

function buildUrl() {
  if (!registrationUrl.value) return
  // Use the hostname the manager is browsing from so the link works for candidates too
  fullRegistrationUrl.value = `${window.location.origin}${registrationUrl.value}`
  renderQr()
}

const joinedCount = computed(() => candidates.value.filter((c) => c.attendance_status === 'joined').length)

onMounted(load)

const expanded = ref({})
function toggleRow(id) {
  expanded.value[id] = !expanded.value[id]
}

function answerRows(c) {
  return Object.entries(c.answers || {}).map(([qid, answer]) => {
    const q = questions.value.find((x) => x.id === Number(qid))
    return { qid, question: q?.question ?? 'Question #' + qid, answer }
  })
}

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
        </div>
        <table v-if="candidates.length">
          <thead>
            <tr><th></th><th>Code</th><th>Name</th><th>Email</th><th>Phone</th><th>Telegram</th><th>Institution</th><th>Role</th><th>Status</th><th>Registered</th><th>Checked in</th></tr>
          </thead>
          <template v-for="c in candidates" :key="c.registration_id">
            <tr>
              <td>
                <button class="row-toggle" type="button" @click="toggleRow(c.registration_id)">
                  {{ expanded[c.registration_id] ? '▾' : '▸' }}
                </button>
              </td>
              <td>{{ c.candidate_code }}</td>
              <td>{{ c.name }}</td>
              <td>{{ c.email || '—' }}</td>
              <td>{{ c.phone }}</td>
              <td>{{ c.telegram_username || '—' }}</td>
              <td>{{ c.institution || '—' }}</td>
              <td>{{ c.role || '—' }}</td>
              <td>
                <span class="badge" :class="c.attendance_status === 'joined' ? 'badge-open' : 'badge-draft'">
                  {{ c.attendance_status }}
                </span>
              </td>
              <td>{{ c.registered_at }}</td>
              <td>{{ c.joined_at || '—' }}</td>
            </tr>
            <tr v-if="expanded[c.registration_id]" class="answers-row">
              <td :colspan="11">
                <table class="answers-table">
                  <tbody>
                    <tr v-for="qa in answerRows(c)" :key="qa.qid">
                      <th>{{ qa.question }}</th>
                      <td>{{ qa.answer || '—' }}</td>
                    </tr>
                  </tbody>
                </table>
              </td>
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
.row-toggle {
  border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 6px;
  width: 26px; height: 26px; cursor: pointer; color: #475569; font-size: 12px;
}
.row-toggle:hover { background: #f0fdfa; border-color: #5eead4; color: #0f766e; }
.cand-toolbar { display: flex; align-items: center; gap: 14px; margin-bottom: 12px; }
.cand-toolbar input { width: 360px; max-width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
.pager { display: flex; align-items: center; gap: 14px; margin-top: 14px; }
.pager button:disabled { opacity: 0.5; cursor: not-allowed; }
.answers-row td { background: #f8fafc; padding: 12px; }
.answers-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.answers-table th {
  text-align: left; color: #334155; font-weight: 600; padding: 6px 10px;
  width: 40%; border-bottom: 1px dashed #e2e8f0; vertical-align: top;
}
.answers-table td { padding: 6px 10px; border-bottom: 1px dashed #e2e8f0; color: #0f172a; }
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
.btn-copied {
  background: #059669; color: #fff;
  animation: pop 0.3s ease;
}
@keyframes pop {
  0% { transform: scale(1); }
  50% { transform: scale(1.06); }
  100% { transform: scale(1); }
}
</style>
