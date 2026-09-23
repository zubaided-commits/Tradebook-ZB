<?php
/**
 * Praxis-Kalender - Backend
 * Laeuft auf jedem IONOS Webspace mit PHP 8 (SQLite oder MySQL).
 * Alle Endpunkte: api.php?a=<aktion>
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

// ---------------------------------------------------------------- Konfiguration
$CFG_FILE = __DIR__ . '/config.php';
if (!is_file($CFG_FILE)) {
  http_response_code(500);
  echo json_encode(['error' => 'config_missing',
    'hinweis' => 'Bitte config.example.php nach config.php kopieren und ausfüllen.']);
  exit;
}
$CFG = require $CFG_FILE;
date_default_timezone_set($CFG['app']['timezone'] ?? 'Europe/Berlin');
$PREFIX = $CFG['db']['prefix'] ?? 'praxis_';

// ---------------------------------------------------------------- Hilfsfunktionen
function out(array $data, int $code = 200) {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
function fail(string $msg, int $code = 400) { out(['error' => $msg], $code); }

function body(): array {
  $raw = file_get_contents('php://input') ?: '';
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}
function uuid(): string { return bin2hex(random_bytes(12)); }
function today(): string { return date('Y-m-d'); }

function s(array $a, string $k, string $def = ''): string {
  return isset($a[$k]) && is_scalar($a[$k]) ? trim((string)$a[$k]) : $def;
}
function f(array $a, string $k, float $def = 0.0): float {
  return isset($a[$k]) && is_numeric($a[$k]) ? (float)$a[$k] : $def;
}
function isDate(string $d): bool {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
  [$y, $m, $dd] = array_map('intval', explode('-', $d));
  return checkdate($m, $dd, $y) && $y >= 1990 && $y <= 2100;
}

// ---------------------------------------------------------------- Datenbank
function db(): PDO {
  static $pdo = null;
  if ($pdo instanceof PDO) return $pdo;
  global $CFG;
  $d = $CFG['db'];
  try {
    if (($d['driver'] ?? 'sqlite') === 'mysql') {
      $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4";
      $pdo = new PDO($dsn, $d['user'], $d['pass']);
    } else {
      $path = $d['sqlite_path'];
      $dir  = dirname($path);
      if (!is_dir($dir)) @mkdir($dir, 0775, true);
      $pdo = new PDO('sqlite:' . $path);
      $pdo->exec('PRAGMA journal_mode=WAL');
      $pdo->exec('PRAGMA foreign_keys=ON');
    }
  } catch (Throwable $e) {
    out(['error' => 'db_connect', 'hinweis' => 'Datenbank nicht erreichbar. Zugangsdaten in config.php prüfen.'], 500);
  }
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  return $pdo;
}
function isMysql(): bool { global $CFG; return ($CFG['db']['driver'] ?? 'sqlite') === 'mysql'; }
function t(string $name): string { global $PREFIX; return $PREFIX . $name; }

function ensureSchema(): void {
  $pdo = db();
  $id  = isMysql() ? 'VARCHAR(32) NOT NULL PRIMARY KEY' : 'TEXT NOT NULL PRIMARY KEY';
  $txt = isMysql() ? 'VARCHAR(255)' : 'TEXT';
  $lng = isMysql() ? 'TEXT'         : 'TEXT';
  $uni = isMysql() ? 'VARCHAR(190)' : 'TEXT';
  $sql = [
    "CREATE TABLE IF NOT EXISTS " . t('users') . " (
       id $id, username $uni NOT NULL, pass_hash $txt NOT NULL,
       anzeige $txt, rolle $txt, created_at $txt)",
    "CREATE TABLE IF NOT EXISTS " . t('settings') . " (
       k $uni NOT NULL PRIMARY KEY, v $lng)",
    "CREATE TABLE IF NOT EXISTS " . t('staff') . " (
       id $id, name $txt NOT NULL, rolle $txt, kuerzel $txt, farbe $txt,
       eintritt $txt, austritt $txt, muster $txt, anspruch DOUBLE,
       aktiv INT DEFAULT 1, sortierung INT DEFAULT 0, notiz $lng)",
    "CREATE TABLE IF NOT EXISTS " . t('absences') . " (
       id $id, staff_id $txt, typ $txt, von $txt, bis $txt,
       halbtag INT DEFAULT 0, tage DOUBLE DEFAULT 0, kalendertage INT DEFAULT 0,
       notiz $lng, status $txt, nachweis INT DEFAULT 0,
       created_at $txt, updated_at $txt, created_by $txt)",
    "CREATE TABLE IF NOT EXISTS " . t('carry') . " (
       id $id, staff_id $txt, jahr INT, uebertrag DOUBLE DEFAULT 0,
       anspruch_override DOUBLE NULL, hinweis_am $txt)",
    "CREATE TABLE IF NOT EXISTS " . t('files') . " (
       id $id, absence_id $txt, staff_id $txt, art $txt,
       dateiname $txt, pfad $txt, mime $txt, groesse INT,
       hochgeladen_am $txt, hochgeladen_von $txt)",
    "CREATE TABLE IF NOT EXISTS " . t('log') . " (
       id $id, ts $txt, nutzer $txt, aktion $txt, details $lng)",
  ];
  foreach ($sql as $q) $pdo->exec($q);
  // Nachtraeglich ergaenzte Spalten (bestehende Installationen)
  ensureColumn('users', 'staff_id', $txt);
  ensureColumn('users', 'aktiv', 'INT DEFAULT 1');
  ensureColumn('absences', 'mail_am', $txt);
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_" . t('abs') . "_von ON " . t('absences') . " (von)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_" . t('abs') . "_staff ON " . t('absences') . " (staff_id)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_" . t('fil') . "_abs ON " . t('files') . " (absence_id)");
}

/** Spalte anlegen, falls sie noch fehlt (SQLite und MySQL). */
function ensureColumn(string $tabelle, string $spalte, string $typ): void {
  $pdo = db(); $t = t($tabelle);
  try {
    if (isMysql()) {
      $st = $pdo->prepare("SELECT COUNT(*) c FROM information_schema.columns
                           WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
      $st->execute([$t, $spalte]);
      $da = ((int)$st->fetch()['c']) > 0;
    } else {
      $da = false;
      foreach ($pdo->query("PRAGMA table_info(" . $t . ")")->fetchAll() as $r) {
        if (($r['name'] ?? '') === $spalte) { $da = true; break; }
      }
    }
    if (!$da) $pdo->exec("ALTER TABLE $t ADD COLUMN $spalte $typ");
  } catch (Throwable $e) { /* Spalte existiert bereits */ }
}

function setting(string $k, ?string $def = null): ?string {
  if (isset($GLOBALS['SETTING_OVERRIDE'][$k])) return $GLOBALS['SETTING_OVERRIDE'][$k];
  static $cache = [];
  if (array_key_exists($k, $cache)) return $cache[$k] ?? $def;
  $st = db()->prepare("SELECT v FROM " . t('settings') . " WHERE k = ?");
  $st->execute([$k]);
  $r = $st->fetch();
  $cache[$k] = $r ? (string)$r['v'] : null;
  return $cache[$k] ?? $def;
}

function setSetting(string $k, string $v): void {
  $pdo = db();
  $st = $pdo->prepare("SELECT 1 FROM " . t('settings') . " WHERE k = ?");
  $st->execute([$k]);
  if ($st->fetch()) {
    $pdo->prepare("UPDATE " . t('settings') . " SET v = ? WHERE k = ?")->execute([$v, $k]);
  } else {
    $pdo->prepare("INSERT INTO " . t('settings') . " (k, v) VALUES (?, ?)")->execute([$k, $v]);
  }
  $GLOBALS['SETTING_OVERRIDE'][$k] = $v;
  feiertageCacheLeeren();
}
function logAction(string $aktion, string $details = ''): void {
  try {
    db()->prepare("INSERT INTO " . t('log') . " (id, ts, nutzer, aktion, details) VALUES (?,?,?,?,?)")
        ->execute([uuid(), date('c'), $_SESSION['user_name'] ?? '-', $aktion, mb_substr($details, 0, 500)]);
  } catch (Throwable $e) { /* Protokoll darf nie den Vorgang stoppen */ }
}

// ---------------------------------------------------------------- Stammdaten: Abwesenheitsarten
const TYPEN = [
  ['key'=>'urlaub',       'label'=>'Urlaub',                'farbe'=>'#16653f', 'konto'=>'urlaub',      'nachweis'=>false],
  ['key'=>'sonderurlaub', 'label'=>'Sonderurlaub (bezahlt)','farbe'=>'#8b5cf6','konto'=>'sonder',      'nachweis'=>false],
  ['key'=>'unbezahlt',    'label'=>'Unbezahlter Urlaub',    'farbe'=>'#78716c', 'konto'=>'unbezahlt',   'nachweis'=>false],
  ['key'=>'krank',        'label'=>'Krank (AU)',            'farbe'=>'#e02424', 'konto'=>'krank',       'nachweis'=>true],
  ['key'=>'kind_krank',   'label'=>'Kind krank',            'farbe'=>'#f59e0b', 'konto'=>'kind',        'nachweis'=>true],
  ['key'=>'fortbildung',  'label'=>'Fortbildung',           'farbe'=>'#2563eb', 'konto'=>'fortbildung', 'nachweis'=>true],
  ['key'=>'abwesend',     'label'=>'Abwesend / Sonstiges',  'farbe'=>'#6b7280', 'konto'=>'keins',       'nachweis'=>false],
  ['key'=>'ueberstunden', 'label'=>'Überstunden / Gleittag','farbe'=>'#0d9488','konto'=>'stunden',     'nachweis'=>false],
  ['key'=>'berufsschule', 'label'=>'Berufsschule (Azubi)',  'farbe'=>'#ca8a04', 'konto'=>'keins',       'nachweis'=>false],
  ['key'=>'mutterschutz', 'label'=>'Mutterschutz / Elternzeit','farbe'=>'#db2777','konto'=>'keins',     'nachweis'=>false],
  ['key'=>'geschlossen',  'label'=>'Praxis geschlossen',    'farbe'=>'#334155', 'konto'=>'keins',       'nachweis'=>false],
];
function typKeys(): array { return array_column(TYPEN, 'key'); }
function typKonto(string $key): string {
  foreach (TYPEN as $t) if ($t['key'] === $key) return $t['konto'];
  return 'keins';
}

const BUNDESLAENDER = [
  'BW'=>'Baden-Württemberg','BY'=>'Bayern','BE'=>'Berlin','BB'=>'Brandenburg','HB'=>'Bremen',
  'HH'=>'Hamburg','HE'=>'Hessen','MV'=>'Mecklenburg-Vorpommern','NI'=>'Niedersachsen',
  'NW'=>'Nordrhein-Westfalen','RP'=>'Rheinland-Pfalz','SL'=>'Saarland','SN'=>'Sachsen',
  'ST'=>'Sachsen-Anhalt','SH'=>'Schleswig-Holstein','TH'=>'Thüringen',
];

// ---------------------------------------------------------------- Feiertage
/** Gesetzliche Feiertage eines Jahres fuer ein Bundesland: [ 'Y-m-d' => 'Name' ] */
function feiertageCacheLeeren(): void { $GLOBALS['FEIERTAGE_CACHE'] = []; }
function feiertage(int $jahr, string $bl): array {
  $ck = $jahr . $bl;
  if (isset($GLOBALS['FEIERTAGE_CACHE'][$ck])) return $GLOBALS['FEIERTAGE_CACHE'][$ck];
  $ostern = easter_date_safe($jahr);           // Ostersonntag als Unix-Timestamp (12:00)
  $d = fn(int $offset) => date('Y-m-d', $ostern + $offset * 86400);
  $fix = fn(string $md) => sprintf('%04d-%s', $jahr, $md);

  $h = [
    $fix('01-01') => 'Neujahr',
    $d(-2)        => 'Karfreitag',
    $d(1)         => 'Ostermontag',
    $fix('05-01') => 'Tag der Arbeit',
    $d(39)        => 'Christi Himmelfahrt',
    $d(50)        => 'Pfingstmontag',
    $fix('10-03') => 'Tag der Deutschen Einheit',
    $fix('12-25') => '1. Weihnachtstag',
    $fix('12-26') => '2. Weihnachtstag',
  ];
  $add = function (array $laender, string $datum, string $name) use (&$h, $bl) {
    if (in_array($bl, $laender, true)) $h[$datum] = $name;
  };
  $add(['BW','BY','ST'],                     $fix('01-06'), 'Heilige Drei Könige');
  $add(['BE','MV'],                          $fix('03-08'), 'Internationaler Frauentag');
  $add(['BB'],                               $d(0),         'Ostersonntag');
  $add(['BB'],                               $d(49),        'Pfingstsonntag');
  $add(['BW','BY','HE','NW','RP','SL'],      $d(60),        'Fronleichnam');
  $add(['SL'],                               $fix('08-15'), 'Mariä Himmelfahrt');
  $add(['TH'],                               $fix('09-20'), 'Weltkindertag');
  $add(['BB','HB','HH','MV','NI','SN','ST','SH','TH'], $fix('10-31'), 'Reformationstag');
  $add(['BW','BY','NW','RP','SL'],           $fix('11-01'), 'Allerheiligen');
  if ($bl === 'SN') $h[bussBettag($jahr)] = 'Buß- und Bettag';

  ksort($h);
  return $GLOBALS['FEIERTAGE_CACHE'][$ck] = $h;
}
/** Ostersonntag ohne Abhaengigkeit von der calendar-Extension (Gauss/Anonyme Gregorianische Formel). */
function easter_date_safe(int $y): int {
  $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100;
  $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25);
  $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
  $i = intdiv($c, 4); $k = $c % 4;
  $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
  $m = intdiv($a + 11 * $h + 22 * $l, 451);
  $monat = intdiv($h + $l - 7 * $m + 114, 31);
  $tag   = (($h + $l - 7 * $m + 114) % 31) + 1;
  return mktime(12, 0, 0, $monat, $tag, $y);
}
/** Mittwoch vor dem 23. November. */
function bussBettag(int $y): string {
  $ts = mktime(12, 0, 0, 11, 23, $y);
  do { $ts -= 86400; } while ((int)date('N', $ts) !== 3);
  return date('Y-m-d', $ts);
}

