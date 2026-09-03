# Praxis-Ruf — Projektkontext für Claude Code

Patientenaufrufsystem für eine Hausarztpraxis in Deutschland. Die Ärztin ruft
Patienten aus dem Wartezimmer ins Sprechzimmer — über Lautsprecher, ohne
aufzustehen. Sprachausgabe automatisch oder mit der eigenen Stimme per
Mikrofon.

Sprache im Code und in der Doku: **Deutsch**. Bezeichner, Kommentare,
Konsolenausgaben und Oberfläche sind deutsch. Bitte beibehalten — die Praxis
bedient und wartet das System selbst.

---

## Aufbau

Zwei Sprechzimmer-PCs, zwei Wartezimmer. Jeder Sprechzimmer-PC führt eine
**eigene, unabhängige Instanz** aus. Die Instanzen kennen einander nicht.

```
Sprechzimmer-PC 1 (192.168.178.41:8080)      Sprechzimmer-PC 2 (192.168.178.42:8080)
   node server.js                                node server.js
   Ärztin: http://localhost:8080/praxis.html     Ärztin: http://localhost:8080/praxis.html
        │                                              │
        └──────────────┬───────────────────────────────┘
                       │  Server-Sent Events (SSE), CORS offen
        ┌──────────────┴───────────────┐
   Bildschirm Wartezimmer 1       Bildschirm Wartezimmer 2
   wartezimmer.html?raum=wz1      wartezimmer.html?raum=wz2
     &quellen=41:8080,42:8080       &quellen=41:8080,42:8080
```

Der Bildschirm öffnet eine EventSource **pro Quelle**. Deshalb erreichen beide
Ärztinnen beide Wartezimmer, obwohl die Server nichts voneinander wissen.

### Dateien

| Datei | Zweck |
|---|---|
| `server.js` | Reiner Relay-Server. Keine Abhängigkeiten, nur Node-Standardmodule. |
| `config.json` | Pro PC unterschiedlich: `sprechzimmer` und `andereSprechzimmer`. Nicht im Repository — enthaelt Praxisname und interne IP-Adressen. |
| `config.beispiel.json` | Vorlage zum Kopieren nach `config.json`. |
| `public/praxis.html` | Bedienseite der Ärztin. Name eingeben, Wartezimmer antippen, Push-to-Talk. |
| `public/wartezimmer.html` | Anzeige + Lautsprecher. Sprachausgabe über Web Speech API, Gong über WebAudio. |
| `ANLEITUNG.md` | Deutsche Einrichtungsanleitung für die Praxis. Bei Änderungen mitpflegen. |
| `start-windows.bat` | Startet den Server unter Windows mit Konsolenfenster. Zeigt die fertigen Adressen an. |
| `praxis-ruf-unsichtbar.vbs` | Startet den Server unter Windows ohne Konsolenfenster. |
| `wartezimmer-starten.bat` | Öffnet `wartezimmer.html` auf einem vorhandenen PC (z. B. Empfang) als kleines Fenster, kein Vollbild. |

### Endpunkte

```
GET  /api/events?raum=wz1     SSE-Strom für einen Wartezimmer-Bildschirm
GET  /api/config              Konfiguration für die Oberflächen
GET  /api/status              Welche Bildschirme sind gerade verbunden
POST /api/call                { name, anrede, wartezimmer }  → Aufruf senden
POST /api/announce?ziel=wz1   Rohes Audio (webm/opus) → als Durchsage verteilen
GET  /api/audio/:id           Liefert eine Durchsage aus (max. 2 Min. im RAM)
POST /api/clear               Anzeige zurücksetzen
```

---

## Feste Randbedingungen

Diese Punkte sind bewusst so entschieden. Bitte nicht ohne Rückfrage ändern —
sie sind der Grund, warum die Praxis das System überhaupt einsetzt.

1. **Keine npm-Abhängigkeiten.** Nur Node-Standardmodule. Die Praxis hat keine
   IT-Abteilung; jede Abhängigkeit ist eine Update- und Sicherheitslast.
   Kein Express, kein `ws`, kein Build-Schritt, kein TypeScript-Kompilat.

2. **Nichts auf die Festplatte.** Keine Datenbank, keine Logdatei, keine
   `state.json`. Namen sind Gesundheitsdaten nach Art. 9 DSGVO. Alles liegt
   im Arbeitsspeicher und ist nach dem Beenden weg. Auch beim Debuggen keine
   Patientennamen in Dateien schreiben.

