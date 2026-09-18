<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
import { api } from '../lib/api'

const props = defineProps({
  eventId: { type: [Number, String], required: true },
  questions: { type: Array, default: () => [] },
})
const emit = defineEmits(['close', 'added'])

const form = ref({ name: '', email: '', phone: '', institution: '', role: '' })
const answers = ref({}) // question_id -> answer (string or array for checkbox)
const saving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const addedCandidate = ref(null) // set after a successful submit -> success view
const nameInput = ref(null)

function toggleCheckbox(q, opt, checked) {
  const current = Array.isArray(answers.value[q.id]) ? [...answers.value[q.id]] : []
  answers.value[q.id] = checked ? [...current, opt] : current.filter((v) => v !== opt)
}

function resetForm() {
  form.value = { name: '', email: '', phone: '', institution: '', role: '' }
  answers.value = {}
  errorMessage.value = ''
}

function collectAnswers() {
  const out = {}
  for (const [qid, value] of Object.entries(answers.value)) {
    if (Array.isArray(value)) {
      if (value.length) out[qid] = value
    } else if (value !== null && value !== undefined && String(value).trim() !== '') {
      out[qid] = value
    }
  }
  return out
}

async function submit() {
  saving.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.post(`/events/${props.eventId}/candidates`, {
      ...form.value,
      email: form.value.email || null,
      answers: collectAnswers(),
    })
    addedCandidate.value = data.candidate
    successMessage.value = data.message || 'Candidate registered for this event.'
    emit('added')
  } catch (e) {
    const msg = e.response?.data?.message
    const fields = e.response?.data?.errors
    errorMessage.value = msg || (fields ? Object.values(fields).flat().join(' ') : 'Could not add candidate.')
  } finally {
    saving.value = false
  }
}

function addAnother() {
  addedCandidate.value = null
  successMessage.value = ''
  resetForm()
  nextTick(() => nameInput.value?.focus())
}

function onKey(e) {
  if (e.key === 'Escape') emit('close')
}
onMounted(() => {
  window.addEventListener('keydown', onKey)
  nextTick(() => nameInput.value?.focus())
})
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="modal-overlay" @click.self="emit('close')">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Add candidate">
      <button class="modal-close" type="button" aria-label="Close" @click="emit('close')">✕</button>

      <!-- Success state -->
      <template v-if="addedCandidate">
        <div class="cand-head">
          <div class="avatar avatar-success">✓</div>
          <div>
            <h2>Candidate added</h2>
            <span class="code-chip">{{ addedCandidate.candidate_code }}</span>
          </div>
        </div>
        <p class="muted">{{ successMessage }} {{ addedCandidate.name }} is now registered for this event.</p>
        <div class="form-actions">
          <button class="btn btn-primary" type="button" @click="addAnother">+ Add another</button>
          <button class="btn btn-ghost" type="button" @click="emit('close')">Done</button>
        </div>
      </template>

      <!-- Form state -->
      <template v-else>
        <div class="cand-head">
          <div class="avatar">+</div>
          <div>
            <h2>Walk-in registration</h2>
            <span class="head-sub">Add a candidate directly to this event</span>
          </div>
        </div>

        <form class="add-form" @submit.prevent="submit">
          <div class="field-grid">
            <div class="field field-wide">
              <label>Full name <em class="req">*</em></label>
              <input ref="nameInput" v-model="form.name" placeholder="e.g. Aisyah Putri" required />
            </div>
            <div class="field">
              <label>Email</label>
              <input v-model="form.email" type="email" placeholder="name@example.com" />
            </div>
            <div class="field">
              <label>Phone</label>
              <input v-model="form.phone" placeholder="08xx xxxx xxxx" />
            </div>
            <div class="field">
              <label>Institution</label>
              <input v-model="form.institution" placeholder="School / organization" />
            </div>
            <div class="field">
              <label>Role</label>
              <input v-model="form.role" placeholder="e.g. Participant" />
            </div>
          </div>

          <template v-if="questions.length">
            <h3 class="section-title">
              Form answers <span class="count">{{ questions.length }}</span>
            </h3>
            <div v-for="q in questions" :key="q.id" class="q-item">
              <label class="q-label">
                {{ q.question }}
                <em v-if="q.required" class="req">*</em>
              </label>

              <input
                v-if="q.type === 'text'"
                v-model="answers[q.id]"
                :required="q.required"
              />
              <textarea
                v-else-if="q.type === 'textarea'"
                v-model="answers[q.id]"
                rows="2"
                :required="q.required"
              />
              <input
                v-else-if="q.type === 'date'"
                v-model="answers[q.id]"
                type="date"
                :required="q.required"
              />
              <select
                v-else-if="q.type === 'select'"
                v-model="answers[q.id]"
                :required="q.required"
              >
                <option value="">— choose —</option>
                <option v-for="opt in q.options || []" :key="opt" :value="opt">{{ opt }}</option>
              </select>
              <div v-else-if="q.type === 'radio'" class="opts">
                <label v-for="opt in q.options || []" :key="opt" class="opt">
                  <input v-model="answers[q.id]" type="radio" :name="'q' + q.id" :value="opt" :required="q.required" />
                  {{ opt }}
                </label>
              </div>
              <div v-else-if="q.type === 'checkbox'" class="opts">
                <label v-for="opt in q.options || []" :key="opt" class="opt">
                  <input
                    v-model="answers[q.id]"
                    type="checkbox"
                    :value="opt"
                    :checked="(answers[q.id] || []).includes(opt)"
                    @change="toggleCheckbox(q, opt, $event.target.checked)"
                  />
                  {{ opt }}
                </label>
              </div>
            </div>
          </template>

          <p v-if="errorMessage" class="modal-error">{{ errorMessage }}</p>

          <div class="form-actions">
            <button class="btn btn-primary" type="submit" :disabled="saving">
              {{ saving ? 'Saving…' : 'Add to event' }}
            </button>
            <button class="btn btn-ghost" type="button" @click="emit('close')">Cancel</button>
          </div>
        </form>
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
  width: 640px; max-width: 100%;
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
.modal-error {
  color: #dc2626; background: #fef2f2; border: 1px solid #fecaca;
  border-radius: 8px; padding: 10px 14px; font-size: 14px; margin: 14px 0 0;
}

