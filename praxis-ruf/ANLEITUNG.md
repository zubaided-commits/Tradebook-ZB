# Praxis-Ruf — Einrichtung auf zwei Sprechzimmer-PCs

Patientenaufruf über Lautsprecher im Wartezimmer. Die Ärztin tippt den Namen
und ruft — ohne aufzustehen. Zusätzlich Durchsagen mit der eigenen Stimme.

**Auf dem Praxis-Server wird nichts installiert.** Jedes Sprechzimmer bekommt
seine eigene, eigenständige Kopie. Die beiden Kopien wissen nichts voneinander —
wer schon dran war, sehen Sie ohnehin in Doctolib.

**Es wird nichts gespeichert.** Keine Namen, keine Liste, kein Protokoll auf der
Festplatte. Alles steht nur im Arbeitsspeicher und ist nach dem Beenden weg.

---

## Was Sie brauchen

* Die beiden vorhandenen Sprechzimmer-PCs
* Zwei Bildschirme oder Tablets mit Lautsprecher — einer je Wartezimmer
* Alle Geräte im selben Praxisnetz
* Node.js (kostenlos, von nodejs.org, LTS-Version) auf beiden Sprechzimmer-PCs

Ein einfacher aktiver Lautsprecher genügt. Wichtiger als der Preis ist die
Aufstellung: mittig im Raum, nicht in einer Ecke.

---

## Schritt 1 — Feste IP-Adressen vergeben

