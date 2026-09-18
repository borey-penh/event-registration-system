#!/usr/bin/env bash
# Rebuilds the frontend SPA and copies it into backend/public/app/ so Laravel
# serves BOTH the app and the API from ONE public URL (the cloudflared tunnel).
# Candidates can then open the QR link / link directly — no CORS, no localhost.
#
# Usage:  bash scripts/build-embedded-frontend.sh
#         VITE_API_URL=https://xxx.trycloudflare.com/api bash scripts/build-embedded-frontend.sh
set -euo pipefail

cd "$(dirname "$0")/.."   # frontend/

# Optional explicit API override; by default the embedded app talks to its own
# origin (/api), so a stale tunnel URL in .env can never break it.
API_URL="${VITE_API_URL:-}"

echo "==> Building SPA (API: ${API_URL:-<same-origin /api>})"
if [ -n "$API_URL" ]; then
  VITE_EMBEDDED_API_URL="$API_URL" npx vite build --mode embedded
else
  npx vite build --mode embedded
fi

echo "==> Copying dist/ into backend/public/app/"
rm -rf ../backend/public/app
mkdir -p ../backend/public/app
cp -r dist/. ../backend/public/app/

echo "==> Done. Laravel now serves the app + API from one URL."