// ---------------------------------------------------------------- Arbeitstage
/** Wochenmuster "1,1,1,1,1,0,0" (Mo..So) -> [1.0,1.0,1.0,1.0,1.0,0,0] */
function muster(string $m): array {
  $p = array_map('floatval', explode(',', $m));
  while (count($p) < 7) $p[] = 0.0;
  return array_map(fn($v) => max(0.0, min(1.0, $v)), array_slice($p, 0, 7));
}
/**
 * Arbeitstage einer Abwesenheit: beruecksichtigt Wochenmuster, Feiertage,
 * Eintritts-/Austrittsdatum und halbe Tage.
 */
function arbeitstage(array $staff, string $von, string $bis, bool $halbtag): array {
  $p  = muster((string)($staff['muster'] ?? '1,1,1,1,1,0,0'));
  $ts = strtotime($von . ' 12:00');
  $te = strtotime($bis . ' 12:00');
  if ($ts === false || $te === false || $te < $ts) return ['tage' => 0.0, 'kalendertage' => 0];

  $tage = 0.0; $kal = 0; $fcache = [];
  for ($t = $ts; $t <= $te; $t += 86400) {
    $datum = date('Y-m-d', $t);
    $kal++;
    if (!empty($staff['eintritt']) && $datum < $staff['eintritt']) continue;
    if (!empty($staff['austritt']) && $datum > $staff['austritt']) continue;
    $jahr = (int)date('Y', $t);
    if (!isset($fcache[$jahr])) $fcache[$jahr] = feiertage($jahr, setting('bundesland', 'HH') ?? 'HH');
    if (isset($fcache[$jahr][$datum])) continue;
    $wd = (int)date('N', $t) - 1;
    $tage += $p[$wd];
  }
  if ($halbtag && $von === $bis) $tage = $tage > 0 ? 0.5 : 0.0;
  return ['tage' => round($tage, 2), 'kalendertage' => $kal];
}

// ---------------------------------------------------------------- Belegte Tage
/**
 * Rang der Abwesenheitsarten. Liegen zwei Eintraege auf demselben Tag, gewinnt der hoehere Rang.
 * Krankheit steht ueber Urlaub: wer im Urlaub krank wird und eine AU vorlegt, bekommt die
 * Urlaubstage zurueck (§ 9 BUrlG).
 */
const RANG = [
  'krank' => 100, 'kind_krank' => 90, 'mutterschutz' => 80, 'unbezahlt' => 70,
  'sonderurlaub' => 60, 'urlaub' => 50, 'ueberstunden' => 40, 'fortbildung' => 30,
  'berufsschule' => 20, 'abwesend' => 10, 'geschlossen' => 5,
];

/**
 * Jeder Kalendertag im Zeitraum wird genau EINMAL gezaehlt - auch wenn mehrere Eintraege
 * darauf liegen. Das verhindert doppelte Zaehlung bei versehentlich doppelten oder
 * ueberlappenden Eintraegen.
 *
 * Rueckgabe: [ 'YYYY-MM-DD' => ['typ' => ..., 'faktor' => 0|0.5|1, 'id' => ...], ... ]
 */
function belegteTage(array $staff, array $abs, string $von, string $bis): array {
  $muster = muster((string)($staff['muster'] ?? '1,1,1,1,1,0,0'));
  $bl = setting('bundesland', 'HH') ?? 'HH';
  $tage = [];
  $fcache = [];
  foreach ($abs as $a) {
    if ((string)$a['staff_id'] !== (string)$staff['id']) continue;
    if (in_array($a['status'], ['abgelehnt', 'storniert'], true)) continue;
    $v = max($a['von'], $von);
    $b = min($a['bis'], $bis);
    if ($b < $v) continue;
    $halb = ((int)$a['halbtag'] === 1 && $a['von'] === $a['bis']);
    $rang = RANG[$a['typ']] ?? 0;
    for ($t = strtotime($v . ' 12:00'); $t !== false && $t <= strtotime($b . ' 12:00'); $t += 86400) {
      $d = date('Y-m-d', $t);
      if (isset($tage[$d]) && (RANG[$tage[$d]['typ']] ?? 0) >= $rang) continue;
      // Arbeitet die Person an diesem Tag ueberhaupt?
      $faktor = 0.0;
      if ((empty($staff['eintritt']) || $d >= $staff['eintritt'])
       && (empty($staff['austritt']) || $d <= $staff['austritt'])) {
        $jahr = (int)date('Y', $t);
        if (!isset($fcache[$jahr])) $fcache[$jahr] = feiertage($jahr, $bl);
        if (!isset($fcache[$jahr][$d])) {
          $faktor = $muster[(int)date('N', $t) - 1];
          if ($halb && $faktor > 0.5) $faktor = 0.5;
        }
      }
      $tage[$d] = ['typ' => $a['typ'], 'faktor' => $faktor, 'id' => $a['id'],
                   'status' => $a['status'], 'nachweis' => (int)$a['nachweis']];
    }
  }
  ksort($tage);
  return $tage;
}

/** Summen je Konto-Gruppe aus den belegten Tagen. */
function summenAusTagen(array $tage, string $stichtag = ''): array {
  $s = ['urlaub_genommen' => 0.0, 'urlaub_geplant' => 0.0, 'krank' => 0.0, 'krank_kal' => 0,
        'kind' => 0.0, 'kind_kal' => 0, 'fortbildung' => 0.0, 'sonder' => 0.0, 'stunden' => 0.0,
        'unbezahlt' => 0.0, 'berufsschule' => 0.0, 'sonstiges' => 0.0,
        'mutterschutz' => 0.0, 'mutterschutz_kal' => 0, 'abwesend' => 0.0, 'abwesend_kal' => 0];
  foreach ($tage as $d => $t) {
    $f = (float)$t['faktor'];
    if ($t['typ'] !== 'geschlossen') { $s['abwesend'] += $f; $s['abwesend_kal']++; }
    switch ($t['typ']) {
      case 'urlaub':
        if ($stichtag !== '' && $d > $stichtag) $s['urlaub_geplant'] += $f;
        else                                    $s['urlaub_genommen'] += $f;
        break;
      case 'krank':        $s['krank'] += $f; $s['krank_kal']++; break;
      case 'kind_krank':   $s['kind'] += $f;  $s['kind_kal']++; break;
      case 'mutterschutz': $s['mutterschutz'] += $f; $s['mutterschutz_kal']++; break;
      case 'unbezahlt':    $s['unbezahlt'] += $f; break;
      case 'sonderurlaub': $s['sonder'] += $f; break;
      case 'fortbildung':  $s['fortbildung'] += $f; break;
      case 'berufsschule': $s['berufsschule'] += $f; break;
      case 'ueberstunden': $s['stunden'] += $f; break;
      case 'abwesend':     $s['sonstiges'] += $f; break;
    }
  }
  foreach ($s as $k => $v) if (is_float($v)) $s[$k] = round($v, 2);
  return $s;
}

/** Eintraege derselben Person, die sich zeitlich ueberschneiden - meist versehentlich doppelt erfasst. */
function doppelteEintraege(array $abs): array {
  $nachPerson = [];
  foreach ($abs as $a) {
    if (in_array($a['status'], ['abgelehnt', 'storniert'], true)) continue;
    if ($a['typ'] === 'geschlossen') continue;
    $nachPerson[$a['staff_id']][] = $a;
  }
  $paare = [];
  foreach ($nachPerson as $sid => $liste) {
    $n = count($liste);
    for ($i = 0; $i < $n; $i++) {
      for ($j = $i + 1; $j < $n; $j++) {
        $a = $liste[$i]; $b = $liste[$j];
        if ($a['von'] <= $b['bis'] && $b['von'] <= $a['bis']) {
          $paare[] = [
            'staff_id' => $sid,
            'gleich' => ($a['typ'] === $b['typ'] && $a['von'] === $b['von'] && $a['bis'] === $b['bis']) ? 1 : 0,
            'a' => ['id' => $a['id'], 'typ' => $a['typ'], 'von' => $a['von'], 'bis' => $a['bis']],
            'b' => ['id' => $b['id'], 'typ' => $b['typ'], 'von' => $b['von'], 'bis' => $b['bis']],
          ];
        }
      }
    }
  }
  return $paare;
}

// ---------------------------------------------------------------- Urlaubskonto
/** Anteiliger Jahresanspruch bei Ein-/Austritt im laufenden Jahr (1/12 je vollem Monat). */
function anspruchJahr(array $staff, int $jahr): float {
  $basis = (float)($staff['anspruch'] ?? 0);
  $von = 1; $bis = 12;
  if (!empty($staff['eintritt']) && (int)substr($staff['eintritt'], 0, 4) === $jahr) {
    $m = (int)substr($staff['eintritt'], 5, 2);
    $tag = (int)substr($staff['eintritt'], 8, 2);
    $von = $tag === 1 ? $m : $m + 1;             // angebrochener Monat zaehlt nicht
  }
  if (!empty($staff['austritt']) && (int)substr($staff['austritt'], 0, 4) === $jahr) {
    $m = (int)substr($staff['austritt'], 5, 2);
    $tag = (int)substr($staff['austritt'], 8, 2);
    $letzter = (int)date('t', mktime(12, 0, 0, $m, 1, $jahr));
    $bis = $tag === $letzter ? $m : $m - 1;
  }
  if (!empty($staff['eintritt']) && (int)substr($staff['eintritt'], 0, 4) > $jahr) return 0.0;
  if (!empty($staff['austritt']) && (int)substr($staff['austritt'], 0, 4) < $jahr) return 0.0;
  $monate = max(0, $bis - $von + 1);
  if ($monate >= 12) return $basis;
  return round($basis * $monate / 12 * 2) / 2;   // auf halbe Tage
}

function konten(int $jahr): array {
  $pdo = db();
  $staff = $pdo->query("SELECT * FROM " . t('staff') . " ORDER BY sortierung, name")->fetchAll();
  $carry = [];
  $st = $pdo->prepare("SELECT * FROM " . t('carry') . " WHERE jahr = ?");
  $st->execute([$jahr]);
  foreach ($st->fetchAll() as $c) $carry[$c['staff_id']] = $c;

  $st = $pdo->prepare("SELECT * FROM " . t('absences') . " WHERE bis >= ? AND von <= ?");
  $st->execute(["$jahr-01-01", "$jahr-12-31"]);
  $abs = $st->fetchAll();

  // Rollierende 12 Monate fuer die Sechs-Wochen-Grenze (EFZG) - ebenfalls tagegenau
  $seit = date('Y-m-d', strtotime('-365 days'));
  $heute = today();
  $st = $pdo->prepare("SELECT * FROM " . t('absences') . " WHERE bis >= ? AND von <= ?");
  $st->execute([$seit, $heute]);
  $abs12 = $st->fetchAll();

  $res = [];
  foreach ($staff as $m) {
    $c = $carry[$m['id']] ?? null;
    $anspruch = ($c && $c['anspruch_override'] !== null && $c['anspruch_override'] !== '')
      ? (float)$c['anspruch_override'] : anspruchJahr($m, $jahr);
    $uebertrag = $c ? (float)$c['uebertrag'] : 0.0;

    $tage = belegteTage($m, $abs, "$jahr-01-01", "$jahr-12-31");
    $s = summenAusTagen($tage, $heute);

    // Krankheits-Kalendertage der letzten 12 Monate, jeder Tag nur einmal
    $k12 = 0;
    foreach (belegteTage($m, $abs12, $seit, $heute) as $t) {
      if ($t['typ'] === 'krank') $k12++;
    }

    $res[$m['id']] = [
      'anspruch'   => round($anspruch, 1),
      'uebertrag'  => round($uebertrag, 1),
      'genommen'   => round($s['urlaub_genommen'], 1),
      'geplant'    => round($s['urlaub_geplant'], 1),
      'rest'       => round($anspruch + $uebertrag - $s['urlaub_genommen'] - $s['urlaub_geplant'], 1),
      'krank'      => round($s['krank'], 1),
      'kind'       => round($s['kind'], 1),
      'fortbildung'=> round($s['fortbildung'], 1),
      'sonder'     => round($s['sonder'], 1),
      'stunden'    => round($s['stunden'], 1),
      'unbezahlt'  => round($s['unbezahlt'], 1),
      'krank_12m_kalendertage' => $k12,
      'hinweis_am' => $c['hinweis_am'] ?? '',
    ];
  }
  return $res;
}

