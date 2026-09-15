<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const auth = useAuthStore()

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="navbar">
    <span class="navbar-title">Event Registration System</span>
    <div class="navbar-right">
      <span class="navbar-user">{{ auth.user?.name }}</span>
      <button class="btn btn-ghost" @click="handleLogout">Logout</button>
    </div>
  </header>
</template>

<style scoped>
.navbar {
  display: flex; justify-content: space-between; align-items: center;
  padding: 12px 24px; background: #fff; border-bottom: 1px solid #e2e8f0;
}
.navbar-title { font-weight: 700; color: #0f766e; }
.navbar-right { display: flex; gap: 12px; align-items: center; }
.navbar-user { color: #475569; font-size: 14px; }
</style>
