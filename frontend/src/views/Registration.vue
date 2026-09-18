<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import QRCode from 'qrcode'
import lockImage from '../assets/login-lock.jpg'
import {
  api,
  clearCandidateSession,
  exchangeSocialToken,
  getCandidateSession,
  readSocialLoginResult,
  setCandidateSession,
  startSocialLogin,
} from '../lib/api'

const route = useRoute()
const router = useRouter()

const token = route.params.token

// ---------- Flow state ----------
// phase 'checking'  → probing whether a candidate session already exists
// phase 'login'     → email + password gate (also used for first-time sign-up)
// phase 'form'      → the registration form itself (prefilled if submitted before)
// phase 'loading'   → fetching form + previous answers
const phase = ref('checking')
const authError = ref('')
const authSubmitting = ref(false)
const alreadySubmitted = ref(false)
const checkInQr = ref('')
const socialLoading = ref('') // which provider button is spinning, '' = none

const credentials = reactive({
  email: '',
  password: '',
})
const showPassword = ref(false)

const account = ref(null)
const registration = ref(null)

// Which social providers this server actually has OAuth credentials for.
// /api/app-info reports them; unconfigured providers get their button hidden
// instead of erroring after the click.
const socialLogin = ref({ google: false, facebook: false })

// ---------- Form state ----------
const event = ref(null)
const questions = ref([])
const loadError = ref('')
const submitting = ref(false)
const submitError = ref('')
const errors = ref({})
const canEdit = ref(false)

const form = reactive({ answers: {} })

onMounted(async () => {
  // Which social providers does this server actually have credentials for?
  // Fire-and-forget: buttons stay hidden until this resolves, so a server
  // without OAuth config never shows buttons that only produce an error.
  api.get('/app-info').then(({ data }) => {
    socialLogin.value = data.social_login ?? { google: false, facebook: false }
  }).catch(() => {})

  // Returning from Google/Facebook? The callback left a one-time token in
  // the URL fragment (#social_login=1&token=...&token_id=...). Swap it for a
  // real session, then continue exactly like a saved-session login.
  const social = readSocialLoginResult()
  if (social?.error) {
    authError.value = social.error
    phase.value = 'login'
    return
  }
  if (social?.token && social?.tokenId) {
    socialLoading.value = 'google'
    try {
      const data = await exchangeSocialToken(social.token, social.tokenId)
      setCandidateSession(data.account, data.token)
      account.value = data.account
      await loadForm()
      phase.value = 'form'
      return
    } catch {
      authError.value = 'Social login could not be completed. Please try again.'
      phase.value = 'login'
      return
    } finally {
      socialLoading.value = ''
    }
  }

  const session = getCandidateSession()
  if (session.token) {
    // Try the saved session; fall back to login if it was revoked/expired.
    try {
      account.value = session.account
      await loadForm()
      phase.value = 'form'
      return
    } catch {
      clearCandidateSession()
    }
  }
  phase.value = 'login'
})

function social(provider) {
  authError.value = ''

  // Not configured yet (no AUTH0 keys / provider credentials on the server):
  // explain instead of kicking off a flow that can only fail.
  if (! socialLogin.value[provider]) {
    authError.value = `${provider === 'google' ? 'Google' : 'Facebook'} login is being set up — please use email and password for now.`
    return
  }

  socialLoading.value = provider
  startSocialLogin(provider, token).catch((e) => {
    socialLoading.value = ''
    // Show the server's actual reason (e.g. "Google login is not
    // configured on this server.") instead of a vague generic message.
    authError.value = e.response?.data?.message || 'Could not start social login. Please try again.'
  })
}

async function loadForm() {
  const { data } = await api.get(`/candidate/my/registration/${token}`)
  event.value = data.event
  questions.value = data.questions
  registration.value = data.registration
  alreadySubmitted.value = !!data.registration
  canEdit.value = !!data.event.can_edit

  const answers = {}
  for (const q of data.questions) {
    const saved = data.registration?.answers?.[q.id]
    answers[q.id] = q.type === 'checkbox'
      ? (saved ? saved.split(', ').filter(Boolean) : [])
      : (saved ?? null)
  }
  form.answers = answers

  if (data.registration?.qr_token) {
    checkInQr.value = await QRCode.toDataURL(data.registration.qr_token, { width: 260, margin: 2 })
  }
}

