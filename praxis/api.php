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
    "CREATE TABLE IF NOT EXISTS " . t('log') . " (
       id $id, ts $txt, nutzer $txt, aktion $txt, details $lng)",
  ];
  foreach ($sql as $q) $pdo->exec($q);
  // Nachtraeglich ergaenzte Spalten (bestehende Installationen)
  ensureColumn('users', 'staff_id', $txt);
  ensureColumn('users', 'aktiv', 'INT DEFAULT 1');
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_" . t('abs') . "_von ON " . t('absences') . " (von)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_" . t('abs') . "_staff ON " . t('absences') . " (staff_id)");
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
  ['key'=>'sonderurlaub', 'label'=>'Sonderurlaub',          'farbe'=>'#8b5cf6', 'konto'=>'sonder',      'nachweis'=>false],
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

  // Rollierende 12 Monate fuer die 6-Wochen-Grenze (EFZG)
  $seit = date('Y-m-d', strtotime('-365 days'));
  $st = $pdo->prepare("SELECT staff_id, von, bis, kalendertage FROM " . t('absences') . " WHERE typ = 'krank' AND bis >= ?");
  $st->execute([$seit]);
  $krank12 = [];
  foreach ($st->fetchAll() as $k) {
    $krank12[$k['staff_id']] = ($krank12[$k['staff_id']] ?? 0) + (int)$k['kalendertage'];
  }

  $heute = today();
  $res = [];
  foreach ($staff as $m) {
    $c = $carry[$m['id']] ?? null;
    $anspruch = ($c && $c['anspruch_override'] !== null && $c['anspruch_override'] !== '')
      ? (float)$c['anspruch_override'] : anspruchJahr($m, $jahr);
    $uebertrag = $c ? (float)$c['uebertrag'] : 0.0;
    $k = ['genommen'=>0.0,'geplant'=>0.0,'krank'=>0.0,'krank_kal'=>0,'kind'=>0.0,
          'fortbildung'=>0.0,'sonder'=>0.0,'stunden'=>0.0];
    foreach ($abs as $a) {
      if ($a['staff_id'] !== $m['id'] || $a['status'] === 'abgelehnt' || $a['status'] === 'storniert') continue;
      $tage = anteilImJahr($a, $m, $jahr);
      switch (typKonto($a['typ'])) {
        case 'urlaub':      if ($a['bis'] < $heute) $k['genommen'] += $tage; else $k['geplant'] += $tage; break;
        case 'krank':       $k['krank'] += $tage; $k['krank_kal'] += (int)$a['kalendertage']; break;
        case 'kind':        $k['kind'] += $tage; break;
        case 'fortbildung': $k['fortbildung'] += $tage; break;
        case 'sonder':      $k['sonder'] += $tage; break;
        case 'stunden':     $k['stunden'] += $tage; break;
      }
    }
    $res[$m['id']] = [
      'anspruch'   => round($anspruch, 1),
      'uebertrag'  => round($uebertrag, 1),
      'genommen'   => round($k['genommen'], 1),
      'geplant'    => round($k['geplant'], 1),
      'rest'       => round($anspruch + $uebertrag - $k['genommen'] - $k['geplant'], 1),
      'krank'      => round($k['krank'], 1),
      'kind'       => round($k['kind'], 1),
      'fortbildung'=> round($k['fortbildung'], 1),
      'sonder'     => round($k['sonder'], 1),
      'stunden'    => round($k['stunden'], 1),
      'krank_12m_kalendertage' => (int)($krank12[$m['id']] ?? 0),
      'hinweis_am' => $c['hinweis_am'] ?? '',
    ];
  }
  return $res;
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
  }
  unset($x);
  $bl = setting('bundesland', 'HH') ?? 'HH';
  $istL    = istLeitung();
  $eigene  = eigeneStaffId();
  $details = setting('details_sichtbar', '1') === '1';

  // Mitarbeiterinnen sehen fremde Eintraege ohne Notiz, auf Wunsch auch ohne Art
  if (!$istL) {
    foreach ($abs as &$x) {
      if ($x['staff_id'] !== $eigene) {
        $x['notiz'] = '';
        if (!$details) $x['typ'] = 'abwesend';
      }
    }
    unset($x);
  }

  $kt = konten($jahr);
  if (!$istL) $kt = isset($kt[$eigene]) ? [$eigene => $kt[$eigene]] : [];

  $offen = 0;
  foreach ($abs as $x) if ($x['status'] === 'beantragt') $offen++;

  out([
    'setup' => false, 'angemeldet' => true,
    'nutzer' => ['name' => $_SESSION['user_name'], 'rolle' => $istL ? 'leitung' : 'mitarbeiter',
                 'staff_id' => $eigene],
    'leitung' => $istL,
    'offene_antraege' => $offen,
    'users' => $istL ? listUsers() : [],
    'details_sichtbar' => $details ? 1 : 0,
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
  $pdo->prepare("DELETE FROM " . t('absences') . " WHERE staff_id = ?")->execute([$id]);
  $pdo->prepare("DELETE FROM " . t('carry') . " WHERE staff_id = ?")->execute([$id]);
  $pdo->prepare("DELETE FROM " . t('staff') . " WHERE id = ?")->execute([$id]);
  logAction('mitarbeiter_geloescht', $id);
  out(['ok' => true]);
}

case 'preview': {
  requireLogin();
  $d = body() ?: $_GET;
  $staff = ladeStaff(s($d, 'staff_id'));
  if (!$staff) fail('mitarbeiter_unbekannt');
  $von = s($d, 'von'); $bis = s($d, 'bis') ?: $von;
  if (!isDate($von) || !isDate($bis)) fail('datum_ungueltig');
  if ($bis < $von) fail('bis_vor_von');
  out(arbeitstage($staff, $von, $bis, !empty($d['halbtag'])));
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

  // Mitarbeiterinnen duerfen nur fuer sich selbst und nur als Antrag eintragen
  if (!istLeitung()) {
    if ($typ === 'geschlossen') fail('keine_berechtigung', 403);
    $eigene = eigeneStaffId();
    if ($eigene === '') fail('kein_mitarbeiter_verknuepft', 403);
    $d['staff_id'] = $eigene;
    $d['status'] = 'beantragt';
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
  if (!in_array($status, ['beantragt','genehmigt','abgelehnt','storniert'], true)) $status = 'genehmigt';
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
  out(['ok' => true, 'ids' => $gespeichert]);
}

case 'del_absence': {
  requirePost(); requireLogin(); requireCsrf();
  $id = s(body(), 'id');
  if ($id === '') fail('id_fehlt');
  if (!istLeitung()) {
    $st = db()->prepare("SELECT * FROM " . t('absences') . " WHERE id = ?");
    $st->execute([$id]);
    $alt = $st->fetch();
    if (!$alt || $alt['staff_id'] !== eigeneStaffId() || $alt['status'] !== 'beantragt') {
      fail('keine_berechtigung', 403);
    }
  }
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
  if (isset($d['details_sichtbar'])) setSetting('details_sichtbar', !empty($d['details_sichtbar']) ? '1' : '0');
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
  if (!in_array($rolle, ['leitung', 'mitarbeiter'], true)) fail('rolle_ungueltig');
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
  out(['ok' => true]);
}

default:
  fail('unbekannte_aktion', 404);
}

// ---------------------------------------------------------------- Nachgelagerte Helfer
function staffCacheLeeren(): void { $GLOBALS['STAFF_CACHE'] = []; }
/** Alle Zugaenge (ohne Passwort-Hashes). */
function listUsers(): array {
  $rows = db()->query("SELECT id, username, anzeige, rolle, staff_id, aktiv, created_at
                       FROM " . t('users') . " ORDER BY rolle, username")->fetchAll();
  foreach ($rows as &$r) {
    $r['aktiv'] = (int)($r['aktiv'] ?? 1);
    $r['rolle'] = in_array($r['rolle'], ['leitung','admin'], true) ? 'leitung' : 'mitarbeiter';
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
