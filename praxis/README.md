# Praxis-Kalender · Hausarztpraxis Dr. med. Victoria Yar

Abwesenheiten und Urlaubskonto für die Hausarztpraxis Dr. med. Victoria Yar, Hamburg Barmbek: **Urlaub, Krank (AU), Kind krank,
Fortbildung, Abwesend** – plus Sonderurlaub, Überstundenabbau, Berufsschule,
Mutterschutz/Elternzeit und Praxisschließung.

Läuft auf einem normalen IONOS Webspace: **PHP 7.4+**, keine Fremddienste,
alle Daten bleiben auf dem eigenen Server.

---

## 1. Installation auf IONOS (ca. 10 Minuten)

1. **Dateien hochladen** – per FTP (z. B. FileZilla) oder über den IONOS File Manager
   den ganzen Ordner `praxis/` in den Webspace legen, z. B. nach
   `/kalender/`. Danach ist die Anwendung unter
   `https://ihre-domain.de/kalender/` erreichbar.

2. **Konfiguration anlegen** – `config.example.php` in `config.php` umbenennen.
   Für den Start genügt die Voreinstellung (`'driver' => 'sqlite'`) – es wird
   automatisch eine Datenbankdatei unter `data/praxis.sqlite` angelegt.

   *Mit MySQL* (empfohlen, wenn im IONOS-Control-Panel eine Datenbank vorhanden ist):
   ```php
   'driver' => 'mysql',
   'host'   => 'db1234567890.hosting-data.io',   // aus IONOS > Hosting > Datenbanken
   'name'   => 'dbs1234567',
   'user'   => 'dbu1234567',
   'pass'   => 'Ihr-Datenbank-Passwort',
   ```
   Die Tabellen legt die Anwendung beim ersten Aufruf selbst an.

3. **Schreibrechte** – der Ordner `data/` muss vom Webserver beschreibbar sein
   (Rechte 755 oder 775). Das gilt für die SQLite-Datenbank **und** für die
   hochgeladenen Nachweise unter `data/nachweise` (wird automatisch angelegt).

   Die mitgelieferte `.user.ini` erlaubt Uploads bis 12 MB. Falls IONOS kleinere
   Werte erzwingt, zeigt die Anwendung im Upload-Feld die tatsächliche Grenze an.

4. **SSL einschalten** – im IONOS-Control-Panel das kostenlose SSL-Zertifikat für die
   Domain aktivieren. Die mitgelieferte `.htaccess` leitet HTTP automatisch auf HTTPS um.

5. **Erste Einrichtung** – die Seite im Browser öffnen. Beim ersten Aufruf werden
   Praxisname, Bundesland (Hamburg ist voreingestellt) und das erste Benutzerkonto abgefragt.
   Passwort: mindestens 10 Zeichen.

6. **PHP-Version prüfen** – IONOS > Hosting > PHP-Einstellungen: PHP 8.1 oder neuer wählen.

---

## 2. Bedienung

### Für die Praxisleitung – fünf Reiter

| Reiter | Inhalt |
|---|---|
| **Kalender** | Oben der Heute-Streifen (wer ist da, wer fehlt) und offene Anträge. Darunter der Plan, umschaltbar zwischen **Monat** und **Ganzes Jahr**. Auf ein Feld tippen legt einen Eintrag an. |
| **Urlaub** | Urlaubskonten aller: Anspruch, Übertrag, genommen, geplant, Rest – dazu Krank-, Kind-krank- und Fortbildungstage. |
| **Auswertung** | Abwesenheiten und Fehlzeiten: **bis heute**, ganzes Jahr, ein Monat oder ein **eigener Zeitraum**. Ganz oben steht je Person „abwesend gesamt", darunter die Aufschlüsselung. Mit CSV-Export. |
| **Team** | Personen anlegen und ändern; darunter die **Zugänge** zum Anmelden. |
| **Einstellungen** | Praxisname, Bundesland, eigenes Passwort, CSV-Export. |

Der grüne Knopf **+ Eintrag** unten rechts ist immer erreichbar.

### Reiter „Lohn" – Auswertung für die Steuerberatung

Fehlzeiten je Person für einen Monat oder ein ganzes Jahr, mit CSV-Export für
DATEV oder Excel und einer Druckansicht. Enthalten ist genau das, was die
Lohnabrechnung braucht:

