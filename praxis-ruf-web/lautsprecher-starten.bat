@echo off
setlocal

rem Praxis-Ruf am Empfangs-PC starten.
rem Vor dem ersten Einsatz die Zeile mit ADRESSE unten anpassen.
rem Diese Datei per Verknuepfung in den Autostart-Ordner legen
rem (Win + R, dann "shell:startup" eingeben).

rem ---- HIER ANPASSEN ------------------------------------------------
rem  Domain und Ordnernamen durch die echten ersetzen.
rem
rem  GROSS- UND KLEINSCHREIBUNG BEACHTEN: Der Webserver unterscheidet sie.
rem  Heisst der Ordner "Praxis-Ruf-web", fuehrt "praxis-ruf-web" ins Leere.
set ADRESSE=https://IHRE-DOMAIN.de/ORDNERNAME/lautsprecher.php
rem --------------------------------------------------------------------

rem Eigenes Browser-Profil, getrennt vom normalen Empfangs-Profil. Darin
rem bleibt die Anmeldung ueber Neustarts hinweg erhalten (Sitzung 30 Tage).
set PROFIL=%LOCALAPPDATA%\PraxisRufBrowser

rem Erst Chrome suchen, sonst Edge: Edge ist auf jedem Windows-Rechner
rem bereits vorhanden, Chrome nicht unbedingt. Beide verstehen dieselben
rem Schalter. Edge bringt zusaetzlich sehr natuerlich klingende Stimmen mit
rem - die werden allerdings ueber das Internet erzeugt, siehe ANLEITUNG-WEB.md.
set BROWSER="%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not exist %BROWSER% set BROWSER="%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not exist %BROWSER% set BROWSER="%LocalAppData%\Google\Chrome\Application\chrome.exe"
if not exist %BROWSER% set BROWSER="%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
if not exist %BROWSER% set BROWSER="%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"

if not exist %BROWSER% (
  echo.
  echo   Weder Chrome noch Edge gefunden. Bitte den Pfad in dieser Datei
  echo   von Hand eintragen.
  echo.
  pause
  exit /b 1
)

rem --app        eigenes Fenster ohne Adressleiste, kein normaler Tab
rem --autoplay-policy=no-user-gesture-required   Ton darf sofort spielen
rem --window-size / --window-position   klein, in eine Ecke — wichtig:
rem   solange das Fenster sichtbar bleibt (nicht minimiert, nicht ganz
rem   verdeckt), bremst Windows/Chrome das Nachfragen NICHT aus.
start "" %BROWSER% --app="%ADRESSE%" --autoplay-policy=no-user-gesture-required ^
  --user-data-dir="%PROFIL%" --window-size=380,340 --window-position=20,20

endlocal
