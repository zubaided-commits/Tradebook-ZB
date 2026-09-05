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

## Ablauf einer Ansage

```
Gong (weicher Zweiklang)  ──2 s──  Name  ──2 s──  Name (Wiederholung)
```

Rufen **beide Sprechzimmer gleichzeitig**, fällt die zweite Ansage der ersten
nicht ins Wort: Sie wird eingereiht und beginnt erst, wenn die erste samt
Wiederholung fertig ist. Das ist nicht nur eine Frage der Höflichkeit — der
Server merkt sich als „aktuell" immer nur *einen* Aufruf, sodass bei zwei
Aufrufen innerhalb derselben zwei Sekunden der erste früher schlicht
verlorenging. Das Gerät liest deshalb die Verlaufsliste statt nur den
aktuellen Aufruf und sagt jeden an, den es noch nicht kennt.

Der Gong ist ein Grundton mit zwei leisen Obertönen, zwei Töne im Abstand
einer Quarte, weich einsetzend und lang ausklingend — freundlich statt
alarmierend, auch beim zwanzigsten Mal am Tag. Er klingt aus, bevor der Name
fällt: Wer im Wartezimmer sitzt, wird erst aufmerksam und hört den Namen dann
von Anfang an. Die Wiederholung setzt zwei Sekunden nach dem **Ende** der
ersten Ansage ein, nicht nach fester Uhr — der Abstand bleibt damit gleich,
ob der Name kurz oder lang ist. Vor einer aufgenommenen Sprachdurchsage
erklingt derselbe Gong. Alle drei Werte stehen in `config.php`
(`gongStaerke`, `gongPauseSekunden`, `wiederholPauseSekunden`).

## Nach einer Unterbrechung

War die Verbindung weg oder der Browser zu, wird der Rückstand **nicht**
nachgeholt. Das ist Absicht: Hört die Praxis nichts, ruft sie denselben
Patienten mehrfach — käme die Verbindung zurück und das Gerät arbeitete alles
ab, folgte eine Kette von Ansagen, die niemanden mehr betrifft und die im
Wartezimmer nur verwirrt.

Stattdessen:

* **Beim Öffnen der Seite** wird gar nichts nachgeholt.
* **Nach einer Unterbrechung** (länger als 15 s ohne Antwort) wird nur der
  *jüngste* Aufruf ausgerufen, und auch der nur, wenn er noch aktuell ist.
  Die Anzeige nennt für eine halbe Minute, wie viele ältere Aufrufe
  übersprungen wurden.
* **Mehrfach derselbe Name** im selben Schwung wird einmal ausgerufen.
* **Staut sich die Schlange**, zählen die neuesten drei; wer beim Drankommen
  älter als zwei Minuten ist, wird übergangen.

Im laufenden Betrieb ändert das nichts — dort wird jeder Aufruf ausgerufen,
auch ein absichtlich wiederholter über „Nochmal".

## Fenster minimiert — wach bleiben

Ist das Browserfenster minimiert oder ganz verdeckt, bremst der Browser nach
wenigen Minuten alle Zeitgeber auf einen Aufruf pro Minute herunter; ein
Patientenaufruf käme dann verspätet oder gar nicht. Wovon der Browser eine
Ausnahme macht: Seiten, die gerade Ton ausgeben. Im Betrieb läuft darum
dauerhaft ein sehr leiser, tiefer Ton in Schleife (`wachton`). Er ist im Raum
nicht zu hören, hält die Seite aber wach — und nebenbei auch einen
Bluetooth-Lautsprecher, der sich sonst nach einigen Minuten Stille abschaltet.

Das Fenster trotzdem sichtbar zu lassen, bleibt der sicherste Weg;
`lautsprecher-starten.bat` legt es dafür klein in eine Bildschirmecke.

## Stimme und Aussprache

Welche Stimmen zur Verfügung stehen, entscheidet allein der Rechner — eine
Webseite kann keine mitbringen oder nachladen. Eine bestimmte Studiostimme
(Mila, Elevenlabs und Ähnliches) lässt sich deshalb nicht einbauen, ohne
jeden Patientennamen zur Vertonung an einen externen Dienst zu senden — das
wäre bei Gesundheitsdaten nach Art. 9 DSGVO ein eigener Auftragsverarbeiter
mit allem, was dazugehört. Die Ansage ist darum auf ruhig, klar und etwas
langsamer als ein Gespräch eingestellt (`tempo` 0.82, `tonhoehe` 1.04).

Unter Windows stehen zwei Arten von Stimmen zur Auswahl, und der Unterschied
ist nicht nur die Tonqualität:

| | Beispiel | Klang | Wo entsteht der Ton? |
|---|---|---|---|
| **örtlich** | Microsoft Katja, Hedda | brauchbar | auf dem Praxis-PC |
| **über Internet** | Microsoft … Online (Natural) | deutlich natürlicher | beim Hersteller |

