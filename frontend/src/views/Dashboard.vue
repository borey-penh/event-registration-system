<script setup>
import { onMounted, ref, computed } from 'vue'
import { api } from '../lib/api'

const stats = ref({ events: 0, registrations: 0, joined: 0, candidates: 0 })
const recentEvents = ref([])

onMounted(async () => {
  // SQL-only aggregate endpoint: stays fast even with millions of registrations.
  const { data } = await api.get('/dashboard-stats')
  stats.value = data
  recentEvents.value = data.recent_events || []
})

const totals = computed(() => stats.value)
</script>

<template>
  <div>
    <h1>Dashboard</h1>
    <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-value">{{ totals.events }}</div>
          <div class="stat-label">Events</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ totals.registrations }}</div>
          <div class="stat-label">Registrations</div>
        </div>
        <div class="stat-card stat-green">
          <div class="stat-value">{{ totals.joined }}</div>
          <div class="stat-label">Checked in</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ totals.candidates }}</div>
          <div class="stat-label">Candidates</div>
        </div>
      </div>

      <div class="card">
        <h2>Recent events</h2>
      <table v-if="recentEvents.length">
          <thead>
            <tr><th>Code</th><th>Title</th><th>Status</th><th>Registrations</th></tr>
          </thead>
          <tbody>
            <tr v-for="e in recentEvents" :key="e.id">
              <td>{{ e.event_code }}</td>
              <td>{{ e.title }}</td>
              <td><span class="badge" :class="'badge-' + e.status">{{ e.status }}</span></td>
              <td>{{ e.registrations_count }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="muted">No events yet. Create your first event.</p>
      </div>
  </div>
</template>

<style scoped>
.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.stat-value { font-size: 28px; font-weight: 800; color: #0f172a; }
.stat-label { color: #64748b; font-size: 13px; }
.stat-green .stat-value { color: #059669; }
.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
h2 { margin-top: 0; font-size: 16px; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th { text-align: left; color: #64748b; font-weight: 600; padding: 8px; border-bottom: 1px solid #e2e8f0; }
td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; }
.muted { color: #94a3b8; }
.badge { padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.badge-open { background: #d1fae5; color: #065f46; }
.badge-draft { background: #fef3c7; color: #92400e; }
.badge-closed { background: #fee2e2; color: #991b1b; }
</style>
