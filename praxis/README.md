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

3. **Schreibrechte** – nur bei SQLite nötig: der Ordner `data/` muss vom Webserver
   beschreibbar sein (Rechte 755 oder 775).

4. **SSL einschalten** – im IONOS-Control-Panel das kostenlose SSL-Zertifikat für die
   Domain aktivieren. Die mitgelieferte `.htaccess` leitet HTTP automatisch auf HTTPS um.

5. **Erste Einrichtung** – die Seite im Browser öffnen. Beim ersten Aufruf werden
   Praxisname, Bundesland (Hamburg ist voreingestellt) und das erste Benutzerkonto abgefragt.
   Passwort: mindestens 10 Zeichen.

6. **PHP-Version prüfen** – IONOS > Hosting > PHP-Einstellungen: PHP 8.1 oder neuer wählen.

---

## 2. Bedienung

### Für die Praxisleitung – vier Reiter

| Reiter | Inhalt |
|---|---|
| **Kalender** | Oben der Heute-Streifen (wer ist da, wer fehlt) und offene Anträge. Darunter der Plan, umschaltbar zwischen **Monat** und **Ganzes Jahr**. Auf ein Feld tippen legt einen Eintrag an. |
| **Urlaub** | Urlaubskonten aller: Anspruch, Übertrag, genommen, geplant, Rest – dazu Krank-, Kind-krank- und Fortbildungstage. |
| **Team** | Personen anlegen und ändern; darunter die **Zugänge** zum Anmelden. |
| **Einstellungen** | Praxisname, Bundesland, eigenes Passwort, CSV-Export. |

Der grüne Knopf **+ Eintrag** unten rechts ist immer erreichbar.

### Für Mitarbeiterinnen mit Zugang – zwei Reiter

| Reiter | Inhalt |
|---|---|
| **Kalender** | Der Plan des ganzen Teams, nur zum Ansehen. |
| **Mein Urlaub** | Eigenes Urlaubskonto, eigene Einträge, **Urlaub beantragen**, eigenes Passwort ändern. |

## 3. Wer darf was

| | Praxisleitung | Mitarbeiterin | ohne Zugang |
|---|---|---|---|
| Plan ansehen | ✔ | ✔ | – |
| Für **jede** Person eintragen, ändern, löschen | ✔ | – | – |
| Für sich selbst Urlaub **beantragen** | ✔ (direkt gültig) | ✔ (Leitung genehmigt) | – |
| Eigenes Urlaubskonto | ✔ alle | ✔ nur das eigene | – |
| Personen anlegen, Zugänge vergeben | ✔ | – | – |
| Praxis-Einstellungen | ✔ | – | – |

**Eine Person braucht keinen Zugang.** Die Leitung trägt Urlaub, Krankheit und alles
Weitere für sie ein – das ist der Normalfall für die meisten Praxen.

### Zugang jederzeit nachträglich anlegen

*Team → bei der Person auf **Zugang anlegen*** → Benutzername (wird vorgeschlagen),
Passwort vergeben, Rolle wählen, speichern. Das funktioniert auch Monate später:
alle bereits eingetragenen Tage gehören dann automatisch zum Konto dieser Person,
es geht nichts verloren.

* **Rolle „Leitung"** darf alles – sinnvoll für die Ärztin und die Praxismanagerin.
* **Rolle „Mitarbeiterin"** sieht den Plan, das eigene Konto und kann Urlaub beantragen.
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
* In das Verzeichnis der Verarbeitungstätigkeiten aufnehmen (Zweck: Urlaubs- und
  Fehlzeitenverwaltung; Rechtsgrundlage: § 26 BDSG / Art. 6 Abs. 1 b DSGVO).

## 5. Sicherung

* **SQLite:** die Datei `data/praxis.sqlite` regelmäßig per FTP herunterladen.
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

## 8. Nächste Ausbaustufen

1. Besetzungsregeln je Wochentag (mind. 1 Ärztin + 2 MFA) mit Ampel und Sperrzeiten.
2. Fristenmodul: Pflichtunterweisungen (jährlich), Strahlenschutz (5 Jahre),
   CME-Punkte, AU-Nachweise, KV-Vertretungsmeldung ab 7 Tagen Abwesenheit.
3. Dienstplan und Arbeitszeiterfassung.
