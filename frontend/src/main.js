import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './style.css'

// ---------------------------------------------------------------------------
// Stale-chunk recovery.
//
// The SPA is served by Laravel from ONE origin, and phones cache index.html.
// After a rebuild the asset hashes change, so a cached page may reference
// .js/.css files that no longer exist. Without recovery the dynamic import
// fails, Vue never renders, and the user sees a silent blank page. Reload
// once (bounded, per navigation) to pick up the fresh index.html.
// ---------------------------------------------------------------------------
const RELOAD_KEY = 'chunk_reload'
const RELOAD_WINDOW_MS = 10_000

function recoverFromStaleChunk() {
  const last = Number(sessionStorage.getItem(RELOAD_KEY) || 0)
  if (Date.now() - last < RELOAD_WINDOW_MS) return false // loop guard
  sessionStorage.setItem(RELOAD_KEY, String(Date.now()))
  window.location.reload()
  return true
}

window.addEventListener('vite:preloadError', (event) => {
  if (recoverFromStaleChunk()) event.preventDefault()
})

router.onError((error, to) => {
  const message = String(error?.message || '')
  const failedToFetch =
    /Failed to fetch dynamically imported module/i.test(message) ||
    /Importing a module script failed/i.test(message) ||
    /error loading dynamically imported module/i.test(message) ||
    /Unable to preload CSS/i.test(message)
  if (!failedToFetch) return

  // Remember the target so the fresh page opens the deep link (/register/...),
  // not whatever route the old bundle happened to resolve first.
  try {
    if (to?.fullPath) sessionStorage.setItem('chunk_reload_redirect', to.fullPath)
  } catch { /* storage unavailable — plain reload still helps */ }

  recoverFromStaleChunk()
})

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')