* Urlaub, bezahlter Sonderurlaub, **unbezahlter Urlaub**
* Krankheitstage als Arbeits- **und** Kalendertage, dazu jeder Zeitraum einzeln –
  für die U1-Erstattung und die Sechs-Wochen-Frist der Entgeltfortzahlung
* Kind-krank-Tage, Mutterschutz und Elternzeit
* Fortbildung, Berufsschule, Überstundenabbau
* Urlaubsstand zum Jahresende für die Rückstellung

Automatische Hinweise: bei unbezahltem Urlaub ab fünf zusammenhängenden
Arbeitstagen (Unterbrechung im Lohnkonto, Meldung zur Sozialversicherung) und
beim Überschreiten von 42 Krankheits-Kalendertagen in zwölf Monaten.

### Für Mitarbeiterinnen mit Zugang – zwei Reiter

| Reiter | Inhalt |
|---|---|
| **Kalender** | Der Plan des ganzen Teams, nur zum Ansehen. |
| **Mein Urlaub** | Eigenes Urlaubskonto, eigene Einträge, **Urlaub beantragen**, eigenes Passwort ändern. |

### Mehrere Zeiträume auf einmal

Ein Eintrag kann aus mehreren Abschnitten bestehen. Beispiel: eine Woche im
Oktober, fünf Tage im November, drei im Dezember. Im Eintragsdialog auf
**+ weiterer Zeitraum** tippen, so oft wie nötig. Die Vorschau zählt alle
Abschnitte zusammen und zeigt den Resturlaub danach; gespeichert wird je
Abschnitt ein Eintrag, damit sich jeder einzeln ändern oder löschen lässt.

Wird ein bestehender Eintrag geöffnet, ist nur sein eigener Zeitraum zu sehen –
weitere Abschnitte legt man über einen neuen Eintrag an.

### Krankenschein und Nachweise hochladen

Das Feld **Nachweis** erscheint nur bei Arten, die einen Beleg brauchen:
**Krank (AU)**, **Kind krank** und **Fortbildung**. Bei Urlaub, Überstunden oder
Berufsschule bleibt es ausgeblendet. Dort lassen sich PDF-Dateien und Fotos
anhängen – ein abfotografierter Krankenschein reicht:

* **Krankenschein / AU (selbst)** – bei „Krank (AU)"
* **Krankenschein / Attest (Kind)** – bei „Kind krank"
* **Fortbildungs-Zertifikat** – bei „Fortbildung"
* **Sonstiger Nachweis** – für alles andere

Die Art wird passend zur Abwesenheit vorgeschlagen und lässt sich ändern. Mehrere
Dateien pro Eintrag sind möglich (z. B. Erst- und Folgebescheinigung). Der Haken
„Nachweis liegt vor" setzt sich beim Hochladen von selbst.

**Wer lädt hoch?** Die Praxisleitung für jede Person – und jede Mitarbeiterin mit
Zugang für sich selbst: *Mein Urlaub → beim Eintrag auf „Nachweis hochladen"*. Das
geht auch bei Einträgen, die die Leitung angelegt hat (der übliche Fall: Krankmeldung
wird eingetragen, die AU kommt später).

Im Kalender zeigt eine kleine Ecke in der Tagesspalte, dass ein Nachweis hinterlegt
ist. Die Leitung sieht außerdem oben die Liste **„Nachweis fehlt noch"** – alle
vergangenen Krank-, Kind-krank- und Fortbildungstage ohne Beleg.

### Meldungen an die Steuerberatung per E-Mail

Wird einer der gewählten Einträge angelegt – ob selbst gemeldet oder von der
Leitung erfasst – geht **am selben Tag automatisch** eine Nachricht an die
Steuerberatung, mit der Praxisleitung in Kopie.

Welche Arten gemeldet werden, stellen Sie unter *Einstellungen* ein:

| Art | Vorgabe | warum sie für die Lohnabrechnung zählt |
|---|---|---|
| Krank (AU) | an | Entgeltfortzahlung, Sechs-Wochen-Frist, U1-Erstattung |
| Kind krank | an | unbezahlt durch die Praxis, Kinderkrankengeld der Kasse |
| Mutterschutz / Elternzeit | an | Mutterschaftsgeld, U2-Umlage, Meldungen zur Sozialversicherung |
| Unbezahlter Urlaub | aus | ab fünf Arbeitstagen Unterbrechung im Lohnkonto |

