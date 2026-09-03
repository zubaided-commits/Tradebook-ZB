# Praxis-Ruf Web

Patientenaufruf über das Internet, für gewöhnliches PHP-Webhosting (IONOS).
Schwesterfassung zu `../praxis-ruf`, die im Praxisnetz läuft.

**Im Wartezimmer wird kein Bildschirm gebraucht — nur ein Lautsprecher.**
Läuft in der Praxis bereits ein Empfangs-PC, genügt der: Bluetooth-Lautsprecher
koppeln, `lautsprecher-starten.bat` einrichten, fertig — keine zusätzliche
Anschaffung nötig. Ein leiser Weckton hält den Lautsprecher wach; reine
WLAN-Lautsprecher (Sonos, Chromecast, AirPlay) sind nicht geeignet — siehe
ANLEITUNG-WEB.md.

- **Einrichtung, Bedienung und Datenschutz:** [ANLEITUNG-WEB.md](ANLEITUNG-WEB.md)

## Aufbau

```
Sprechzimmer 1  ─┐                                  ┌─ Lautsprecher Wartezimmer 1
                 ├─►  IONOS-Webhosting (PHP)  ──────┤
Sprechzimmer 2  ─┘     ruf.php / api.php            └─ Lautsprecher Wartezimmer 2
                       daten/stand.php
```

Kein Node, keine Datenbank, keine Abhängigkeiten. Die Seiten fragen alle
zwei Sekunden nach dem Stand — das funktioniert auf jedem Webhosting,
auch dort, wo dauerhafte Verbindungen (SSE, WebSocket) abgeschnitten werden.

| Datei | Zweck |
|---|---|
| `inc.php` | Konfiguration, Anmeldung, Zustandsdatei mit Dateisperre |
| `api.php` | Schnittstelle: `stand`, `aufruf`, `durchsage`, `ton`, `leeren` |
| `ruf.php` | Bedienseite der Ärztin, gemeinsame Sicht beider Sprechzimmer |
| `lautsprecher.php` | Gerät im Wartezimmer, reine Tonausgabe |
| `anmelden.php` / `abmelden.php` | Zugang per gemeinsamem Passwort |
| `config.beispiel.php` | Vorlage für `config.php` (nicht im Repository) |
| `lautsprecher-starten.bat` | Startet `lautsprecher.php` am Empfangs-PC in einem eigenen Fenster |

## Datenschutz in Kürze

Namen stehen nie in einer Adresszeile, nur im POST-Inhalt — sonst lägen sie
in den Zugriffsprotokollen des Hosters. Aufrufe verfallen nach 45 Sekunden,
die Liste „Zuletzt aufgerufen" nach 10 Minuten, Sprachdurchsagen nach
2 Minuten. Die Dateien in `daten/` tragen die Endung `.php` und beginnen mit
`<?php exit;` — ein direkter Abruf gibt nichts preis, auch wenn `.htaccess`
einmal nicht greifen sollte.

**Vor dem Echtbetrieb ist ein Auftragsverarbeitungsvertrag mit IONOS nach
Art. 28 DSGVO nötig.** Siehe ANLEITUNG-WEB.md.
