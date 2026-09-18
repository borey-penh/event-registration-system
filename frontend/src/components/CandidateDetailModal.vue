<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { api } from '../lib/api'

const props = defineProps({
  candidateId: { type: Number, default: null },
})
const emit = defineEmits(['close'])

const candidate = ref(null)
const loading = ref(false)
const error = ref('')
const openRegistration = ref(null) // registration id whose answers are expanded

watch(
  () => props.candidateId,
  async (id) => {
    candidate.value = null
    error.value = ''
    openRegistration.value = null
    if (!id) return
    loading.value = true
    try {
      const { data } = await api.get(`/candidates/${id}`)
      candidate.value = data
    } catch {
      error.value = 'Could not load candidate details.'
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

function onKey(e) {
  if (e.key === 'Escape') emit('close')
}
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))

function initials(name) {
  return (name || '?')
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('')
}

const detailRows = [
  ['Email', 'email'],
  ['Phone', 'phone'],
  ['Institution', 'institution'],
  ['Role', 'role'],
]

function eventAnswers(reg) {
  return (reg.answers || []).map((a) => ({
    question: a.question?.question || 'Question #' + a.question_id,
    answer: a.answer,
  }))
}
</script>

<template>
  <div v-if="candidateId" class="modal-overlay" @click.self="emit('close')">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Candidate details">
      <button class="modal-close" type="button" aria-label="Close" @click="emit('close')">✕</button>

      <p v-if="loading" class="muted modal-status">Loading…</p>
      <p v-else-if="error" class="modal-error">{{ error }}</p>

      <template v-else-if="candidate">
        <!-- Header -->
        <div class="cand-head">
          <div class="avatar">{{ initials(candidate.name) }}</div>
          <div>
            <h2>{{ candidate.name }}</h2>
            <span class="code-chip">{{ candidate.candidate_code }}</span>
          </div>
        </div>

        <!-- Profile details -->
        <dl class="detail-grid">
          <div v-for="[label, key] in detailRows" :key="key" class="detail-item">
            <dt>{{ label }}</dt>
            <dd :class="{ empty: !candidate[key] }">{{ candidate[key] || '—' }}</dd>
          </div>
        </dl>

        <!-- Registrations / events -->
        <h3 class="section-title">
          Events <span class="count">{{ candidate.registrations?.length || 0 }}</span>
        </h3>
        <p v-if="!candidate.registrations?.length" class="muted">Not registered for any event.</p>

        <div v-for="reg in candidate.registrations" :key="reg.id" class="event-box">
          <div class="event-head">
            <div class="event-title">
              <strong>{{ reg.event?.title || 'Event #' + reg.event_id }}</strong>
              <span class="badge" :class="reg.attendance_status === 'joined' ? 'badge-open' : 'badge-draft'">
                {{ reg.attendance_status }}
              </span>
            </div>
            <div class="event-meta">
              <span v-if="reg.registered_at">Registered {{ reg.registered_at }}</span>
              <span v-if="reg.joined_at" class="joined">Checked in {{ reg.joined_at }}</span>
            </div>
            <button
              v-if="eventAnswers(reg).length"
              class="answers-toggle"
              type="button"
              @click="openRegistration = openRegistration === reg.id ? null : reg.id"
            >
              <span class="row-toggle-arrow">{{ openRegistration === reg.id ? '▾' : '▸' }}</span>
              {{ openRegistration === reg.id ? 'Hide answers' : 'Show answers' }}
            </button>
          </div>

          <table v-if="openRegistration === reg.id && eventAnswers(reg).length" class="answers-table">
            <tbody>
              <tr v-for="qa in eventAnswers(reg)" :key="qa.question">
                <th>{{ qa.question }}</th>
                <td>{{ qa.answer || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay {
  position: fixed; inset: 0; z-index: 60;
  background: rgba(15, 23, 42, 0.55);
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.modal-card {
  position: relative;
  background: #fff; border-radius: 16px;
  width: 560px; max-width: 100%;
  max-height: 85vh; overflow-y: auto;
  padding: 24px;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
}
.modal-close {
  position: absolute; top: 14px; right: 14px;
  width: 30px; height: 30px; border-radius: 999px;
  border: none; background: #f1f5f9; color: #475569;
  font-size: 13px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}
.modal-close:hover { background: #e2e8f0; color: #0f172a; }
.modal-status { padding: 24px 0; text-align: center; }
.modal-error {
  color: #dc2626; background: #fef2f2; border: 1px solid #fecaca;
  border-radius: 8px; padding: 10px 14px; font-size: 14px;
}

.cand-head { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
.avatar {
  width: 52px; height: 52px; border-radius: 999px; flex-shrink: 0;
  background: linear-gradient(135deg, #14b8a6, #0f766e);
  color: #fff; font-weight: 700; font-size: 18px;
  display: flex; align-items: center; justify-content: center;
}
.cand-head h2 { margin: 0 0 4px; font-size: 18px; }
.code-chip {
  display: inline-block; background: #f0fdfa; color: #0f766e;
  border-radius: 999px; padding: 2px 10px; font-size: 12px; font-weight: 600;
}

.detail-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 10px 18px;
  margin: 0 0 18px; padding: 14px 16px;
  background: #f8fafc; border-radius: 12px;
}
.detail-item dt {
  font-size: 11px; font-weight: 600; color: #64748b;
  text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 2px;
}
.detail-item dd { margin: 0; font-size: 14px; color: #0f172a; word-break: break-word; }
.detail-item dd.empty { color: #cbd5e1; }
@media (max-width: 480px) { .detail-grid { grid-template-columns: 1fr; } }

.section-title { font-size: 14px; color: #334155; margin: 0 0 10px; }
.section-title .count {
  background: #e2e8f0; color: #475569; border-radius: 999px;
  font-size: 11px; padding: 1px 8px; margin-left: 4px;
}
.muted { color: #94a3b8; font-size: 14px; }

.event-box {
  border: 1px solid #e2e8f0; border-radius: 12px;
  padding: 12px 14px; margin-bottom: 10px;
}
.event-head { display: flex; flex-direction: column; gap: 6px; }
.event-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 14px; }
.event-meta { display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; color: #64748b; }
.event-meta .joined { color: #0f766e; font-weight: 600; }
.badge {
  border-radius: 999px; padding: 2px 10px; font-size: 11px;
  font-weight: 600; text-transform: capitalize;
}
.badge-open { background: #dcfce7; color: #15803d; }
.badge-draft { background: #f1f5f9; color: #64748b; }

.answers-toggle {
  align-self: flex-start;
  display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
  border: 1px solid #e2e8f0; background: #f8fafc; border-radius: 999px;
  padding: 4px 10px; cursor: pointer; color: #475569; font-size: 12px;
  font-weight: 500; line-height: 1;
}
.answers-toggle:hover { background: #f0fdfa; border-color: #5eead4; color: #0f766e; }
.row-toggle-arrow { font-size: 10px; }

.answers-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
.answers-table th {
  text-align: left; color: #64748b; font-weight: 600;
  padding: 6px 8px 6px 0; border-bottom: 1px solid #f1f5f9; width: 45%;
  vertical-align: top;
}
.answers-table td { padding: 6px 0; border-bottom: 1px solid #f1f5f9; word-break: break-word; }
</style>