/** Anteil einer Abwesenheit, der in ein beliebiges Fenster faellt (Arbeitstage). */
function anteilImZeitraum(array $a, array $staff, string $von, string $bis): float {
  $v = max($a['von'], $von);
  $b = min($a['bis'], $bis);
  if ($b < $v) return 0.0;
  $r = arbeitstage($staff, $v, $b, (bool)$a['halbtag'] && $v === $b && $a['von'] === $a['bis']);
  return $r['tage'];
}
/** Kalendertage einer Abwesenheit innerhalb eines Fensters. */
function kalenderImZeitraum(array $a, string $von, string $bis): int {
  $v = max($a['von'], $von);
  $b = min($a['bis'], $bis);
  if ($b < $v) return 0;
  return (int)round((strtotime($b) - strtotime($v)) / 86400) + 1;
}

/** Anteil einer (ggf. jahresuebergreifenden) Abwesenheit, der in $jahr faellt. */
function anteilImJahr(array $a, array $staff, int $jahr): float {
  if (substr($a['von'], 0, 4) === (string)$jahr && substr($a['bis'], 0, 4) === (string)$jahr) {
    return (float)$a['tage'];
  }
  $von = max($a['von'], "$jahr-01-01");
  $bis = min($a['bis'], "$jahr-12-31");
  $r = arbeitstage($staff, $von, $bis, (bool)$a['halbtag'] && $von === $bis);
  return $r['tage'];
}

// ---------------------------------------------------------------- Nachweise (AU, Atteste, Zertifikate)
const NACHWEIS_ARTEN = [
  'au'        => 'Krankenschein / AU (selbst)',
  'au_kind'   => 'Krankenschein / Attest (Kind)',
  'zertifikat'=> 'Fortbildungs-Zertifikat',
  'sonstiges' => 'Sonstiger Nachweis',
];
const ERLAUBTE_TYPEN = [
  'application/pdf' => 'pdf',
  'image/jpeg'      => 'jpg',
  'image/png'       => 'png',
  'image/heic'      => 'heic',
  'image/heif'      => 'heif',
  'image/webp'      => 'webp',
];
const MAX_DATEI = 10485760;   // 10 MB

function nachweisOrdner(): string {
  $dir = __DIR__ . '/data/nachweise';
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
    // Zusaetzlicher Schutz direkt im Ordner
    @file_put_contents($dir . '/.htaccess',
      "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n" .
      "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n" .
      "php_flag engine off\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8\n");
  }
  return $dir;
}
/** Groesste Datei, die PHP auf diesem Server annimmt. */
function maxUpload(): int {
  $zuByte = function (string $v): int {
    $v = trim($v);
    if ($v === '') return 0;
    $n = (int)$v;
    switch (strtolower(substr($v, -1))) {
      case 'g': return $n * 1073741824;
      case 'm': return $n * 1048576;
      case 'k': return $n * 1024;
    }
    return $n;
  };
  $werte = array_filter([MAX_DATEI, $zuByte(ini_get('upload_max_filesize')), $zuByte(ini_get('post_max_size'))]);
  return $werte ? (int)min($werte) : MAX_DATEI;
}
/** Nachweise einer Abwesenheit bzw. alle (ohne Dateipfade nach aussen). */
function dateienFuer(?string $absenceId = null): array {
  if ($absenceId === null) {
    $rows = db()->query("SELECT * FROM " . t('files') . " ORDER BY hochgeladen_am")->fetchAll();
  } else {
    $st = db()->prepare("SELECT * FROM " . t('files') . " WHERE absence_id = ? ORDER BY hochgeladen_am");
    $st->execute([$absenceId]);
    $rows = $st->fetchAll();
  }
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'id' => $r['id'], 'absence_id' => $r['absence_id'], 'staff_id' => $r['staff_id'],
      'art' => $r['art'], 'art_label' => NACHWEIS_ARTEN[$r['art']] ?? 'Nachweis',
      'dateiname' => $r['dateiname'], 'mime' => $r['mime'], 'groesse' => (int)$r['groesse'],
      'hochgeladen_am' => $r['hochgeladen_am'], 'hochgeladen_von' => $r['hochgeladen_von'],
    ];
  }
  return $out;
}
/** Darf die angemeldete Person diesen Nachweis sehen oder hochladen? */
/** Darf hochladen oder loeschen? Die Steuerberatung nie. */
function darfNachweisAendern(string $staffId): bool {
  if (istSteuerberater()) return false;
  return istLeitung() || ($staffId !== '' && $staffId === eigeneStaffId());
}
function darfNachweis(string $staffId): bool {
  if (istLeitung()) return true;
  if (istSteuerberater()) return setting('stb_nachweise', '0') === '1';
  return $staffId !== '' && $staffId === eigeneStaffId();
}

// ---------------------------------------------------------------- E-Mail (Krankmeldung an die Steuerberatung)
/** Einstellungen fuer den Versand. config.php hat Vorrang vor den Werten aus der Oberflaeche. */
function mailKonfig(): array {
  global $CFG;
  $c = $CFG['mail'] ?? [];
  return [
    'aktiv'    => setting('mail_aktiv', '0') === '1',
    'art'      => setting('mail_art', 'smtp'),
    'host'     => (string)($c['host'] ?? setting('mail_host', 'smtp.ionos.de')),
    'port'     => (int)   ($c['port'] ?? setting('mail_port', '587')),
    'user'     => (string)($c['user'] ?? setting('mail_user', '')),
    'pass'     => (string)($c['pass'] ?? setting('mail_pass', '')),
    'von'      => (string)($c['von']  ?? setting('mail_von', '')),
    'von_name' => setting('mail_von_name', setting('praxisname', 'Praxis')),
    'an'       => setting('mail_an', ''),
    'cc'       => setting('mail_cc', ''),
    'arten'    => mailArten(),
  ];
}
/** Abwesenheitsarten, die automatisch an die Steuerberatung gemeldet werden. */
const MELDBARE_ARTEN = ['krank', 'kind_krank', 'mutterschutz', 'unbezahlt'];
function mailArten(): array {
  $roh = setting('mail_arten', 'krank,kind_krank,mutterschutz');
  $liste = array_values(array_intersect(array_map('trim', explode(',', (string)$roh)), MELDBARE_ARTEN));
  return $liste;
}
function mailAdressen(string $roh): array {
  $out = [];
  foreach (preg_split('/[,;\s]+/', $roh) as $a) {
    $a = trim($a);
    if ($a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL)) $out[] = $a;
  }
  return array_values(array_unique($out));
}
function mimeKopf(string $text): string {
  return preg_match('/[^\x20-\x7E]/', $text)
    ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
}
/** Kleiner SMTP-Client: Port 465 direkt ueber SSL, sonst STARTTLS. */
function smtpSenden(array $c, array $an, array $cc, string $betreff, string $text): array {
  if ($c['host'] === '' || $c['user'] === '' || $c['pass'] === '' || $c['von'] === '') {
    return ['ok' => false, 'stufe' => 'angaben', 'fehler' => 'E-Mail-Zugangsdaten unvollständig'];
  }
  if (!$an) return ['ok' => false, 'stufe' => 'angaben', 'fehler' => 'Keine Empfängeradresse hinterlegt'];

  $ziel = ($c['port'] === 465 ? 'ssl://' : '') . $c['host'] . ':' . $c['port'];
  $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true,
                                          'SNI_enabled' => true, 'peer_name' => $c['host']]]);
  $fp = @stream_socket_client($ziel, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
  if (!$fp) return ['ok' => false, 'stufe' => 'verbindung',
                    'fehler' => 'Verbindung zum Mailserver nicht möglich (' . $errstr . ')'];
  stream_set_timeout($fp, 15);

  $lesen = function () use ($fp) {
    $antwort = '';
    while (($zeile = fgets($fp, 1024)) !== false) {
      $antwort .= $zeile;
      if (strlen($zeile) < 4 || $zeile[3] !== '-') break;
    }
    return $antwort;
  };
  $senden = function (string $befehl) use ($fp, $lesen) { fwrite($fp, $befehl . "\r\n"); return $lesen(); };
  $code = fn(string $a) => (int)substr(trim($a), 0, 3);

  $schluss = function (string $stufe, string $fehler) use ($fp) {
    @fclose($fp);
    return ['ok' => false, 'stufe' => $stufe, 'fehler' => $fehler];
  };

  if ($code($lesen()) !== 220) return $schluss('verbindung', 'Mailserver meldet sich nicht');
  $host = $_SERVER['HTTP_HOST'] ?? 'praxis';
  if ($code($senden('EHLO ' . $host)) !== 250) return $schluss('verbindung', 'EHLO abgelehnt');
  if ($c['port'] !== 465) {
    if ($code($senden('STARTTLS')) !== 220) return $schluss('tls', 'STARTTLS nicht möglich');
    if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
      return $schluss('tls', 'Verschlüsselung fehlgeschlagen');
    }
    if ($code($senden('EHLO ' . $host)) !== 250) return $schluss('tls', 'EHLO nach STARTTLS abgelehnt');
  }
  if ($code($senden('AUTH LOGIN')) !== 334) return $schluss('anmeldung', 'Anmeldung nicht möglich');
  if ($code($senden(base64_encode($c['user']))) !== 334) return $schluss('anmeldung', 'Benutzername abgelehnt');
  $pw = trim($senden(base64_encode($c['pass'])));
  if ($code($pw) !== 235) return $schluss('anmeldung', 'Passwort abgelehnt (' . mb_substr($pw, 0, 60) . ')');
  if ($code($senden('MAIL FROM:<' . $c['von'] . '>')) !== 250) {
    return $schluss('absender', 'Absenderadresse abgelehnt – sie muss dem Postfach entsprechen');
  }
  foreach (array_merge($an, $cc) as $e) {
    $r = $code($senden('RCPT TO:<' . $e . '>'));
    if ($r !== 250 && $r !== 251) return $schluss('empfaenger', 'Empfänger abgelehnt: ' . $e);
  }
  if ($code($senden('DATA')) !== 354) return $schluss('daten', 'DATA abgelehnt');

  $kopf = 'From: ' . mimeKopf($c['von_name']) . ' <' . $c['von'] . '>' . "\r\n"
        . 'To: ' . implode(', ', $an) . "\r\n"
        . ($cc ? 'Cc: ' . implode(', ', $cc) . "\r\n" : '')
        . 'Subject: ' . mimeKopf($betreff) . "\r\n"
        . 'Date: ' . date('r') . "\r\n"
        . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $c['host'] . '>' . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: base64' . "\r\n"
        . 'Auto-Submitted: auto-generated' . "\r\n\r\n";
  fwrite($fp, $kopf . chunk_split(base64_encode($text), 76, "\r\n") . "\r\n.\r\n");
  if ($code($lesen()) !== 250) return $schluss('daten', 'Nachricht wurde nicht angenommen');
  $senden('QUIT');
  @fclose($fp);
  return ['ok' => true, 'stufe' => 'ok'];
}

