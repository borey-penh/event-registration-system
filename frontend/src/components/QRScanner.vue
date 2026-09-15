<script setup>
import { ref } from 'vue'
import { QrcodeStream } from 'vue-qrcode-reader'

defineProps({ paused: { type: Boolean, default: false } })
const emit = defineEmits(['detected'])
const cameraError = ref(false)

function onDetect(result) {
  if (!result?.content) return
  emit('detected', result.content)
}
</script>

<template>
  <div class="scanner">
    <QrcodeStream v-if="!cameraError" :paused="paused" @detect="onDetect" @error="cameraError = true" />
    <div v-else class="scanner-fallback">
      <p>📷 Camera not available.<br />Type the QR code (e.g. REG-2026-XXXXXXXX) below:</p>
    </div>
    <slot />
  </div>
</template>

<style scoped>
.scanner { border-radius: 12px; overflow: hidden; background: #000; min-height: 240px; }
.scanner-fallback { color: #fff; padding: 40px 20px; text-align: center; }
</style>