async function submitAuth(mode) {
  authError.value = ''
  authSubmitting.value = true
  try {
    const { data } = await api.post(`/candidate/${mode}`, {
      email: credentials.email.trim(),
      password: credentials.password,
    })
    setCandidateSession(data.account, data.token)
    account.value = data.account
    await loadForm()
    phase.value = 'form'
  } catch (e) {
    const fields = e.response?.data?.errors
    if (fields) {
      authError.value = Object.values(fields).flat().join(' ')
    } else if (e.response?.status === 404) {
      loadError.value = 'Registration link is invalid or closed.'
    } else {
      authError.value = 'Login failed. Please try again.'
    }
  } finally {
    authSubmitting.value = false
  }
}

function logout() {
  clearCandidateSession()
  account.value = null
  registration.value = null
  event.value = null
  questions.value = []
  checkInQr.value = ''
  credentials.email = ''
  credentials.password = ''
  phase.value = 'login'
}

async function submit() {
  if (!canEdit.value) return
  if (!validate()) return
  submitting.value = true
  submitError.value = ''
  try {
    const { data } = await api.post(`/register/${token}`, {
      answers: form.answers,
    })
    // Keep every submission on the candidate's own registration page. This
    // immediately shows their saved information and personal check-in QR, and
    // lets them edit their answers while the event remains open.
    await loadForm()
    justSaved.value = true
    setTimeout(() => (justSaved.value = false), 4000)
  } catch (e) {
    if (e.response?.status === 401) {
      submitError.value = ''
      logout()
    } else {
      submitError.value = e.response?.data?.message || 'Failed to submit registration'
    }
  } finally {
    submitting.value = false
  }
}

const justSaved = ref(false)
const submitLabel = computed(() => (alreadySubmitted.value ? 'Save Changes' : 'Submit Registration'))

function validate() {
  errors.value = {}
  for (const q of questions.value) {
    const val = form.answers[q.id]
    const empty = q.type === 'checkbox' ? !val?.length : val === undefined || val === null || val === ''
    if (q.required && empty) {
      errors.value[q.id] = 'This field is required'
    }
  }
  return Object.keys(errors.value).length === 0
}
</script>

