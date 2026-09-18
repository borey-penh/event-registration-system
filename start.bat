@echo off
REM Start backend + frontend reachable from other devices on the SAME Wi-Fi/LAN.
REM For ANY network (mobile data, other Wi-Fi), run scripts\start-public.bat instead.

cd /d "%~dp0"

echo Starting backend on http://0.0.0.0:8000 ...
start "Backend" cmd /k "cd backend && php artisan serve --host=0.0.0.0 --port=8000"

echo Starting frontend on http://0.0.0.0:5173 ...
start "Frontend" cmd /k "cd frontend && npm run dev -- --host"

timeout /t 6 >nul

echo.
echo ============================================================
echo   MANAGER PANEL:  open the Network URL shown in the
echo   Frontend window (e.g. http://192.168.88.45:5173/)
echo.
echo   CANDIDATE LINK: http://YOUR-IP:5173/register/IT2026ABC
echo   (shown in the Frontend window as "Network: ...")
echo.
echo   NOTE: only works on the SAME Wi-Fi as this PC.
echo   For any-network QR links, run: scripts\start-public.bat
echo ============================================================
echo.
pause
