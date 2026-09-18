@echo off
REM ===========================================================================
REM  ANY-NETWORK mode: starts backend + frontend + a PUBLIC cloudflared tunnel
REM  so the registration QR works from ANY Wi-Fi or mobile data — not just the
REM  organizer's own network. The tunnel URL is captured automatically and the
REM  app picks it up (no rebuild needed).
REM
REM  Requires: php (backend), node (frontend), cloudflared.exe
REM            (already in backend\, or in PATH).
REM ===========================================================================
setlocal enabledelayedexpansion
cd /d "%~dp0.."

REM --- 0. Where things live -------------------------------------------------
set "ROOT=%CD%"
set "BACKEND=%ROOT%\backend"
set "FRONTEND=%ROOT%\frontend"
set "CFLOG=%TEMP%\eventreg_cloudflared.log"
set "URLFILE=%BACKEND%\storage\app\public_origin.txt"
set "CLOUDFLARED=%BACKEND%\cloudflared.exe"
where cloudflared >nul 2>nul && set "CLOUDFLARED=cloudflared"

if not exist "%CLOUDFLARED%" (
  echo [ERROR] cloudflared.exe not found in backend\ or PATH.
  echo         Download: https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/
  pause
  exit /b 1
)

REM Clean any previous tunnel URL so a stale one is never reused.
del "%URLFILE%" 2>nul
del "%CFLOG%" 2>nul

echo.
echo === 1/4  Starting backend on http://0.0.0.0:8000 ==========================
start "Backend" cmd /k "cd /d %BACKEND% && php artisan serve --host=0.0.0.0 --port=8000"

echo === 2/4  Starting frontend dev server on http://0.0.0.0:5173 ==============
start "Frontend" cmd /k "cd /d %FRONTEND% && npm run dev -- --host"

echo === 3/4  Waiting for backend to accept requests ===========================
set /a TRIES=0
:wait_backend
set /a TRIES+=1
if %TRIES% gtr 60 (
  echo [ERROR] Backend did not come up on port 8000.
  pause
  exit /b 1
)
curl -s -o nul http://127.0.0.1:8000/api/app-info 2>nul
if errorlevel 1 (
  timeout /t 1 /nobreak >nul
  goto wait_backend
)
echo         Backend is up.

echo === 4/4  Starting PUBLIC tunnel (cloudflared) =============================
REM Quick tunnel: prints the assigned https://xxxx.trycloudflare.com to stderr.
REM We log to a file, then extract the URL below (works with no tee on cmd).
start "Tunnel" cmd /k "cd /d %BACKEND% && "%CLOUDFLARED%" tunnel --url http://localhost:8000 >> "%CFLOG%" 2>&1"

echo         Tunnel starting (first start takes ~10 seconds)...
set /a TRIES=0
:wait_url
set /a TRIES+=1
if %TRIES% gtr 45 (
  echo [WARN]  No tunnel URL captured yet. Check the "Tunnel" window for errors.
  goto done
)
powershell -NoProfile -Command "$m = Select-String -Path '%CFLOG%' -Pattern 'https://[a-zA-Z0-9-]+\.trycloudflare\.com' -ErrorAction SilentlyContinue | Select-Object -First 1; if ($m) { $m.Matches[0].Value | Out-File -Encoding ascii '%URLFILE%' }" >nul 2>nul
if exist "%URLFILE%" goto done
timeout /t 2 /nobreak >nul
goto wait_url

:done
echo.
echo ============================================================================
echo   MANAGER PANEL:      http://localhost:5173/   (login: manager@example.com)
echo.
echo   CANDIDATE QR/LINK:  works on ANY network automatically — open an event,
echo                       "Registration QR" tab, scan/copy the link shown.
echo.
if exist "%URLFILE%" (
  set /p PUBLICURL=<"%URLFILE%"
  echo   PUBLIC URL:         !PUBLICURL!
  echo   Candidate example:  !PUBLICURL!/register/YOUR-EVENT-TOKEN
)
echo.
echo   Keep the "Tunnel" window open while the event runs — closing it stops
echo   the public link. The QR on phones keeps working while the tunnel runs.
echo ============================================================================
echo.
pause