Urlaub, Fortbildung, Berufsschule und Überstunden werden **nie** gemeldet.

Die Nachricht enthält Name, Art, Zeitraum, Arbeits- und Kalendertage, das
Meldedatum und – bei Krankheit – ob ein Nachweis in der Praxis vorliegt.
**Ohne Nachweis im Anhang und ohne Diagnose.**

Trägt eine Mitarbeiterin selbst etwas ein, gilt: eine **Krankmeldung** geht
sofort raus, ein **Antrag** (z. B. Elternzeit) erst, wenn die Praxisleitung ihn
genehmigt hat. Jeder Eintrag zeigt beim Öffnen, ob und wann er gemeldet wurde,
mit einem Knopf zum erneuten Senden.

Einrichtung unter *Einstellungen → Krankmeldung per E-Mail*:

| Feld | Wert bei IONOS |
|---|---|
| Postausgangsserver | `smtp.ionos.de` |
| Port | 587 (STARTTLS) oder 465 (SSL) |
| Postfach / Benutzername | die volle E-Mail-Adresse des Praxis-Postfachs |
| Passwort | Passwort dieses Postfachs |
| Absenderadresse | dasselbe Postfach – sonst weist IONOS die Nachricht ab |
| An | Adresse der Steuerberatung |
| Kopie an (Cc) | Adresse von Frau Dr. Yar |

Mit **Testnachricht senden** lässt sich das sofort prüfen. Klappt es nicht, sagt
**Verbindung prüfen** Schritt für Schritt, woran es liegt – vom fehlenden Feld bis
zum abgelehnten Passwort – und nennt den nächsten Schritt.

Sperrt der Hoster den direkten Versand, lässt sich unter **Versandweg** auf
*„Über PHP mail()"* umstellen; dann verschickt der Webserver die Nachricht selbst. In der Karte
*Krankmeldungen* steht bei jeder Meldung, ob sie versendet wurde; über
**Erneut senden** geht sie noch einmal raus.

**Wo landet das Passwort des Postfachs?**

* **Normalfall – einfach in der Anwendung eintragen.** Es wird in der Datenbank
  gespeichert, also in `data/praxis.sqlite`. Dieser Ordner ist per `.htaccess`
  gesperrt und über das Internet nicht erreichbar; an den Browser wird das
  Passwort nie zurückgegeben. **Für die Praxis ist das völlig in Ordnung – Sie
  müssen nichts weiter tun.**
* **Etwas strenger – in `config.php`.** Wer möchte, trägt die Zugangsdaten dort
  ein (Block `mail`); dann stehen sie nur in dieser einen Datei und nicht in der
  Datenbank. Nützlich vor allem, wenn die Datenbank öfter weitergegeben oder
  gesichert wird. Ist der Block vorhanden, hat er Vorrang, und die Felder in der
  Oberfläche werden ausgeblendet.

> Hinweis: E-Mail ist auf dem Transportweg verschlüsselt, aber nicht
> Ende-zu-Ende. Deshalb enthält die Nachricht bewusst nur das Nötigste und
> niemals eine Diagnose oder den Krankenschein.

## 3. Wer darf was

| | Praxisleitung | Mitarbeiterin | Steuerberatung | ohne Zugang |
|---|---|---|---|---|
| Plan ansehen | ✔ komplett | ✔ nur **Urlaub** der anderen | – | – |
| Für **jede** Person eintragen, ändern, löschen | ✔ | – | – | – |
| Für sich selbst Urlaub **beantragen** | ✔ (direkt gültig) | ✔ (Leitung genehmigt) | – | – |
| Sich selbst **krank melden** | ✔ | ✔ (gilt sofort, danach gesperrt) | – | – |
| Eigenen Antrag ändern/zurückziehen | ✔ | nur solange **offen** | – | – |
| Urlaubskonto | ✔ alle | ✔ nur das eigene | ✔ nur Jahreswerte | – |
| Nachweise hochladen | für alle | nur eigene | – | – |
| Nachweise ansehen | alle | nur eigene | nur wenn freigegeben | – |
| Fehlzeiten-Auswertung (Lohn) | ✔ | – | ✔ | – |
| Personen anlegen, Zugänge vergeben | ✔ | – | – | – |
| Praxis-Einstellungen | ✔ | – | – | – |

