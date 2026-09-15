<script setup>
import { ref } from 'vue'
import { api } from '../lib/api'
import QRScanner from '../components/QRScanner.vue'

const result = ref(null)
const error = ref('')
const manualToken = ref('')
const scanning = ref(true)

async function handleDetected(content) {
  if (!scanning.value) return
  scanning.value = false
  error.value = ''
  result.value = null
  try {
    const { data } = await api.post('/check-in', { qr_token: content.trim() })
    result.value = data
  } catch (e) {
    if (e.response?.status === 404) {
      result.value = { status: 'not_found', message: e.response.data.message }
    } else {
      error.value = e.response?.data?.message || 'Check-in failed'
    }
  }
}

function reset() {
  result.value = null
  error.value = ''
  manualToken.value = ''
  scanning.value = true
}

async function submitManual() {
  if (!manualToken.value.trim()) return
  await handleDetected(manualToken.value)
}
</script>

<template>
  <div>
    <h1>Scan QR — Check-in</h1>

    <div class="scan-grid">
      <div class="card">
        <QRScanner v-if="scanning" @detected="handleDetected">
          <div class="scan-hint">Point the camera at the candidate's QR code</div>
        </QRScanner>
        <div v-else class="scan-paused">
          <p>Scan paused. Resume to scan the next candidate.</p>
          <button class="btn btn-primary" @click="reset">Scan next</button>
        </div>

        <div class="manual">
          <input v-model="manualToken" placeholder="Or enter QR token manually…" @keyup.enter="submitManual" />
          <button class="btn btn-ghost" @click="submitManual">Check in</button>
        </div>
      </div>

      <div class="card result-card">
        <p v-if="error" class="result error">{{ error }}</p>
        <template v-else-if="result">
          <div v-if="result.status === 'joined'" class="result success">
            <div class="result-icon">✅</div>
            <h2>Welcome!</h2>
            <p>{{ result.message }}</p>
            <p class="result-name">{{ result.candidate?.name }} ({{ result.candidate?.candidate_code }})</p>
            <p class="muted">{{ result.event_title }} · {{ result.joined_at }}</p>
          </div>
          <div v-else-if="result.status === 'already_joined'" class="result warning">
            <div class="result-icon">⚠️</div>
            <h2>Already checked in</h2>
            <p>{{ result.message }}</p>
            <p class="result-name">{{ result.candidate?.name }} ({{ result.candidate?.candidate_code }})</p>
            <p class="muted">Joined at: {{ result.joined_at }}</p>
          </div>
          <div v-else class="result error">
            <div class="result-icon">❌</div>
            <h2>Not found</h2>
            <p>{{ result.message }}</p>
          </div>
        </template>
        <p v-else class="muted">Waiting for scan…</p>

        <button v-if="result" class="btn btn-primary" @click="reset">Scan next</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
h1 { margin-top: 0; font-size: 22px; }
.scan-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 900px) { .scan-grid { grid-template-columns: 1fr; } }
.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.scan-hint { color: #fff; text-align: center; padding: 10px; font-size: 13px; background: rgba(0,0,0,0.6); }
.scan-paused { text-align: center; padding: 40px 0; color: #475569; }
.manual { display: flex; gap: 8px; margin-top: 12px; }
.manual input { flex: 1; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
.result-card { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 8px; }
.result-icon { font-size: 48px; }
.result h2 { margin: 0; }
.success { color: #059669; }
.warning { color: #d97706; }
.error { color: #dc2626; }
.result-name { font-size: 18px; font-weight: 700; color: #0f172a; margin: 4px 0; }
.muted { color: #64748b; font-size: 13px; }
</style>
