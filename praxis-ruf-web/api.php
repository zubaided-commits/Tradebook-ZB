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

        antwort([
            'zeit'         => time(),
            'aufruf'       => $stand['aufruf'],
            'durchsage'    => $stand['durchsage'],
            'verlauf'      => $stand['verlauf'],
            'lautsprecher' => $lautsprecher,
            'aerzte'       => array_keys($aerzte),
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

        $aufruf = [
            'id'           => kennung(),
            'name'         => $k['nurNachname'] ? nurNachname($name) : $name,
            'anrede'       => $anrede,
            'sprechzimmer' => $zimmerName,
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
        $laenge = (int) filesize($datei) - strlen(SCHUTZ_VORSPANN);
        header('Content-Type: ' . $typ);
        header('Content-Length: ' . (string) max(0, $laenge));
        header('Cache-Control: no-store');
        $griff = fopen($datei, 'rb');
        if ($griff) {
            fseek($griff, strlen(SCHUTZ_VORSPANN));
            fpassthru($griff);
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
