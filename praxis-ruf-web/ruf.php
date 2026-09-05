<?php
declare(strict_types=1);
require __DIR__ . '/inc.php';
anmeldungVerlangen();
$k = konfig();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Praxis-Ruf — <?= h($k['praxis']) ?></title>
<style>
  :root{
    --gruen-tief:#024629; --gruen:#15633d; --gruen-hell:#1d7a4c;
    --mint:#e3f0e9; --papier:#f2f7f4; --linie:#d5e4dc;
    --text:#17251e; --grau:#617068; --messing:#b8862c; --rot:#a8452f;
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--papier);color:var(--text);min-height:100vh;
       display:flex;flex-direction:column;
       font-family:"Segoe UI",system-ui,-apple-system,"Helvetica Neue",Arial,sans-serif;
       -webkit-font-smoothing:antialiased}
  button,input,select{font:inherit}
  button{cursor:pointer;border:0}
  :focus-visible{outline:3px solid var(--gruen-hell);outline-offset:2px}

  header{background:var(--gruen-tief);color:var(--papier);padding:14px 20px;
         display:flex;align-items:center;justify-content:space-between;gap:12px}
  header .praxis{font-size:11px;letter-spacing:.22em;text-transform:uppercase;opacity:.55}
  header .raum{font-size:20px;font-weight:600;margin-top:4px}
  header select{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.25);
                border-radius:9px;padding:9px 10px;font-size:14px}
  header select option{color:#17251e}
  header a{color:var(--papier);opacity:.55;font-size:12px;text-decoration:underline}

  main{flex:1;padding:20px;max-width:640px;width:100%;margin:0 auto}

  label.feld{display:block;font-size:13px;color:var(--grau);margin-bottom:7px}
  .eingabe{display:flex;gap:8px;margin-bottom:16px}
  .eingabe select{padding:16px 10px;border:1px solid var(--linie);border-radius:11px;background:#fff}
  .eingabe input{flex:1;min-width:0;padding:16px 15px;border:1px solid var(--linie);
                 border-radius:11px;background:#fff;font-size:19px}

  .ziele{display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr))}
  .ziele button{background:var(--gruen);color:#fff;border-radius:13px;padding:20px 12px;
                font-size:17px;font-weight:600;line-height:1.3;
                display:flex;flex-direction:column;align-items:center;gap:7px}
  .ziele button:active{background:var(--gruen-tief)}
  .ziele button .lampe{width:8px;height:8px;border-radius:50%;background:#8fe0b4}
  .ziele button .lampe.fehlt{background:#f0c14b}
  .beide{width:100%;margin-top:10px;background:transparent;color:var(--gruen);
         padding:13px;font-size:14px;text-decoration:underline;border-radius:10px}

  .trenner{height:1px;background:var(--linie);margin:24px 0 18px}
  h2{font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:var(--grau);margin-bottom:12px}

  ul.verlauf{list-style:none;background:#fff;border:1px solid var(--linie);
             border-radius:13px;overflow:hidden}
  ul.verlauf li{display:flex;align-items:center;gap:10px;padding:12px 14px;
                border-bottom:1px solid var(--linie)}
  ul.verlauf li:last-child{border-bottom:0}
  ul.verlauf .wer{flex:1;min-width:0}
  ul.verlauf .nm{font-size:16px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  ul.verlauf .meta{font-size:12px;color:var(--grau);margin-top:2px}
  ul.verlauf button{background:var(--mint);color:var(--gruen-tief);border-radius:9px;
                    padding:10px 15px;font-size:14px;font-weight:600}
  .leer{padding:18px 14px;color:var(--grau);font-size:14px;background:#fff;
        border:1px solid var(--linie);border-radius:13px}

  .durchsage{background:#fff;border:1px solid var(--linie);border-radius:13px;
             padding:14px;display:flex;align-items:center;gap:14px}
  .mikro{flex:0 0 auto;width:78px;height:78px;border-radius:50%;background:var(--gruen-tief);
         color:#fff;display:grid;place-items:center;font-size:11px;font-weight:600;
         letter-spacing:.05em;text-transform:uppercase;line-height:1.3;
         touch-action:none;user-select:none;-webkit-user-select:none;
         transition:transform .12s,background .12s}
  .mikro.laeuft{background:var(--rot);transform:scale(1.06)}
  .mikro:disabled{background:var(--linie);color:var(--grau);cursor:default}
  .durchsage .info{flex:1;min-width:0}
  .durchsage .info b{display:block;font-size:14px;margin-bottom:3px}
  .durchsage .info span{font-size:12.5px;color:var(--grau)}
  .durchsage select{margin-top:9px;width:100%;padding:10px;border:1px solid var(--linie);
                    border-radius:9px;background:#fff}

  .warnung{background:#fdf4e0;border:1px solid #e8d5a8;border-radius:11px;padding:12px 14px;
           font-size:13px;color:#5b4715;margin-bottom:16px;display:none}
  .warnung.an{display:block}

  .toast{position:fixed;left:50%;bottom:26px;transform:translateX(-50%) translateY(10px);
         background:var(--gruen-tief);color:#fff;padding:13px 22px;border-radius:999px;
         font-size:14.5px;opacity:0;pointer-events:none;transition:.22s;z-index:40;
         max-width:90vw;text-align:center}
  .toast.an{opacity:1;transform:translateX(-50%)}
  .toast.warn{background:var(--messing);color:#1a1408}
  @media (prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body>

<header>
  <div>
    <div class="praxis"><?= h($k['praxis']) ?></div>
    <div class="raum" id="raumName">Sprechzimmer</div>
  </div>
  <div style="text-align:right">
    <select id="zimmerWahl" aria-label="Mein Sprechzimmer">
      <?php foreach ($k['sprechzimmer'] as $z): ?>
        <option value="<?= h((string) $z['kurz']) ?>"><?= h($z['name']) ?></option>
      <?php endforeach; ?>
    </select><br>
    <a href="abmelden.php">abmelden</a>
  </div>
</header>

<main>
  <?php if (($schwach = passwortSchwach()) !== ''): ?>
    <div class="warnung an">
      <strong>Sicherheitshinweis:</strong> <?= h($schwach) ?>
      Das Passwort ist der einzige Schutz für alle Patientennamen in diesem
      System. Bitte in <code>config.php</code> ein längeres, eigenes Passwort
      eintragen — danach müssen sich alle Geräte einmal neu anmelden.
    </div>
  <?php endif; ?>

  <div class="warnung" id="warnung"></div>

  <label class="feld" for="name">Wen rufen Sie auf?</label>
  <div class="eingabe">
    <select id="anrede" aria-label="Anrede">
      <option value="">ohne</option>
      <option>Herr</option>
      <option>Frau</option>
    </select>
    <input id="name" placeholder="Name" autocomplete="off" autocapitalize="words"
           maxlength="60" enterkeyhint="go">
  </div>

  <div class="ziele" id="ziele"></div>
  <button class="beide" id="beide">In alle Wartezimmer rufen</button>

  <div class="trenner"></div>

  <h2>Zuletzt aufgerufen <span id="mitleser" style="text-transform:none;letter-spacing:0"></span></h2>
  <div id="verlaufBox"><div class="leer">Noch keine Aufrufe.</div></div>

  <div class="trenner"></div>

  <h2>Durchsage mit eigener Stimme</h2>
  <div class="durchsage">
    <button class="mikro" id="mikro">Halten<br>zum<br>Sprechen</button>
    <div class="info">
      <b id="mikroTitel">Gedrückt halten, sprechen, loslassen</b>
      <span id="mikroStatus">Die Aufnahme wird sofort abgespielt.</span>
      <select id="durchsageZiel"></select>
    </div>
  </div>
</main>

<p style="text-align:center;font-size:11px;opacity:.35;margin:14px 0 22px">
  Fassung <?= h(FASSUNG) ?>
</p>

<div class="toast" id="toast"></div>

<script>
(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) =>
    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  const konfig = <?= json_encode([
      'wartezimmer'  => $k['wartezimmer'],
      'sprechzimmer' => $k['sprechzimmer'],
  ], JSON_UNESCAPED_UNICODE) ?>;

  const TAKT_MS = 2000;
  let meinZimmer = konfig.sprechzimmer[0].kurz;
  let letzterStand = '';

  const geraetId = (() => {
    let id = null;
    try { id = localStorage.getItem('praxisruf-arzt'); } catch (e) {}
    if (!id) {
      id = Math.random().toString(16).slice(2) + Date.now().toString(16);
      try { localStorage.setItem('praxisruf-arzt', id); } catch (e) {}
    }
    return id.slice(0, 32);
  })();

  function toast(text, warn) {
    const el = $('toast');
    el.textContent = text;
    el.className = 'toast an' + (warn ? ' warn' : '');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.className = 'toast', 2600);
  }

  /* ---------- Eigenes Sprechzimmer ---------- */
  try {
    const gemerkt = localStorage.getItem('praxisruf-zimmer');
    if (gemerkt && konfig.sprechzimmer.some((z) => String(z.kurz) === gemerkt)) {
      meinZimmer = gemerkt;
    }
  } catch (e) {}
  $('zimmerWahl').value = meinZimmer;
  zimmerAnzeigen();

  $('zimmerWahl').addEventListener('change', () => {
    meinZimmer = $('zimmerWahl').value;
    try { localStorage.setItem('praxisruf-zimmer', meinZimmer); } catch (e) {}
    zimmerAnzeigen();
  });

  function zimmerAnzeigen() {
    const z = konfig.sprechzimmer.find((x) => String(x.kurz) === String(meinZimmer));
    $('raumName').textContent = z ? z.name : 'Sprechzimmer';
    document.title = 'Praxis-Ruf — ' + (z ? z.name : '');
  }

  /* ---------- Ziele aufbauen ---------- */
  $('ziele').innerHTML = konfig.wartezimmer.map((w) =>
    '<button type="button" data-wz="' + esc(w.id) + '">' +
    '<span class="lampe fehlt" data-lampe="' + esc(w.id) + '"></span>' + esc(w.name) + '</button>'
  ).join('');

  $('durchsageZiel').innerHTML =
    '<option value="alle">An alle Wartezimmer</option>' +
    konfig.wartezimmer.map((w) => '<option value="' + esc(w.id) + '">Nur ' + esc(w.name) + '</option>').join('');

  $('ziele').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-wz]');
    if (b) rufen(b.dataset.wz);
  });
  $('beide').addEventListener('click', () => rufen('alle'));
  $('name').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') rufen(konfig.wartezimmer[0].id);
  });

  /* ---------- Aufrufen ---------- */
  async function rufen(wartezimmer, vorgabe) {
    const name = vorgabe ? vorgabe.name : $('name').value.trim();
    const anrede = vorgabe ? vorgabe.anrede : $('anrede').value;
    if (!name) { $('name').focus(); return toast('Bitte zuerst einen Namen eingeben', true); }

    // Der Name geht ausschliesslich im POST-Koerper zum Server, nie in der Adresse.
    const koerper = new URLSearchParams({
      name, anrede: anrede || '', wartezimmer, kurz: meinZimmer
    });

    try {
      const antwort = await fetch('api.php?was=aufruf', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: koerper.toString()
      });
      if (antwort.status === 401) return toast('Anmeldung abgelaufen — bitte neu laden', true);
      const r = await antwort.json();
      if (!r.ok) return toast(r.fehler || 'Aufruf fehlgeschlagen', true);

      const wo = wartezimmer === 'alle' ? 'alle Wartezimmer'
        : (konfig.wartezimmer.find((w) => w.id === wartezimmer) || {}).name;

      if (r.erreicht === 0) toast('Kein Lautsprecher in ' + wo + ' verbunden', true);
      else toast(((anrede ? anrede + ' ' : '') + r.aufruf.name) + ' → ' + wo);

      if (!vorgabe) { $('name').value = ''; $('anrede').value = ''; $('name').focus(); }
      standHolen();
    } catch (e) {
      toast('Keine Verbindung zum Server', true);
    }
  }

  /* ---------- Stand holen (beide Sprechzimmer sehen dasselbe) ---------- */
  async function standHolen() {
    const zimmer = konfig.sprechzimmer.find((x) => String(x.kurz) === String(meinZimmer));
    const adresse = 'api.php?was=stand&rolle=arzt&geraet=' + encodeURIComponent(geraetId)
                  + '&sprechzimmer=' + encodeURIComponent(zimmer ? zimmer.name : '');
    try {
      const antwort = await fetch(adresse, { cache: 'no-store' });
      if (antwort.status === 401) {
        $('warnung').textContent = 'Die Anmeldung ist abgelaufen. Bitte die Seite neu laden.';
        $('warnung').classList.add('an');
        return;
      }
      const d = await antwort.json();

      for (const w of konfig.wartezimmer) {
        const l = document.querySelector('[data-lampe="' + w.id + '"]');
        if (!l) continue;
        const da = (d.lautsprecher && d.lautsprecher[w.id]) > 0;
        l.className = 'lampe' + (da ? '' : ' fehlt');
        l.closest('button').title = da
          ? 'Lautsprecher verbunden'
          : 'Kein Lautsprecher verbunden — der Aufruf wird nicht gehört';
      }

      const andere = (d.aerzte || []).filter((n) => n !== (zimmer ? zimmer.name : ''));
      $('mitleser').textContent = andere.length ? '· auch offen in ' + andere.join(', ') : '';

      const stand = JSON.stringify(d.verlauf);
      if (stand !== letzterStand) { letzterStand = stand; verlaufZeichnen(d.verlauf || []); }
    } catch (e) { /* naechster Takt versucht es erneut */ }
  }

  function verlaufZeichnen(verlauf) {
    if (!verlauf.length) {
      $('verlaufBox').innerHTML = '<div class="leer">Noch keine Aufrufe.</div>';
      return;
    }
    $('verlaufBox').innerHTML = '<ul class="verlauf">' + verlauf.map((v, i) => {
      const zeit = new Date(v.zeit * 1000).toLocaleTimeString('de-DE',
        { hour: '2-digit', minute: '2-digit' });
      const wz = (konfig.wartezimmer.find((w) => w.id === v.wartezimmer) || {}).name
              || (v.wartezimmer === 'alle' ? 'alle Wartezimmer' : v.wartezimmer);
      return '<li><div class="wer">' +
        '<div class="nm">' + esc(((v.anrede ? v.anrede + ' ' : '') + v.name).trim()) + '</div>' +
        '<div class="meta">' + esc(v.sprechzimmer) + ' → ' + esc(wz) + ' · ' + zeit + '</div>' +
        '</div><button type="button" data-nochmal="' + i + '">Nochmal</button></li>';
    }).join('') + '</ul>';
  }

  $('verlaufBox').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-nochmal]');
    if (!b) return;
    try {
      const v = JSON.parse(letzterStand)[Number(b.dataset.nochmal)];
      if (v) rufen(v.wartezimmer, v);
    } catch (f) {}
  });

  standHolen();
  setInterval(standHolen, TAKT_MS);

  /* ---------- Durchsage ---------- */
  const mikro = $('mikro');
  let recorder = null, teile = [], laeuft = false;

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
    mikro.disabled = true;
    $('mikroTitel').textContent = 'Mikrofon nicht verfügbar';
    $('mikroStatus').textContent = 'Bitte die Seite über https:// öffnen.';
    $('warnung').textContent = 'Für die Durchsage muss diese Seite über https:// geöffnet sein. '
      + 'Aufrufe funktionieren trotzdem.';
    $('warnung').classList.add('an');
  }

  async function aufnahmeStarten() {
    if (laeuft || mikro.disabled) return;
    try {
      const strom = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }
      });
      teile = [];
      recorder = new MediaRecorder(strom);
      recorder.ondataavailable = (e) => { if (e.data.size) teile.push(e.data); };
      recorder.onstop = async () => {
        strom.getTracks().forEach((t) => t.stop());
        const blob = new Blob(teile, { type: recorder.mimeType || 'audio/webm' });
        if (blob.size < 1200) { $('mikroStatus').textContent = 'Zu kurz — Taste länger halten.'; return; }
        $('mikroStatus').textContent = 'Wird gesendet …';
        try {
          const r = await fetch('api.php?was=durchsage&ziel='
              + encodeURIComponent($('durchsageZiel').value), {
            method: 'POST', headers: { 'Content-Type': blob.type }, body: blob
          }).then((x) => x.json());
          const wo = $('durchsageZiel').selectedOptions[0].textContent;
          if (r.erreicht === 0) toast('Kein Lautsprecher verbunden — nicht gehört', true);
          else toast('Durchsage läuft · ' + wo);
          $('mikroStatus').textContent = 'Die Aufnahme wird sofort abgespielt.';
        } catch (e) {
          $('mikroStatus').textContent = 'Senden fehlgeschlagen.';
        }
      };
      recorder.start();
      laeuft = true;
      mikro.classList.add('laeuft');
      mikro.innerHTML = 'Sprechen';
      $('mikroTitel').textContent = 'Aufnahme läuft';
      $('mikroStatus').textContent = 'Loslassen zum Senden.';
    } catch (e) {
      $('mikroStatus').textContent = 'Kein Zugriff auf das Mikrofon.';
    }
  }

  function aufnahmeBeenden() {
    if (!laeuft) return;
    laeuft = false;
    mikro.classList.remove('laeuft');
    mikro.innerHTML = 'Halten<br>zum<br>Sprechen';
    $('mikroTitel').textContent = 'Gedrückt halten, sprechen, loslassen';
    try { recorder.stop(); } catch (e) {}
  }

  mikro.addEventListener('pointerdown', (e) => { e.preventDefault(); aufnahmeStarten(); });
  ['pointerup', 'pointercancel', 'pointerleave'].forEach((ev) =>
    mikro.addEventListener(ev, aufnahmeBeenden));
  mikro.addEventListener('contextmenu', (e) => e.preventDefault());
})();
</script>
</body>
</html>