**Eine Person braucht keinen Zugang.** Die Leitung trägt Urlaub, Krankheit und alles
Weitere für sie ein – das ist der Normalfall für die meisten Praxen.

### Zugang jederzeit nachträglich anlegen

*Team → bei der Person auf **Zugang anlegen*** → Benutzername (wird vorgeschlagen),
Passwort vergeben, Rolle wählen, speichern. Das funktioniert auch Monate später:
alle bereits eingetragenen Tage gehören dann automatisch zum Konto dieser Person,
es geht nichts verloren.

* **Rolle „Leitung"** darf alles – sinnvoll für die Ärztin und die Praxismanagerin.
* **Rolle „Steuerberatung"** sieht ausschließlich den Reiter *Lohnabrechnung*: keinen
  Kalender, keine Notizen, keine Zugänge, keine Einstellungen, standardmäßig auch
  keine hochgeladenen Krankenscheine. Diese Person muss **nicht** im Team stehen –
  Zugang einfach unter *Team → Zugänge → + Zugang* anlegen und die Rolle wählen.
* **Rolle „Mitarbeiterin"**: sieht im Plan von den Kolleginnen **nur genehmigten
  Urlaub** – damit sie ihren eigenen planen kann. Krankheit, Kind krank,
  Fortbildung, Notizen und Nachweise der anderen bleiben verborgen. Die eigenen
  Daten sieht sie vollständig.
  * **Urlaub und ähnliche Wünsche** gehen als *Antrag* an die Leitung. Solange er
    offen ist, kann sie ihn ändern oder zurückziehen; **sobald er genehmigt ist,
    nicht mehr** – dann nur noch die Praxisleitung.
  * Eine **Krankmeldung** gilt sofort und ist danach für sie gesperrt: ändern
    oder löschen kann sie nur die Praxisleitung.
  * Umstellbar unter *Einstellungen → Was Mitarbeiterinnen von anderen sehen*.
  * **Mutterschutz / Elternzeit** steht Mitarbeiterinnen normalerweise **nicht**
    zur Auswahl – die Art taucht in ihrem Eintragsdialog gar nicht erst auf.
    Im Team-Dialog lässt sich das **je Person** freigeben (*darf Mutterschutz /
    Elternzeit selbst eintragen*); dann kann diese eine Mitarbeiterin es als
    Antrag stellen. Die Praxisleitung kann es ohnehin jederzeit für jede Person
    eintragen. Dass bei jemandem freigegeben ist, sehen die Kolleginnen nicht.
  * Von Kolleginnen sieht eine Mitarbeiterin außerdem **nicht** deren
    Urlaubsanspruch und keine Notizen.
* **Anmelden erlaubt** abschalten sperrt den Zugang, ohne etwas zu löschen (z. B. bei
  längerer Abwesenheit oder nach dem Austritt).
* Ein Zugang kann auch **ohne Person** bestehen (reines Verwaltungskonto).
* Die letzte Leitung lässt sich weder löschen noch herabstufen – so sperrt sich niemand aus.

Passwörter: mindestens 10 Zeichen. Beim Anlegen wird das Passwort im Klartext
angezeigt, damit Sie es weitergeben können – danach ist es nicht mehr lesbar.
Bitte die Mitarbeiterin bitten, es nach der ersten Anmeldung unter *Mein Urlaub*
selbst zu ändern.

### Wochenmuster und Teilzeit
Im Team-Dialog werden die Wochentage angetippt: **1× = ganzer Arbeitstag, 2× = halber
Tag, 3× = frei**. Daraus berechnet die Anwendung die Arbeitstage jeder Abwesenheit –
Wochenenden, Feiertage des gewählten Bundeslands und Tage vor dem Eintritt bzw. nach
dem Austritt zählen nicht mit.

Der Urlaubsanspruch wird in **Tagen** geführt. Faustregel bei Teilzeit:
`Vollzeittage × (Arbeitstage pro Woche ÷ 5)` – bei 28 Tagen Vollzeit und 3 Tagen
pro Woche also 16,8 ≈ 17 Tage.

### Wie gezählt wird

Gezählt werden **Kalendertage, nicht Einträge**. Jeder Tag zählt genau einmal –
auch wenn versehentlich zwei Einträge auf demselben Tag liegen. Überschneiden
sich zwei Arten am selben Tag, gilt diese Rangfolge:

