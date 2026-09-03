# Praxis-Ruf über das Internet — Einrichtung auf IONOS

Diese Fassung läuft **auf Ihrem IONOS-Webhosting**, nicht auf einem Rechner in
der Praxis. Auf dem Praxis-Server wird nichts installiert.

Beide Ärztinnen öffnen dieselbe Adresse und sehen **denselben Stand in
Echtzeit** — wer zuletzt aufgerufen wurde, aus welchem Sprechzimmer, und ob
im Wartezimmer überhaupt ein Lautsprecher verbunden ist.

Im Wartezimmer wird **kein Bildschirm** gebraucht. Nur Ton.

---

## Zuerst: was Sie im Wartezimmer wirklich brauchen

Ein Lautsprecher allein genügt leider nicht. Ein Lautsprecher kann sich nicht
selbst mit dem Internet verbinden — es braucht ein Gerät, das die Seite
geöffnet hält und den Ton ausgibt.

**Ohne zusätzliche Anschaffung: den vorhandenen Empfangs-PC benutzen.**
Läuft in Ihrer Praxis am Empfang bereits ein PC, ist das der einfachste Weg —
kein Mini-PC, kein Tablet, keine neue Hardware nötig. Nur ein
Bluetooth-Lautsprecher zum Anschließen an die Steckdose.

* **Lautsprecher koppeln.** Windows: Einstellungen → Bluetooth & Geräte →
  Gerät hinzufügen → Lautsprecher in den Kopplungsmodus versetzen (meist
  Ein/Aus-Taste gedrückt halten) → verbinden. Danach unter Sound-Einstellungen
  als **Standard-Wiedergabegerät** festlegen.
* **`lautsprecher-starten.bat`** (liegt in diesem Ordner) öffnet die Seite in
  einem eigenen kleinen Fenster statt in einem gewöhnlichen Browser-Tab —
  wichtig, siehe unten. Adresse in der Datei einmal eintragen, dann per
  Verknüpfung in den Autostart-Ordner legen (`Win + R` → `shell:startup`).
* **Ruhezustand für diesen PC ausschalten** (Energieoptionen), zumindest
  während der Öffnungszeiten — sonst reißt die Verbindung ab, sobald Windows
  einschläft.
* **Das Fenster sichtbar lassen — nicht minimieren.** Läuft die Seite als
  Tab oder Fenster im Hintergrund, drosselt Chrome das Nachfragen nach
  einigen Minuten spürbar aus, um Akku/Leistung zu sparen (das betrifft
  jeden PC, nicht nur Laptops). Deshalb startet die `.bat`-Datei ein
  kleines **eigenes Fenster**, keinen Tab — das in eine Ecke des
  Bildschirms schieben und dort stehen lassen reicht; es muss nicht im
  Vordergrund sein, nur sichtbar (nicht verdeckt, nicht minimiert).
  Gerät jemand versehentlich ins Minimieren, holt die Seite beim
  Zurückholen sofort den aktuellen Stand nach — aber besser, es kommt gar
  nicht erst so weit.
* **Eingebautes Sicherheitsnetz:** Meldet sich der Empfangs-PC 30 Sekunden
  lang nicht, springt bei beiden Ärztinnen die Lampe für dieses Wartezimmer
  von Grün auf Gelb — sie sehen also sofort, wenn dort etwas nicht stimmt.

**Falls kein PC in Wartezimmernähe steht**, zwei Alternativen mit eigener,
kleiner Anschaffung:

* **Mini-PC oder Raspberry Pi** (ca. 60–150 €) — läuft am zuverlässigsten
  durch, da vollständig eigenständig.
* **Altes Tablet am Ladekabel** — kostenlos, wenn eines übrig ist, aber
  anfälliger: sperrt sich das Tablet oder wechselt die App, bremst Android
  die Seite genauso aus wie oben beschrieben, oft stärker.

### Welcher Lautsprecher?

Aufstellung ist wichtiger als der Preis: mittig im Raum, in Ohrhöhe, nicht in
einer Ecke und nicht hinter einer Pflanze.

**Ein gewöhnlicher Bluetooth-Lautsprecher genügt** — kein bestimmtes Modell
nötig, auch ein normaler Musik-Lautsprecher (JBL o. ä.) funktioniert. Wichtig
ist nur: **Strom aus der Steckdose**, nicht Akkubetrieb. Ohne Akku entfällt
das automatische Abschalten, das tragbare Lautsprecher zum Batteriesparen
eingebaut haben.

Eine Kleinigkeit bleibt trotzdem: Manche Bluetooth-Verstärker legen die
**Funkstrecke selbst** nach einigen Minuten Stille schlafen (unabhängig vom
Stromanschluss) — dann fehlt beim Aufwachen die erste Silbe. Dagegen läuft
der eingebaute **Weckton**: alle 60 Sekunden ein sehr leiser, tiefer Ton
(60 Hz), im Raum nicht hörbar, hält aber die Funkstrecke wach.

```php
'wachton'         => true,
'wachtonSekunden' => 60,
'wachtonHertz'    => 60,
'wachtonStaerke'  => 0.02,
```