<template>
  <div class="reg-page">
    <!-- ================= Navbar (logged in only) ================= -->
    <nav v-if="account" class="reg-nav">
      <div class="reg-nav-inner">
        <div class="nav-brand">📋 <span>EventReg</span></div>
        <div class="nav-user">
          <span class="nav-avatar">{{ (account.name || account.email || '?').charAt(0).toUpperCase() }}</span>
          <span class="nav-user-name">{{ account.name || account.email }}</span>
          <button type="button" class="nav-logout" @click="logout">Log out</button>
        </div>
      </div>
    </nav>

    <!-- ================= Login / sign-up gate ================= -->
    <div v-if="phase === 'login'" class="reg-card auth-card">
      <img :src="lockImage" alt="Secure login" class="auth-hero" />
      <h1 class="auth-title">Login</h1>

      <form @submit.prevent="submitAuth(alreadySubmitted ? 'login' : 'register')">
        <label class="auth-label">Email</label>
        <input
          v-model="credentials.email"
          type="email"
          required
          autocomplete="email"
          class="auth-input"
          placeholder="you@example.com"
        />

        <label class="auth-label">Password</label>
        <div class="password-wrap">
          <input
            v-model="credentials.password"
            :type="showPassword ? 'text' : 'password'"
            required
            minlength="6"
            autocomplete="current-password"
            class="auth-input"
            placeholder="••••••••"
          />
          <button
            type="button"
            class="password-toggle"
            :aria-label="showPassword ? 'Hide password' : 'Show password'"
            @click="showPassword = !showPassword"
          >
            <svg v-if="showPassword" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
              <line x1="1" y1="1" x2="23" y2="23" />
            </svg>
            <svg v-else viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </button>
        </div>

        <p v-if="authError" class="auth-error">{{ authError }}</p>
        <p v-if="loadError" class="auth-error">{{ loadError }}</p>

        <button class="submit-btn" type="submit" :disabled="authSubmitting || !!socialLoading">
          {{ authSubmitting ? 'Please wait…' : 'Log In & Open Form' }}
        </button>
      </form>

      <div class="social-divider"><span>or continue with</span></div>

      <div class="social-buttons">
        <button type="button" class="social-btn google" :disabled="!!socialLoading || authSubmitting" @click="social('google')">
          <span v-if="socialLoading === 'google'" class="spinner"></span>
          <svg v-else class="social-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M23.5 12.27c0-.85-.08-1.66-.22-2.45H12v4.64h6.45a5.52 5.52 0 0 1-2.39 3.62v3h3.86c2.26-2.09 3.58-5.17 3.58-8.81z"/>
            <path fill="#34A853" d="M12 24c3.24 0 5.96-1.08 7.94-2.91l-3.86-3c-1.08.72-2.45 1.16-4.08 1.16-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09A11.99 11.99 0 0 0 12 24z"/>
            <path fill="#FBBC05" d="M5.27 14.29a7.2 7.2 0 0 1 0-4.58V6.62H1.29a12 12 0 0 0 0 10.76l3.98-3.09z"/>
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.43-3.43C17.95 1.19 15.24 0 12 0 7.7 0 3.99 2.47 1.29 6.62l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75z"/>
          </svg>
          Google
        </button>
        <button type="button" class="social-btn facebook" :disabled="!!socialLoading || authSubmitting" @click="social('facebook')">
          <span v-if="socialLoading === 'facebook'" class="spinner"></span>
          <svg v-else class="social-icon" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#1877F2" d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.09 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.09 24 18.1 24 12.07z"/>
          </svg>
          Facebook
        </button>
      </div>
    </div>

    <p v-else-if="phase === 'checking' || phase === 'loading'" class="muted" style="text-align:center; padding:40px;">Loading…</p>

    <!-- ================= Registration form ================= -->
    <div v-else-if="phase === 'form' && event" class="reg-card">
      <header class="reg-header">
        <h1>{{ event.title }}</h1>
        <p v-if="event.description">{{ event.description }}</p>
        <p class="reg-meta">
          <span v-if="event.location">📍 {{ event.location }}</span>
          <span v-if="event.start_date || event.end_date">📅 {{ event.start_date }}<template v-if="event.end_date"> → {{ event.end_date }}</template><template v-if="event.start_time"> · {{ event.start_time }}</template></span>
        </p>
      </header>

      <p v-if="justSaved" class="saved-banner">✓ Your changes were saved.</p>
      <p v-else-if="alreadySubmitted" class="edit-banner">
        ✓ We found your registration for <strong>{{ event.event_code }}</strong> — your answers are
        loaded below (filled on any device with this email). Edit anything and press
        <strong>Save Changes</strong>.
      </p>
      <p v-else class="edit-banner">
        You are not registered for <strong>{{ event.event_code }}</strong> yet. Fill in the form below
        to submit your registration.
      </p>

      <!-- Returning user: their saved info + check-in QR, read-only -->
      <div v-if="alreadySubmitted && registration" class="my-reg">
        <div class="my-reg-info">
          <h2>Your registration</h2>
          <p><strong>Name:</strong> {{ registration.candidate_name || account?.name || '—' }}</p>
          <p><strong>Status:</strong> <span class="badge" :class="registration.attendance_status === 'joined' ? 'badge-joined' : 'badge-registered'">{{ registration.attendance_status }}</span></p>
        </div>
        <div class="my-reg-qr">
          <img v-if="checkInQr" :src="checkInQr" alt="Your check-in QR code" class="checkin-qr" />
          <p class="qr-hint">Show this QR at the door for check-in</p>
        </div>
      </div>

      <p v-if="!canEdit" class="edit-banner">This event is closed. You can view your saved registration, but editing is disabled.</p>

      <!-- Dynamic questions -->
      <form @submit.prevent="submit">
      <div v-for="(q, i) in questions" :key="q.id" class="q-block">
        <div class="q-head">
          <span class="q-num">{{ i + 1 }}</span>
          <label class="q-label">{{ q.question }} <b v-if="q.required" class="req">*</b></label>
        </div>

        <input v-if="q.type === 'text'" v-model="form.answers[q.id]" :disabled="!canEdit" class="q-input" />
        <textarea v-else-if="q.type === 'textarea'" v-model="form.answers[q.id]" :disabled="!canEdit" rows="3" class="q-input"></textarea>
        <input v-else-if="q.type === 'date'" v-model="form.answers[q.id]" :disabled="!canEdit" type="date" class="q-input" />

        <template v-else-if="q.type === 'radio'">
          <label v-for="opt in q.options" :key="opt" class="q-option">
            <input v-model="form.answers[q.id]" :disabled="!canEdit" type="radio" :name="'q' + q.id" :value="opt" />
            <span>{{ opt }}</span>
          </label>
        </template>

        <template v-else-if="q.type === 'checkbox'">
          <label v-for="opt in q.options" :key="opt" class="q-option">
            <input v-model="form.answers[q.id]" :disabled="!canEdit" type="checkbox" :value="opt" />
            <span>{{ opt }}</span>
          </label>
        </template>

        <select v-else-if="q.type === 'select'" v-model="form.answers[q.id]" :disabled="!canEdit" class="q-input">
          <option value="" disabled>Select…</option>
          <option v-for="opt in q.options" :key="opt" :value="opt">{{ opt }}</option>
        </select>

        <p v-if="errors[q.id]" class="q-error">{{ errors[q.id] }}</p>
      </div>

      <p v-if="submitError" class="q-error">{{ submitError }}</p>
      <button v-if="canEdit" class="submit-btn" type="submit" :disabled="submitting">
        {{ submitting ? 'Saving…' : submitLabel }}
      </button>
      </form>
    </div>

    <div v-else-if="loadError" class="reg-card reg-error">{{ loadError }}</div>
  </div>