`Krank` › `Kind krank` › `Mutterschutz` › `Unbezahlt` › `Sonderurlaub` ›
`Urlaub` › `Überstunden` › `Fortbildung` › `Berufsschule` › `Abwesend`

Daraus folgt unter anderem: Wer **im Urlaub krank** wird, trägt die Krankheit
über den Urlaubszeitraum ein – die betroffenen Tage wandern automatisch vom
Urlaubs- ins Krankheitskonto und der Urlaub steht wieder zur Verfügung
(§ 9 BUrlG).

Jede Zahl lässt sich nachprüfen: im Reiter *Urlaub* öffnet
**„… Urlaubseinträge anzeigen"** die Einzelposten hinter dem Konto.
Überschneidungen sind dort rot markiert, und im Kalender führt die Karte
**„Doppelte Einträge prüfen"** alle betroffenen Fälle auf. Beim Eintragen warnt
die Vorschau, wenn es für diese Person im gewählten Zeitraum schon etwas gibt.

### Was die Anwendung automatisch beachtet
* **Anteiliger Anspruch** bei Ein- oder Austritt im laufenden Jahr (1/12 je vollem Monat).
* **Übertrag ins Folgejahr** inklusive Feld „Hinweis erteilt am“. Seit den BAG-Urteilen
  vom 20.12.2022 verfällt Resturlaub nur, wenn die Mitarbeiterin nachweislich und
  individuell schriftlich auf den konkreten Resturlaub und die Verfallfrist hingewiesen
  wurde. Über „Hinweisschreiben erzeugen“ entsteht der fertige Text zum Ausdrucken.
* **6-Wochen-Grenze:** ab 42 Krankheits-Kalendertagen in rollierenden 12 Monaten
  erscheint ein Hinweis auf Entgeltfortzahlung (EFZG) und BEM.
* **Kinderkrankentage:** Hinweis ab 15 Tagen im Kalenderjahr
  (gesetzlicher Rahmen je Kind und Elternteil, Stand 2026).
* **Krank im Urlaub (§ 9 BUrlG):** Urlaubseintrag öffnen, Art auf „Krank (AU)“ stellen –
  die Tage gehen ins Urlaubskonto zurück.

---

### Bedienung unterwegs

* Der Dialog lässt sich **nach unten wischen**, um ihn zu schließen – oder über
  den Griff oben, den Knopf „Abbrechen", einen Tipp neben das Fenster, oder mit
  der Escape-Taste am Rechner.
* Optionale Datumsfelder (Eintritt, Austritt, Hinweisdatum) haben einen Knopf
  **Leeren**; im Eintrag setzt **Zurücksetzen** das ganze Formular auf Anfang.
* Der Monatsplaner passt seine Spaltenbreite an den Bildschirm an: am iPad und am
  Rechner füllt er die Breite, am Handy bleibt er lesbar und lässt sich seitlich
  schieben.
* Über *Teilen → Zum Home-Bildschirm* legen Sie den Kalender wie eine App aufs
  iPad oder Handy; er öffnet dann ohne Browserleiste.

## 4. Datenschutz

Hier stehen Beschäftigtendaten und Krankheitszeiten – **besondere Kategorien nach
Art. 9 DSGVO**. Deshalb:

* **Keine Diagnosen** eintragen. „Krank“ genügt; das Notizfeld ist für Organisatorisches.
* Jede Person mit eigenem Konto – Zugänge nicht teilen.
* Mitarbeiterinnen sehen fremde Notizen nie. Ob sie im Plan auch die **Art** der
  Abwesenheit sehen (Urlaub, Krank …) oder nur, *dass* jemand fehlt, stellen Sie
  unter *Einstellungen* ein.
* Nur über **HTTPS** aufrufen; die `.htaccess` erzwingt das.
* `config.php` und der Ordner `data/` sind per `.htaccess` vom Webzugriff ausgenommen.
  Bitte nach der Installation einmal prüfen: `https://ihre-domain.de/kalender/config.php`
  darf **keinen** Inhalt anzeigen, und `…/data/praxis.sqlite` darf sich nicht herunterladen lassen.
* Einträge ausgeschiedener Mitarbeiterinnen nach Ablauf der Aufbewahrungsfristen löschen.

