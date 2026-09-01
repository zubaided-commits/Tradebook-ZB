<?php
declare(strict_types=1);
require __DIR__ . '/inc.php';

sitzungStarten();
$k = konfig();
$fehler = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!anmeldungErlaubt()) {
        $fehler = 'Zu viele Fehlversuche. Bitte zehn Minuten warten.';
    } elseif (hash_equals((string) $k['passwort'], (string) ($_POST['passwort'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['angemeldet'] = true;
        fehlversucheLoeschen();
        header('Location: ' . (($_POST['rolle'] ?? '') === 'lautsprecher'
            ? 'lautsprecher.php' : 'ruf.php'));
        exit;
    } else {
        fehlversuchMerken();
        $fehler = 'Passwort stimmt nicht.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Anmelden — <?= h($k['praxis']) ?></title>
<style>
  :root{--gruen-tief:#024629;--gruen:#15633d;--mint:#e3f0e9;--papier:#f2f7f4;
        --linie:#d5e4dc;--text:#17251e;--grau:#617068;--rot:#a8452f}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--gruen-tief);color:var(--papier);min-height:100vh;
       display:grid;place-items:center;padding:24px;
       font-family:"Segoe UI",system-ui,-apple-system,Arial,sans-serif}
  .karte{background:var(--papier);color:var(--text);border-radius:18px;
         padding:34px 30px;width:100%;max-width:380px;text-align:center}
  .praxis{font-size:12px;letter-spacing:.2em;text-transform:uppercase;
          color:var(--grau);margin-bottom:8px}
  h1{font-size:22px;font-weight:600;margin-bottom:24px}
  label{display:block;text-align:left;font-size:13px;color:var(--grau);margin-bottom:7px}
  input[type=password]{width:100%;padding:15px;font:inherit;font-size:17px;
       border:1px solid var(--linie);border-radius:11px;background:#fff}
  .rolle{display:flex;gap:8px;margin:16px 0 20px}
  .rolle label{flex:1;display:block;text-align:center;border:1px solid var(--linie);
       border-radius:11px;padding:12px 8px;cursor:pointer;font-size:14px;
       color:var(--text);background:#fff;margin:0}
  .rolle input{position:absolute;opacity:0;pointer-events:none}
  .rolle input:checked + span{font-weight:700;color:var(--gruen)}
  .rolle label:has(input:checked){border-color:var(--gruen);background:var(--mint)}
  button{width:100%;background:var(--gruen);color:#fff;border:0;border-radius:999px;
         padding:16px;font:inherit;font-size:17px;font-weight:600;cursor:pointer}
  .fehler{background:#fdeceb;border:1px solid #f0c4bd;color:var(--rot);
          border-radius:10px;padding:11px;font-size:14px;margin-bottom:16px}
  .fuss{margin-top:20px;font-size:12px;color:var(--grau);line-height:1.5}
</style>
</head>
<body>
<form class="karte" method="post" autocomplete="off">
  <div class="praxis"><?= h($k['praxis']) ?></div>
  <h1>Praxis-Ruf</h1>

  <?php if ($fehler !== ''): ?>
    <div class="fehler"><?= h($fehler) ?></div>
  <?php endif; ?>

  <label for="pw">Passwort</label>
  <input type="password" id="pw" name="passwort" required autofocus
         autocomplete="current-password" enterkeyhint="go">

  <div class="rolle">
    <label><input type="radio" name="rolle" value="arzt" checked><span>Sprechzimmer</span></label>
    <label><input type="radio" name="rolle" value="lautsprecher"><span>Lautsprecher</span></label>
  </div>

  <button type="submit">Anmelden</button>

  <div class="fuss">
    „Sprechzimmer" ist die Bedienseite der Ärztin.<br>
    „Lautsprecher" ist das Gerät im Wartezimmer.
  </div>
</form>
</body>
</html>
