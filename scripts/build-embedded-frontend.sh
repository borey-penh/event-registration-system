#!/usr/bin/env bash
# ===========================================================================
# Build the frontend in "embedded" mode and install it into backend/public/app.
#
# The Laravel app then serves the SPA itself (see backend/routes/web.php):
# one origin serves BOTH the API and the pages. This is what phones reach
# through the public cloudflared tunnel started by scripts/start-public.bat,
# so the registration QR works from ANY Wi-Fi or mobile data.
#
# Usage (from the repo root, Git Bash / WSL / macOS / Linux):
#   bash scripts/build-embedded-frontend.sh
# ===========================================================================
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Installing frontend dependencies (if needed)…"
(cd frontend && npm install)

echo "==> Building frontend in embedded mode (base: /app/)…"
(cd frontend && npm run build -- --mode embedded --outDir dist-embedded --emptyOutDir)

echo "==> Replacing backend/public/app…"
rm -rf backend/public/app
mkdir -p backend/public/app
cp -r frontend/dist-embedded/. backend/public/app/
rm -rf frontend/dist-embedded

echo "==> Done. The embedded app now serves from backend/public/app."
echo "    Restart 'php artisan serve' if it was already running, then use"
echo "    scripts/start-public.bat to expose it on a public tunnel URL."