.cand-head { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
.avatar {
  width: 52px; height: 52px; border-radius: 999px; flex-shrink: 0;
  background: linear-gradient(135deg, #14b8a6, #0f766e);
  color: #fff; font-weight: 700; font-size: 22px;
  display: flex; align-items: center; justify-content: center;
}
.avatar-success { background: linear-gradient(135deg, #22c55e, #15803d); font-size: 20px; }
.cand-head h2 { margin: 0 0 4px; font-size: 18px; }
.head-sub { font-size: 13px; color: #64748b; }
.code-chip {
  display: inline-block; background: #f0fdfa; color: #0f766e;
  border-radius: 999px; padding: 2px 10px; font-size: 12px; font-weight: 600;
}
.muted { color: #64748b; font-size: 14px; margin: 0 0 6px; }

.add-form { display: flex; flex-direction: column; }
.field-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px 14px;
  padding: 14px 16px;
  background: #f8fafc; border-radius: 12px;
}
.field { display: flex; flex-direction: column; gap: 4px; }
.field-wide { grid-column: 1 / -1; }
.field label {
  font-size: 11px; font-weight: 600; color: #64748b;
  text-transform: uppercase; letter-spacing: 0.4px;
}
.field input { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
.field input:focus { outline: 2px solid #14b8a6; outline-offset: -1px; }
.req { color: #dc2626; font-style: normal; }

.section-title { font-size: 14px; color: #334155; margin: 18px 0 10px; }
.section-title .count {
  background: #e2e8f0; color: #475569; border-radius: 999px;
  font-size: 11px; padding: 1px 8px; margin-left: 4px;
}
.q-item { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.q-label { font-size: 13px; font-weight: 600; color: #334155; }
.q-item input,
.q-item textarea,
.q-item select {
  padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;
  font-family: inherit; width: 100%; box-sizing: border-box;
}
.opts { display: flex; flex-wrap: wrap; gap: 6px 16px; padding: 2px 0; }
.opt {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 14px; color: #0f172a; cursor: pointer;
}

.form-actions { display: flex; gap: 10px; margin-top: 16px; }

@media (max-width: 560px) {
  .modal-card { padding: 18px 16px; }
  .field-grid { grid-template-columns: 1fr; }
  .form-actions { flex-wrap: wrap; }
  .form-actions .btn { flex: 1 1 auto; }
}
</style>