</template>

<style scoped>
.reg-page {
  position: relative;
  min-height: 100vh;
  padding: 24px 16px 64px;
  background:
    radial-gradient(1100px 600px at 90% -10%, rgba(18, 179, 130, 0.16), transparent 60%),
    radial-gradient(900px 550px at -10% 110%, rgba(13, 148, 136, 0.14), transparent 60%),
    linear-gradient(165deg, #eaf7f0 0%, #ddf0ea 40%, #d3ebe6 100%);
  /* clip (not hidden) so the sticky navbar keeps working */
  overflow-x: clip;
}

.reg-card,
.reg-error,
.muted { position: relative; z-index: 1; }
.reg-card {
  max-width: 820px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 26px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  padding: 40px 48px;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.05),
    0 12px 32px -12px rgba(15, 118, 110, 0.18);
  animation: rise 0.45s ease both;
}
@keyframes rise {
  from { opacity: 0; transform: translateY(14px); }
  to { opacity: 1; transform: translateY(0); }
}
.reg-error { color: #dc2626; text-align: center; padding: 40px; }

/* ---------- Navbar ---------- */
.reg-nav {
  position: sticky;
  top: 0;
  z-index: 20;
  /* Full-bleed bar: cancel the page's side/top padding so it spans the
     whole screen, content inside stays aligned with the card below. */
  margin: -24px -16px 26px;
  background: rgba(255, 255, 255, 0.97);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border-bottom: 1.5px solid rgba(15, 118, 110, 0.3);
  box-shadow:
    0 2px 5px rgba(15, 23, 42, 0.07),
    0 16px 36px -12px rgba(15, 118, 110, 0.5);
}
.reg-nav-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  max-width: 820px; /* same width as the card below — edges line up */
  margin: 0 auto;
  padding: 14px 24px;
}
.nav-brand {
  display: flex;
  align-items: center;
  gap: 9px;
  font-size: 18px;
  font-weight: 800;
  color: #0f766e;
  letter-spacing: 0.2px;
  white-space: nowrap;
}
.nav-user {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}
.nav-avatar {
  flex: 0 0 auto;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, #0f766e, #14b8a6);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 800;
  box-shadow: 0 3px 8px -2px rgba(13, 148, 136, 0.65);
}
.nav-user-name {
  font-size: 14px;
  font-weight: 700;
  color: #1e293b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.nav-logout {
  flex: 0 0 auto;
  background: #fff;
  border: 1.5px solid #5eead4;
  color: #0f766e;
  border-radius: 999px;
  padding: 8px 18px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 3px 10px -4px rgba(15, 118, 110, 0.4);
  transition: background 0.15s ease, transform 0.15s ease;
}
.nav-logout:hover { background: #f0fdfa; transform: translateY(-1px); }

/* ---------- Login card ---------- */
.auth-card {
  max-width: 460px;
  align-items: center;
  text-align: center;
  gap: 14px; /* tighter than the form card — the hero image adds height */
  margin-top: 44px; /* room for the hero image popping out the top */
}
.auth-hero {
  width: 112px;
  height: 112px;
  object-fit: cover;
  border-radius: 28px;
  margin-top: -64px; /* pops out the top of the card */
  box-shadow:
    0 0 0 6px #fff,
    0 14px 30px -10px rgba(15, 118, 110, 0.45);
  animation: hero-float 3.5s ease-in-out infinite;
}
@keyframes hero-float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-6px); }
}
.auth-icon { font-size: 44px; }
.auth-title { margin: 0; font-size: 24px; color: #0f172a; letter-spacing: 0.2px; }
.auth-card form { width: 100%; display: flex; flex-direction: column; gap: 8px; }
.auth-label {
  text-align: left; font-size: 13px; font-weight: 600; color: #334155; margin-top: 8px;
}
.auth-input {
  padding: 12px 14px;
  border: 1.5px solid #dbe3ec;
  border-radius: 12px;
  font-family: inherit;
  font-size: 14px;
  background: #f8fafc;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;
}
.auth-input:focus {
  outline: none;
  background: #fff;
  border-color: #0d9488;
  box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
}
.password-wrap {
  position: relative;
  display: flex;
}
.password-wrap .auth-input {
  width: 100%;
  box-sizing: border-box;
  padding-right: 44px;
}
.password-toggle {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 999px;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  color: #94a3b8;
  transition: color 0.15s ease, background-color 0.15s ease;
}
.password-toggle:hover {
  color: #0d9488;
  background: #f1f5f9;
}
.password-toggle:active {
  background: #e2e8f0;
}
.auth-error {
  color: #dc2626; font-size: 13px; margin: 8px 0 0;
  background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 8px 12px;
}
.auth-card .submit-btn { margin-top: 16px; }

/* ---------- Social login ---------- */
.social-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  margin: 10px 0 2px;
  color: #94a3b8;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.6px;
}
.social-divider::before,
.social-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #e2e8f0;
}
.social-buttons {
  display: flex;
  gap: 10px;
  width: 100%;
}
.social-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  padding: 11px 12px;
  border: 1.5px solid #dbe3ec;
  border-radius: 12px;
  background: #fff;
  font-family: inherit;
  font-size: 14px;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
}
.social-btn:hover:not(:disabled) {
  border-color: #0d9488;
  box-shadow: 0 4px 12px -6px rgba(13, 148, 136, 0.45);
  transform: translateY(-1px);
}
.social-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.social-icon { width: 18px; height: 18px; flex: 0 0 auto; }
.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid #cbd5e1;
  border-top-color: #0d9488;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ---------- Banners ---------- */
