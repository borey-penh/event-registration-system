<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'

defineProps({ menuOpen: { type: Boolean, default: false } })
const emit = defineEmits(['toggle-menu'])

const auth = useAuthStore()

const initials = computed(() => {
  const name = auth.user?.name || 'Manager'
  return name.split(/\s+/).map((p) => p[0]).join('').slice(0, 2).toUpperCase()
})

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
      <span class="title-accent"></span>
      <div class="title-block">
        <span class="navbar-title">Event Registration System</span>
        <span class="navbar-sub">Production Workspace</span>
      </div>
    </div>

    <div class="navbar-right">
      <button class="icon-btn" type="button" title="Notifications">🔔<i class="ping"></i></button>
      <div class="user-chip">
        <span class="avatar">{{ initials }}</span>
        <span class="user-meta">
          <span class="user-name">{{ auth.user?.name }}</span>
          <span class="user-role">Admin Access</span>
        </span>
      </div>
      <button class="btn btn-ghost btn-logout" @click="handleLogout">⎋ Logout</button>
    </div>
  </header>
</template>

<style scoped>
.navbar {
  display: flex; justify-content: space-between; align-items: center;
  gap: 10px;
  padding: 12px 24px; background: #fff; border-bottom: 1px solid #e2e8f0;
}
.navbar-left { display: flex; align-items: center; gap: 12px; min-width: 0; }
.title-accent { width: 5px; height: 26px; border-radius: 999px; background: #10b981; flex: 0 0 auto; }
.title-block { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
.navbar-title { font-weight: 800; color: #0f172a; font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.navbar-sub { font-size: 11px; color: #94a3b8; }

.navbar-right { display: flex; gap: 12px; align-items: center; }
.icon-btn {
  position: relative; width: 38px; height: 38px; border-radius: 10px;
  border: 1px solid #e2e8f0; background: #fff; cursor: pointer; font-size: 15px;
}
.ping {
  position: absolute; top: 7px; right: 8px; width: 7px; height: 7px;
  border-radius: 50%; background: #10b981; border: 1.5px solid #fff;
}
.user-chip {
  display: flex; align-items: center; gap: 9px;
  background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 999px;
  padding: 4px 14px 4px 4px;
}
.avatar {
  width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center;
  background: #0f766e; color: #fff; font-size: 12px; font-weight: 800;
}
.user-meta { display: flex; flex-direction: column; line-height: 1.15; }
.user-name { font-size: 12.5px; font-weight: 700; color: #0f172a; white-space: nowrap; }
.user-role { font-size: 10.5px; color: #94a3b8; }
.btn-logout { border-radius: 999px; }
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
  .navbar-title { font-size: 14px; }
  .navbar-sub { display: none; }
  .title-accent { height: 22px; }
  .user-chip { display: none; }
  .icon-btn { display: none; }
  .btn-logout { padding: 8px 14px; }
}
</style>
