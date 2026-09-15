<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../lib/api'

const events = ref([])

onMounted(async () => {
  events.value = (await api.get('/events')).data
})
</script>

<template>
  <div>
    <div class="page-head">
      <h1>Events</h1>
      <RouterLink class="btn btn-primary" :to="{ name: 'event-create' }">+ Create Event</RouterLink>
    </div>

    <div class="card">
      <table v-if="events.length">
        <thead>
          <tr><th>Code</th><th>Title</th><th>Date</th><th>Status</th><th>Regs</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="e in events" :key="e.id">
            <td>{{ e.event_code }}</td>
            <td>{{ e.title }}</td>
            <td>{{ e.start_date }}<template v-if="e.start_time"> · {{ e.start_time.slice(0, 5) }}</template></td>
            <td><span class="badge" :class="'badge-' + e.status">{{ e.status }}</span></td>
            <td>{{ e.registrations_count }}</td>
            <td><RouterLink :to="{ name: 'event-detail', params: { id: e.id } }" class="link">Manage →</RouterLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else class="muted">No events yet.</p>
    </div>
  </div>
</template>

<style scoped>
.page-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
h1 { margin: 0; font-size: 22px; }
.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th { text-align: left; color: #64748b; font-weight: 600; padding: 8px; border-bottom: 1px solid #e2e8f0; }
td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; }
.muted { color: #94a3b8; }
.link { color: #0f766e; font-weight: 600; text-decoration: none; }
.badge { padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.badge-open { background: #d1fae5; color: #065f46; }
.badge-draft { background: #fef3c7; color: #92400e; }
.badge-closed { background: #fee2e2; color: #991b1b; }
</style>
