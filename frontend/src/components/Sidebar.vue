<script setup>
const links = [
  { name: 'dashboard', label: 'Dashboard', icon: '📊' },
  { name: 'events', label: 'Events', icon: '📅' },
  { name: 'candidates', label: 'Candidates', icon: '👥' },
  { name: 'scan', label: 'Scan QR', icon: '📷' },
]
</script>

<template>
  <aside class="sidebar">
    <div class="sidebar-logo">📋 EventReg</div>
    <nav class="sidebar-nav">
      <RouterLink
        v-for="link in links"
        :key="link.name"
        :to="{ name: link.name }"
        class="sidebar-link"
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
.sidebar-logo { font-size: 18px; font-weight: 800; padding: 8px 12px; }
.sidebar-nav { display: flex; flex-direction: column; gap: 4px; }
.sidebar-link {
  display: flex; gap: 10px; align-items: center;
  padding: 10px 12px; border-radius: 8px;
  color: #ccfbf1; text-decoration: none; font-size: 14px;
}
.sidebar-link:hover { background: rgba(255,255,255,0.1); }
.sidebar-link.router-link-active { background: #115e59; color: #fff; font-weight: 600; }
</style>
