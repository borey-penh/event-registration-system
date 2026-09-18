<script setup>
import { useAuthStore } from '../stores/auth'

defineProps({ menuOpen: { type: Boolean, default: false } })
const emit = defineEmits(['toggle-menu'])

const auth = useAuthStore()

async function handleLogout() {
  await auth.logout()
  window.location.href = '/login'
}
</script>

<template>
  <header class="navbar">
    <div class="navbar-left">
      <button
        class="navbar-burger"
        type="button"
        aria-label="Toggle navigation menu"
        :aria-expanded="menuOpen"
        @click="emit('toggle-menu')"
      >
        ☰
      </button>
      <span class="navbar-title">Event Registration System</span>
    </div>
    <div class="navbar-right">
      <span class="navbar-user">{{ auth.user?.name }}</span>
      <button class="btn btn-ghost btn-logout" @click="handleLogout">Logout</button>
    </div>
  </header>
</template>

<style scoped>
.navbar {
  display: flex; justify-content: space-between; align-items: center;
  gap: 10px;
  padding: 12px 24px; background: #fff; border-bottom: 1px solid #e2e8f0;
}
.navbar-left { display: flex; align-items: center; gap: 10px; min-width: 0; }
.navbar-title { font-weight: 700; color: #0f766e; }
.navbar-right { display: flex; gap: 12px; align-items: center; }
.navbar-user { color: #475569; font-size: 14px; }
.navbar-burger { display: none; }

/* Phones: burger menu, compact bar, hide the (long) title if it crowds */
@media (max-width: 768px) {
  .navbar {
    position: sticky;
    top: 0;
    z-index: 40;
    padding: 10px 14px;
    padding-top: calc(10px + env(safe-area-inset-top, 0px));
  }
  .navbar-burger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    margin-left: -6px;
    border: 0;
    background: transparent;
    color: #0f766e;
    font-size: 22px;
    cursor: pointer;
    border-radius: 10px;
  }
  .navbar-burger:active { background: #f0fdfa; }
  .navbar-title { font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .navbar-user { display: none; }
  .btn-logout { padding: 8px 14px; }
}
</style>
