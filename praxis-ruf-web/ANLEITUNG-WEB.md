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
selbst mit dem Internet verbinden — es braucht ein kleines Gerät, das die
Seite geöffnet hält und den Ton ausgibt.

Zwei Wege, beide bewährt:

**A — Am zuverlässigsten: kleiner Mini-PC oder Raspberry Pi**
Einmal einrichten, dann läuft er durch. Ein Bildschirm wird nur zum Einrichten
gebraucht und kann danach abgezogen werden.

* Mini-PC oder Raspberry Pi (ca. 60–150 €)
* Aktivlautsprecher am Kopfhörerausgang oder per USB
* Chrome im Kiosk-Modus mit der Adresse `.../lautsprecher.php`

**B — Am günstigsten: altes Tablet oder Smartphone**
Ein ausgedientes Android-Tablet reicht völlig.

* Tablet am **Ladekabel lassen**, Display-Timeout auf „Nie"
* Lautsprecher per Klinkenkabel oder Bluetooth
* Tablet flach hinlegen oder in eine Schublade — der Bildschirm darf dunkel
  sein, die Seite muss aber **im Vordergrund geöffnet bleiben**. Wird das
  Tablet gesperrt oder die App gewechselt, bremst Android die Seite aus und
  Aufrufe können ausbleiben.

### Welcher Lautsprecher?

Aufstellung ist wichtiger als der Preis: mittig im Raum, in Ohrhöhe, nicht in
einer Ecke und nicht hinter einer Pflanze.

**Am Kabel (empfohlen).** Ein Aktivlautsprecher am Kopfhörerausgang des
Geräts. Nichts schläft ein, nichts muss gekoppelt werden, die ersten Silben
werden nie abgeschnitten. In `config.php` dann `'wachton' => false`.

**Per Bluetooth (JBL & ähnliche).** Funktioniert, aber mit zwei Tücken:

* Die meisten tragbaren Funklautsprecher **schalten sich nach 10–20 Minuten
  Stille selbst ab**. Im Praxisalltag sind die Pausen zwischen zwei Aufrufen
  oft länger — der nächste Patient würde dann ins Leere gerufen.
* Nach einer Pause braucht die Funkstrecke eine knappe Sekunde, bis Ton
  kommt. Die ersten Silben fehlen.

Beides fängt der **Weckton** ab: alle 60 Sekunden ein sehr leiser, tiefer Ton
(60 Hz). Im Raum ist er nicht zu hören, der Lautsprecher sieht aber ein
Signal und bleibt wach. Voreingestellt ist er an:

```php
'wachton'         => true,
'wachtonSekunden' => 60,
'wachtonHertz'    => 60,
'wachtonStaerke'  => 0.02,
```

Schaltet sich Ihr Lautsprecher trotzdem ab, `'wachtonSekunden' => 30` und
notfalls `'wachtonStaerke' => 0.05` setzen. Ist der Weckton im ruhigen
Wartezimmer leise hörbar, `0.01` versuchen.

**Bitte testen Sie das einmal bewusst:** Aufruf auslösen, dann 30 Minuten
nichts tun, dann erneut aufrufen. Kommt der zweite Aufruf sauber und
vollständig, ist der Lautsprecher geeignet.

Weitere Hinweise zu Bluetooth: Lautsprecher **am Ladekabel lassen**, im
Gerät als Standard-Wiedergabegerät festlegen, und die Lautstärke am
Lautsprecher selbst auf etwa drei Viertel stellen.

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

Auf dem Gerät im Wartezimmer dieselbe Adresse öffnen, Passwort eingeben und
diesmal **„Lautsprecher"** auswählen. Dann das Wartezimmer wählen und auf
**„Ton freigeben und starten"** tippen.

Der eine Tipp ist nötig, damit der Browser Ton abspielen darf. Zur Bestätigung
ertönt sofort ein Gong — hören Sie ihn, ist alles richtig.

Danach zeigt die Seite nur noch einen grünen Punkt. Der Bildschirm darf
dunkel werden.

**Kiosk-Modus (Mini-PC oder Raspberry Pi), empfohlen:**

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
| Stimme klingt abgehackt | in `config.php` `'tempo' => 0.82` setzen |
| „Anmeldung abgelaufen" | Seite neu laden und erneut anmelden |
