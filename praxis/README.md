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

| Reiter | Inhalt |
|---|---|
| **Monat** | Klassischer Wandplaner: Zeilen = Team, Spalten = Tage. Auf ein Feld tippen legt einen Eintrag an. Unten steht je Tag, wie viele anwesend sind. |
| **Jahr** | Jahresübersicht über alle 365 Tage, zum Ausdrucken (Querformat). |
| **Heute** | Wer ist heute da, wer fehlt, und was steht in den nächsten 14 Tagen an. |
| **Urlaubskonto** | Anspruch, Übertrag, genommen, geplant, Rest – dazu Krank-, Kind-krank- und Fortbildungstage. |
| **Team** | Mitarbeiterinnen anlegen: Wochenmuster, Urlaubstage, Ein-/Austritt, Farbe. |
| **Einstellungen** | Praxisname, Bundesland, Passwort, CSV-Export. |

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

## 3. Datenschutz

Hier stehen Beschäftigtendaten und Krankheitszeiten – **besondere Kategorien nach
Art. 9 DSGVO**. Deshalb:

* **Keine Diagnosen** eintragen. „Krank“ genügt; das Notizfeld ist für Organisatorisches.
* Zugang nur für die Praxisleitung bzw. Praxismanagerin, jede Person mit eigenem Konto.
* Nur über **HTTPS** aufrufen; die `.htaccess` erzwingt das.
* `config.php` und der Ordner `data/` sind per `.htaccess` vom Webzugriff ausgenommen.
  Bitte nach der Installation einmal prüfen: `https://ihre-domain.de/kalender/config.php`
  darf **keinen** Inhalt anzeigen, und `…/data/praxis.sqlite` darf sich nicht herunterladen lassen.
* Einträge ausgeschiedener Mitarbeiterinnen nach Ablauf der Aufbewahrungsfristen löschen.
* In das Verzeichnis der Verarbeitungstätigkeiten aufnehmen (Zweck: Urlaubs- und
  Fehlzeitenverwaltung; Rechtsgrundlage: § 26 BDSG / Art. 6 Abs. 1 b DSGVO).

## 4. Sicherung

* **SQLite:** die Datei `data/praxis.sqlite` regelmäßig per FTP herunterladen.
* **MySQL:** Export über phpMyAdmin im IONOS-Control-Panel.
* Zusätzlich im Reiter *Urlaubskonto* den **CSV-Export** je Jahr sichern.

## 5. Gestaltung

Farben und Schriften sind an die Praxis-Website angeglichen (Praxisgrün `#16653f`,
Serifenschrift für Überschriften). Es werden **keine externen Schriften oder Skripte**
geladen – beim Aufruf verlässt keine Anfrage den Praxis-Server.

## 6. Hinweise zu den Feiertagen

Die Feiertage werden aus dem Bundesland berechnet (Ostern nach Gauß, daraus Karfreitag,
Ostermontag, Christi Himmelfahrt, Pfingstmontag, Fronleichnam). Nicht abgebildet sind
Feiertage, die nur in einzelnen Gemeinden gelten – **Mariä Himmelfahrt** in Teilen Bayerns
und **Fronleichnam** in einzelnen Gemeinden Sachsens und Thüringens. Solche Tage bitte
als Eintrag „Praxis geschlossen“ erfassen.

### Hamburg
Voreingestellt ist **Hamburg**: neun bundesweite Feiertage plus Reformationstag (31.10.).

## 7. Nächste Ausbaustufen

1. Eigene Zugänge für das Team mit Antrag → Genehmigung.
2. Besetzungsregeln je Wochentag (mind. 1 Ärztin + 2 MFA) mit Ampel und Sperrzeiten.
3. Fristenmodul: Pflichtunterweisungen (jährlich), Strahlenschutz (5 Jahre),
   CME-Punkte, AU-Nachweise, KV-Vertretungsmeldung ab 7 Tagen Abwesenheit.
4. Dienstplan und Arbeitszeiterfassung.