Von selbst wird **immer eine örtliche Stimme** genommen: Eine Netzstimme
schickt den Namen der Patientin zum Hersteller, und das soll niemand
versehentlich bekommen. Im Auswahlfeld am Lautsprecher sind solche Stimmen
mit „— über Internet" gekennzeichnet und lassen sich bewusst wählen — vor dem
Echtbetrieb gehört dann aber derselbe Auftragsverarbeitungsvertrag dazu wie
beim Hosting.

Mehr örtliche Stimmen lassen sich in Windows nachinstallieren:
*Einstellungen → Zeit und Sprache → Sprache und Region → Deutsch → drei Punkte
→ Sprachoptionen → Sprache (Text-zu-Sprache)*. Nach einem Neustart des
Browsers stehen sie im Auswahlfeld. Auf dem Startbildschirm des
Lautsprechers lässt sich unter den vorhandenen deutschen Stimmen auswählen;
die Auswahl wird sofort vorgehört und auf dem Gerät gemerkt. Auf iPad und
iPhone bringt es hörbar am meisten, unter *Einstellungen → Bedienungshilfen →
Gesprochene Inhalte → Stimmen → Deutsch* eine Stimme in „Premium" zu laden.

Die Ansage wird in **zwei Bögen** gesprochen — erst der Name, dann eine
halbe Sekunde Atempause, dann das Zimmer. Das ist der hörbarste Unterschied
zwischen „abgelesen" und „gesprochen", der sich ohne eine andere Stimme
herausholen lässt: Jeder Bogen bekommt eine eigene Sprachmelodie mit eigenem
Anfang und Ende, statt in einem Zug heruntergelesen zu werden
(`phrasenPauseSekunden`). Die Tonhöhe bleibt bei 1.0 — sie zu verschieben
klingt bei diesen Stimmen blechern, nicht wärmer.

