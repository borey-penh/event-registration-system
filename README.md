# Event Registration System

Full-stack event registration + QR check-in system.

- **Backend:** Laravel 12 API (SQLite) with Sanctum token auth
- **Frontend:** Vue 3 + Vite + Pinia + Vue Router

## Flow

```
Manager: login → create event → build registration form → share QR/link
Candidate: scan QR → fill form → submit → receive candidate QR
Event day: manager scans candidate QR → joined / warning if already joined
```

## Run — same Wi-Fi only

For QR links that work on **any** network (mobile data, another Wi-Fi), run:

```bat
scripts\start-public.bat
```

It starts the backend, frontend, and a public cloudflared tunnel, and the registration QR automatically uses the public URL (see [Any-network QR](#any-network-qr) below).

## Run

Backend (port 8000):

```bash
cd backend
composer install
php artisan migrate:fresh --seed     # creates manager + demo event
php artisan serve --port=8000
```

Frontend (port 5173):

```bash
cd frontend
npm install
npm run dev
```

## Accounts & demo data

| What | Value |
|---|---|
| Manager login | `manager@example.com` / `password` |
| Demo registration link | `http://localhost:5173/register/IT2026ABC` |

## Social login (Google / Facebook) — candidate flow

On the `/register/{token}` page candidates can sign in with Google or Facebook instead of email + password. The server runs a standard OAuth 2.0 authorization-code flow (no Socialite), and the SPA receives a one-time handoff token via the URL fragment, then swaps it for its regular Sanctum candidate session.

Backend endpoints:

- `POST /api/candidate/social/redirect` — `{ provider, event_token, origin }` → `{ redirect_url }`
- `GET  /api/candidate/social/{google|facebook}/callback` — provider redirect (browser only)
- `POST /api/candidate/social/exchange` — swap the one-time handoff token for a session (Bearer handoff token)

### Setup

1. **Google** — create OAuth credentials at <https://console.cloud.google.com/apis/credentials> (type *Web application*). Add an *Authorized redirect URI* for **every origin you serve the app from**, e.g.:
   - `http://localhost:8000/api/candidate/social/google/callback`
   - `https://your-tunnel.trycloudflare.com/api/candidate/social/google/callback`
2. **Facebook** — create an app at <https://developers.facebook.com/apps> (type *Consumer*), add the *Facebook Login* product, and register the equivalent `.../api/candidate/social/facebook/callback` redirect URIs in *Facebook Login → Settings*.
3. Put the credentials in `backend/.env`:

```dotenv
GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxx
FACEBOOK_CLIENT_ID=xxx
FACEBOOK_CLIENT_SECRET=xxx
```

Notes:

- The redirect URI is derived from `APP_URL`; set `APP_URL` to the origin the API runs on (for the embedded tunnel setup, that is the tunnel URL).
- Returning users are matched by `(provider, provider_id)` first, then by the provider-verified email — a candidate who previously used email + password gets their registration history linked, not a second account.
- Google/Facebook-only accounts have no password; the password login tells them to use the social button instead.
- Google/Facebook only return an email when the user has one verified on the account; Facebook users who sign up with a phone number are told to use email + password instead.

## API overview

Public:
- `GET  /api/register/{token}` — form definition (event + questions)
- `POST /api/register/{token}` — submit registration → returns `qr_token`
- `POST /api/login` — manager login → Bearer token

Protected (`Authorization: Bearer <token>`):
- `GET/POST /api/events`, `GET/PUT/DELETE /api/events/{id}`
- `PUT  /api/events/{id}/questions` — replace the form builder questions
- `GET  /api/events/{id}/stats` — total / joined counts
- `GET  /api/candidates` — search candidates
- `POST /api/check-in` — body `{ "qr_token": "REG-..." }` → joined / already_joined / not_found
- `POST /api/check-in/{registration}/undo` — revert a check-in

## Database tables

`users`, `events` (with `registration_token` → `/register/{token}`),
`form_questions` (text/textarea/radio/checkbox/select/date, options as JSON),
`candidates`, `registrations` (unique `qr_token`, `attendance_status`),
`registration_answers`.

Backend test script: `php backend/api_test.php` (while `artisan serve` runs).

## Any-network QR

By default the registration QR points at the PC's LAN IP — phones on the **same Wi-Fi** can open it, but mobile data or a different Wi-Fi cannot. To make it work everywhere:

```bat
scripts\start-public.bat
```

This starts the backend + frontend + a free `cloudflared` quick tunnel and captures the public URL (e.g. `https://xxxx.trycloudflare.com`) into `backend/storage/app/public_origin.txt`. `GET /api/app-info` then reports it as `public_origin`, and the Registration QR tab uses it automatically.

- Pin a fixed URL instead: set `APP_PUBLIC_ORIGIN=https://your-domain` in `backend/.env`
- Or per-browser: set `localStorage.publicOrigin = "https://xxxx.trycloudflare.com"`
- Manager panel keeps working on `http://localhost:5173` regardless.