/** Versand ueber die PHP-Funktion mail() - Rueckfallweg, wenn SMTP gesperrt ist. */
function phpMailSenden(array $c, array $an, array $cc, string $betreff, string $text): array {
  if (!function_exists('mail')) return ['ok' => false, 'fehler' => 'Die Funktion mail() ist auf dem Server gesperrt'];
  if (!$an) return ['ok' => false, 'fehler' => 'Keine Empfängeradresse hinterlegt'];
  $von = $c['von'] !== '' ? $c['von'] : $c['user'];
  if ($von === '') return ['ok' => false, 'fehler' => 'Keine Absenderadresse hinterlegt'];
  $kopf = 'From: ' . mimeKopf($c['von_name']) . ' <' . $von . '>' . "\r\n"
        . ($cc ? 'Cc: ' . implode(', ', $cc) . "\r\n" : '')
        . 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: base64' . "\r\n"
        . 'Auto-Submitted: auto-generated';
  $ok = @mail(implode(', ', $an), mimeKopf($betreff),
              chunk_split(base64_encode($text), 76, "\r\n"), $kopf, '-f' . $von);
  return $ok ? ['ok' => true]
             : ['ok' => false, 'fehler' => 'Der Server hat die Nachricht nicht angenommen (mail() meldet Fehler)'];
}
/** Versand ueber den eingestellten Weg. */
function mailVersenden(array $c, array $an, array $cc, string $betreff, string $text): array {
  return ($c['art'] === 'php')
    ? phpMailSenden($c, $an, $cc, $betreff, $text)
    : smtpSenden($c, $an, $cc, $betreff, $text);
}

/** Text der Meldung - ohne Diagnose, ohne Anhang. */
function meldungMailText(array $m, array $a): array {
  $bezeichnung = [
    'krank'        => ['Krankmeldung',            'Arbeitsunfähigkeit'],
    'kind_krank'   => ['Kind krank',              'Betreuung eines erkrankten Kindes'],
    'mutterschutz' => ['Mutterschutz / Elternzeit','Mutterschutz bzw. Elternzeit'],
    'unbezahlt'    => ['Unbezahlter Urlaub',      'Unbezahlter Urlaub'],
  ][$a['typ']] ?? ['Meldung', $a['typ']];

  $zeitraum = $a['von'] === $a['bis']
    ? date('d.m.Y', (int)strtotime($a['von']))
    : date('d.m.Y', (int)strtotime($a['von'])) . ' bis ' . date('d.m.Y', (int)strtotime($a['bis']));
  $betreff = $bezeichnung[0] . ' ' . $m['name'] . ' – ' . $zeitraum;

  $text = setting('praxisname', 'Praxis') . "\n\n"
    . $bezeichnung[0] . " zur Lohnabrechnung\n"
    . str_repeat('-', 40) . "\n\n"
    . "Mitarbeiterin/Mitarbeiter: " . $m['name'] . "\n"
    . "Art: " . $bezeichnung[1] . "\n"
    . "Zeitraum: " . $zeitraum . "\n"
    . "Arbeitstage: " . rtrim(rtrim(number_format((float)$a['tage'], 1, ',', ''), '0'), ',') . "\n"
    . "Kalendertage: " . (int)$a['kalendertage'] . "\n";

  if (in_array($a['typ'], ['krank', 'kind_krank'], true)) {
    $text .= "Nachweis liegt in der Praxis vor: " . (((int)$a['nachweis'] === 1) ? 'ja' : 'noch nicht') . "\n";
  }
  $text .= "Gemeldet am: " . date('d.m.Y') . "\n\n";

  if (in_array($a['typ'], ['krank', 'kind_krank'], true)) {
    $text .= "Die Arbeitsunfähigkeitsbescheinigung bzw. das Attest wird aus Datenschutzgründen nicht "
           . "mitgeschickt; der Nachweis liegt in der Praxis vor. Diese Nachricht enthält keine Diagnose.\n\n";
  } elseif ($a['typ'] === 'mutterschutz') {
    $text .= "Bescheinigungen liegen in der Praxis vor und werden aus Datenschutzgründen nicht "
           . "mitgeschickt. Bitte prüfen, was für Lohnabrechnung und Meldungen zur Sozialversicherung "
           . "zu veranlassen ist.\n\n";
  } elseif ($a['typ'] === 'unbezahlt') {
    $text .= "Bitte prüfen, ob eine Unterbrechung im Lohnkonto und eine Meldung zur "
           . "Sozialversicherung zu erfassen ist.\n\n";
  }
  $text .= "Automatisch erzeugt vom Praxis-Kalender.";
  return ['betreff' => $betreff, 'text' => $text];
}

/** Meldung verschicken und das Datum am Eintrag vermerken. */
function meldungMailSenden(string $absenceId, bool $erneut = false): array {
  $c = mailKonfig();
  if (!$c['aktiv']) return ['ok' => false, 'fehler' => 'Versand ist ausgeschaltet', 'still' => true];
  $st = db()->prepare("SELECT * FROM " . t('absences') . " WHERE id = ?");
  $st->execute([$absenceId]);
  $a = $st->fetch();
  if (!$a) return ['ok' => false, 'fehler' => 'Eintrag nicht gefunden'];
  if (!in_array($a['typ'], $c['arten'], true)) {
    return ['ok' => false, 'fehler' => 'Diese Art wird nicht gemeldet', 'still' => true];
  }
  // Ein noch nicht genehmigter Antrag wird nicht gemeldet - erst bei der Genehmigung
  if ($a['status'] === 'beantragt') {
    return ['ok' => false, 'fehler' => 'Antrag ist noch nicht genehmigt', 'still' => true];
  }
  if (!$erneut && !empty($a['mail_am'])) return ['ok' => true, 'schon' => true];
  $m = ladeStaff((string)$a['staff_id']);
  if (!$m) return ['ok' => false, 'fehler' => 'Person nicht gefunden'];

  $inhalt = meldungMailText($m, $a);
  $r = mailVersenden($c, mailAdressen($c['an']), mailAdressen($c['cc']), $inhalt['betreff'], $inhalt['text']);
  if ($r['ok']) {
    db()->prepare("UPDATE " . t('absences') . " SET mail_am = ? WHERE id = ?")
       ->execute([date('c'), $absenceId]);
    logAction('meldung_versandt', $a['typ'] . ' ' . $m['name'] . ' ' . $a['von']);
  } else {
    logAction('meldung_fehlgeschlagen', ($r['fehler'] ?? '?'));
  }
  return $r;
}

// ---------------------------------------------------------------- Auswertung fuer die Lohnabrechnung
/**
 * Fehlzeiten je Mitarbeiterin fuer einen Monat oder ein ganzes Jahr.
 * Enthaelt ausschliesslich das, was die Lohnabrechnung braucht - keine Notizen,
 * keine Diagnosen, keine Angaben zu anderen Bereichen.
 */
function reportDaten(int $jahr, int $monat, string $vonFrei = '', string $bisFrei = ''): array {
  if ($vonFrei !== '' && $bisFrei !== '' && isDate($vonFrei) && isDate($bisFrei) && $bisFrei >= $vonFrei) {
    $von = $vonFrei; $bis = $bisFrei;
  } else {
    $von = $monat ? sprintf('%04d-%02d-01', $jahr, $monat) : sprintf('%04d-01-01', $jahr);
    $bis = $monat ? date('Y-m-t', (int)strtotime($von))     : sprintf('%04d-12-31', $jahr);
  }

  $pdo = db();
  $staff = $pdo->query("SELECT * FROM " . t('staff') . " ORDER BY sortierung, name")->fetchAll();
  $st = $pdo->prepare("SELECT * FROM " . t('absences') . " WHERE bis >= ? AND von <= ? ORDER BY von");
  $st->execute([$von, $bis]);
  $abs = $st->fetchAll();

  $dateien = [];
  foreach (db()->query("SELECT id, absence_id, dateiname FROM " . t('files'))->fetchAll() as $f) {
    $dateien[$f['absence_id']][] = ['id' => $f['id'], 'name' => $f['dateiname']];
  }
  $konten = konten($jahr);

  $zeilen = [];
  foreach ($staff as $m) {
    $tage = belegteTage($m, $abs, $von, $bis);
    $sum  = summenAusTagen($tage, '');
    $z = [
      'name' => $m['name'], 'rolle' => $m['rolle'],
      'eintritt' => $m['eintritt'], 'austritt' => $m['austritt'],
      'urlaub' => round($sum['urlaub_genommen'], 1), 'sonderurlaub' => round($sum['sonder'], 1),
      'unbezahlt' => round($sum['unbezahlt'], 1),
      'krank_at' => round($sum['krank'], 1), 'krank_kt' => $sum['krank_kal'],
      'kind_at' => round($sum['kind'], 1), 'kind_kt' => $sum['kind_kal'],
      'mutterschutz_kt' => $sum['mutterschutz_kal'], 'mutterschutz_at' => round($sum['mutterschutz'], 1),
      'sonstiges' => round($sum['sonstiges'], 1),
      'fortbildung' => round($sum['fortbildung'], 1), 'berufsschule' => round($sum['berufsschule'], 1),
      'ueberstunden' => round($sum['stunden'], 1),
      'abwesend_at' => round($sum['abwesend'], 1), 'abwesend_kt' => $sum['abwesend_kal'],
      'perioden' => [], 'hinweise' => [],
    ];
    // Einzelne Zeitraeume weiterhin aus den Eintraegen - sie sind fuer die Lohnabrechnung nötig
    foreach ($abs as $a) {
      if ((string)$a['staff_id'] !== (string)$m['id']) continue;
      if (in_array($a['status'], ['abgelehnt', 'storniert'], true)) continue;
      if (!in_array($a['typ'], ['krank', 'kind_krank', 'unbezahlt', 'mutterschutz'], true)) continue;
      $at = anteilImZeitraum($a, $m, $von, $bis);
      $kt = kalenderImZeitraum($a, $von, $bis);
      if ($at <= 0 && $kt <= 0) continue;
      $z['perioden'][] = [
        'id' => $a['id'], 'typ' => $a['typ'],
        'von' => $a['von'], 'bis' => $a['bis'],
        'arbeitstage' => round((float)$a['tage'], 1),
        'kalendertage' => (int)$a['kalendertage'],
        'nachweis' => (isset($dateien[$a['id']]) || (int)$a['nachweis'] === 1) ? 1 : 0,
        'dateien' => darfNachweis((string)$m['id']) ? ($dateien[$a['id']] ?? []) : [],
        'im_zeitraum_at' => round($at, 1),
      ];
      if ($a['typ'] === 'unbezahlt' && (float)$a['tage'] >= 5) {
        $z['hinweise'][] = 'Unbezahlter Urlaub ab ' . $a['von'] . ': mindestens fünf zusammenhängende '
          . 'Arbeitstage – Unterbrechung im Lohnkonto und Meldung zur Sozialversicherung prüfen.';
      }
    }
    $k = $konten[$m['id']] ?? [];
    $z['konto'] = [
      'anspruch'  => $k['anspruch']  ?? 0,
      'uebertrag' => $k['uebertrag'] ?? 0,
      'genommen'  => $k['genommen']  ?? 0,
      'geplant'   => $k['geplant']   ?? 0,
      'rest'      => $k['rest']      ?? 0,
    ];
    if (($k['krank_12m_kalendertage'] ?? 0) >= 42) {
      $z['hinweise'][] = ($k['krank_12m_kalendertage']) . ' Krankheits-Kalendertage in den letzten '
        . '12 Monaten – Ende der Entgeltfortzahlung nach sechs Wochen prüfen.';
    }
    $z['leer'] = ($z['abwesend_at'] > 0 || $z['abwesend_kt'] > 0) ? 0 : 1;
    $zeilen[] = $z;
  }
  return ['von' => $von, 'bis' => $bis, 'jahr' => $jahr, 'monat' => $monat,
          'praxis' => setting('praxisname', 'Praxis'), 'zeilen' => $zeilen];
}

// ---------------------------------------------------------------- Sitzung / Auth
function startSession(): void {
  global $CFG;
  if (session_status() === PHP_SESSION_ACTIVE) return;
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
  session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true,
    'samesite' => 'Lax', 'secure' => $https,
  ]);
  session_name('praxiskal');
  session_start();
  $max = (int)($CFG['app']['session_minutes'] ?? 240) * 60;
  if (isset($_SESSION['last']) && time() - $_SESSION['last'] > $max) {
    $_SESSION = []; session_destroy(); session_start();
  }
  $_SESSION['last'] = time();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function angemeldet(): bool { return !empty($_SESSION['user_id']); }
/** Praxisleitung: darf alles. 'admin' ist die alte Bezeichnung und gilt weiter. */
function istLeitung(): bool {
  return in_array($_SESSION['user_rolle'] ?? '', ['leitung', 'admin'], true);
}
function requireLeitung(): void { requireLogin(); if (!istLeitung()) fail('keine_berechtigung', 403); }
/** Steuerberatung: sieht ausschliesslich die Lohn-Auswertung, sonst nichts. */
function istSteuerberater(): bool { return ($_SESSION['user_rolle'] ?? '') === 'steuerberater'; }
/** Mit welcher Mitarbeiterin ist das angemeldete Konto verknuepft? */
function eigeneStaffId(): string { return (string)($_SESSION['user_staff'] ?? ''); }
function requireLogin(): void { if (!angemeldet()) fail('nicht_angemeldet', 401); }
function requireCsrf(): void {
  $t = $_SERVER['HTTP_X_PRAXIS_TOKEN'] ?? '';
  if (!hash_equals($_SESSION['csrf'] ?? '', (string)$t)) fail('csrf', 403);
}
function requirePost(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') fail('methode', 405);
}
function nutzerAnzahl(): int {
  return (int)db()->query("SELECT COUNT(*) c FROM " . t('users'))->fetch()['c'];
}