.edit-banner, .saved-banner {
  margin: 0; padding: 12px 16px; border-radius: 12px; font-size: 14px; line-height: 1.5;
}
.edit-banner { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
.saved-banner { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; font-weight: 600; }

/* ---------- Saved registration summary + check-in QR ---------- */
.my-reg {
  display: flex;
  gap: 20px;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 18px 20px;
}
.my-reg-info h2 {
  margin: 0 0 10px;
  font-size: 16px;
  color: #0f172a;
}
.my-reg-info p { margin: 4px 0; font-size: 14px; color: #334155; }
.badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}
.badge-registered { background: #dbeafe; color: #1e40af; }
.badge-joined { background: #d1fae5; color: #065f46; }
.my-reg-qr { display: flex; flex-direction: column; align-items: center; gap: 6px; }
.checkin-qr {
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  background: #fff;
  padding: 6px;
  width: 150px;
  height: 150px;
}
.qr-hint { margin: 0; font-size: 12px; color: #64748b; text-align: center; max-width: 170px; }

/* ---------- Header ---------- */
.reg-header {
  position: relative;
  overflow: hidden;
  border-radius: 16px;
  padding: 24px;
  background: linear-gradient(135deg, #0f766e 0%, #0d9488 55%, #14b8a6 100%);
  color: #fff;
  box-shadow: 0 10px 24px -10px rgba(13, 148, 136, 0.55);
}
.reg-header::after {
  content: '';
  position: absolute;
  right: -40px;
  top: -40px;
  width: 160px;
  height: 160px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.12);
}
.reg-header::before {
  content: '';
  position: absolute;
  right: 30px;
  bottom: -50px;
  width: 110px;
  height: 110px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
}
.reg-header h1 {
  margin: 0 0 8px;
  font-size: 20px;
  letter-spacing: 0.2px;
  color: #fff;
  position: relative;
}
.reg-header p { margin: 0; color: rgba(255, 255, 255, 0.92); font-size: 14px; line-height: 1.7; position: relative; }
.reg-meta { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px !important; }
.reg-meta span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 32px;
  box-sizing: border-box;
  background: #fff;
  border: 1px solid #fff;
  color: #0f766e;
  box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.18);
  padding: 0 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
}

/* ---------- Question blocks ---------- */
.q-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-bottom: 22px;
  border-bottom: 1px dashed #e2e8f0;
}
.q-block:last-of-type { border-bottom: 0; padding-bottom: 0; }
.q-head { display: flex; gap: 10px; align-items: flex-start; }
.q-num {
  background: linear-gradient(135deg, #0f766e, #14b8a6);
  color: #fff;
  min-width: 24px;
  height: 24px;
  border-radius: 8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  box-shadow: 0 3px 8px -2px rgba(13, 148, 136, 0.5);
}
.q-label { font-weight: 600; color: #0f172a; font-size: 15px; padding-top: 2px; }
.req { color: #e11d48; }

/* ---------- Inputs ---------- */
.q-input {
  padding: 12px 14px;
  border: 1.5px solid #dbe3ec;
  border-radius: 12px;
  font-family: inherit;
  font-size: 14px;
  background: #f8fafc;
  transition: border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
}
.q-input:hover { border-color: #94a3b8; }
.q-input:focus {
  outline: none;
  background: #fff;
  border-color: #0d9488;
  box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
}
textarea.q-input { resize: vertical; min-height: 90px; }

/* ---------- Radio / checkbox as selectable cards ---------- */
.q-option {
  display: flex;
  gap: 10px;
  align-items: center;
  font-size: 14px;
  color: #334155;
  padding: 11px 14px;
  margin: -2px 0;
  border: 1.5px solid #e2e8f0;
  border-radius: 12px;
  background: #f8fafc;
  cursor: pointer;
  transition: all 0.15s ease;
}
.q-option:hover {
  border-color: #5eead4;
  background: #f0fdfa;
  transform: translateX(3px);
}
.q-option:has(input:checked) {
  border-color: #0d9488;
  background: #ecfdf8;
  box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
  color: #0f766e;
  font-weight: 600;
}
.q-option input { accent-color: #0d9488; width: 17px; height: 17px; cursor: pointer; }

/* ---------- Submit ---------- */
.q-error {
  color: #dc2626;
  font-size: 13px;
  margin: 0;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 6px 10px;
  width: fit-content;
}
.submit-btn {
  position: relative;
  margin-top: 26px; /* breathing room after the last question */
  background: linear-gradient(135deg, #0f766e, #0d9488);
  color: #fff;
  border: 0;
  border-radius: 14px;
  padding: 15px;
  font-size: 16px;
  font-weight: 700;
  letter-spacing: 0.3px;
  cursor: pointer;
  box-shadow: 0 10px 20px -8px rgba(13, 148, 136, 0.6);
  transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
}
.submit-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 14px 26px -8px rgba(13, 148, 136, 0.7);
  filter: brightness(1.05);
}
.submit-btn:active:not(:disabled) { transform: translateY(0); box-shadow: 0 6px 14px -6px rgba(13, 148, 136, 0.6); }
.submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.muted { text-align: center; color: #64748b; }

@media (max-width: 900px) {
  .reg-card { padding: 28px 24px; }
}

/* ---------- Phone: compact full-width navbar ---------- */
@media (max-width: 640px) {
  .reg-nav { margin: -24px -16px 16px; }
  .reg-nav-inner { padding: 11px 16px; gap: 8px; }
  .nav-brand { font-size: 16px; }
  /* Name/email is truncated ugly on tiny screens — the avatar initial is
     enough up here, the full name already shows in "Your registration". */
  .nav-user-name { display: none; }
  .nav-user { gap: 10px; }
  .nav-avatar { width: 34px; height: 34px; font-size: 14px; }
  .nav-logout { padding: 9px 18px; } /* comfortable touch target */
}

@media (max-width: 480px) {
  .reg-card { padding: 20px; border-radius: 16px; }
  .reg-header { padding: 18px; }
  .auth-hero { width: 92px; height: 92px; margin-top: -54px; }
}
</style>
