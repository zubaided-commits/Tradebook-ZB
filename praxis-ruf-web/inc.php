<?php
/**
 * Praxis-Ruf Web — gemeinsame Grundlagen
 *
 * Laeuft auf gewoehnlichem PHP-Webhosting (IONOS). Kein Node, keine
 * Datenbank, keine Abhaengigkeiten.
 *
 * Datenschutz: Patientennamen stehen NIE in einer Adresszeile, sondern
 * ausschliesslich im POST-Koerper — sonst landen sie in den Zugriffs-
 * protokollen des Hosters. Sie liegen nur so lange in daten/stand.php,
 * wie der Aufruf sichtbar ist, und werden danach automatisch geloescht.
 */

declare(strict_types=1);

// Die Dateien tragen die Endung .php und beginnen mit einem Schutzvorspann.
// Selbst wenn der Webserver den Ordner daten/ ausliefert — etwa weil .htaccess
// nicht gilt — fuehrt er die Datei aus und gibt nichts preis.
require __DIR__ . '/aussprache.php';

const SCHUTZ_VORSPANN = "<?php exit; ?>\n";
const STAND_DATEI   = __DIR__ . '/daten/stand.php';
const TON_ORDNER    = __DIR__ . '/daten/ton';
const TON_LEBEN_S   = 120;      // Durchsagen verfallen nach 2 Minuten
const GERAET_LEBEN_S = 30;      // danach gilt ein Geraet als getrennt
const TON_MAX_BYTES = 5242880;  // 5 MB je Durchsage
const AUTH_COOKIE   = 'praxisruf_auth';
const AUTH_TAGE     = 30;       // wie lange eine Anmeldung gilt

// Sichtbar auf jeder Seite unten. Nach dem Hochladen einer neuen Fassung
// laesst sich damit auf einen Blick pruefen, ob der Browser wirklich die
// neue Datei zeigt und nicht eine zwischengespeicherte alte.
const FASSUNG       = '2026-09-04-e';

/* ------------------------------------------------------------------ *
 * Konfiguration
 * ------------------------------------------------------------------ */

function konfig(): array
{
    static $k = null;
    if ($k !== null) {
        return $k;
    }

    $pfad = __DIR__ . '/config.php';
    if (!is_file($pfad)) {
        abbruch('config.php fehlt. Bitte config.beispiel.php kopieren und anpassen.');
    }
    $roh = require $pfad;

    $k = array_merge([
        'praxis'               => 'Praxis',
        'passwort'             => '',
        'sprechzimmer'         => [['kurz' => '1', 'name' => 'Sprechzimmer 1']],
        'wartezimmer'          => [['id' => 'wz1', 'name' => 'Wartezimmer 1']],
        'nurNachname'          => false,
        'wiederholen'          => true,
        'gong'                 => true,
        'anzeigeDauerSekunden' => 45,
        'verlaufDauerMinuten'  => 10,
        'wachton'              => true,
        'wachtonSekunden'      => 60,
        'wachtonHertz'         => 60,
        'wachtonStaerke'       => 0.02,
        'gongStaerke'          => 0.16,   // leiser und weicher als ein Signalton
        'gongPauseSekunden'    => 2,      // Gong klingt aus, dann der Name
        'wiederholPauseSekunden' => 2,    // Atempause vor der Wiederholung
        // Bevorzugt werden weibliche deutsche Stimmen, die es auf den
        // jeweiligen Geraeten wirklich gibt — von der besten zur einfachsten.
        // Welche vorhanden sind, entscheidet allein das Geraet; auf der
        // Lautsprecherseite laesst sich unter den vorhandenen auswaehlen.
        // Ruhig, klar und etwas langsamer als ein Gespraech.
        'stimme'               => ['sprache' => 'de-DE', 'tempo' => 0.82,
                                   'tonhoehe' => 1.04, 'lautstaerke' => 1.0,
                                   'bevorzugt' => [
                                       'Anna (Premium)', 'Anna (Erweitert)',
                                       'Anna (Enhanced)', 'Helena', 'Katja',
                                       'Anna', 'Petra', 'Marlene', 'Martina',
                                       'Google Deutsch', 'Hedda',
                                   ]],
        // Eigene Aussprache-Eintraege der Praxis: 'Name' => 'so vorlesen'.
        // Ergaenzt und ueberschreibt das mitgelieferte Woerterbuch.
        'aussprache'           => [],
    ], $roh);

    if ($k['passwort'] === '') {
        abbruch('In config.php ist noch kein Passwort gesetzt.');
    }
    return $k;
}