Bleibt es trotzdem einmal stumm, `'wachtonSekunden' => 30` setzen. Ist der
Weckton im ruhigen Wartezimmer leise hörbar, `0.01` versuchen. Hängt der
Lautsprecher stattdessen am Kabel (Kopfhörerausgang des PCs), kann
`'wachton' => false` gesetzt werden — dann wird nichts gebraucht.

**Bitte einmal bewusst testen:** Aufruf auslösen, 30 Minuten nichts tun,
erneut aufrufen. Kommt der zweite Aufruf sauber und vollständig an, passt
der Lautsprecher.

**Nicht geeignet: reine WLAN-Lautsprecher** (Sonos, Chromecast built-in,
AirPlay, JBL Authentics und ähnliche). Eine Internetseite kann von sich aus
keine Übertragung dorthin starten — das verlangt jedes Mal einen Handgriff.
Solche Geräte würden die Aufrufe schlicht nicht abspielen.

---

## Schritt 1 — Dateien hochladen

Den gesamten Ordner `praxis-ruf-web` per FTP in Ihr IONOS-Webhosting laden,
zum Beispiel nach `/praxisruf/`. Die Seite ist dann unter
`https://ihre-domain.de/praxisruf/` erreichbar.

Voraussetzung: **PHP 8.0 oder neuer**. In jedem IONOS-Webhosting-Paket
enthalten, im Kundenmenü unter „PHP-Einstellungen" prüfbar.

---

## Schritt 2 — Konfiguration anlegen

`config.beispiel.php` kopieren und in **`config.php`** umbenennen. Darin
anpassen:

```php
'praxis'   => 'Hausarztpraxis Dr. med. …',
'passwort' => 'ein-langes-eigenes-Passwort',
```

Das Passwort ist der einzige Zugangsschutz. Bitte mindestens 12 Zeichen, und
nicht dasselbe wie für andere Dienste. Beide Ärztinnen und das
Wartezimmer-Gerät benutzen dasselbe Passwort.

---

## Schritt 3 — Ordner `daten` beschreibbar machen

Im Ordner `praxis-ruf-web` muss der Unterordner `daten` existieren und
beschreibbar sein (Rechte `755`, notfalls `775`). Legt PHP ihn nicht selbst
an, per FTP anlegen.

Darin merkt sich das Programm den aktuellen Aufruf. Die Dateien tragen die
Endung `.php` und beginnen mit einer Sperrzeile — selbst wenn jemand sie
direkt aufruft, bekommt er nichts zu sehen.

---

## Schritt 4 — HTTPS einschalten

Im IONOS-Kundenmenü das kostenlose SSL-Zertifikat für die Domain aktivieren.

Das ist **nicht optional**:

* Ohne HTTPS gibt der Browser das Mikrofon für die Durchsage nicht frei.
* Ohne HTTPS liefen Patientennamen unverschlüsselt durchs Netz.

Die mitgelieferte `.htaccess` leitet `http://` automatisch auf `https://` um.

---

## Schritt 5 — Sprechzimmer einrichten

Jede Ärztin öffnet auf ihrem PC:

```
https://ihre-domain.de/praxisruf/
```

Passwort eingeben, **„Sprechzimmer"** auswählen, anmelden. Danach oben rechts
das eigene Sprechzimmer wählen — das merkt sich der Browser.

Als Lesezeichen speichern.

---

## Schritt 6 — Wartezimmer-Gerät einrichten

**Auf dem Empfangs-PC (empfohlener Weg):**

1. `lautsprecher-starten.bat` öffnen (Rechtsklick → Bearbeiten) und in der
   Zeile `set ADRESSE=` die eigene Domain eintragen, z. B.
   `https://ihre-domain.de/praxisruf/lautsprecher.php`. Speichern.
2. Doppelklick auf die Datei — ein kleines eigenes Fenster öffnet sich.
3. Passwort eingeben, **„Lautsprecher"** auswählen, anmelden.
4. Wartezimmer wählen, auf **„Ton freigeben und starten"** tippen. Zur
   Bestätigung ertönt sofort ein Gong.
5. Fenster in eine Bildschirmecke schieben und dort **sichtbar** stehen
   lassen (nicht minimieren — siehe Hinweis weiter oben).
6. Eine Verknüpfung zu `lautsprecher-starten.bat` in den Autostart-Ordner
   legen (`Win + R` → `shell:startup`). Nach einem Neustart öffnet sich das
   Fenster von selbst; Schritt 3–4 sind dann nur noch einmal pro Neustart
   nötig (die Anmeldung selbst bleibt 30 Tage gespeichert).

**Auf einem Mini-PC, Raspberry Pi oder Tablet (Alternative):**

Dieselbe Adresse öffnen, „Lautsprecher" wählen, Wartezimmer wählen, „Ton
freigeben und starten" tippen. Danach zeigt die Seite nur noch einen grünen
Punkt — der Bildschirm darf dunkel werden.

Kiosk-Modus, empfohlen für ein dediziertes Gerät:

```
chrome --kiosk --autoplay-policy=no-user-gesture-required "https://ihre-domain.de/praxisruf/lautsprecher.php"
```