Beide Sprechzimmer-PCs brauchen eine Adresse, die sich nicht ändert.
Im Router einstellen (FRITZ!Box: Heimnetz → Netzwerk → Gerätedetails →
„Immer die gleiche IPv4-Adresse zuweisen").

Notieren Sie beide Adressen, zum Beispiel:

```
Sprechzimmer 1 →  192.168.178.41
Sprechzimmer 2 →  192.168.178.42
```

Ohne diesen Schritt finden die Wartezimmer-Bildschirme die PCs nach einem
Neustart nicht mehr wieder.

---

## Schritt 2 — Ordner auf beide PCs kopieren

Den Ordner `praxis-ruf` auf jeden der beiden PCs kopieren, z. B. nach
`C:\praxis-ruf`.

---

## Schritt 3 — `config.json` anpassen

Im Ordner liegt die Vorlage `config.beispiel.json`. Einmal je PC kopieren und
in `config.json` umbenennen — darin stehen Praxisname und die internen
Adressen, deshalb gehoert sie nicht ins Repository.

Nur **zwei Zeilen** unterscheiden sich zwischen den beiden PCs.

**Auf dem PC von Sprechzimmer 1:**

```json
"sprechzimmer": { "name": "Sprechzimmer 1", "kurz": "1" },
"andereSprechzimmer": ["192.168.178.42"]
```

**Auf dem PC von Sprechzimmer 2:**

```json
"sprechzimmer": { "name": "Sprechzimmer 2", "kurz": "2" },
"andereSprechzimmer": ["192.168.178.41"]
```

Also jeweils die Adresse des *anderen* PCs. Alles Übrige bleibt auf beiden
gleich.

---

## Schritt 4 — Starten

Auf jedem PC `start-windows.bat` doppelklicken. Im Fenster stehen dann die
fertigen Adressen, etwa:

```
Für die Ärztin an DIESEM PC:
    http://localhost:8080/praxis.html

Für die Wartezimmer-Bildschirme:
    Wartezimmer 1
    http://192.168.178.41:8080/wartezimmer.html?raum=wz1&quellen=192.168.178.41:8080,192.168.178.42:8080
```

Windows fragt beim ersten Start nach der Firewall. **Nur „Privates Netzwerk"
zulassen**, niemals „Öffentliches Netzwerk".

---

## Schritt 5 — Ärztin-Seite einrichten

Auf jedem Sprechzimmer-PC im Browser öffnen:

```
http://localhost:8080/praxis.html
```

Als Lesezeichen speichern oder als Verknüpfung auf den Desktop legen.

**Wichtig: unbedingt `localhost` verwenden, nicht die IP-Adresse.** Nur dann gibt
der Browser das Mikrofon für Durchsagen frei — ohne Zertifikate, ohne
Sicherheitswarnung. Über die IP-Adresse funktionieren die Namensaufrufe zwar
auch, die Sprechtaste bleibt aber grau.

---

## Schritt 6 — Wartezimmer-Bildschirme einrichten

Am Bildschirm im Wartezimmer die passende Adresse aus Schritt 4 öffnen und
**„Anzeige starten"** antippen. Der eine Tipp ist nötig, damit der Browser Ton
abspielen darf. Danach läuft alles allein: Vollbild, Bildschirm bleibt wach.

Der Teil `&quellen=192.168.178.41:8080,192.168.178.42:8080` ist entscheidend —
dadurch hört der Bildschirm **beiden** Sprechzimmern zu. Fehlt er, hört er nur
den PC, von dem die Seite geladen wurde.

Der Punkt links oben zeigt den Zustand:

| Punkt | Bedeutung |
|---|---|
| grün | beide Sprechzimmer verbunden |
| gelb | nur eines verbunden — das andere Programm läuft nicht |
| rot | keine Verbindung |

**Kiosk-Modus (empfohlen), Windows.** Verknüpfung im Autostart-Ordner
(`Win + R` → `shell:startup`):

```
chrome.exe --kiosk --autoplay-policy=no-user-gesture-required "http://192.168.178.41:8080/wartezimmer.html?raum=wz1&quellen=192.168.178.41:8080,192.168.178.42:8080"
```

**Android-Tablet:** Chrome öffnen → Menü → „Zum Startbildschirm hinzufügen".
Display-Timeout auf „Nie", Tablet am Ladekabel lassen.

---

## Bedienung

**Aufrufen.** Anrede wählen, Namen tippen, auf **Wartezimmer 1** oder
**Wartezimmer 2** tippen. Im Wartezimmer ertönt ein Gong, der Name erscheint
gross auf dem Bildschirm und wird zweimal angesagt:
*„Frau Müller, bitte in Sprechzimmer 2."*

Der kleine Punkt auf jeder Taste zeigt, ob dort überhaupt ein Bildschirm
verbunden ist. Ist keiner da, meldet das Programm das nach dem Aufruf.

**Nochmal rufen.** Unter „Zuletzt aufgerufen" steht neben jedem Namen eine
Taste **Nochmal** — praktisch, wenn jemand nicht kommt. Diese Liste steht nur
im Arbeitsspeicher und ist beim Neuladen der Seite wieder leer.

**Durchsage mit eigener Stimme.** Ziel wählen, die runde Taste **gedrückt
halten**, sprechen, loslassen. Die Aufnahme wird sofort über die Lautsprecher
abgespielt. Für Sätze wie: *„Der Praxisbetrieb verzögert sich um etwa
20 Minuten."*

---

## Autostart ohne schwarzes Fenster

Damit niemand das Konsolenfenster versehentlich schliesst:

1. Rechtsklick auf `praxis-ruf-unsichtbar.vbs` → Verknüpfung erstellen
2. Die Verknüpfung in den Autostart-Ordner ziehen (`Win + R` → `shell:startup`)

Das Programm startet dann unsichtbar mit Windows. Beenden über den
Task-Manager, Prozess „Node.js".

Zusätzlich in den Energieoptionen den **Ruhezustand ausschalten** — Bildschirm
aus ist in Ordnung, der PC selbst darf nicht schlafen.

---

## Einstellungen — `config.json`

```jsonc
"nurNachname": false          // true = nur der Nachname wird gezeigt und gesagt
"wiederholen": true           // Ansage zweimal
"gong": true
"anzeigeDauerSekunden": 45    // wie lange der Name stehen bleibt
"stimme": { "tempo": 0.88 }   // niedriger = deutlicher, 0.80–1.00 sinnvoll
```

Nach jeder Änderung das Programm neu starten.

**Sprachqualität.** Die Ansage nutzt die im Browser vorhandene deutsche Stimme.
Am besten klingt Chrome unter Windows („Google Deutsch", „Microsoft Katja").
Testen Sie einmal aus fünf Metern Entfernung und senken Sie bei Bedarf das
Tempo auf `0.82`.

---

## Datenschutz

* Läuft ausschliesslich im Praxisnetz, keine Cloud, kein externer Dienst.
* Nichts wird auf der Festplatte gespeichert — weder Namen noch Aufrufe.
* Sprachdurchsagen liegen höchstens zwei Minuten im Arbeitsspeicher.
* Kein Zugriff auf CGM, Doctolib oder Patientendaten. Das Programm kennt nur
  die Namen, die gerade eingetippt werden.
* Keine Portfreigabe im Router einrichten — das System darf nicht aus dem
  Internet erreichbar sein.
* Der Aufruf mit vollem Namen ist übliche Praxis, aber nicht unumstritten.
  `"nurNachname": true` reduziert das.
* Ein kurzer Eintrag im Verzeichnis von Verarbeitungstätigkeiten ist sinnvoll.

---

## Wenn etwas nicht funktioniert

| Symptom | Ursache |
|---|---|
| Punkt am Bildschirm ist gelb | Ein Sprechzimmer-Programm läuft nicht — dort `start-windows.bat` starten |
| Punkt ist rot | Kein Programm läuft, oder falsche IP-Adresse in der Adresszeile |
| Sprechtaste ist grau | Seite wurde über die IP geöffnet statt über `http://localhost:8080` |
| Aufruf kommt nur in einem Wartezimmer an | `&quellen=…` fehlt in der Adresse des Bildschirms |
| Name erscheint, aber kein Ton | „Anzeige starten" wurde nicht angetippt, oder Lautstärke auf null |
| Stimme klingt abgehackt | `"tempo"` auf `0.82` senken |
| Nach PC-Neustart geht nichts mehr | Feste IP fehlt, oder Autostart nicht eingerichtet |

Wenn ein Sprechzimmer-PC aus ist, funktioniert das andere Sprechzimmer
weiterhin ganz normal. Das ist der Vorteil der getrennten Installation.
