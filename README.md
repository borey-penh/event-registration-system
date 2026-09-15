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
