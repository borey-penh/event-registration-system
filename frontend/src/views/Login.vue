<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const email = ref('manager@example.com')
const password = ref('password')
const error = ref('')
const showPassword = ref(false)

async function submit() {
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    router.push({ name: 'dashboard' })
  } catch (e) {
    const fields = e.response?.data?.errors
    if (fields) {
      error.value = Object.values(fields).flat().join(' ')
    } else if (e.response?.data?.message) {
      error.value = e.response.data.message
    } else {
      // No HTTP response at all → the app can't reach the API (e.g. a stale
      // tunnel/URL). Don't show this as "bad credentials".
      error.value = 'Cannot reach the server. Please try again.'
    }
  }
}
</script>

<template>
  <div class="login-page">
    <form class="login-card" @submit.prevent="submit">
      <h1>📋 Event Registration</h1>
      <p class="login-sub">Manager sign in</p>

      <label>Email</label>
      <input v-model="email" type="email" required placeholder="manager@example.com" />

      <label>Password</label>
      <div class="password-wrap">
        <input
          v-model="password"
          :type="showPassword ? 'text' : 'password'"
          required
          placeholder="••••••••"
        />
        <button
          type="button"
          class="password-toggle"
          :aria-label="showPassword ? 'Hide password' : 'Show password'"
          @click="showPassword = !showPassword"
        >
          <svg v-if="showPassword" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
            <line x1="1" y1="1" x2="23" y2="23" />
          </svg>
          <svg v-else viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
            <circle cx="12" cy="12" r="3" />
          </svg>
        </button>
      </div>

      <p v-if="error" class="login-error">{{ error }}</p>

      <button class="btn btn-primary" type="submit" :disabled="auth.loading">
        Sign in
      </button>
    </form>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh; display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #0f766e, #134e4a);
}
.login-card {
  background: #fff; padding: 32px; border-radius: 16px; width: 360px; max-width: calc(100vw - 32px);
  display: flex; flex-direction: column; gap: 8px; box-shadow: 0 20px 50px rgba(0,0,0,0.25);
}
.login-card h1 { margin: 0; font-size: 20px; color: #0f766e; }
.login-sub { margin: 0 0 12px; color: #64748b; font-size: 14px; }
label { font-size: 13px; color: #334155; font-weight: 600; }
input {
  padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;
  margin-bottom: 8px;
}
input:focus { outline: 2px solid #14b8a6; border-color: transparent; }
.password-wrap { position: relative; margin-bottom: 8px; }
.password-wrap input { margin-bottom: 0; width: 100%; box-sizing: border-box; padding-right: 40px; }
.password-toggle {
  position: absolute; right: 6px; top: 50%; transform: translateY(-50%);
  display: flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 999px;
  background: none; border: none; padding: 0; cursor: pointer; color: #94a3b8;
  transition: color 0.15s ease, background-color 0.15s ease;
}
.password-toggle:hover { color: #0f766e; background: #f1f5f9; }
.password-toggle:active { background: #e2e8f0; }
.login-error { color: #dc2626; font-size: 13px; margin: 0 0 8px; }

/* ---------- Mobile ---------- */
@media (max-width: 480px) {
  .login-page { padding: 16px; align-items: flex-start; }
  .login-card { padding: 24px 20px; margin-top: 10vh; }
  .login-card h1 { font-size: 18px; }
  .login-card input { padding: 12px 12px; } /* 16px font stops iOS zoom */
  .login-card .btn { padding: 12px; font-size: 15px; }
}
</style>
