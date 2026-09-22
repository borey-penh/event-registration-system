import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  // Public candidate flow
  { path: '/register/:token', name: 'registration', component: () => import('../views/Registration.vue') },
  { path: '/register-success', name: 'registration-success', component: () => import('../views/RegistrationSuccess.vue') },

  // Manager panel
  { path: '/login', name: 'login', component: () => import('../views/Login.vue') },
  {
    path: '/',
    component: () => import('../views/PanelLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('../views/Dashboard.vue') },
      { path: 'events', name: 'events', component: () => import('../views/Events.vue') },
      { path: 'events/create', name: 'event-create', component: () => import('../views/EventCreate.vue') },
      { path: 'events/:id', name: 'event-detail', component: () => import('../views/EventDetail.vue') },
      { path: 'candidates', name: 'candidates', component: () => import('../views/CandidateList.vue') },
      { path: 'scan', name: 'scan', component: () => import('../views/ScanQR.vue') },
    ],
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }
})

// After a stale-chunk reload (see main.js) landed us on a fallback URL,
// continue to the route the user actually wanted — e.g. the /register/{token}
// link they opened from a QR code.
try {
  router.isReady().then(() => {
    const target = sessionStorage.getItem('chunk_reload_redirect')
    if (!target) return
    sessionStorage.removeItem('chunk_reload_redirect')
    if (target !== window.location.pathname + window.location.search) {
      router.push(target).catch(() => {})
    }
  })
} catch { /* storage unavailable — plain reload still helps */ }

export default router
