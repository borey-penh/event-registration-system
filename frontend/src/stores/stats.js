import { defineStore } from 'pinia'
import { api } from '../lib/api'

/**
 * Shared dashboard stats. Both the sidebar (nav badges) and the Dashboard
 * view read from this store, so opening the panel fires one request, not two.
 * Cached briefly; callers can force a refresh after data-changing actions.
 */
export const useStatsStore = defineStore('stats', {
  state: () => ({
    data: null,
    loading: false,
    error: '',
    loadedAt: 0,
  }),

  actions: {
    async load(force = false) {
      if (this.loading) return
      if (!force && this.data && Date.now() - this.loadedAt < 30_000) return

      this.loading = true
      this.error = ''
      try {
        const { data } = await api.get('/dashboard-stats')
        this.data = data
        this.loadedAt = Date.now()
      } catch {
        this.error = 'Could not load dashboard stats.'
      } finally {
        this.loading = false
      }
    },
  },
})
