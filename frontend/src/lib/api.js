import axios from 'axios'

// Embedded mode: when the SPA is built with `vite build --mode embedded`
// (see scripts/build-embedded-frontend.sh) it is served BY Laravel itself,
// so the API lives on the same origin the page was opened from — this makes
// the registration QR/link work from any device via the cloudflared tunnel,
// and the tunnel URL can change without rebuilding.
// VITE_EMBEDDED_API_URL (build-time only) can still force a different origin.
const embeddedOrigin = import.meta.env.MODE === 'embedded'
  ? window.location.origin + '/api'
  : null

// Dev server: always talk to the API same-origin — the Vite proxy forwards
// /api to Laravel. Pages opened on phones via the LAN IP then work with zero
// CORS setup; pointing them straight at localhost:8000 would only work on
// the PC itself.
const devSameOrigin = import.meta.env.DEV ? '/api' : null

export const api = axios.create({
  baseURL:
    import.meta.env.VITE_EMBEDDED_API_URL ||
    embeddedOrigin ||
    devSameOrigin ||
    import.meta.env.VITE_API_URL ||
    'http://localhost:8000/api',
})

// Which side of the app a request belongs to. Candidate requests (the public
// /register flow) carry the candidate token; everything else carries the
// manager token. Keeping them separate means one can never read the other's
// endpoints, and a logged-in candidate and manager can coexist in one browser.
export function isCandidateRequest(config = {}) {
  return typeof config.url === 'string' &&
    (/^\/candidate(\/|$)/.test(config.url) || /^\/register\//.test(config.url))
}

api.interceptors.request.use((config) => {
  if (isCandidateRequest(config)) {
    const candidateToken = localStorage.getItem('candidate_token') || sessionStorage.getItem('candidate_token')
    if (candidateToken) {
      config.headers.Authorization = `Bearer ${candidateToken}`
    }
    return config
  }

  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401 && localStorage.getItem('token')) {
      // Manager token rejected → clear panel session only.
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

// ---------- Candidate session (public registration flow) ----------
// Persisted in localStorage: re-scanning the event QR must silently restore
// the candidate's login and show their saved registration ("scan once").

const CANDIDATE_ACCOUNT_KEY = 'candidate_account'
const CANDIDATE_TOKEN_KEY = 'candidate_token'

function readLegacyCandidateSession() {
  try {
    return {
      account: JSON.parse(sessionStorage.getItem(CANDIDATE_ACCOUNT_KEY) || 'null'),
      token: sessionStorage.getItem(CANDIDATE_TOKEN_KEY),
    }
  } catch {
    return { account: null, token: null }
  }
}

export function setCandidateSession(account, token) {
  localStorage.setItem(CANDIDATE_ACCOUNT_KEY, JSON.stringify(account))
  localStorage.setItem(CANDIDATE_TOKEN_KEY, token)
}

export function getCandidateSession() {
  try {
    const account = JSON.parse(localStorage.getItem(CANDIDATE_ACCOUNT_KEY) || 'null')
    const token = localStorage.getItem(CANDIDATE_TOKEN_KEY)
    if (account || token) return { account, token }
  } catch {
    // fall through to the old sessionStorage copy below
  }
  return readLegacyCandidateSession()
}

export function clearCandidateSession() {
  localStorage.removeItem(CANDIDATE_ACCOUNT_KEY)
  localStorage.removeItem(CANDIDATE_TOKEN_KEY)
  sessionStorage.removeItem(CANDIDATE_ACCOUNT_KEY)
  sessionStorage.removeItem(CANDIDATE_TOKEN_KEY)
}

export function hasCandidateSession() {
  return !!(localStorage.getItem(CANDIDATE_TOKEN_KEY) || sessionStorage.getItem(CANDIDATE_TOKEN_KEY))
}

// ---------- Social login (Google / Facebook) ----------
// Server-driven OAuth: the SPA asks Laravel for the consent-screen URL, the
// provider 302s to the Laravel callback, and Laravel 302s back here with a
// one-time token in the URL *fragment* (#...). Fragments never hit server
// logs or the Referer header, so the token stays browser-side.

export async function startSocialLogin(provider, eventToken) {
  const { data } = await api.post('/candidate/social/redirect', {
    provider,
    event_token: eventToken,
    // The origin the callback should return to — validated server-side.
    origin: window.location.origin,
  })
  window.location.href = data.redirect_url
}

/** Consume `#social_login=1&token=...` (or `#social_error=...`) after the redirect. */
export function readSocialLoginResult() {
  if (typeof window === 'undefined' || !window.location.hash) return null

  const params = new URLSearchParams(window.location.hash.slice(1))
  if (!params.has('social_login') && !params.has('social_error')) return null

  const result = params.has('social_error')
    ? { error: params.get('social_error') || 'Social login failed.' }
    : {
        token: params.get('token') || '',
        tokenId: params.get('token_id') || '',
        created: params.get('created') === '1',
      }

  // Strip the fragment immediately so refresh/back doesn't re-consume it.
  history.replaceState(null, '', window.location.pathname + window.location.search)

  return result
}

/** Swap the one-time handoff token for a real candidate session. */
export async function exchangeSocialToken(handoffToken, tokenId) {
  // The handoff token authorizes exactly this one call.
  const { data } = await api.post(
    '/candidate/social/exchange',
    { token_id: Number(tokenId) },
    { headers: { Authorization: `Bearer ${handoffToken}` } },
  )
  return data
}
