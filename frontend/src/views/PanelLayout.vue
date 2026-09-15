<script setup>
import Navbar from '../components/Navbar.vue'
import Sidebar from '../components/Sidebar.vue'
</script>

<template>
  <div class="panel">
    <Sidebar />
    <div class="panel-main">
      <Navbar />
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
.sidebar {
  flex-shrink: 0;
  height: 100vh;
  overflow-y: auto;
}
.panel-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.navbar {
  flex-shrink: 0;
}
.panel-content {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 24px;
  background: #f1f5f9;
}
</style>