Zahlen werden für die Ansage ausgeschrieben: Aus „Sprechzimmer 1" wird
gesprochen „Sprechzimmer eins". Ohne das liest eine deutsche Stimme die
Ziffer als Ordnungszahl — „Sprechzimmer erste" —, weil dort im Deutschen oft
eine steht („am 1. Mai"). Angezeigt bleibt überall die Ziffer.

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
Fehlermeldung und den auf dem Rechner vorhandenen Stimmen — jede einzeln
aufgeführt und mit dem Vermerk, ob sie auf dem PC läuft oder über das
Internet. Damit lässt sich vor Ort entscheiden, welche Stimme genommen wird.

Unten auf jeder Seite steht die Fassungsnummer. Nach dem Hochladen einer
neuen Datei lässt sich damit prüfen, ob der Browser wirklich die neue Fassung
zeigt und nicht eine zwischengespeicherte alte.

## Nicht auffindbar bleiben

Alles hier wirkt **nur im Ordner des Aufrufsystems**. Die `.htaccess` gilt für
ihren eigenen Ordner und darunter, und `inc.php` wird nur von den Seiten des
Aufrufsystems eingebunden — die Praxis-Website und alle anderen Seiten unter
derselben Domain bleiben unberührt.

* **Sammler werden abgewiesen** — Suchmaschinen, KI-Crawler (GPTBot,
  ClaudeBot, PerplexityBot, CCBot, Bytespider, Amazonbot …) und
  Abgras-Werkzeuge (curl, wget, python-requests, Scrapy) sowie Anfragen ganz
  ohne Browserkennung. Sie bekommen **404**, nicht 403: Ein „verboten" verrät,
  dass hier etwas liegt; ein „nicht gefunden" sieht aus wie eine leere
  Adresse. Die Sperre steht doppelt — in `.htaccess` und in `inc.php` —, damit
  sie auch dann greift, wenn eine der beiden einmal nicht wirkt.
* **`X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex`** auf
  jeder Seite.
* **`Referrer-Policy: no-referrer`** — klickt jemand von hier weg, erfährt die
  andere Seite die Adresse nicht.

### Nicht in die robots.txt eintragen

Es liegt nahe, `Disallow: /praxisruf/` in die `robots.txt` der Domain zu
schreiben. **Das wäre das Gegenteil von hilfreich.** Die `robots.txt` liegt
öffentlich unter `ihre-domain.de/robots.txt` und kann von jedem gelesen
werden — man würde die Adresse dort also selbst veröffentlichen. Der
`X-Robots-Tag` oben erledigt dasselbe, ohne sie zu verraten.

### Der wirksamste Schritt: unratbarer Ordnername

`/praxisruf/` errät ein Scanner in Sekunden. Ein Name wie `/pr-7k2m9x4q/`
nicht. So geht es:

1. Ordner auf dem Webspace umbenennen (im IONOS-Dateimanager oder per FTP).
2. In `lautsprecher-starten.bat` die Zeile `set ADRESSE=` anpassen.
3. Neues Lesezeichen in den Sprechzimmern anlegen, altes löschen.
4. Alle Geräte melden sich einmal neu an.

Zusammen mit der Sammlersperre ist die Seite damit praktisch nicht mehr
auffindbar: Sie steht in keiner Suchmaschine, wird von keinem Crawler
gelesen, ist von nirgendwo verlinkt, und die Adresse lässt sich nicht raten.

### Am stärksten: nur aus der Praxis erreichbar

Hat der Praxisanschluss eine **feste** IP-Adresse, lässt sich in der
`.htaccess` (Abschnitt 6, auskommentiert vorbereitet) festlegen, dass nur
diese Adresse überhaupt hereinkommt — dann erreicht von außen niemand auch
nur die Anmeldeseite. Vorher beim Anbieter fragen, ob die Adresse fest ist:
Wechselt sie, sperrt man sich selbst aus.

## Sicherheit

Zugang gibt es ausschließlich über das gemeinsame Praxis-Passwort. Es gibt
keine zweite Tür: keine Benutzerliste, keinen Wiederherstellungslink, keine
Fernwartung.

| | |
|---|---|
| **Anmeldung** | Selbst signiertes Cookie (HMAC-SHA256, Schlüssel ist das Passwort). Rolle und Ablauf stehen im Cookie und lassen sich nicht ändern, ohne die Signatur zu brechen. `HttpOnly` (kein Zugriff per JavaScript), `Secure`, `SameSite=Lax`. |
| **Passwort ändern** | Macht sofort **alle** bestehenden Anmeldungen ungültig — der Weg bei Personalwechsel oder Verdacht. |
| **Durchprobieren** | Zwei Stufen: 8 Fehlversuche je Absender (10 min gesperrt) **und** 30 Fehlversuche insgesamt (5 min gesperrt). Die zweite Stufe wirkt auch dann, wenn der Absender nicht zuverlässig erkennbar ist. |
| **Geheime Dateien** | `config.php`, `inc.php` und `daten/` sind per `.htaccess` gesperrt. Zusätzlich tragen die Dateien in `daten/` die Endung `.php` und beginnen mit `<?php exit;` — ein direkter Abruf gibt auch dann nichts preis, wenn `.htaccess` einmal nicht greift. |
| **Kopfzeilen** | HSTS, Content-Security-Policy, `X-Frame-Options: DENY`, `nosniff`, `no-referrer`, `noindex` — gesetzt **in PHP**, nicht nur in `.htaccess`, damit sie auch dann stehen, wenn dort einmal eine Regel nicht greift. |
| **Datensparsamkeit** | Ein Gerät im Wartezimmer bekommt nur die Aufrufe für seinen eigenen Raum — nicht die Namen aus dem anderen Wartezimmer und nicht, welche Sprechzimmer offen sind. |
| **Namen** | Stehen nie in einer Adresszeile, nur im POST-Inhalt — sonst lägen sie in den Zugriffsprotokollen des Hosters. Angemeldete Seiten sind `no-store`, landen also in keinem Zwischenspeicher. |

Geprüft mit `angriff.js`: 36 Angriffsversuche — Zugriff ohne Anmeldung,
gefälschte und manipulierte Cookies, Passwort-Durchprobieren mit wechselnder
Absenderadresse, Pfad-Ausbruch über die Ton-Schnittstelle, eingeschleustes
HTML in Patientennamen, direkter Abruf der Konfiguration.

**Was das nicht abdeckt:** Wer physisch am Empfangs-PC sitzt, ist angemeldet —
der Bildschirm gehört gesperrt, wenn niemand da ist. Und das Passwort ist nur
so gut, wie es gewählt wurde; ist es zu kurz oder zu leicht zu erraten, weist
die Bedienseite nach der Anmeldung darauf hin.

## Datenschutz in Kürze

Namen stehen nie in einer Adresszeile, nur im POST-Inhalt — sonst lägen sie
in den Zugriffsprotokollen des Hosters. Aufrufe verfallen nach 45 Sekunden,
die Liste „Zuletzt aufgerufen" nach 10 Minuten, Sprachdurchsagen nach
2 Minuten. Die Dateien in `daten/` tragen die Endung `.php` und beginnen mit
`<?php exit;` — ein direkter Abruf gibt nichts preis, auch wenn `.htaccess`
einmal nicht greifen sollte.

**Vor dem Echtbetrieb ist ein Auftragsverarbeitungsvertrag mit IONOS nach
Art. 28 DSGVO nötig.** Siehe ANLEITUNG-WEB.md.