// ---------------------------------------------------------------- Start
startSession();
ensureSchema();
$a = $_GET['a'] ?? 'state';

// ---------------------------------------------------------------- Endpunkte
switch ($a) {

case 'state': {
  $jahr = (int)($_GET['jahr'] ?? date('Y'));
  if ($jahr < 2000 || $jahr > 2100) $jahr = (int)date('Y');
  if (nutzerAnzahl() === 0) {
    out(['setup' => true, 'app' => $GLOBALS['CFG']['app']['name'] ?? 'Praxis-Kalender',
         'bundeslaender' => BUNDESLAENDER]);
  }
  if (!angemeldet()) out(['setup' => false, 'angemeldet' => false,
    'praxis' => setting('praxisname', 'Praxis'), 'app' => $GLOBALS['CFG']['app']['name']]);

  if (istSteuerberater()) {
    $liste = [];
    foreach (db()->query("SELECT * FROM " . t('staff') . " ORDER BY sortierung, name")->fetchAll() as $m) {
      $liste[] = ['id' => $m['id'], 'name' => $m['name'], 'rolle' => $m['rolle'],
                  'eintritt' => $m['eintritt'], 'austritt' => $m['austritt'], 'aktiv' => (int)$m['aktiv']];
    }
    out([
      'setup' => false, 'angemeldet' => true,
      'nutzer' => ['name' => $_SESSION['user_name'], 'rolle' => 'steuerberater', 'staff_id' => ''],
      'leitung' => false, 'steuerberater' => true,
      'csrf' => $_SESSION['csrf'],
      'praxis' => setting('praxisname', 'Praxis'),
      'jahr' => $jahr, 'heute' => today(),
      'typen' => TYPEN, 'staff' => $liste,
      'stb_nachweise' => setting('stb_nachweise', '0') === '1' ? 1 : 0,
    ]);
  }

  $pdo = db();
  $staff = $pdo->query("SELECT * FROM " . t('staff') . " ORDER BY sortierung, name")->fetchAll();
  foreach ($staff as &$m) {
    $m['anspruch'] = (float)$m['anspruch'];
    $m['aktiv'] = (int)$m['aktiv'];
    $m['sortierung'] = (int)$m['sortierung'];
  }
  unset($m);
  $st = $pdo->prepare("SELECT * FROM " . t('absences') . " WHERE bis >= ? AND von <= ? ORDER BY von");
  $st->execute(["$jahr-01-01", "$jahr-12-31"]);
  $abs = $st->fetchAll();
  foreach ($abs as &$x) {
    $x['tage'] = (float)$x['tage'];
    $x['halbtag'] = (int)$x['halbtag'];
    $x['kalendertage'] = (int)$x['kalendertage'];
    $x['nachweis'] = (int)$x['nachweis'];
    $x['mail_am'] = (string)($x['mail_am'] ?? '');
  }
  unset($x);
  $bl = setting('bundesland', 'HH') ?? 'HH';
  $istL   = istLeitung();
  $eigene = eigeneStaffId();
  // 'urlaub' (Vorgabe): von anderen ist nur Urlaub sichtbar - fuer die eigene Planung.
  // 'alle': von anderen sind alle Arten sichtbar.
  $sicht = setting('mitarbeiter_sicht', 'urlaub');

  if (!$istL) {
    $gefiltert = [];
    foreach ($abs as $x) {
      if ($x['staff_id'] === $eigene) { $gefiltert[] = $x; continue; }
      // Fremde Eintraege: nie Notizen, nie Nachweise
      $x['notiz'] = '';
      $x['nachweis'] = 0;
      if ($sicht === 'alle') { $gefiltert[] = $x; continue; }
      // Nur genehmigter Urlaub und Praxisschliessungen sind fuer die Planung sichtbar
      if (in_array($x['typ'], ['urlaub', 'geschlossen'], true)
          && in_array($x['status'], ['genehmigt', 'gemeldet'], true)) {
        $gefiltert[] = $x;
      }
    }
    $abs = $gefiltert;
  }

  $kt = konten($jahr);
  if (!$istL) $kt = isset($kt[$eigene]) ? [$eigene => $kt[$eigene]] : [];

  $offen = 0; $gemeldet = 0;
  if ($istL) {
    foreach ($abs as $x) {
      if ($x['status'] === 'beantragt') $offen++;
      elseif ($x['status'] === 'gemeldet') $gemeldet++;
    }
  }

  out([
    'setup' => false, 'angemeldet' => true,
    'nutzer' => ['name' => $_SESSION['user_name'], 'rolle' => $istL ? 'leitung' : 'mitarbeiter',
                 'staff_id' => $eigene],
    'leitung' => $istL,
    'offene_antraege' => $offen,
    'krankmeldungen' => $gemeldet,
    'krankmeldung_mail' => mailKonfig()['aktiv'] ? 1 : 0,
    'mail_arten' => mailKonfig()['aktiv'] ? mailArten() : [],
    'mitarbeiter_sicht' => $sicht,
    'users' => $istL ? listUsers() : [],
    'doppelte' => $istL ? doppelteEintraege($abs) : [],
    'stb_nachweise' => setting('stb_nachweise', '0') === '1' ? 1 : 0,
    'mail' => $istL ? [
      'aktiv' => setting('mail_aktiv', '0') === '1' ? 1 : 0,
      'art' => setting('mail_art', 'smtp'),
      'host' => setting('mail_host', 'smtp.ionos.de'),
      'port' => (int)setting('mail_port', '587'),
      'user' => setting('mail_user', ''),
      'von' => setting('mail_von', ''),
      'von_name' => setting('mail_von_name', setting('praxisname', 'Praxis')),
      'an' => setting('mail_an', ''),
      'cc' => setting('mail_cc', ''),
      'arten' => mailArten(),
      'meldbar' => MELDBARE_ARTEN,
      'pass_gesetzt' => setting('mail_pass', '') !== '' ? 1 : 0,
      'aus_config' => isset($GLOBALS['CFG']['mail']) ? 1 : 0,
    ] : null,
    'csrf' => $_SESSION['csrf'],
    'praxis' => setting('praxisname', 'Praxis'),
    'bundesland' => $bl,
    'bundeslaender' => BUNDESLAENDER,
    'jahr' => $jahr,
    'heute' => today(),
    'typen' => TYPEN,
    'staff' => $staff,
    'absences' => $abs,
    'feiertage' => feiertage($jahr, $bl),
    'konten' => $kt,
    'dateien' => array_values(array_filter(dateienFuer(), function ($f) use ($istL, $eigene) {
      return $istL || $f['staff_id'] === $eigene;
    })),
    'nachweis_arten' => NACHWEIS_ARTEN,
    'max_upload' => maxUpload(),
  ]);
}

case 'setup': {
  requirePost();
  if (nutzerAnzahl() > 0) fail('bereits_eingerichtet', 409);
  $d = body();
  $user = s($d, 'username'); $pass = s($d, 'passwort');
  $praxis = s($d, 'praxisname', 'Praxis'); $bl = s($d, 'bundesland', 'HH');
  if (strlen($user) < 3) fail('benutzername_zu_kurz');
  if (strlen($pass) < 10) fail('passwort_zu_kurz');
  if (!isset(BUNDESLAENDER[$bl])) fail('bundesland');
  db()->prepare("INSERT INTO " . t('users') . " (id, username, pass_hash, anzeige, rolle, created_at) VALUES (?,?,?,?,?,?)")
      ->execute([uuid(), $user, password_hash($pass, PASSWORD_DEFAULT), 'Praxisleitung', 'leitung', date('c')]);
  setSetting('praxisname', $praxis);
  setSetting('bundesland', $bl);
  setSetting('urlaub_sichtbar', '1');
  out(['ok' => true]);
}

case 'login': {
  requirePost();
  $d = body();
  $user = s($d, 'username'); $pass = s($d, 'passwort');
  // einfache Bremse gegen Passwort-Raten
  $_SESSION['versuche'] = ($_SESSION['versuche'] ?? 0);
  if ($_SESSION['versuche'] >= 5 && (time() - ($_SESSION['sperre'] ?? 0)) < 300) {
    fail('gesperrt', 429);
  }
  $st = db()->prepare("SELECT * FROM " . t('users') . " WHERE username = ?");
  $st->execute([$user]);
  $u = $st->fetch();
  if ($u && (int)($u['aktiv'] ?? 1) === 0) { usleep(300000); fail('zugang_deaktiviert', 403); }
  if (!$u || !password_verify($pass, $u['pass_hash'])) {
    $_SESSION['versuche']++; $_SESSION['sperre'] = time();
    usleep(400000);
    fail('login_falsch', 401);
  }
  session_regenerate_id(true);
  $_SESSION['versuche'] = 0;
  $_SESSION['user_id'] = $u['id'];
  $_SESSION['user_name'] = $u['anzeige'] ?: $u['username'];
  $_SESSION['user_rolle'] = $u['rolle'] ?: 'leitung';
  $_SESSION['user_staff'] = (string)($u['staff_id'] ?? '');
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
  logAction('login', $user);
  out(['ok' => true]);
}

case 'logout': {
  logAction('logout');
  $_SESSION = []; session_destroy();
  out(['ok' => true]);
}

case 'passwort': {
  requirePost(); requireLogin(); requireCsrf();
  $d = body();
  $alt = s($d, 'alt'); $neu = s($d, 'neu');
  if (strlen($neu) < 10) fail('passwort_zu_kurz');
  $st = db()->prepare("SELECT * FROM " . t('users') . " WHERE id = ?");
  $st->execute([$_SESSION['user_id']]);
  $u = $st->fetch();
  if (!$u || !password_verify($alt, $u['pass_hash'])) fail('login_falsch', 401);
  db()->prepare("UPDATE " . t('users') . " SET pass_hash = ? WHERE id = ?")
      ->execute([password_hash($neu, PASSWORD_DEFAULT), $u['id']]);
  logAction('passwort_geaendert');
  out(['ok' => true]);
}

case 'save_staff': {
  requirePost(); requireLeitung(); requireCsrf();
  $d = body();
  $id = s($d, 'id');
  $name = s($d, 'name');
  if ($name === '') fail('name_fehlt');
  $eintritt = s($d, 'eintritt'); $austritt = s($d, 'austritt');
  if ($eintritt !== '' && !isDate($eintritt)) fail('eintritt_ungueltig');
  if ($austritt !== '' && !isDate($austritt)) fail('austritt_ungueltig');
  $musterStr = s($d, 'muster', '1,1,1,1,1,0,0');
  $musterStr = implode(',', muster($musterStr));
  $row = [
    'name' => $name,
    'rolle' => s($d, 'rolle', 'mfa'),
    'kuerzel' => mb_substr(s($d, 'kuerzel') ?: mb_substr($name, 0, 2), 0, 4),
    'farbe' => s($d, 'farbe', '#1f9d55'),
    'eintritt' => $eintritt, 'austritt' => $austritt,
    'muster' => $musterStr,
    'anspruch' => f($d, 'anspruch', 25),
    'aktiv' => !empty($d['aktiv']) ? 1 : 0,
    'sortierung' => (int)f($d, 'sortierung', 0),
    'notiz' => mb_substr(s($d, 'notiz'), 0, 500),
  ];
  $pdo = db();
  if ($id !== '') {
    $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($row)));
    $pdo->prepare("UPDATE " . t('staff') . " SET $set WHERE id = ?")
        ->execute([...array_values($row), $id]);
  } else {
    $id = uuid();
    $cols = implode(', ', ['id', ...array_keys($row)]);
    $ph = implode(', ', array_fill(0, count($row) + 1, '?'));
    $pdo->prepare("INSERT INTO " . t('staff') . " ($cols) VALUES ($ph)")
        ->execute([$id, ...array_values($row)]);
  }
  // Tage aller Abwesenheiten dieser Person neu berechnen (Muster kann sich geaendert haben)
  staffCacheLeeren();
  neuBerechnen($id);
  logAction('mitarbeiter_gespeichert', $name);
  out(['ok' => true, 'id' => $id]);
}

case 'del_staff': {
  requirePost(); requireLeitung(); requireCsrf();
  $id = s(body(), 'id');
  if ($id === '') fail('id_fehlt');
  $pdo = db();
  dateienLoeschen('staff_id', $id);
  $pdo->prepare("UPDATE " . t('users') . " SET staff_id = '' WHERE staff_id = ?")->execute([$id]);
  $pdo->prepare("DELETE FROM " . t('absences') . " WHERE staff_id = ?")->execute([$id]);
  $pdo->prepare("DELETE FROM " . t('carry') . " WHERE staff_id = ?")->execute([$id]);
  $pdo->prepare("DELETE FROM " . t('staff') . " WHERE id = ?")->execute([$id]);
  logAction('mitarbeiter_geloescht', $id);
  out(['ok' => true]);
}