function abbruch(string $text): void
{
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Praxis-Ruf: " . $text . "\n";
    exit;
}

/* ------------------------------------------------------------------ *
 * Anmeldung — ueber ein selbst signiertes Cookie, absichtlich OHNE
 * PHP-Sitzung.
 *
 * Auf geteiltem Webhosting steht session.gc_maxlifetime selten auf
 * 30 Tage — oft bleibt es bei der Voreinstellung von rund 24 Minuten.
 * Ein Cookie mit 30 Tagen Laufzeit haette dann im Browser weiterhin
 * gegolten, waehrend die zugehoerige Sitzungsdatei auf dem Server laengst
 * geloescht war — die Anmeldung waere mitten am Tag unbemerkt abgerissen,
 * ausgerechnet auf dem unbeaufsichtigten Empfangs-PC. Das selbst
 * signierte Cookie braucht keine Server-Ablage: Rolle und Ablaufzeit
 * stehen im Cookie selbst, gesichert per HMAC mit dem Praxis-Passwort als
 * Schluessel. Wird das Passwort geaendert, werden dadurch automatisch
 * alle bestehenden Anmeldungen ungueltig — praktisch bei einem
 * Personalwechsel.
 * ------------------------------------------------------------------ */

function istHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    // IONOS setzt hinter dem Lastverteiler diesen Kopf.
    return (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function authSignatur(string $wert): string
{
    return hash_hmac('sha256', $wert, (string) konfig()['passwort']);
}

/** Meldet an, mit "arzt" oder "lautsprecher". Setzt das Cookie fuer AUTH_TAGE Tage. */
function anmelden(string $rolle): void
{
    $rolle = $rolle === 'lautsprecher' ? 'lautsprecher' : 'arzt';
    $ablauf = time() + 60 * 60 * 24 * AUTH_TAGE;
    $wert = $rolle . '.' . $ablauf;
    $wert .= '.' . authSignatur($wert);

    setcookie(AUTH_COOKIE, $wert, [
        'expires'  => $ablauf,
        'path'     => '/',
        'secure'   => istHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[AUTH_COOKIE] = $wert;   // noch im selben Aufruf verfuegbar
}

function abmelden(): void
{
    setcookie(AUTH_COOKIE, '', [
        'expires' => time() - 3600, 'path' => '/',
        'secure' => istHttps(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    unset($_COOKIE[AUTH_COOKIE]);
}

/** "arzt" oder "lautsprecher" bei gueltiger Anmeldung, sonst null. */
function angemeldeteRolle(): ?string
{
    $teile = explode('.', (string) ($_COOKIE[AUTH_COOKIE] ?? ''));
    if (count($teile) !== 3) {
        return null;
    }
    [$rolle, $ablauf, $signatur] = $teile;
    if (!in_array($rolle, ['arzt', 'lautsprecher'], true) || !ctype_digit($ablauf)) {
        return null;
    }
    if ((int) $ablauf < time()) {
        return null;
    }
    if (!hash_equals(authSignatur($rolle . '.' . $ablauf), $signatur)) {
        return null;
    }
    return $rolle;
}

function angemeldet(): bool
{
    return angemeldeteRolle() !== null;
}

function anmeldungVerlangen(): void
{
    // Angemeldete Seiten duerfen nirgends zwischengespeichert werden: nicht
    // im Browser, nicht in einem Zwischenspeicher des Hosters. Sonst zeigt
    // Safari nach dem Hochladen einer neuen Fassung weiter die alte — und
    // nebenbei haetten Patientennamen nichts in einem Zwischenspeicher zu
    // suchen.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    if (!angemeldet()) {
        header('Location: anmelden.php');
        exit;
    }
}

function anmeldungVerlangenApi(): void
{
    if (!angemeldet()) {
        antwort(['fehler' => 'Nicht angemeldet'], 401);
    }
}

/** Einfacher Schutz gegen Durchprobieren: nach 8 Fehlversuchen 10 Minuten Sperre. */
function anmeldungErlaubt(): bool
{
    $s = standLesen();
    $ip = herkunft();
    $e = $s['sperren'][$ip] ?? null;
    return !($e && $e['zahl'] >= 8 && (time() - $e['zeit']) < 600);
}

function fehlversuchMerken(): void
{
    standAendern(function (array $s): array {
        $ip = herkunft();
        $e = $s['sperren'][$ip] ?? ['zahl' => 0, 'zeit' => 0];
        if (time() - $e['zeit'] > 600) {
            $e['zahl'] = 0;
        }
        $e['zahl']++;
        $e['zeit'] = time();
        $s['sperren'][$ip] = $e;
        return $s;
    });
}

function fehlversucheLoeschen(): void
{
    standAendern(function (array $s): array {
        unset($s['sperren'][herkunft()]);
        return $s;
    });
}

/**
 * IP-Adresse fuer die Fehlversuch-Sperre. IONOS liegt hinter einem
 * Lastverteiler — ohne X-Forwarded-For waere REMOTE_ADDR fuer alle
 * Besucher dieselbe Adresse, und acht Fehlversuche von irgendjemandem
 * wuerden die gesamte Praxis fuer zehn Minuten aussperren.
 */
function herkunft(): string
{
    $weitergeleitet = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($weitergeleitet !== '') {
        $erste = trim(explode(',', $weitergeleitet)[0]);
        if ($erste !== '') {
            return substr(hash('sha256', $erste), 0, 16);
        }
    }
    return substr(hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '-'), 0, 16);
}

/* ------------------------------------------------------------------ *
 * Zustand — eine kleine JSON-Datei, mit Dateisperre
 * ------------------------------------------------------------------ */

/** Entfernt den Schutzvorspann vor dem JSON. */
function vorspannAb(string $roh): string
{
    return str_starts_with($roh, SCHUTZ_VORSPANN)
        ? substr($roh, strlen(SCHUTZ_VORSPANN))
        : $roh;
}

function leererStand(): array
{
    return ['aufruf' => null, 'durchsage' => null, 'verlauf' => [],
            'geraete' => [], 'sperren' => []];
}

function ordnerSicherstellen(): void
{
    foreach ([dirname(STAND_DATEI), TON_ORDNER] as $o) {
        if (!is_dir($o) && !@mkdir($o, 0770, true) && !is_dir($o)) {
            abbruch('Der Ordner ' . basename($o) . ' laesst sich nicht anlegen. '
                  . 'Bitte daten/ per FTP anlegen und beschreibbar machen.');
        }
    }
}

function standLesen(): array
{
    ordnerSicherstellen();
    if (!is_file(STAND_DATEI)) {
        return leererStand();
    }
    $roh = @file_get_contents(STAND_DATEI);
    $s = $roh ? json_decode(vorspannAb((string) $roh), true) : null;
    return is_array($s) ? array_merge(leererStand(), $s) : leererStand();
}

/**
 * Liest den Zustand, laesst ihn veraendern und schreibt ihn zurueck —
 * unter Dateisperre, damit zwei gleichzeitige Aufrufe sich nicht ueberholen.
 */
function standAendern(callable $aenderung): array
{
    ordnerSicherstellen();
    $griff = fopen(STAND_DATEI, 'c+');
    if ($griff === false) {
        abbruch('daten/stand.php ist nicht beschreibbar.');
    }
    flock($griff, LOCK_EX);

    $roh = stream_get_contents($griff);
    $s = $roh ? json_decode(vorspannAb((string) $roh), true) : null;
    $s = is_array($s) ? array_merge(leererStand(), $s) : leererStand();

    $s = aufraeumen($s);
    $s = $aenderung($s);
    $s = aufraeumen($s);

    ftruncate($griff, 0);
    rewind($griff);
    fwrite($griff, SCHUTZ_VORSPANN . json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($griff);
    flock($griff, LOCK_UN);
    fclose($griff);
    return $s;
}

/**
 * Loescht abgelaufene Daten. Namen verschwinden von selbst — das ist die
 * wichtigste Datenschutzmassnahme dieser Fassung.
 */
function aufraeumen(array $s): array
{
    $k = konfig();
    $jetzt = time();

    if ($s['aufruf'] && ($jetzt - $s['aufruf']['zeit']) > (int) $k['anzeigeDauerSekunden']) {
        $s['aufruf'] = null;
    }

    $verlaufGrenze = $jetzt - ((int) $k['verlaufDauerMinuten'] * 60);
    $s['verlauf'] = array_values(array_filter(
        $s['verlauf'],
        fn ($e) => ($e['zeit'] ?? 0) > $verlaufGrenze
    ));
    $s['verlauf'] = array_slice($s['verlauf'], 0, 5);

    if ($s['durchsage'] && ($jetzt - $s['durchsage']['zeit']) > TON_LEBEN_S) {
        $s['durchsage'] = null;
    }

    foreach ($s['geraete'] as $id => $g) {
        if (($jetzt - ($g['zeit'] ?? 0)) > GERAET_LEBEN_S) {
            unset($s['geraete'][$id]);
        }
    }

    foreach ($s['sperren'] as $ip => $e) {
        if (($jetzt - ($e['zeit'] ?? 0)) > 3600) {
            unset($s['sperren'][$ip]);
        }
    }

    altenTonLoeschen();
    return $s;
}

function altenTonLoeschen(): void
{
    if (!is_dir(TON_ORDNER)) {
        return;
    }
    foreach (glob(TON_ORDNER . '/*.php') ?: [] as $datei) {
        if (is_file($datei) && (time() - (int) filemtime($datei)) > TON_LEBEN_S) {
            @unlink($datei);
        }
    }
}

/* ------------------------------------------------------------------ *
 * Kleine Helfer
 * ------------------------------------------------------------------ */

/**
 * Liest den Anfragekoerper, bricht aber ab, sobald mehr als maxBytes
 * eingegangen sind — damit eine zu grosse Uebertragung nicht erst
 * vollstaendig im Speicher landet, bevor sie abgelehnt wird.
 * Gibt false bei Ueberschreitung zurueck, sonst den (auch leeren) Inhalt.
 */
function koerperBegrenztLesen(int $maxBytes): string|false
{
    $griff = fopen('php://input', 'rb');
    if ($griff === false) {
        return false;
    }
    $daten = '';
    while (!feof($griff)) {
        $stueck = fread($griff, 65536);
        if ($stueck === false) {
            break;
        }
        $daten .= $stueck;
        if (strlen($daten) > $maxBytes) {
            fclose($griff);
            return false;
        }
    }
    fclose($griff);
    return $daten;
}

/** Ein unerfuellbarer Range-Wunsch: 416 mit der wahren Laenge. */
function antwortAufKaputtenBereich(int $laenge): void
{
    http_response_code(416);
    header('Content-Range: bytes */' . $laenge);
    exit;
}

function antwort(array $daten, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($daten, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Steuerzeichen entfernen, Leerraum zusammenziehen, Laenge begrenzen. */
function nameSaeubern(string $roh): string
{
    $t = preg_replace('/[\x00-\x1f\x7f]/u', ' ', $roh) ?? '';
    $t = preg_replace('/\s+/u', ' ', $t) ?? '';
    return mb_substr(trim($t), 0, 60);
}

function nurNachname(string $name): string
{
    $teile = array_values(array_filter(explode(' ', $name)));
    return $teile ? end($teile) : $name;
}

function kennung(): string
{
    return bin2hex(random_bytes(8));
}

function h(?string $t): string
{
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

function istWartezimmer(string $id): bool
{
    foreach (konfig()['wartezimmer'] as $w) {
        if ($w['id'] === $id) {
            return true;
        }
    }
    return false;
}