Mit `--autoplay-policy=no-user-gesture-required` entfällt sogar der Tipp, das
Gerät ist nach einem Stromausfall von allein wieder betriebsbereit.

---

## Bedienung

**Aufrufen.** Anrede wählen, Namen tippen, auf **Wartezimmer 1** oder
**Wartezimmer 2** tippen. Im Wartezimmer ertönt ein Gong, dann die Ansage:
*„Frau Müller, bitte in Sprechzimmer 2."* — standardmäßig zweimal.

Der Punkt auf jeder Taste zeigt, ob dort ein Lautsprecher verbunden ist.
Grau/gelb heißt: der Aufruf wird nicht gehört.

**Beide Sprechzimmer sehen dasselbe.** Unter „Zuletzt aufgerufen" stehen die
Aufrufe **beider** Ärztinnen, mit Uhrzeit und Sprechzimmer. Daneben steht,
ob die Kollegin die Seite gerade offen hat.

**Nochmal rufen.** Neben jedem Namen die Taste **Nochmal**.

**Durchsage mit eigener Stimme.** Ziel wählen, die runde Taste gedrückt
halten, sprechen, loslassen. Für Sätze wie *„Der Praxisbetrieb verzögert
sich um etwa 20 Minuten."*

---

## Was sich gegenüber der Fassung im Praxisnetz ändert

| | im Praxisnetz | über IONOS |
|---|---|---|
| Installation in der Praxis | zwei Sprechzimmer-PCs | keine |
| Wartezimmer | Bildschirm + Ton | nur Ton |
| Gemeinsame Sicht beider Ärztinnen | nein | ja |
| Namen verlassen die Praxis | nein | **ja** |
| Bei Internetausfall | funktioniert weiter | **keine Aufrufe** |

Die letzten beiden Zeilen sind der Preis für die einfache Einrichtung.
Fällt das Internet oder das Praxis-WLAN aus, kommt kein Aufruf mehr im
Wartezimmer an. Das sollte im Praxisalltag eingeplant sein.

---

## Datenschutz — bitte vor dem Echtbetrieb lesen

Patientennamen sind Gesundheitsdaten nach Art. 9 DSGVO. Sobald sie auf einem
Server bei IONOS verarbeitet werden, ist IONOS **Auftragsverarbeiter**.

Was dafür nötig ist:

1. **Auftragsverarbeitungsvertrag (AVV) mit IONOS** nach Art. 28 DSGVO.
   IONOS stellt ihn im Kundenmenü bereit; er muss abgeschlossen werden.
2. **Serverstandort Deutschland oder EU** — im IONOS-Vertrag prüfbar.
3. **Eintrag im Verzeichnis von Verarbeitungstätigkeiten** ergänzen.
4. **Passwort** sorgfältig wählen und nicht weitergeben. Es ist der einzige
   Schutz davor, dass Fremde Aufrufe auslösen oder Namen mitlesen.
5. Bei Personalwechsel Passwort ändern.

Was das Programm selbst dafür tut:

* Namen stehen **nie in einer Adresszeile**, nur im Inhalt der Anfrage —
  sonst stünden sie in den Zugriffsprotokollen von IONOS.
* Ein Aufruf wird nach `anzeigeDauerSekunden` (45) automatisch gelöscht.
* Die Liste „Zuletzt aufgerufen" verfällt nach `verlaufDauerMinuten` (10).
* Sprachdurchsagen werden nach 2 Minuten gelöscht.
* Es gibt keine dauerhafte Liste, keine Datenbank, keinen Verlauf über den
  Tag hinaus.
* Suchmaschinen sind über `robots.txt` und `X-Robots-Tag` ausgeschlossen.

Wenn Sie ganz ohne Namen auskommen möchten, lässt sich in `config.php`
`'nurNachname' => true` setzen — dann wird nur der Nachname gesagt.

---

## Wenn etwas nicht funktioniert

| Symptom | Ursache |
|---|---|
| „config.php fehlt" | Schritt 2 — Datei kopieren und umbenennen |
| „daten/stand.php ist nicht beschreibbar" | Schritt 3 — Rechte auf 755/775 setzen |
| Punkt am Wartezimmer bleibt grau | Gerät im Wartezimmer nicht angemeldet oder Seite geschlossen |
| Sprechtaste ist grau | Seite über `http://` statt `https://` geöffnet |
| Kein Ton im Wartezimmer | „Ton freigeben" wurde nicht getippt, oder Lautstärke auf null |
| Ansage bleibt nach einiger Zeit aus | Tablet gesperrt oder App gewechselt — Seite muss offen bleiben |
| Aufrufe kommen am Empfangs-PC verspätet an | Fenster war minimiert oder ganz verdeckt — sichtbar in eine Ecke stellen |
| Empfangs-PC verliert nachts die Verbindung | Ruhezustand für diesen PC in den Energieoptionen ausschalten |
| Stimme klingt abgehackt | in `config.php` `'tempo' => 0.82` setzen |
| „Anmeldung abgelaufen" | Seite neu laden und erneut anmelden |
