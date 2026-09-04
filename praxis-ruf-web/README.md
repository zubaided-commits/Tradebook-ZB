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

Die Anmeldung nutzt bewusst **kein** PHP-`$_SESSION`, sondern ein selbst
signiertes Cookie (Rolle + Ablaufzeit, per HMAC mit dem Passwort gesichert).
Geteiltes Webhosting hebt `session.gc_maxlifetime` selten auf 30 Tage an —
eine gewöhnliche PHP-Sitzung wäre server-seitig oft schon nach rund
24 Minuten weg, während das Cookie im Browser noch gültig aussähe. Das
selbst signierte Cookie braucht keine Serverablage und übersteht das.
Ändert sich das Passwort, werden alle bestehenden Anmeldungen sofort
ungültig — der eigentliche Weg für einen Personalwechsel.

| Datei | Zweck |
|---|---|
| `inc.php` | Konfiguration, Anmeldung, Zustandsdatei mit Dateisperre |
| `api.php` | Schnittstelle: `stand`, `aufruf`, `durchsage`, `ton`, `leeren` |
| `ruf.php` | Bedienseite der Ärztin, gemeinsame Sicht beider Sprechzimmer |
| `lautsprecher.php` | Gerät im Wartezimmer, reine Tonausgabe |
| `tonpruefung.php` | Diagnoseseite: prüft Gong, Sprachausgabe und Tondatei einzeln |
| `aussprache.php` | Schreibt Namen für die Stimme in deutsche Rechtschreibung um |
| `anmelden.php` / `abmelden.php` | Zugang per gemeinsamem Passwort, 30 Tage gültig |
| `config.beispiel.php` | Vorlage für `config.php` (nicht im Repository) |
| `lautsprecher-starten.bat` | Startet `lautsprecher.php` am Empfangs-PC in einem eigenen Fenster |

## Stimme und Aussprache

Welche Stimmen zur Verfügung stehen, entscheidet allein das Gerät — eine
Webseite kann keine mitbringen oder nachladen. Auf dem Startbildschirm des
Lautsprechers lässt sich unter den vorhandenen deutschen Stimmen auswählen;
die Auswahl wird sofort vorgehört und auf dem Gerät gemerkt. Auf iPad und
iPhone bringt es hörbar am meisten, unter *Einstellungen → Bedienungshilfen →
Gesprochene Inhalte → Stimmen → Deutsch* eine Stimme in „Premium" zu laden.

Eine deutsche Stimme liest deutsche Rechtschreibung — bei Namen aus dem
Persischen, Dari, Paschto und Arabischen geht das schief: `Z` klingt wie
„ts", `J` wie in „ja", `sh` und `q` kennt das Deutsche so nicht. `aussprache.php`
schreibt solche Namen für die Ansage um (aus *Zubaida* wird *Subaida*, aus
*Najib* wird *Nadschib*); **angezeigt und gespeichert bleibt immer der echte
Name.** Deutsche Namen bleiben unangetastet — bewusst wörterbuchgestützt,
denn eine Regel wie „Z wird S" würde aus *Zimmermann* ein *Simmermann* machen.
Eigene Namen kommen in `config.php` unter `aussprache` dazu.

## Wenn kein Ton kommt

`tonpruefung.php` im Browser des betroffenen Geräts öffnen. Die Seite probiert
die drei Wege einzeln durch, auf denen der Lautsprecher Ton erzeugt — Gong
(Web Audio), Sprachausgabe und Tondatei — jeweils einmal direkt auf
Tastendruck und einmal drei Sekunden später, denn iOS behandelt beides
unterschiedlich streng. Jeder Schritt wird sichtbar mitgeschrieben, samt
Fehlermeldung und den auf dem Gerät vorhandenen Stimmen. Auf einem iPad gibt
es keine Entwicklerwerkzeuge; ohne diese Seite bliebe nur Raten.

Unten auf jeder Seite steht die Fassungsnummer. Nach dem Hochladen einer
neuen Datei lässt sich damit prüfen, ob der Browser wirklich die neue Fassung
zeigt und nicht eine zwischengespeicherte alte.

## Datenschutz in Kürze

Namen stehen nie in einer Adresszeile, nur im POST-Inhalt — sonst lägen sie
in den Zugriffsprotokollen des Hosters. Aufrufe verfallen nach 45 Sekunden,
die Liste „Zuletzt aufgerufen" nach 10 Minuten, Sprachdurchsagen nach
2 Minuten. Die Dateien in `daten/` tragen die Endung `.php` und beginnen mit
`<?php exit;` — ein direkter Abruf gibt nichts preis, auch wenn `.htaccess`
einmal nicht greifen sollte.

**Vor dem Echtbetrieb ist ein Auftragsverarbeitungsvertrag mit IONOS nach
Art. 28 DSGVO nötig.** Siehe ANLEITUNG-WEB.md.
