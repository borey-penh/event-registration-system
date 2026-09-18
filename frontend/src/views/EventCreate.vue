<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../lib/api'

const router = useRouter()
const form = reactive({
  title: '',
  description: '',
  location: '',
  start_date: '',
  start_time: '',
  end_date: '',
  status: 'draft',
})
const error = ref('')
const saving = ref(false)

async function submit() {
  error.value = ''
  saving.value = true
  try {
    const { data } = await api.post('/events', form)
    router.push({ name: 'event-detail', params: { id: data.id } })
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to create event'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <h1>Create Event</h1>
    <form class="card form" @submit.prevent="submit">
      <label>Title *</label>
      <input v-model="form.title" required placeholder="IT Training 2026" />

      <label>Description</label>
      <textarea v-model="form.description" rows="3" placeholder="Event description"></textarea>

      <label>Location</label>
      <input v-model="form.location" placeholder="Phnom Penh" />

      <div class="form-row">
        <div>
          <label>Start date</label>
          <input v-model="form.start_date" type="date" />
        </div>
        <div>
          <label>Start time</label>
          <input v-model="form.start_time" type="time" />
        </div>
        <div>
          <label>End date</label>
          <input v-model="form.end_date" type="date" />
        </div>
        <div>
          <label>Status</label>
          <select v-model="form.status">
            <option value="draft">draft</option>
            <option value="open">open</option>
            <option value="closed">closed</option>
          </select>
        </div>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
      <button class="btn btn-primary" type="submit" :disabled="saving">
        {{ saving ? 'Creating…' : 'Create Event' }}
      </button>
    </form>
  </div>
</template>

<style scoped>
h1 { margin-top: 0; font-size: 22px; }
.card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); max-width: 640px; }
.form { display: flex; flex-direction: column; gap: 6px; }
label { font-size: 13px; font-weight: 600; color: #334155; margin-top: 8px; }
input, textarea, select {
  padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit;
}
.form-row { display: flex; gap: 12px; }
.form-row > div { flex: 1; display: flex; flex-direction: column; }
.error { color: #dc2626; font-size: 13px; }

/* ---------- Mobile ---------- */
@media (max-width: 640px) {
  .card { padding: 16px; }
  /* Date/time/status inputs wrap to two-per-row instead of four squished */
  .form-row { flex-wrap: wrap; }
  .form-row > div { flex: 1 1 40%; }
  .card .btn { width: 100%; padding: 13px; font-size: 15px; }
}
</style>
