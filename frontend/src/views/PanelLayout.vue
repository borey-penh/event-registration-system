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
  background: #f1f5f9;
}

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
}
</style>
