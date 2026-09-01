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
const SCHUTZ_VORSPANN = "<?php exit; ?>\n";
const STAND_DATEI   = __DIR__ . '/daten/stand.php';
const TON_ORDNER    = __DIR__ . '/daten/ton';
const TON_LEBEN_S   = 120;      // Durchsagen verfallen nach 2 Minuten
const GERAET_LEBEN_S = 30;      // danach gilt ein Geraet als getrennt
const TON_MAX_BYTES = 5242880;  // 5 MB je Durchsage

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
        'stimme'               => ['sprache' => 'de-DE', 'tempo' => 0.88,
                                   'tonhoehe' => 1.0, 'lautstaerke' => 1.0,
                                   'bevorzugt' => ['Google Deutsch', 'Microsoft Katja']],
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
 * Anmeldung
 * ------------------------------------------------------------------ */

function sitzungStarten(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,   // damit das Lautsprecher-Geraet angemeldet bleibt
        'path'     => '/',
        'secure'   => istHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('praxisruf');
    session_start();
}

function istHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    // IONOS setzt hinter dem Lastverteiler diesen Kopf.
    return (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function angemeldet(): bool
{
    sitzungStarten();
    return !empty($_SESSION['angemeldet']);
}

function anmeldungVerlangen(): void
{
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

function herkunft(): string
{
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
