<?php
/**
 * Praxis-Ruf Web — Schnittstelle
 *
 * Alle Endpunkte ueber ?was=...
 *   stand      (GET)   Aktueller Stand. Meldet zugleich das eigene Geraet an.
 *   aufruf     (POST)  Neuer Patientenaufruf. Name NUR im POST-Koerper.
 *   durchsage  (POST)  Rohes Audio, wird als Durchsage verteilt.
 *   ton        (GET)   Liefert eine Durchsage aus.
 *   leeren     (POST)  Anzeige/Aufruf zuruecksetzen.
 */

declare(strict_types=1);
require __DIR__ . '/inc.php';

anmeldungVerlangenApi();

$k   = konfig();
$was = $_GET['was'] ?? '';
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

switch ($was) {

    /* -------------------------------------------------------------- *
     * Stand abholen — wird im Sekundentakt aufgerufen
     * -------------------------------------------------------------- */
    case 'stand':
        $geraet = substr((string) ($_GET['geraet'] ?? ''), 0, 32);
        $rolle  = ($_GET['rolle'] ?? '') === 'lautsprecher' ? 'lautsprecher' : 'arzt';
        $raum   = (string) ($_GET['raum'] ?? '');
        $zimmer = substr((string) ($_GET['sprechzimmer'] ?? ''), 0, 40);

        if ($geraet !== '') {
            $stand = standAendern(function (array $s) use ($geraet, $rolle, $raum, $zimmer): array {
                $s['geraete'][$geraet] = [
                    'rolle' => $rolle,
                    'raum'  => $raum,
                    'name'  => $zimmer,
                    'zeit'  => time(),
                ];
                return $s;
            });
        } else {
            $stand = standAendern(fn (array $s): array => $s);
        }

        // Welche Lautsprecher sind gerade erreichbar?
        $lautsprecher = [];
        foreach ($k['wartezimmer'] as $w) {
            $lautsprecher[$w['id']] = 0;
        }
        $aerzte = [];
        foreach ($stand['geraete'] as $g) {
            if ($g['rolle'] === 'lautsprecher' && isset($lautsprecher[$g['raum']])) {
                $lautsprecher[$g['raum']]++;
            } elseif ($g['rolle'] === 'arzt' && $g['name'] !== '') {
                $aerzte[$g['name']] = true;
            }
        }

        // Ein Geraet im Wartezimmer bekommt nur, was es fuer seine eigene
        // Ansage braucht. Bisher ging die vollstaendige Liste aller Aufrufe
        // an jeden Lautsprecher — auch die Namen aus dem anderen
        // Wartezimmer, die dort niemand hoeren wird. Weniger Daten auf einem
        // unbeaufsichtigten Geraet im Wartebereich ist der Sinn der Sache.
        $verlauf = $stand['verlauf'];
        $aufrufFuerAntwort = $stand['aufruf'];
        if ($rolle === 'lautsprecher' && $raum !== '') {
            $passt = static fn (array $a): bool =>
                ($a['wartezimmer'] ?? '') === 'alle' || ($a['wartezimmer'] ?? '') === $raum;
            $verlauf = array_values(array_filter($verlauf, $passt));
            if ($aufrufFuerAntwort && !$passt($aufrufFuerAntwort)) {
                $aufrufFuerAntwort = null;
            }
        }

        antwort([
            'zeit'         => time(),
            'aufruf'       => $aufrufFuerAntwort,
            'durchsage'    => $stand['durchsage'],
            'verlauf'      => $verlauf,
            'lautsprecher' => $lautsprecher,
            // Welche Sprechzimmer offen sind, geht nur die Aerztinnen an.
            'aerzte'       => $rolle === 'lautsprecher' ? [] : array_keys($aerzte),
        ]);
        // kein break noetig, antwort() beendet

    /* -------------------------------------------------------------- *
     * Aufruf senden
     * -------------------------------------------------------------- */
    case 'aufruf':
        if (!$post) {
            antwort(['fehler' => 'Nur per POST'], 405);
        }

        $name = nameSaeubern((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            antwort(['fehler' => 'Kein Name angegeben'], 400);
        }

        $anrede = (string) ($_POST['anrede'] ?? '');
        if (!in_array($anrede, ['', 'Herr', 'Frau'], true)) {
            $anrede = '';
        }

        $ziel = (string) ($_POST['wartezimmer'] ?? 'alle');
        if ($ziel !== 'alle' && !istWartezimmer($ziel)) {
            antwort(['fehler' => 'Unbekanntes Wartezimmer'], 400);
        }

        // Welches Sprechzimmer ruft? Kommt aus der Auswahl der Aerztin.
        $kurz = substr((string) ($_POST['kurz'] ?? ''), 0, 4);
        $zimmerName = 'Sprechzimmer';
        foreach ($k['sprechzimmer'] as $z) {
            if ((string) $z['kurz'] === $kurz) {
                $zimmerName = $z['name'];
            }
        }

        $gezeigt = $k['nurNachname'] ? nurNachname($name) : $name;

        $aufruf = [
            'id'           => kennung(),
            'name'         => $gezeigt,
            // Fuer die Stimme in deutsche Rechtschreibung umgeschrieben, damit
            // eine deutsche Stimme auch persische, Dari-, Paschto- und
            // arabische Namen richtig ausspricht. Angezeigt wird weiterhin
            // 'name' — der echte Name, unveraendert.
            'ansage'       => fuerAussprache($gezeigt, $k['aussprache']),
            'anrede'       => $anrede,
            'sprechzimmer' => $zimmerName,
            // Fuer die Stimme mit ausgeschriebener Zahl: "Sprechzimmer 1"
            // liest eine deutsche Stimme sonst als "Sprechzimmer erste".
            // Angezeigt bleibt ueberall die Ziffer.
            'zimmerAnsage' => zahlenAusschreiben($zimmerName),
            'kurz'         => $kurz,
            'wartezimmer'  => $ziel,
            'wiederholen'  => (bool) $k['wiederholen'],
            'zeit'         => time(),
        ];

        $stand = standAendern(function (array $s) use ($aufruf): array {
            $s['aufruf'] = $aufruf;
            array_unshift($s['verlauf'], $aufruf);
            $s['verlauf'] = array_slice($s['verlauf'], 0, 5);
            return $s;
        });

        $erreicht = 0;
        foreach ($stand['geraete'] as $g) {
            if ($g['rolle'] === 'lautsprecher' && ($ziel === 'alle' || $g['raum'] === $ziel)) {
                $erreicht++;
            }
        }

        antwort(['ok' => true, 'aufruf' => $aufruf, 'erreicht' => $erreicht]);

    /* -------------------------------------------------------------- *
     * Durchsage mit eigener Stimme
     * -------------------------------------------------------------- */
    case 'durchsage':
        if (!$post) {
            antwort(['fehler' => 'Nur per POST'], 405);
        }

        $ziel = (string) ($_GET['ziel'] ?? 'alle');
        if ($ziel !== 'alle' && !istWartezimmer($ziel)) {
            antwort(['fehler' => 'Unbekanntes Ziel'], 400);
        }

        // Der volle Medientyp wird behalten (z. B. audio/webm;codecs=opus),
        // geprueft wird nur der Teil davor.
        $vollTyp = trim((string) ($_SERVER['CONTENT_TYPE'] ?? 'audio/webm'));
        $vollTyp = preg_replace('/[^\x20-\x7e]/', '', $vollTyp) ?? 'audio/webm';
        $vollTyp = substr($vollTyp, 0, 80);
        $basisTyp = trim(strtok($vollTyp, ';') ?: '');
        if (!str_starts_with($basisTyp, 'audio/')) {
            antwort(['fehler' => 'Kein Audioformat'], 415);
        }

        $daten = koerperBegrenztLesen(TON_MAX_BYTES);
        if ($daten === false) {
            antwort(['fehler' => 'Durchsage zu lang'], 413);
        }
        if ($daten === '') {
            antwort(['fehler' => 'Leere Aufnahme'], 400);
        }

        ordnerSicherstellen();
        $id = kennung();
        if (@file_put_contents(TON_ORDNER . '/' . $id . '.php', SCHUTZ_VORSPANN . $daten) === false) {
            antwort(['fehler' => 'Aufnahme konnte nicht abgelegt werden'], 500);
        }

        $stand = standAendern(function (array $s) use ($id, $ziel, $vollTyp): array {
            $s['durchsage'] = ['id' => $id, 'ziel' => $ziel,
                               'typ' => $vollTyp, 'zeit' => time()];
            return $s;
        });

        $erreicht = 0;
        foreach ($stand['geraete'] as $g) {
            if ($g['rolle'] === 'lautsprecher' && ($ziel === 'alle' || $g['raum'] === $ziel)) {
                $erreicht++;
            }
        }

        antwort(['ok' => true, 'id' => $id, 'erreicht' => $erreicht]);

    /* -------------------------------------------------------------- *
     * Durchsage ausliefern
     * -------------------------------------------------------------- */
    case 'ton':
        $id = (string) ($_GET['id'] ?? '');
        if (!preg_match('/^[0-9a-f]{16}$/', $id)) {
            http_response_code(400);
            exit;
        }
        $datei = TON_ORDNER . '/' . $id . '.php';
        if (!is_file($datei) || (time() - (int) filemtime($datei)) > TON_LEBEN_S) {
            http_response_code(404);
            exit;
        }
        $stand = standLesen();
        $typ = ($stand['durchsage']['id'] ?? '') === $id
             ? ($stand['durchsage']['typ'] ?? 'audio/webm') : 'audio/webm';

        // Den Schutzvorspann beim Ausliefern ueberspringen.
        $vorspann = strlen(SCHUTZ_VORSPANN);
        $laenge   = max(0, (int) filesize($datei) - $vorspann);

        header('Content-Type: ' . $typ);
        header('Cache-Control: no-store');

        // Safari auf iPhone und iPad laedt Ton in einem <audio>-Element nicht
        // am Stueck, sondern fragt zuerst mit "Range" einen kleinen Ausschnitt
        // an. Antwortet der Server darauf mit 200 und der ganzen Datei, statt
        // mit 206 und genau dem Ausschnitt, bricht Safari ab und spielt gar
        // nichts — ohne Fehlermeldung. Darum wird "Range" hier bedient.
        header('Accept-Ranges: bytes');

        $von = 0;
        $bis = $laenge - 1;
        $roh = (string) ($_SERVER['HTTP_RANGE'] ?? '');
        $teilweise = false;

        if ($laenge > 0 && preg_match('/^bytes=(\d*)-(\d*)$/', trim($roh), $t)) {
            if ($t[1] === '' && $t[2] === '') {
                antwortAufKaputtenBereich($laenge);
            }
            if ($t[1] === '') {
                // "bytes=-500" — die letzten 500 Bytes.
                $von = max(0, $laenge - (int) $t[2]);
            } else {
                $von = (int) $t[1];
                if ($t[2] !== '') {
                    $bis = (int) $t[2];
                }
            }
            $bis = min($bis, $laenge - 1);
            if ($von > $bis) {
                antwortAufKaputtenBereich($laenge);
            }
            $teilweise = true;
        }

        $menge = $bis - $von + 1;
        if ($teilweise) {
            http_response_code(206);
            header('Content-Range: bytes ' . $von . '-' . $bis . '/' . $laenge);
        }
        header('Content-Length: ' . (string) $menge);

        $griff = fopen($datei, 'rb');
        if ($griff) {
            fseek($griff, $vorspann + $von);
            $offen = $menge;
            while ($offen > 0 && !feof($griff)) {
                $stueck = fread($griff, (int) min(65536, $offen));
                if ($stueck === false || $stueck === '') {
                    break;
                }
                echo $stueck;
                $offen -= strlen($stueck);
            }
            fclose($griff);
        }
        exit;

    /* -------------------------------------------------------------- *
     * Anzeige zuruecksetzen
     * -------------------------------------------------------------- */
    case 'leeren':
        if (!$post) {
            antwort(['fehler' => 'Nur per POST'], 405);
        }
        standAendern(function (array $s): array {
            $s['aufruf'] = null;
            return $s;
        });
        antwort(['ok' => true]);

    default:
        antwort(['fehler' => 'Unbekannter Endpunkt'], 404);
}
