@echo off
setlocal

rem Praxis-Ruf am Empfangs-PC starten.
rem Vor dem ersten Einsatz die Zeile mit ADRESSE unten anpassen.
rem Diese Datei per Verknuepfung in den Autostart-Ordner legen
rem (Win + R, dann "shell:startup" eingeben).

rem ---- HIER ANPASSEN ------------------------------------------------
set ADRESSE=https://ihre-domain.de/praxisruf/lautsprecher.php
rem --------------------------------------------------------------------

rem Eigenes Chrome-Profil, getrennt vom normalen Empfangs-Profil. Darin
rem bleibt die Anmeldung ueber Neustarts hinweg erhalten (Sitzung 30 Tage).
set PROFIL=%LOCALAPPDATA%\PraxisRufChrome

set CHROME="%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not exist %CHROME% set CHROME="%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not exist %CHROME% set CHROME="%LocalAppData%\Google\Chrome\Application\chrome.exe"

if not exist %CHROME% (
  echo.
  echo   Chrome wurde nicht gefunden. Bitte den Pfad in dieser Datei von
  echo   Hand eintragen, oder Chrome installieren.
  echo.
  pause
  exit /b 1
)

rem --app        eigenes Fenster ohne Adressleiste, kein normaler Tab
rem --autoplay-policy=no-user-gesture-required   Ton darf sofort spielen
rem --window-size / --window-position   klein, in eine Ecke — wichtig:
rem   solange das Fenster sichtbar bleibt (nicht minimiert, nicht ganz
rem   verdeckt), bremst Windows/Chrome das Nachfragen NICHT aus.
start "" %CHROME% --app="%ADRESSE%" --autoplay-policy=no-user-gesture-required ^
  --user-data-dir="%PROFIL%" --window-size=380,340 --window-position=20,20

endlocal
