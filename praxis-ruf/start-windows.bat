@echo off
rem Praxis-Ruf starten. Dieses Fenster offen lassen.
chcp 65001 >nul
cd /d "%~dp0"

where node >nul 2>nul
if errorlevel 1 (
  echo.
  echo   Node.js wurde nicht gefunden.
  echo   Bitte von nodejs.org die LTS-Fassung installieren und neu starten.
  echo.
  pause
  exit /b 1
)

title Praxis-Ruf
node server.js

echo.
echo   Praxis-Ruf wurde beendet.
pause