case 'preview': {
  requireLogin();
  if (istSteuerberater()) fail('keine_berechtigung', 403);
  $d = body() ?: $_GET;
  $sid = s($d, 'staff_id');
  if (!istLeitung() && $sid !== eigeneStaffId()) fail('keine_berechtigung', 403);
  $staff = ladeStaff($sid);
  if (!$staff) fail('mitarbeiter_unbekannt');
  $von = s($d, 'von'); $bis = s($d, 'bis') ?: $von;
  if (!isDate($von) || !isDate($bis)) fail('datum_ungueltig');
  if ($bis < $von) fail('bis_vor_von');
  $r = arbeitstage($staff, $von, $bis, !empty($d['halbtag']));

  // Gibt es fuer diese Person bereits einen Eintrag in diesem Zeitraum?
  $ausser = s($d, 'id');
  $st = db()->prepare("SELECT * FROM " . t('absences') . "
                       WHERE staff_id = ? AND bis >= ? AND von <= ?
                       AND status NOT IN ('abgelehnt','storniert') AND typ <> 'geschlossen'");
  $st->execute([$sid, $von, $bis]);
  $schon = [];
  foreach ($st->fetchAll() as $a) {
    if ($ausser !== '' && $a['id'] === $ausser) continue;
    $label = $a['typ'];
    foreach (TYPEN as $ty) if ($ty['key'] === $a['typ']) $label = $ty['label'];
    $schon[] = ['id' => $a['id'], 'label' => $label, 'von' => $a['von'], 'bis' => $a['bis']];
  }
  $r['vorhanden'] = $schon;
  out($r);
}

case 'save_absence': {
  requirePost(); requireLogin(); requireCsrf();
  $d = body();
  $id = s($d, 'id');
  $typ = s($d, 'typ');
  if (!in_array($typ, typKeys(), true)) fail('typ_ungueltig');
  $von = s($d, 'von'); $bis = s($d, 'bis') ?: $von;
  if (!isDate($von) || !isDate($bis)) fail('datum_ungueltig');
  if ($bis < $von) fail('bis_vor_von');
  if ((strtotime($bis) - strtotime($von)) / 86400 > 400) fail('zeitraum_zu_lang');
  $halbtag = !empty($d['halbtag']) && $von === $bis;

  if (istSteuerberater()) fail('keine_berechtigung', 403);
  // Mitarbeiterinnen duerfen nur fuer sich selbst und nur als Antrag eintragen
  if (!istLeitung()) {
    if ($typ === 'geschlossen') fail('keine_berechtigung', 403);
    $eigene = eigeneStaffId();
    if ($eigene === '') fail('kein_mitarbeiter_verknuepft', 403);
    $d['staff_id'] = $eigene;
    // Urlaub & Co. sind Antraege, eine Krankmeldung ist eine Meldung - sofort verbindlich
    $d['status'] = in_array($typ, ['krank', 'kind_krank'], true) ? 'gemeldet' : 'beantragt';
    if ($id !== '') {
      $st = db()->prepare("SELECT * FROM " . t('absences') . " WHERE id = ?");
      $st->execute([$id]);
      $alt = $st->fetch();
      if (!$alt || $alt['staff_id'] !== $eigene || $alt['status'] !== 'beantragt') {
        fail('keine_berechtigung', 403);
      }
    }
  }

  $staffIds = [];
  if ($typ === 'geschlossen') {                    // gilt fuer die ganze Praxis
    foreach (db()->query("SELECT id FROM " . t('staff') . " WHERE aktiv = 1")->fetchAll() as $r) {
      $staffIds[] = $r['id'];
    }
    if (!$staffIds) fail('keine_mitarbeiter');
  } else {
    $sid = s($d, 'staff_id');
    if (!ladeStaff($sid)) fail('mitarbeiter_unbekannt');
    $staffIds = [$sid];
  }

  $pdo = db();
  $status = s($d, 'status', 'genehmigt');
  if (!in_array($status, ['beantragt','gemeldet','genehmigt','abgelehnt','storniert'], true)) $status = 'genehmigt';
  $notiz = mb_substr(s($d, 'notiz'), 0, 300);
  $nachweis = !empty($d['nachweis']) ? 1 : 0;
  $gespeichert = [];

  foreach ($staffIds as $sid) {
    $staff = ladeStaff($sid);
    $r = arbeitstage($staff, $von, $bis, $halbtag);
    if ($id !== '' && count($staffIds) === 1) {
      $pdo->prepare("UPDATE " . t('absences') . " SET staff_id=?, typ=?, von=?, bis=?, halbtag=?, tage=?,
                     kalendertage=?, notiz=?, status=?, nachweis=?, updated_at=? WHERE id=?")
          ->execute([$sid, $typ, $von, $bis, $halbtag ? 1 : 0, $r['tage'], $r['kalendertage'],
                     $notiz, $status, $nachweis, date('c'), $id]);
      $gespeichert[] = $id;
    } else {
      $neu = uuid();
      $pdo->prepare("INSERT INTO " . t('absences') . " (id, staff_id, typ, von, bis, halbtag, tage,
                     kalendertage, notiz, status, nachweis, created_at, updated_at, created_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
          ->execute([$neu, $sid, $typ, $von, $bis, $halbtag ? 1 : 0, $r['tage'], $r['kalendertage'],
                     $notiz, $status, $nachweis, date('c'), date('c'), $_SESSION['user_name']]);
      $gespeichert[] = $neu;
    }
  }
  logAction('eintrag_gespeichert', "$typ $von..$bis");

  // Meldung noch am selben Tag an die Steuerberatung schicken
  $mail = null;
  if (in_array($typ, mailArten(), true)) {
    foreach ($gespeichert as $gid) {
      $r = meldungMailSenden($gid);
      if (empty($r['still']) && empty($r['schon'])) $mail = $r;
    }
  }
  out(['ok' => true, 'ids' => $gespeichert, 'mail' => $mail]);
}

case 'del_absence': {
  requirePost(); requireLogin(); requireCsrf();
  $id = s(body(), 'id');
  if ($id === '') fail('id_fehlt');
  if (istSteuerberater()) fail('keine_berechtigung', 403);
  if (!istLeitung()) {
    $st = db()->prepare("SELECT * FROM " . t('absences') . " WHERE id = ?");
    $st->execute([$id]);
    $alt = $st->fetch();
    if (!$alt || $alt['staff_id'] !== eigeneStaffId() || $alt['status'] !== 'beantragt') {
      fail('keine_berechtigung', 403);
    }
  }
  dateienLoeschen('absence_id', $id);
  db()->prepare("DELETE FROM " . t('absences') . " WHERE id = ?")->execute([$id]);
  logAction('eintrag_geloescht', $id);
  out(['ok' => true]);
}

case 'save_carry': {
  requirePost(); requireLeitung(); requireCsrf();
  $d = body();
  $sid = s($d, 'staff_id'); $jahr = (int)f($d, 'jahr', (float)date('Y'));
  if (!ladeStaff($sid)) fail('mitarbeiter_unbekannt');
  $uebertrag = f($d, 'uebertrag', 0);
  $override = (isset($d['anspruch_override']) && $d['anspruch_override'] !== '' && $d['anspruch_override'] !== null)
    ? f($d, 'anspruch_override') : null;
  $hinweis = s($d, 'hinweis_am');
  $pdo = db();
  $st = $pdo->prepare("SELECT id FROM " . t('carry') . " WHERE staff_id = ? AND jahr = ?");
  $st->execute([$sid, $jahr]);
  $row = $st->fetch();
  if ($row) {
    $pdo->prepare("UPDATE " . t('carry') . " SET uebertrag=?, anspruch_override=?, hinweis_am=? WHERE id=?")
        ->execute([$uebertrag, $override, $hinweis, $row['id']]);
  } else {
    $pdo->prepare("INSERT INTO " . t('carry') . " (id, staff_id, jahr, uebertrag, anspruch_override, hinweis_am) VALUES (?,?,?,?,?,?)")
        ->execute([uuid(), $sid, $jahr, $uebertrag, $override, $hinweis]);
  }
  logAction('urlaubskonto_angepasst', "$sid $jahr");
  out(['ok' => true]);
}

case 'save_settings': {
  requirePost(); requireLeitung(); requireCsrf();
  $d = body();
  if (($p = s($d, 'praxisname')) !== '') setSetting('praxisname', mb_substr($p, 0, 80));
  if (($b = s($d, 'bundesland')) !== '') {
    if (!isset(BUNDESLAENDER[$b])) fail('bundesland');
    setSetting('bundesland', $b);
    neuBerechnen(null);                      // Feiertage aendern die Arbeitstage
  }
  if (isset($d['mitarbeiter_sicht'])) {
    $w = s($d, 'mitarbeiter_sicht', 'urlaub');
    setSetting('mitarbeiter_sicht', in_array($w, ['urlaub', 'alle'], true) ? $w : 'urlaub');
  }
  if (isset($d['stb_nachweise'])) setSetting('stb_nachweise', !empty($d['stb_nachweise']) ? '1' : '0');

  // E-Mail-Versand
  if (isset($d['mail_aktiv'])) setSetting('mail_aktiv', !empty($d['mail_aktiv']) ? '1' : '0');
  if (isset($d['mail_art'])) setSetting('mail_art', s($d, 'mail_art') === 'php' ? 'php' : 'smtp');
  if (isset($d['mail_arten']) && is_array($d['mail_arten'])) {
    $gewaehlt = array_values(array_intersect($d['mail_arten'], MELDBARE_ARTEN));
    setSetting('mail_arten', implode(',', $gewaehlt));
  }
  foreach (['mail_host' => 190, 'mail_user' => 190, 'mail_von' => 190, 'mail_von_name' => 80,
            'mail_an' => 400, 'mail_cc' => 400] as $feld => $laenge) {
    if (isset($d[$feld])) setSetting($feld, mb_substr(s($d, $feld), 0, $laenge));
  }
  if (isset($d['mail_port'])) {
    $port = (int)f($d, 'mail_port', 587);
    setSetting('mail_port', (string)(in_array($port, [25, 465, 587, 2525], true) ? $port : 587));
  }
  // Passwort nur ersetzen, wenn eines eingegeben wurde - es wird nie zurueckgeliefert
  if (isset($d['mail_pass']) && s($d, 'mail_pass') !== '') setSetting('mail_pass', s($d, 'mail_pass'));
  if (!empty($d['mail_pass_loeschen'])) setSetting('mail_pass', '');
  foreach (['mail_an', 'mail_cc'] as $feld) {
    if (isset($d[$feld]) && s($d, $feld) !== '' && !mailAdressen(s($d, $feld))) fail('adresse_ungueltig');
  }
  logAction('einstellungen_gespeichert');
  out(['ok' => true]);
}

case 'export': {
  requireLogin();
  $jahr = (int)($_GET['jahr'] ?? date('Y'));
  $pdo = db();
  $staff = [];
  foreach ($pdo->query("SELECT * FROM " . t('staff'))->fetchAll() as $m) $staff[$m['id']] = $m;
  $st = $pdo->prepare("SELECT * FROM " . t('absences') . " WHERE bis >= ? AND von <= ? ORDER BY von");
  $st->execute(["$jahr-01-01", "$jahr-12-31"]);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="abwesenheiten-' . $jahr . '.csv"');
  $o = fopen('php://output', 'w');
  fwrite($o, "\xEF\xBB\xBF");                       // BOM, damit Excel Umlaute erkennt
  fputcsv($o, ['Mitarbeiter','Rolle','Art','Von','Bis','Arbeitstage','Kalendertage','Halbtag','Status','Nachweis','Notiz'], ';', '"', '');
  foreach ($st->fetchAll() as $r) {
    $m = $staff[$r['staff_id']] ?? ['name' => '?', 'rolle' => ''];
    $label = $r['typ'];
    foreach (TYPEN as $ty) if ($ty['key'] === $r['typ']) $label = $ty['label'];
    fputcsv($o, [$m['name'], $m['rolle'], $label, $r['von'], $r['bis'], $r['tage'],
                 $r['kalendertage'], $r['halbtag'] ? 'ja' : 'nein', $r['status'],
                 $r['nachweis'] ? 'ja' : 'nein', $r['notiz']], ';', '"', '');
  }
  fclose($o);
  exit;
}

case 'recalc': {
  requirePost(); requireLeitung(); requireCsrf();
  neuBerechnen(null);
  out(['ok' => true]);
}

case 'save_user': {
  requirePost(); requireLeitung(); requireCsrf();
  $d = body();
  $id    = s($d, 'id');
  $user  = mb_strtolower(s($d, 'username'));
  $pass  = s($d, 'passwort');
  $rolle = s($d, 'rolle', 'mitarbeiter');
  if (!in_array($rolle, ['leitung', 'mitarbeiter', 'steuerberater'], true)) fail('rolle_ungueltig');
  $sid   = s($d, 'staff_id');
  if ($sid !== '' && !ladeStaff($sid)) fail('mitarbeiter_unbekannt');
  $aktiv = !empty($d['aktiv']) ? 1 : 0;
  $pdo   = db();

  if ($user === '' || mb_strlen($user) < 3) fail('benutzername_zu_kurz');
  if (!preg_match('/^[a-z0-9._-]+$/', $user)) fail('benutzername_zeichen');

  // Name aus der verknuepften Mitarbeiterin, sonst der Benutzername
  $anzeige = s($d, 'anzeige');
  if ($anzeige === '' && $sid !== '') { $m = ladeStaff($sid); $anzeige = $m['name']; }
  if ($anzeige === '') $anzeige = $user;

  $st = $pdo->prepare("SELECT id FROM " . t('users') . " WHERE LOWER(username) = ?");
  $st->execute([$user]);
  $vorhanden = $st->fetch();
  if ($vorhanden && $vorhanden['id'] !== $id) fail('benutzername_vergeben');

  if ($id === '') {
    if (mb_strlen($pass) < 10) fail('passwort_zu_kurz');
    $pdo->prepare("INSERT INTO " . t('users') . "
        (id, username, pass_hash, anzeige, rolle, created_at, staff_id, aktiv)
        VALUES (?,?,?,?,?,?,?,?)")
        ->execute([uuid(), $user, password_hash($pass, PASSWORD_DEFAULT), $anzeige,
                   $rolle, date('c'), $sid, $aktiv]);
    logAction('zugang_angelegt', $user);
    out(['ok' => true]);
  }

  $st = $pdo->prepare("SELECT * FROM " . t('users') . " WHERE id = ?");
  $st->execute([$id]);
  $alt = $st->fetch();
  if (!$alt) fail('zugang_unbekannt', 404);

  // Die letzte Leitung darf nicht herabgestuft oder abgeschaltet werden
  $leitungAktiv = (int)$pdo->query("SELECT COUNT(*) c FROM " . t('users') . "
      WHERE rolle IN ('leitung','admin') AND (aktiv IS NULL OR aktiv = 1)")->fetch()['c'];
  $warLeitung = in_array($alt['rolle'], ['leitung','admin'], true) && (int)($alt['aktiv'] ?? 1) === 1;
  if ($warLeitung && $leitungAktiv <= 1 && ($rolle !== 'leitung' || !$aktiv)) {
    fail('letzte_leitung', 409);
  }
  $pdo->prepare("UPDATE " . t('users') . " SET username=?, anzeige=?, rolle=?, staff_id=?, aktiv=? WHERE id=?")
      ->execute([$user, $anzeige, $rolle, $sid, $aktiv, $id]);
  if ($pass !== '') {
    if (mb_strlen($pass) < 10) fail('passwort_zu_kurz');
    $pdo->prepare("UPDATE " . t('users') . " SET pass_hash = ? WHERE id = ?")
        ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
  }
  // eigene Sitzung aktuell halten
  if ($id === ($_SESSION['user_id'] ?? '')) {
    $_SESSION['user_rolle'] = $rolle;
    $_SESSION['user_staff'] = $sid;
    $_SESSION['user_name']  = $anzeige;
  }
  logAction('zugang_geaendert', $user);
  out(['ok' => true]);
}

case 'del_user': {
  requirePost(); requireLeitung(); requireCsrf();
  $id = s(body(), 'id');
  if ($id === '') fail('id_fehlt');
  if ($id === ($_SESSION['user_id'] ?? '')) fail('eigener_zugang', 409);
  $pdo = db();
  $st = $pdo->prepare("SELECT * FROM " . t('users') . " WHERE id = ?");
  $st->execute([$id]);
  $u = $st->fetch();
  if (!$u) fail('zugang_unbekannt', 404);
  $leitung = (int)$pdo->query("SELECT COUNT(*) c FROM " . t('users') . "
      WHERE rolle IN ('leitung','admin')")->fetch()['c'];
  if (in_array($u['rolle'], ['leitung','admin'], true) && $leitung <= 1) fail('letzte_leitung', 409);
  $pdo->prepare("DELETE FROM " . t('users') . " WHERE id = ?")->execute([$id]);
  logAction('zugang_geloescht', (string)$u['username']);
  out(['ok' => true]);
}

case 'entscheiden': {
  requirePost(); requireLeitung(); requireCsrf();
  $d = body();
  $id = s($d, 'id');
  $status = s($d, 'status');
  if (!in_array($status, ['genehmigt', 'abgelehnt'], true)) fail('status_ungueltig');
  db()->prepare("UPDATE " . t('absences') . " SET status = ?, updated_at = ? WHERE id = ?")
      ->execute([$status, date('c'), $id]);
  logAction('antrag_' . $status, $id);
  // Genehmigte Meldearten gehen jetzt an die Steuerberatung
  $mail = null;
  if ($status === 'genehmigt') {
    $r = meldungMailSenden($id);
    if (empty($r['still']) && empty($r['schon'])) $mail = $r;
  }
  out(['ok' => true, 'mail' => $mail]);
}

case 'upload': {
  requirePost(); requireLogin(); requireCsrf();
  $absenceId = s($_POST, 'absence_id');
  $art       = s($_POST, 'art', 'sonstiges');
  if (!isset(NACHWEIS_ARTEN[$art])) $art = 'sonstiges';
  if ($absenceId === '') fail('eintrag_fehlt');

  $st = db()->prepare("SELECT * FROM " . t('absences') . " WHERE id = ?");
  $st->execute([$absenceId]);
  $eintrag = $st->fetch();
  if (!$eintrag) fail('eintrag_unbekannt', 404);
  if (!darfNachweisAendern((string)$eintrag['staff_id'])) fail('keine_berechtigung', 403);

  // Datei groesser als post_max_size: PHP liefert ein leeres $_FILES
  if (empty($_FILES['datei'])) {
    $laenge = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    fail($laenge > 0 ? 'datei_zu_gross' : 'keine_datei');
  }
  $f = $_FILES['datei'];
  if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    fail(in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
      ? 'datei_zu_gross' : 'upload_fehlgeschlagen');
  }
  if (!is_uploaded_file($f['tmp_name'])) fail('upload_fehlgeschlagen');
  if ((int)$f['size'] <= 0) fail('datei_leer');
  if ((int)$f['size'] > maxUpload()) fail('datei_zu_gross');

  // Typ am Inhalt pruefen, nicht am Namen
  $mime = '';
  if (function_exists('finfo_open')) {
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = (string)finfo_file($fi, $f['tmp_name']);
    finfo_close($fi);
  }
  $endungAlt = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
  if (!isset(ERLAUBTE_TYPEN[$mime])) {
    // Aeltere Server erkennen HEIC/HEIF nicht - dann die Endung akzeptieren
    if (in_array($endungAlt, ['heic', 'heif'], true) &&
        in_array($mime, ['application/octet-stream', 'image/heif', 'image/heic', ''], true)) {
      $mime = 'image/' . $endungAlt;
    } else {
      fail('dateityp');
    }
  }
  $endung = ERLAUBTE_TYPEN[$mime];

  $ordner = nachweisOrdner();
  if (!is_dir($ordner) || !is_writable($ordner)) fail('ordner_nicht_beschreibbar', 500);
  $name = bin2hex(random_bytes(16)) . '.' . $endung;
  if (!move_uploaded_file($f['tmp_name'], $ordner . '/' . $name)) fail('speichern_fehlgeschlagen', 500);
  @chmod($ordner . '/' . $name, 0640);

  // Anzeigenamen bereinigen: nur harmlose Zeichen behalten, keine Pfadangaben
  $roh = str_replace(['\\', '/'], '-', (string)$f['name']);
  $anzeige = (string)preg_replace('/[^\p{L}\p{N} ._()\-]+/u', '', $roh);
  $anzeige = trim($anzeige) !== '' ? mb_substr(trim($anzeige), 0, 120) : 'Nachweis';
  db()->prepare("INSERT INTO " . t('files') . "
      (id, absence_id, staff_id, art, dateiname, pfad, mime, groesse, hochgeladen_am, hochgeladen_von)
      VALUES (?,?,?,?,?,?,?,?,?,?)")
     ->execute([uuid(), $absenceId, (string)$eintrag['staff_id'], $art, $anzeige, $name,
                $mime, (int)$f['size'], date('c'), $_SESSION['user_name']]);
  // Haken "Nachweis liegt vor" automatisch setzen
  db()->prepare("UPDATE " . t('absences') . " SET nachweis = 1 WHERE id = ?")->execute([$absenceId]);
  logAction('nachweis_hochgeladen', $art . ' zu ' . $absenceId);
  out(['ok' => true]);
}

case 'datei': {
  requireLogin();
  $id = s($_GET, 'id');
  $st = db()->prepare("SELECT * FROM " . t('files') . " WHERE id = ?");
  $st->execute([$id]);
  $f = $st->fetch();
  if (!$f) fail('datei_unbekannt', 404);
  if (!darfNachweis((string)$f['staff_id'])) fail('keine_berechtigung', 403);
  $pfad = nachweisOrdner() . '/' . basename((string)$f['pfad']);
  if (!is_file($pfad)) fail('datei_fehlt', 404);

  header_remove('Content-Type');
  header('Content-Type: ' . $f['mime']);
  header('Content-Length: ' . filesize($pfad));
  $name = preg_replace('/["\r\n]/', '', (string)$f['dateiname']);
  header('Content-Disposition: ' . (empty($_GET['download']) ? 'inline' : 'attachment')
         . '; filename="' . $name . '"');
  header('X-Content-Type-Options: nosniff');
  header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; object-src 'self'; sandbox");
  header('Cache-Control: private, no-store');
  readfile($pfad);
  exit;
}

case 'del_datei': {
  requirePost(); requireLogin(); requireCsrf();
  $id = s(body(), 'id');
  $st = db()->prepare("SELECT * FROM " . t('files') . " WHERE id = ?");
  $st->execute([$id]);
  $f = $st->fetch();
  if (!$f) fail('datei_unbekannt', 404);
  if (!darfNachweisAendern((string)$f['staff_id'])) fail('keine_berechtigung', 403);
  @unlink(nachweisOrdner() . '/' . basename((string)$f['pfad']));
  db()->prepare("DELETE FROM " . t('files') . " WHERE id = ?")->execute([$id]);
  // Haken zuruecksetzen, wenn kein Nachweis mehr haengt
  $rest = db()->prepare("SELECT COUNT(*) c FROM " . t('files') . " WHERE absence_id = ?");
  $rest->execute([$f['absence_id']]);
  if ((int)$rest->fetch()['c'] === 0) {
    db()->prepare("UPDATE " . t('absences') . " SET nachweis = 0 WHERE id = ?")->execute([$f['absence_id']]);
  }
  logAction('nachweis_geloescht', (string)$f['dateiname']);
  out(['ok' => true]);
}

case 'aufraeumen': {
  requirePost(); requireLeitung(); requireCsrf();
  $jahre = (int)f(body(), 'jahre', 3);
  if ($jahre < 1) $jahre = 1;
  $grenze = date('c', strtotime('-' . $jahre . ' years'));
  $st = db()->prepare("SELECT * FROM " . t('files') . " WHERE hochgeladen_am < ?");
  $st->execute([$grenze]);
  $weg = $st->fetchAll();
  foreach ($weg as $f) {
    @unlink(nachweisOrdner() . '/' . basename((string)$f['pfad']));
    db()->prepare("DELETE FROM " . t('files') . " WHERE id = ?")->execute([$f['id']]);
  }
  logAction('nachweise_aufgeraeumt', count($weg) . ' Dateien aelter als ' . $jahre . ' Jahre');
  out(['ok' => true, 'geloescht' => count($weg)]);
}

case 'report': {
  requireLogin();
  if (!istLeitung() && !istSteuerberater()) fail('keine_berechtigung', 403);
  $jahr  = (int)($_GET['jahr'] ?? date('Y'));
  $monat = (int)($_GET['monat'] ?? 0);
  if ($jahr < 2000 || $jahr > 2100) $jahr = (int)date('Y');
  if ($monat < 0 || $monat > 12) $monat = 0;
  out(reportDaten($jahr, $monat, s($_GET, 'von'), s($_GET, 'bis')));
}

case 'report_csv': {
  requireLogin();
  if (!istLeitung() && !istSteuerberater()) fail('keine_berechtigung', 403);
  $jahr  = (int)($_GET['jahr'] ?? date('Y'));
  $monat = (int)($_GET['monat'] ?? 0);
  if ($monat < 0 || $monat > 12) $monat = 0;
  $d = reportDaten($jahr, $monat, s($_GET, 'von'), s($_GET, 'bis'));
  header_remove('Content-Type');
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="fehlzeiten-' . $jahr
         . ($monat ? '-' . sprintf('%02d', $monat) : '') . '.csv"');
  $o = fopen('php://output', 'w');
  fwrite($o, "\xEF\xBB\xBF");
  fputcsv($o, ['Zeitraum', $d['von'] . ' bis ' . $d['bis']], ';', '"', '');
  fputcsv($o, [], ';', '"', '');
  fputcsv($o, ['Mitarbeiter','Rolle','Eintritt','Austritt','Abwesend gesamt (AT)','Abwesend gesamt (KT)',
               'Urlaub (AT)','Sonderurlaub (AT)',
               'Unbezahlt (AT)','Krank (AT)','Krank (KT)','Kind krank (AT)','Mutterschutz/Elternzeit (KT)',
               'Fortbildung (AT)','Berufsschule (AT)','Ueberstundenabbau (AT)','Sonstiges (AT)',
               'Urlaubsanspruch','Uebertrag','genommen','geplant','Resturlaub'], ';', '"', '');
  foreach ($d['zeilen'] as $z) {
    fputcsv($o, [$z['name'], $z['rolle'], $z['eintritt'], $z['austritt'],
                 $z['abwesend_at'], $z['abwesend_kt'], $z['urlaub'], $z['sonderurlaub'],
                 $z['unbezahlt'], $z['krank_at'], $z['krank_kt'], $z['kind_at'], $z['mutterschutz_kt'],
                 $z['fortbildung'], $z['berufsschule'], $z['ueberstunden'], $z['sonstiges'],
                 $z['konto']['anspruch'], $z['konto']['uebertrag'], $z['konto']['genommen'],
                 $z['konto']['geplant'], $z['konto']['rest']], ';', '"', '');
  }
  fputcsv($o, [], ';', '"', '');
  fputcsv($o, ['Einzelne Zeitraeume (Krankheit, Kind krank, unbezahlt, Mutterschutz)'], ';', '"', '');
  fputcsv($o, ['Mitarbeiter','Art','Von','Bis','Arbeitstage','Kalendertage','Nachweis'], ';', '"', '');
  foreach ($d['zeilen'] as $z) {
    foreach ($z['perioden'] as $pz) {
      $label = $pz['typ'];
      foreach (TYPEN as $ty) if ($ty['key'] === $pz['typ']) $label = $ty['label'];
      fputcsv($o, [$z['name'], $label, $pz['von'], $pz['bis'], $pz['arbeitstage'],
                   $pz['kalendertage'], $pz['nachweis'] ? 'ja' : 'nein'], ';', '"', '');
    }
  }
  fclose($o);
  exit;
}

case 'mail_diagnose': {
  requirePost(); requireLeitung(); requireCsrf();
  $c = mailKonfig();
  $schritte = [];
  $merk = function (string $titel, bool $ok, string $text = '') use (&$schritte) {
    $schritte[] = ['titel' => $titel, 'ok' => $ok, 'text' => $text];
  };

  // 1. Was kann dieser Server ueberhaupt?
  $merk('PHP-Version', version_compare(PHP_VERSION, '7.4', '>='), PHP_VERSION);
  $merk('Verschlüsselung (OpenSSL)', extension_loaded('openssl'),
        extension_loaded('openssl') ? 'vorhanden' : 'fehlt - SMTP ist ohne OpenSSL nicht möglich');
  $gesperrt = array_map('trim', explode(',', (string)ini_get('disable_functions')));
  $sockOk = function_exists('stream_socket_client') && !in_array('stream_socket_client', $gesperrt, true);
  $merk('Ausgehende Verbindungen (stream_socket_client)', $sockOk,
        $sockOk ? 'erlaubt' : 'vom Hoster gesperrt - bitte auf "PHP mail()" umstellen');
  $mailOk = function_exists('mail') && !in_array('mail', $gesperrt, true);
  $merk('PHP-Funktion mail() als Rückfallweg', $mailOk, $mailOk ? 'vorhanden' : 'gesperrt');

  // 2. Angaben vollstaendig?
  $fehlt = [];
  foreach (['host' => 'Postausgangsserver', 'user' => 'Benutzername', 'pass' => 'Passwort',
            'von' => 'Absenderadresse'] as $k => $name) {
    if (($c[$k] ?? '') === '') $fehlt[] = $name;
  }
  if (!mailAdressen($c['an'])) $fehlt[] = 'Empfänger (An)';
  $merk('Angaben vollständig', !$fehlt, $fehlt ? 'Es fehlt: ' . implode(', ', $fehlt) : 'alles ausgefüllt');
  if (($c['von'] ?? '') !== '' && ($c['user'] ?? '') !== '') {
    $merk('Absender = Postfach', strcasecmp($c['von'], $c['user']) === 0,
          strcasecmp($c['von'], $c['user']) === 0
            ? 'stimmt überein'
            : 'Absender "' . $c['von'] . '" und Postfach "' . $c['user'] . '" sind verschieden – IONOS weist das ab');
  }

  // 3. Server erreichbar?
  if ($c['host'] !== '') {
    if (filter_var($c['host'], FILTER_VALIDATE_IP)) {
      $merk('Adresse des Mailservers', true, $c['host'] . ' (feste IP-Adresse)');
    } else {
      $ip = @gethostbyname($c['host']);
      $merk('Name auflösen (' . $c['host'] . ')', $ip !== $c['host'],
            $ip !== $c['host'] ? $ip : 'unbekannter Name – bitte Schreibweise prüfen');
    }
    if ($sockOk) {
      $pruefePort = function (int $port) use ($c) {
        $t0 = microtime(true);
        $fp = @stream_socket_client(($port === 465 ? 'ssl://' : '') . $c['host'] . ':' . $port,
              $errno, $errstr, 5, STREAM_CLIENT_CONNECT,
              stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]));
        $ms = (int)round((microtime(true) - $t0) * 1000);
        if (!$fp) return ['ok' => false, 'text' => 'nicht erreichbar (' . $errstr . ')'];
        $gruss = trim((string)fgets($fp, 512));
        @fclose($fp);
        return ['ok' => true, 'text' => $ms . ' ms · ' . mb_substr($gruss, 0, 90)];
      };
      $eigener = $pruefePort($c['port']);
      $portOffen = $eigener['ok'];
      $merk('Eingestellter Port ' . $c['port'] . ' erreichbar', $eigener['ok'], $eigener['text']);
      // Erst wenn der eingestellte Port klemmt, die Alternativen durchprobieren
      if (!$eigener['ok']) {
        foreach ([587, 465, 25] as $port) {
          if ($port === $c['port']) continue;
          $r2 = $pruefePort($port);
          if ($r2['ok']) $merk('Port ' . $port . ' wäre erreichbar', false,
                               'Bitte im Feld Port auf ' . $port . ' umstellen – ' . $r2['text']);
        }
      }
    }
  }

  // 4. Echte Anmeldung versuchen
  if ($c['art'] === 'php') {
    $merk('Versandart', true, 'PHP mail() – SMTP-Anmeldung wird nicht verwendet');
  } elseif ($sockOk && !$fehlt && !empty($portOffen)) {
    // Probelauf bis zur Empfaengerpruefung - es wird nichts verschickt
    $r = smtpSenden($c, ['pruefung@example.invalid'], [], 'Test', 'Test');
    $stufe = $r['stufe'] ?? 'unbekannt';
    $verbunden = !in_array($stufe, ['verbindung', 'angaben'], true);
    $merk('Verbindung zum Mailserver', $verbunden,
          $verbunden ? 'steht' : ($r['fehler'] ?? 'unbekannt'));
    if ($verbunden) {
      $tlsOk = $stufe !== 'tls';
      $merk('Verschlüsselung (STARTTLS/SSL)', $tlsOk, $tlsOk ? 'in Ordnung' : ($r['fehler'] ?? ''));
      if ($tlsOk) {
        $authOk = !in_array($stufe, ['anmeldung'], true);
        $merk('Anmeldung am Postfach', $authOk,
              $authOk ? 'Benutzername und Passwort werden akzeptiert' : ($r['fehler'] ?? ''));
        if ($authOk) {
          $absOk = $stufe !== 'absender';
          $merk('Absenderadresse akzeptiert', $absOk, $absOk ? 'in Ordnung' : ($r['fehler'] ?? ''));
        }
      }
    }
  }
  out(['schritte' => $schritte, 'art' => $c['art'], 'port' => $c['port']]);
}

case 'mail_test': {
  requirePost(); requireLeitung(); requireCsrf();
  $c = mailKonfig();
  $an = mailAdressen($c['an']);
  $cc = mailAdressen($c['cc']);
  if (!$an) fail('keine_empfaenger');
  $text = setting('praxisname', 'Praxis') . "\n\nTestnachricht des Praxis-Kalenders.\n\n"
        . "Wenn Sie diese E-Mail erhalten, funktioniert der Versand der Krankmeldungen.\n"
        . "Gesendet am " . date('d.m.Y H:i') . " Uhr.";
  $r = mailVersenden($c, $an, $cc, 'Test: Krankmeldungen aus dem Praxis-Kalender', $text);
  logAction('mail_test', $r['ok'] ? 'erfolgreich' : ('fehlgeschlagen: ' . ($r['fehler'] ?? '?')));
  if (!$r['ok']) out(['ok' => false, 'fehler' => $r['fehler'] ?? 'unbekannt'], 200);
  out(['ok' => true, 'an' => $an, 'cc' => $cc]);
}

case 'mail_erneut': {
  requirePost(); requireLeitung(); requireCsrf();
  $r = meldungMailSenden(s(body(), 'id'), true);
  if (!$r['ok']) out(['ok' => false, 'fehler' => $r['fehler'] ?? 'unbekannt'], 200);
  out(['ok' => true]);
}

default:
  fail('unbekannte_aktion', 404);
}

// ---------------------------------------------------------------- Nachgelagerte Helfer
function staffCacheLeeren(): void { $GLOBALS['STAFF_CACHE'] = []; }
/** Hochgeladene Nachweise mitsamt Dateien entfernen. */
function dateienLoeschen(string $spalte, string $wert): void {
  $st = db()->prepare("SELECT * FROM " . t('files') . " WHERE $spalte = ?");
  $st->execute([$wert]);
  foreach ($st->fetchAll() as $f) {
    @unlink(nachweisOrdner() . '/' . basename((string)$f['pfad']));
  }
  db()->prepare("DELETE FROM " . t('files') . " WHERE $spalte = ?")->execute([$wert]);
}

/** Alle Zugaenge (ohne Passwort-Hashes). */
function listUsers(): array {
  $rows = db()->query("SELECT id, username, anzeige, rolle, staff_id, aktiv, created_at
                       FROM " . t('users') . " ORDER BY rolle, username")->fetchAll();
  foreach ($rows as &$r) {
    $r['aktiv'] = (int)($r['aktiv'] ?? 1);
    if (in_array($r['rolle'], ['leitung','admin'], true)) $r['rolle'] = 'leitung';
    elseif ($r['rolle'] === 'steuerberater')                $r['rolle'] = 'steuerberater';
    else                                                    $r['rolle'] = 'mitarbeiter';
    $r['staff_id'] = (string)($r['staff_id'] ?? '');
  }
  return $rows;
}

function ladeStaff(string $id): ?array {
  if ($id === '') return null;
  $cache = &$GLOBALS['STAFF_CACHE'];
  if (!is_array($cache)) $cache = [];
  if (isset($cache[$id])) return $cache[$id];
  $st = db()->prepare("SELECT * FROM " . t('staff') . " WHERE id = ?");
  $st->execute([$id]);
  $r = $st->fetch();
  return $cache[$id] = ($r ?: null);
}
/** Arbeitstage neu berechnen - fuer eine Person oder (null) fuer alle. */
function neuBerechnen(?string $staffId): void {
  $pdo = db();
  if ($staffId !== null) {
    $st = $pdo->prepare("SELECT * FROM " . t('absences') . " WHERE staff_id = ?");
    $st->execute([$staffId]);
  } else {
    $st = $pdo->query("SELECT * FROM " . t('absences'));
  }
  $upd = $pdo->prepare("UPDATE " . t('absences') . " SET tage = ?, kalendertage = ? WHERE id = ?");
  foreach ($st->fetchAll() as $a) {
    $m = ladeStaff($a['staff_id']);
    if (!$m) continue;
    $r = arbeitstage($m, $a['von'], $a['bis'], (bool)$a['halbtag']);
    $upd->execute([$r['tage'], $r['kalendertage'], $a['id']]);
  }
}
