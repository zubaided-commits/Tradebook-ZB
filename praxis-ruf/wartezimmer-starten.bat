@echo off
rem Praxis-Ruf-Anzeige auf einem bereits vorhandenen PC starten
rem (z. B. am Empfang) — kein eigenes Wartezimmer-Geraet noetig.
rem
rem Vor dem ersten Einsatz die Zeile mit ADRESSE unten anpassen: die
rem beiden IP-Adressen der Sprechzimmer-PCs eintragen (siehe Schritt 1
rem der ANLEITUNG.md) und das gewuenschte Wartezimmer waehlen.
rem
rem Diese Datei per Verknuepfung in den Autostart-Ordner legen
rem (Win + R, dann "shell:startup" eingeben).

setlocal
chcp 65001 >nul

rem ---- HIER ANPASSEN --------------------------------------------------
set RAUM=wz1
set QUELLEN=192.168.178.41:8080,192.168.178.42:8080
rem ----------------------------------------------------------------------

set ADRESSE=http://localhost:8080/wartezimmer.html?raum=%RAUM%^&quellen=%QUELLEN%^&vollbild=nein

rem Eigenes Chrome-Profil, getrennt vom normalen Profil an diesem PC.
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

rem --app          eigenes Fenster ohne Adressleiste, kein normaler Tab
rem Der Parameter "vollbild=nein" in ADRESSE verhindert, dass die Seite
rem   den ganzen Bildschirm uebernimmt — wichtig, wenn an diesem PC noch
rem   anderes gearbeitet wird
rem --autoplay-policy=no-user-gesture-required   Ton darf sofort spielen
rem --window-size / --window-position   klein, in eine Ecke — solange das
rem   Fenster sichtbar bleibt (nicht minimiert, nicht ganz verdeckt),
rem   drosselt Chrome dessen Zeitgeber (Weckton, Uhr) nicht
start "" %CHROME% --app="%ADRESSE%" --autoplay-policy=no-user-gesture-required ^
  --user-data-dir="%PROFIL%" --window-size=380,340 --window-position=20,20

endlocal
