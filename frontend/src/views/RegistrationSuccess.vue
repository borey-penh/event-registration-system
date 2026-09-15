<script setup>
import { onMounted, ref } from 'vue'
import QRCode from 'qrcode'

const registration = ref(null)
const qrDataUrl = ref('')

onMounted(async () => {
  registration.value = JSON.parse(sessionStorage.getItem('lastRegistration') || 'null')
  if (registration.value?.qr_token) {
    qrDataUrl.value = await QRCode.toDataURL(registration.value.qr_token, { width: 260, margin: 2 })
  }
})
</script>

<template>
  <div class="success-page">
    <div v-if="registration" class="success-card">
      <div class="success-icon">🎉</div>
      <h1>Registration Successful!</h1>
      <p class="muted">Please save this QR code — you will need it for check-in at the event.</p>

      <img v-if="qrDataUrl" :src="qrDataUrl" alt="Your QR code" class="qr" />
      <code class="token">{{ registration.qr_token }}</code>

      <div class="details">
        <p><strong>Name:</strong> {{ registration.candidate?.name }}</p>
        <p><strong>Candidate ID:</strong> {{ registration.candidate?.candidate_code }}</p>
      </div>

      <p class="muted small">Take a screenshot or download this page. The QR contains your personal code: <strong>{{ registration.qr_token }}</strong></p>
    </div>
    <div v-else class="success-card">
      <p>No recent registration found.</p>
    </div>
  </div>
</template>

<style scoped>
.success-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f0fdfa; padding: 24px; }
.success-card {
  background: #fff; border-radius: 16px; padding: 40px; max-width: 420px; width: 100%;
  text-align: center; box-shadow: 0 10px 40px rgba(15,118,110,0.15);
  display: flex; flex-direction: column; gap: 12px; align-items: center;
}
.success-icon { font-size: 56px; }
h1 { font-size: 22px; color: #0f766e; margin: 0; }
.qr { border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px; }
.token { background: #f1f5f9; padding: 8px 16px; border-radius: 8px; font-size: 14px; }
.details { text-align: left; width: 100%; font-size: 14px; color: #334155; }
.details p { margin: 4px 0; }
.muted { color: #64748b; font-size: 14px; }
.small { font-size: 12px; }
</style>
