<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../lib/api'

const route = useRoute()
const router = useRouter()

const event = ref(null)
const questions = ref([])
const loadError = ref('')
const submitting = ref(false)
const submitError = ref('')
const errors = ref({})

const form = reactive({ answers: {} })

onMounted(async () => {
  try {
    const { data } = await api.get(`/register/${route.params.token}`)
    event.value = data.event
    questions.value = data.questions
  } catch (e) {
    loadError.value = e.response?.status === 404
      ? 'Registration link is invalid or closed.'
      : 'Failed to load registration form.'
  }
})

function validate() {
  errors.value = {}
  for (const q of questions.value) {
    const val = form.answers[q.id]
    const empty = q.type === 'checkbox' ? !val?.length : val === undefined || val === null || val === ''
    if (q.required && empty) {
      errors.value[q.id] = 'This field is required'
    }
  }
  return Object.keys(errors.value).length === 0
}

async function submit() {
  if (!validate()) return
  submitting.value = true
  submitError.value = ''
  try {
    const { data } = await api.post(`/register/${route.params.token}`, {
      answers: form.answers,
    })
    sessionStorage.setItem('lastRegistration', JSON.stringify(data))
    router.push({ name: 'registration-success' })
  } catch (e) {
    submitError.value = e.response?.data?.message || 'Failed to submit registration'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="reg-page">
    <div v-if="loadError" class="reg-card reg-error">{{ loadError }}</div>
    <p v-else-if="!event" class="muted" style="text-align:center; padding:40px;">Loading…</p>

    <form v-else class="reg-card" @submit.prevent="submit">
      <header class="reg-header">
        <h1>{{ event.title }}</h1>
        <p v-if="event.description">{{ event.description }}</p>
        <p class="reg-meta">
          <span v-if="event.location">📍 {{ event.location }}</span>
          <span v-if="event.start_date || event.end_date">📅 {{ event.start_date }}<template v-if="event.end_date"> → {{ event.end_date }}</template><template v-if="event.start_time"> · {{ event.start_time }}</template></span>
        </p>
      </header>

      <!-- Dynamic questions -->
      <div v-for="(q, i) in questions" :key="q.id" class="q-block">
        <div class="q-head">
          <span class="q-num">{{ i + 1 }}</span>
          <label class="q-label">{{ q.question }} <b v-if="q.required" class="req">*</b></label>
        </div>

        <input v-if="q.type === 'text'" v-model="form.answers[q.id]" class="q-input" />
        <textarea v-else-if="q.type === 'textarea'" v-model="form.answers[q.id]" rows="3" class="q-input"></textarea>
        <input v-else-if="q.type === 'date'" v-model="form.answers[q.id]" type="date" class="q-input" />

        <template v-else-if="q.type === 'radio'">
          <label v-for="opt in q.options" :key="opt" class="q-option">
            <input v-model="form.answers[q.id]" type="radio" :name="'q' + q.id" :value="opt" />
            <span>{{ opt }}</span>
          </label>
        </template>

        <template v-else-if="q.type === 'checkbox'">
          <label v-for="opt in q.options" :key="opt" class="q-option">
            <input v-model="form.answers[q.id]" type="checkbox" :value="opt" />
            <span>{{ opt }}</span>
          </label>
        </template>

        <select v-else-if="q.type === 'select'" v-model="form.answers[q.id]" class="q-input">
          <option value="" disabled>Select…</option>
          <option v-for="opt in q.options" :key="opt" :value="opt">{{ opt }}</option>
        </select>

        <p v-if="errors[q.id]" class="q-error">{{ errors[q.id] }}</p>
      </div>

      <p v-if="submitError" class="q-error">{{ submitError }}</p>
      <button class="submit-btn" type="submit" :disabled="submitting">
        Submit Registration
      </button>
    </form>
  </div>
</template>

<style scoped>
.reg-page {
  position: relative;
  min-height: 100vh;
  padding: 40px 16px 64px;
  background:
    radial-gradient(1100px 600px at 90% -10%, rgba(18, 179, 130, 0.16), transparent 60%),
    radial-gradient(900px 550px at -10% 110%, rgba(13, 148, 136, 0.14), transparent 60%),
    linear-gradient(165deg, #eaf7f0 0%, #ddf0ea 40%, #d3ebe6 100%);
  overflow: hidden;
}

.reg-card,
.reg-error,
.muted { position: relative; z-index: 1; }
.reg-card {
  max-width: 820px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 26px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 40px 48px;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.05),
    0 12px 32px -12px rgba(15, 118, 110, 0.18);
  animation: rise 0.45s ease both;
}
@keyframes rise {
  from { opacity: 0; transform: translateY(14px); }
  to { opacity: 1; transform: translateY(0); }
}
.reg-error { color: #dc2626; text-align: center; padding: 40px; }

/* ---------- Header ---------- */
.reg-header {
  position: relative;
  overflow: hidden;
  border-radius: 16px;
  padding: 24px;
  background: linear-gradient(135deg, #0f766e 0%, #0d9488 55%, #14b8a6 100%);
  color: #fff;
  box-shadow: 0 10px 24px -10px rgba(13, 148, 136, 0.55);
}
.reg-header::after {
  content: '';
  position: absolute;
  right: -40px;
  top: -40px;
  width: 160px;
  height: 160px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.12);
}
.reg-header::before {
  content: '';
  position: absolute;
  right: 30px;
  bottom: -50px;
  width: 110px;
  height: 110px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
}
.reg-header h1 {
  margin: 0 0 8px;
  font-size: 20px;
  letter-spacing: 0.2px;
  color: #fff;
  position: relative;
}
.reg-header p { margin: 0; color: rgba(255, 255, 255, 0.92); font-size: 14px; line-height: 1.7; position: relative; }
.reg-meta { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px !important; }
.reg-meta span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #fff;
  border: 1px solid #fff;
  color: #0f766e;
  box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.18);
  padding: 5px 12px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
}

/* ---------- Question blocks ---------- */
.q-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-bottom: 22px;
  border-bottom: 1px dashed #e2e8f0;
}
.q-block:last-of-type { border-bottom: 0; padding-bottom: 0; }
.q-head { display: flex; gap: 10px; align-items: flex-start; }
.q-num {
  background: linear-gradient(135deg, #0f766e, #14b8a6);
  color: #fff;
  min-width: 24px;
  height: 24px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  box-shadow: 0 3px 8px -2px rgba(13, 148, 136, 0.5);
}
.q-label { font-weight: 600; color: #0f172a; font-size: 15px; padding-top: 2px; }
.req { color: #e11d48; }

/* ---------- Inputs ---------- */
.q-input {
  padding: 12px 14px;
  border: 1.5px solid #dbe3ec;
  border-radius: 12px;
  font-family: inherit;
  font-size: 14px;
  background: #f8fafc;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}
.q-input:hover { border-color: #94a3b8; }
.q-input:focus {
  outline: none;
  background: #fff;
  border-color: #0d9488;
  box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
}
textarea.q-input { resize: vertical; min-height: 90px; }

/* ---------- Radio / checkbox as selectable cards ---------- */
.q-option {
  display: flex;
  gap: 10px;
  align-items: center;
  font-size: 14px;
  color: #334155;
  padding: 11px 14px;
  margin: -2px 0;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  background: #f8fafc;
  cursor: pointer;
  transition: all 0.15s ease;
}
.q-option:hover {
  border-color: #5eead4;
  background: #f0fdfa;
  transform: translateX(3px);
}
.q-option:has(input:checked) {
  border-color: #0d9488;
  background: #ecfdf8;
  box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
  color: #0f766e;
  font-weight: 600;
}
.q-option input { accent-color: #0d9488; width: 17px; height: 17px; cursor: pointer; }

/* ---------- Submit ---------- */
.q-error {
  color: #dc2626;
  font-size: 13px;
  margin: 0;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 6px 10px;
  width: fit-content;
}
.submit-btn {
  position: relative;
  background: linear-gradient(135deg, #0f766e, #0d9488);
  color: #fff;
  border: 0;
  border-radius: 14px;
  padding: 15px;
  font-size: 16px;
  font-weight: 700;
  letter-spacing: 0.3px;
  cursor: pointer;
  box-shadow: 0 10px 20px -8px rgba(13, 148, 136, 0.6);
  transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
}
.submit-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 14px 26px -8px rgba(13, 148, 136, 0.7);
  filter: brightness(1.05);
}
.submit-btn:active:not(:disabled) { transform: translateY(0); box-shadow: 0 6px 14px -6px rgba(13, 148, 136, 0.6); }
.submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.muted { text-align: center; color: #64748b; }

@media (max-width: 900px) {
  .reg-card { padding: 28px 24px; }
}
@media (max-width: 480px) {
  .reg-card { padding: 20px; border-radius: 16px; }
  .reg-header { padding: 18px; }
}
</style>
