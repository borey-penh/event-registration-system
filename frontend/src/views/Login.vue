<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const email = ref('manager@example.com')
const password = ref('password')
const error = ref('')

async function submit() {
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = e.response?.data?.message || 'Invalid email or password'
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
      <input v-model="password" type="password" required placeholder="••••••••" />

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
  background: #fff; padding: 32px; border-radius: 16px; width: 360px;
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
.login-error { color: #dc2626; font-size: 13px; margin: 0 0 8px; }
</style>
