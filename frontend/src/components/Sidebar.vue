<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import heroImg from '../assets/hero.png'

const emit = defineEmits(['close'])
const props = defineProps({ open: { type: Boolean, default: false } })

const links = [
  { name: 'dashboard', label: 'Dashboard', icon: '📊', match: ['dashboard'] },
  { name: 'events', label: 'Events', icon: '📅', match: ['events', 'event-create', 'event-detail'] },
  { name: 'candidates', label: 'Candidates', icon: '👥', match: ['candidates'] },
  { name: 'scan', label: 'Scan QR', icon: '📷', match: ['scan'] },
]

const route = useRoute()
const isActive = (link) => link.match.includes(route.name)

// Close the drawer whenever the route changes (phone navigation)
watch(() => route.fullPath, () => emit('close'))
</script>

<template>
  <aside class="sidebar" :class="{ 'sidebar-open': open }">
    <div class="sidebar-logo">
      <img :src="heroImg" class="sidebar-logo-img" alt="" />
      <span>EventReg</span>
    </div>
    <nav class="sidebar-nav">
      <RouterLink
        v-for="link in links"
        :key="link.name"
        :to="{ name: link.name }"
        class="sidebar-link"
        :class="{ 'sidebar-link-active': isActive(link) }"
      >
        <span>{{ link.icon }}</span>
        <span>{{ link.label }}</span>
      </RouterLink>
    </nav>
  </aside>
</template>

<style scoped>
.sidebar {
  width: 220px; background: #0f766e; color: #fff;
  display: flex; flex-direction: column; padding: 16px 12px; gap: 16px;
}
.sidebar-logo { font-size: 18px; font-weight: 800; padding: 8px 12px; display: flex; align-items: center; gap: 9px; }
.sidebar-logo-img { width: 52px; height: 52px; object-fit: over; border-radius: 50%; }
.sidebar-nav { display: flex; flex-direction: column; gap: 4px; }
.sidebar-link {
  display: flex; gap: 10px; align-items: center;
  padding: 10px 12px; border-radius: 8px;
  color: #ccfbf1; text-decoration: none; font-size: 14px;
}
.sidebar-link:hover { background: rgba(255,255,255,0.1); }
.sidebar-link.sidebar-link-active { background: #115e59; color: #fff; font-weight: 600; }

/* Small screens (phones): slide-in drawer, hidden off-canvas by default */
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 60;
    width: 240px;
    max-width: 80vw;
    padding-top: calc(16px + env(safe-area-inset-top, 0px));
    transform: translateX(-100%);
    transition: transform 0.22s ease;
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.3);
    overflow-y: auto;
  }
  .sidebar-open { transform: translateX(0); }
  .sidebar-logo { font-size: 16px; }
  .sidebar-link { padding: 12px 14px; font-size: 15px; } /* finger-sized */
}
</style>
