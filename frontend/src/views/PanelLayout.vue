<script setup>
import { ref } from 'vue'
import Navbar from '../components/Navbar.vue'
import Sidebar from '../components/Sidebar.vue'

// Off-canvas sidebar on phones, opened from the navbar burger button.
const menuOpen = ref(false)
</script>

<template>
  <div class="panel">
    <Sidebar :open="menuOpen" @close="menuOpen = false" />
    <!-- Dim + block the page while the drawer is open -->
    <div
      v-if="menuOpen"
      class="drawer-backdrop"
      @click="menuOpen = false"
    ></div>
    <div class="panel-main">
      <Navbar :menu-open="menuOpen" @toggle-menu="menuOpen = !menuOpen" />
      <main class="panel-content">
        <RouterView />
      </main>
      <!-- Status bar: pinned under the content area, like the design -->
      <footer class="panel-footer">
        <span class="foot-copy">© 2026 LLC-Event — Professional Event Registration &amp; Verification Platform</span>
        <span class="status-ok">● All systems operational</span>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.panel {
  display: flex;
  height: 100vh;
  overflow: hidden;
}
.panel-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.drawer-backdrop { display: none; }
.panel-content {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 24px;
  background: #f7fafc;
}

/* Full-width mint status bar pinned at the bottom of the content area */
.panel-footer {
  flex: 0 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  padding: 13px 24px;
  background: #e7f8f1;
  border-top: 1px solid #d3efe3;
  color: #7c8ba1;
  font-size: 12.5px;
}
.foot-copy { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.status-ok { color: #059669; font-weight: 700; white-space: nowrap; }

/* Small screens (phones): fixed drawer + dimmed backdrop */
@media (max-width: 768px) {
  .panel {
    height: auto;
    min-height: 100vh;
    height: 100dvh;
    overflow: visible;
  }
  .panel-main {
    min-height: 100dvh;
  }
  .drawer-backdrop {
    display: block;
    position: fixed;
    inset: 0;
    z-index: 50;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(1px);
  }
  .panel-content {
    overflow: visible;
    padding: 16px;
  }
  .panel-footer {
    flex-direction: column;
    justify-content: center;
    gap: 4px;
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px));
    font-size: 11px;
    text-align: center;
  }
}
</style>
