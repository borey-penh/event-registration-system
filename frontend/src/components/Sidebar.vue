<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import heroImg from '../assets/hero.png'
import { useStatsStore } from '../stores/stats'

const emit = defineEmits(['close'])
const props = defineProps({ open: { type: Boolean, default: false } })

const statsStore = useStatsStore()
const route = useRoute()
const isActive = (link) => link.match.includes(route.name)

const links = computed(() => [
  { name: 'dashboard', label: 'Dashboard', icon: '🏠', match: ['dashboard'], badge: '' },
  { name: 'events', label: 'Events', icon: '📅', match: ['events', 'event-create', 'event-detail'], badge: badge('events') },
  { name: 'candidates', label: 'Attendance', icon: '👥', match: ['candidates'], badge: badge('candidates') },
  { name: 'scan', label: 'Check-in', icon: '📷', match: ['scan'], tag: 'FAST' },
])

// Count badges only render once the shared stats fetch has landed; on the
// dashboard route that same request powers the stat cards (no extra call).
function badge(key) {
  const data = statsStore.data
  if (!data) return ''
  return String(data[key] ?? '')
}

// Close the drawer whenever the route changes (phone navigation)
watch(() => route.fullPath, () => emit('close'))

// Live badge updates while the drawer sits open (desktop) — cheap interval.
let timer = null
onMounted(() => { timer = setInterval(() => statsStore.load(), 60_000) })
onBeforeUnmount(() => { clearInterval(timer); })
</script>

<template>
  <aside class="sidebar" :class="{ 'sidebar-open': open }">
    <div class="sidebar-logo">
      <span class="logo-mark"><img :src="heroImg" class="logo-img" alt="" /></span>
      <span class="logo-text">
        <span class="logo-title">LLC-<em>Event</em></span>
        <span class="logo-sub">Management Suite</span>
      </span>
    </div>

    <nav class="sidebar-nav">
      <RouterLink
        v-for="link in links"
        :key="link.name"
        :to="{ name: link.name }"
        class="sidebar-link"
        :class="{ 'sidebar-link-active': isActive(link) }"
      >
        <span class="link-ico">{{ link.icon }}</span>
        <span class="link-label">{{ link.label }}</span>
        <span v-if="link.badge" class="link-badge">{{ link.badge }}</span>
        <span v-else-if="link.tag" class="link-tag">{{ link.tag }}</span>
      </RouterLink>
    </nav>

    <div class="sidebar-foot">
      <div class="station-card">
        <div class="station-title">● Check-in Station Ready</div>
        <p>Fast check-in scanner is standing by for new arrivals.</p>
      </div>
      <div class="foot-links">
        <span>Settings</span><i>•</i><span>Support Docs</span><i>•</i><span>v2.4</span>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  width: 240px; background: #062e2b; color: #fff;
  display: flex; flex-direction: column; padding: 18px 14px; gap: 18px;
}
.sidebar-logo { display: flex; align-items: center; gap: 13px; padding: 4px 6px; }
.logo-mark {
  width: 62px; height: 62px; border-radius: 50%; overflow: hidden;
  background: #10b981; flex: 0 0 auto;
  box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}
.logo-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.logo-text { display: flex; flex-direction: column; line-height: 1.15; min-width: 0; }
.logo-title { font-size: 20px; font-weight: 800; color: #fff; letter-spacing: 0.01em; }
.logo-title em { font-style: normal; color: #34d399; }
.logo-sub { font-size: 9px; letter-spacing: 0.14em; text-transform: uppercase; color: #5eead4; }

.sidebar-nav { display: flex; flex-direction: column; gap: 6px; }
.sidebar-link {
  position: relative;
  display: flex; gap: 10px; align-items: center;
  padding: 11px 12px; border-radius: 10px;
  color: #99f6e4; text-decoration: none; font-size: 14px; font-weight: 500;
}
.sidebar-link:hover { background: rgba(255, 255, 255, 0.06); color: #fff; }
.sidebar-link.sidebar-link-active {
  background: linear-gradient(90deg, #10b981, #0d9488);
  color: #fff; font-weight: 600;
  box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
}
.link-ico { font-size: 19px; width: 26px; text-align: center; line-height: 1; }
.link-label { flex: 1; }
.link-badge {
  min-width: 26px; height: 24px; padding: 0 8px;
  display: grid; place-items: center;
  background: rgba(2, 44, 34, 0.65); border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  font-size: 11.5px; font-weight: 700; color: #e2e8f0;
}
.sidebar-link-active .link-badge { background: rgba(2, 44, 34, 0.45); border-color: rgba(255, 255, 255, 0.18); color: #fff; }
.link-tag {
  background: #10b981; color: #022c22; border-radius: 6px;
  font-size: 9px; font-weight: 800; letter-spacing: 0.08em; padding: 3px 7px;
}

.sidebar-foot { margin-top: auto; display: flex; flex-direction: column; gap: 14px; }
.station-card {
  background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px; padding: 12px 14px;
}
.station-title { color: #34d399; font-size: 12px; font-weight: 700; }
.station-card p { margin: 6px 0 0; color: #99f6e4; font-size: 11.5px; line-height: 1.5; }
.foot-links { display: flex; align-items: center; gap: 8px; padding: 0 4px; color: #5eead4; font-size: 11.5px; }
.foot-links i { font-style: normal; color: #134e4a; }

/* Small screens (phones): slide-in drawer, hidden off-canvas by default */
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 60;
    width: 250px;
    max-width: 80vw;
    padding-top: calc(16px + env(safe-area-inset-top, 0px));
    transform: translateX(-100%);
    transition: transform 0.22s ease;
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.3);
    overflow-y: auto;
  }
  .sidebar-open { transform: translateX(0); }
  .sidebar-link { padding: 12px 14px; font-size: 15px; } /* finger-sized */
  .logo-mark { width: 54px; height: 54px; }
}
</style>