### Hochgeladene Nachweise

Krankenscheine und Atteste sind besonders schutzbedürftig. Deshalb:

* Die Dateien liegen in `data/nachweise` und sind über das Web **nicht direkt
  abrufbar** – weder über den Dateinamen noch durch Raten: jede Datei bekommt einen
  zufälligen Namen, und der Ordner ist per `.htaccess` gesperrt (zusätzlich ist dort
  die PHP-Ausführung abgeschaltet).
* Ausgeliefert wird nur über `api.php`, und nur nach Anmeldung und Rechteprüfung:
  **die Praxisleitung sieht alle, jede Mitarbeiterin ausschließlich ihre eigenen.**
* Erlaubt sind PDF, JPG, PNG, HEIC und WEBP. Der Typ wird am Inhalt geprüft, nicht am
  Dateinamen – eine als Bild getarnte PHP-Datei wird abgelehnt.
* Wird ein Eintrag oder eine Person gelöscht, verschwinden die zugehörigen Dateien mit.
* Für die Arbeitgeberkopie einer AU gibt es keine starre gesetzliche Aufbewahrungsfrist;
  sie ist zu löschen, sobald der Zweck entfällt. Unter *Einstellungen → Nachweise*
  löschen Sie mit einem Klick alles, was älter als 2, 3, 5 oder 10 Jahre ist. Welche
  Frist für Ihre Praxis passt, klären Sie bitte mit Steuerberatung bzw.
  Datenschutzbeauftragtem.
* Die AU selbst enthält keine Diagnose – bitte auch keine in die Notiz schreiben.
* Die **Steuerberatung** sieht die Nachweise nur, wenn die Praxisleitung das unter
  *Einstellungen* ausdrücklich freigibt. Für Lohnabrechnung und U1-Erstattung
  genügen in aller Regel die Zeiträume; der Krankenschein selbst gehört in die
  Personalakte der Praxis.
* In das Verzeichnis der Verarbeitungstätigkeiten aufnehmen (Zweck: Urlaubs- und
  Fehlzeitenverwaltung; Rechtsgrundlage: § 26 BDSG / Art. 6 Abs. 1 b DSGVO).

## 5. Sicherung

* **SQLite:** die Datei `data/praxis.sqlite` regelmäßig per FTP herunterladen –
  zusammen mit dem Ordner `data/nachweise`, sonst fehlen die Belege.
* **MySQL:** Export über phpMyAdmin im IONOS-Control-Panel.
* Zusätzlich im Reiter *Urlaubskonto* den **CSV-Export** je Jahr sichern.

## 6. Gestaltung

Farben und Schriften sind an die Praxis-Website angeglichen (Praxisgrün `#16653f`,
Serifenschrift für Überschriften). Es werden **keine externen Schriften oder Skripte**
geladen – beim Aufruf verlässt keine Anfrage den Praxis-Server.

## 7. Hinweise zu den Feiertagen

Die Feiertage werden aus dem Bundesland berechnet (Ostern nach Gauß, daraus Karfreitag,
Ostermontag, Christi Himmelfahrt, Pfingstmontag, Fronleichnam). Nicht abgebildet sind
Feiertage, die nur in einzelnen Gemeinden gelten – **Mariä Himmelfahrt** in Teilen Bayerns
und **Fronleichnam** in einzelnen Gemeinden Sachsens und Thüringens. Solche Tage bitte
als Eintrag „Praxis geschlossen“ erfassen.

### Hamburg
Voreingestellt ist **Hamburg**: neun bundesweite Feiertage plus Reformationstag (31.10.).

Unter *Einstellungen* stehen alle Feiertage des Jahres mit Wochentag. Fallen
mehrere direkt aufeinander – etwa der 1. und 2. Weihnachtstag – erscheinen sie
als ein Zeitraum mit der Zahl der Tage.

## 8. Nächste Ausbaustufen

1. Besetzungsregeln je Wochentag (mind. 1 Ärztin + 2 MFA) mit Ampel und Sperrzeiten.
2. Fristenmodul: Pflichtunterweisungen (jährlich), Strahlenschutz (5 Jahre),
   CME-Punkte, AU-Nachweise, KV-Vertretungsmeldung ab 7 Tagen Abwesenheit.
3. Dienstplan und Arbeitszeiterfassung.