3. **Nichts auf dem Praxis-Server.** Läuft ausschliesslich auf den beiden
   Sprechzimmer-PCs. Kein Zugriff auf CGM, Doctolib, PVS oder Netzlaufwerke.

4. **Kein Internet.** Rein lokales Netz. Keine CDNs, keine Web-Fonts, keine
   externen APIs, keine Telemetrie. Alles muss offline funktionieren.

5. **`localhost` für die Bedienseite.** Die Ärztin öffnet
   `http://localhost:8080/praxis.html` auf ihrem eigenen PC. Nur dadurch gilt
   die Seite als sicherer Kontext und `getUserMedia` funktioniert ohne
   HTTPS-Zertifikat. Diesen Weg nicht aufgeben — HTTPS mit selbstsignierten
   Zertifikaten wurde bewusst verworfen, weil es an jedem Gerät eine
   Browserwarnung erzeugt.

6. **Kein gemeinsamer Zustand zwischen den beiden Instanzen.** Wurde
   ausdrücklich gewünscht. Wer schon aufgerufen wurde, sieht die Praxis in
   Doctolib. Keine Warteliste, keine Synchronisierung, kein zweiter Dienst.

7. **Einfachheit vor Funktionsumfang.** Eine frühere, umfangreichere Fassung
   mit gemeinsamer Warteliste wurde als zu komplex abgelehnt.

---

## Umgebung in der Praxis

* Windows-PCs, Chrome. Node.js LTS, per Hand installiert.
* Feste IPs über den Router vergeben.
* Windows-Firewall: nur „privates Netzwerk" freigegeben.
* Autostart über eine Verknüpfung auf `praxis-ruf-unsichtbar.vbs` im
  Autostart-Ordner.
* Wartezimmer-Gerät: entweder ein dedizierter Bildschirm/Tablet im
  Chrome-Kiosk-Modus, oder ein bereits vorhandener PC (z. B. Empfang) mit
  Bluetooth-Lautsprecher über `wartezimmer-starten.bat` — dort läuft die
  Seite bewusst mit `&vollbild=nein` als kleines Fenster, nicht im
  Vollbild, damit der PC weiter für anderes benutzt werden kann.

## Bekannte Schwachstellen

* **Sprachqualität** hängt von der Browserstimme ab („Google Deutsch",
  „Microsoft Katja"). Nächster sinnvoller Schritt wäre **Piper TTS** lokal auf
  dem PC — deutsche Modelle, offline, deutlich natürlicher. Das würde
  Randbedingung 1 berühren und braucht eine bewusste Entscheidung.
* **Aussprache von Namen** ist nicht korrigierbar. Eine Ersetzungstabelle in
  `config.json` wäre ein kleiner, lohnender Zusatz.
* Kein Schutz gegen Fehlbedienung im Netz — jeder im Praxis-WLAN könnte einen
  Aufruf auslösen. Bisher als unkritisch bewertet.
* MediaRecorder-Format ist browserabhängig (meist `audio/webm;codecs=opus`).
  Wird unverändert durchgereicht; Safari kann abweichen.
* Läuft `wartezimmer.html` als Fenster/Tab auf einem PC, der auch für
  anderes benutzt wird, und wird dieses Fenster minimiert oder lange
  verdeckt, drosselt Chrome erwiesenermassen dessen `setInterval`-Zeitgeber
  (Weckton, Uhr). Ob und wie stark ein `EventSource`-Aufruf (Namensaufruf)
  in einem solchen Fenster verzögert wird, ist nicht abschliessend
  getestet — verlässlich ist nur, das Fenster sichtbar zu lassen (nicht
  minimiert, nicht vollständig verdeckt). Getestet wurde ausschliesslich
  das reine Verstecken/Wiedereinblenden per `visibilitychange`, nicht ein
  minutenlanges Verweilen im Hintergrund.

## Testen

Ohne Praxisnetz lassen sich beide Instanzen lokal simulieren: eine zweite Kopie
mit `"port": 8081` und anderem `sprechzimmer`-Namen starten, dann einen
Bildschirm mit `?quellen=localhost:8080,localhost:8081` öffnen.

```bash
npm start                    # bzw. node server.js
curl -X POST localhost:8080/api/call \
  -H 'Content-Type: application/json' \
  -d '{"name":"Müller","anrede":"Frau","wartezimmer":"wz1"}'
```

Vor jeder Übergabe prüfen: Aufruf erreicht beide Wartezimmer, Durchsage wird
abgespielt, nach dem Beenden liegt keine neue Datei im Ordner.
