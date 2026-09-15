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

export default router
