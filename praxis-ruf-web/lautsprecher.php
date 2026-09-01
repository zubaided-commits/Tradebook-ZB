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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Lautsprecher — <?= h($k['praxis']) ?></title>
<style>
  :root{--gruen-tief:#024629;--gruen:#15633d;--mint:#a9d9bf;--papier:#f2f7f4;
        --messing:#e0b458;--rot:#b4553f}
  *{box-sizing:border-box;margin:0;padding:0}
  html,body{height:100%}
  body{background:#04170e;color:var(--papier);overflow:hidden;
       font-family:"Segoe UI",system-ui,-apple-system,Arial,sans-serif;
       -webkit-font-smoothing:antialiased;cursor:none;user-select:none}
  button,select{font:inherit}

  .schicht{position:fixed;inset:0;display:grid;place-items:center;text-align:center;padding:6vw}
  .weg{display:none}

  /* Einrichtung und Tonfreigabe */
  #start{background:var(--gruen-tief);z-index:30;cursor:pointer}
  #start h1{font-size:clamp(19px,3.2vh,32px);font-weight:300;margin-bottom:1.2vh}
  #start .praxis{font-size:12px;letter-spacing:.2em;text-transform:uppercase;
                 opacity:.5;margin-bottom:2.4vh}
  #start p{color:var(--mint);opacity:.75;font-size:clamp(13px,1.9vh,17px);
           max-width:36ch;margin:0 auto 3vh;line-height:1.55}
  #start select{padding:14px 12px;border-radius:11px;border:1px solid rgba(169,217,191,.4);
                background:#fff;color:#17251e;font-size:16px;margin-bottom:2.6vh;min-width:230px}
  #start button{background:var(--messing);color:#1a2b20;border:0;border-radius:999px;
                padding:1.1em 2.8em;font-size:clamp(16px,2.3vh,22px);font-weight:600;cursor:pointer}

  /* Betrieb — der Bildschirm darf ausgeschaltet sein */
  #betrieb{background:#04170e;z-index:10}
  #betrieb .innen{width:100%}
  .lampe{width:clamp(54px,9vh,96px);height:clamp(54px,9vh,96px);border-radius:50%;
         margin:0 auto 3vh;background:var(--gruen);position:relative}
  .lampe::after{content:"";position:absolute;inset:0;border-radius:50%;
                border:3px solid var(--gruen);animation:puls 3.4s ease-out infinite}
  .lampe.fehler{background:var(--rot)}
  .lampe.fehler::after{border-color:var(--rot)}
  .lampe.spricht{background:var(--messing)}
  .lampe.spricht::after{border-color:var(--messing);animation-duration:1.1s}
  @keyframes puls{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.7);opacity:0}}

  .raum{font-size:clamp(16px,2.6vh,26px);font-weight:600;letter-spacing:.04em}
  .lage{margin-top:1.4vh;font-size:clamp(12px,1.8vh,17px);color:var(--mint);opacity:.62}
  .letzter{margin-top:4vh;font-size:clamp(13px,2vh,19px);opacity:.4}
  .hinweis{position:fixed;left:0;right:0;bottom:2.4vh;text-align:center;
           font-size:clamp(10px,1.4vh,13px);opacity:.22;padding:0 4vw}
  @media (prefers-reduced-motion:reduce){*{animation:none!important}}
</style>
</head>
<body>

<div class="schicht" id="start">
  <div>
    <div class="praxis"><?= h($k['praxis']) ?></div>
    <h1>Lautsprecher im Wartezimmer</h1>
    <p>Wartezimmer auswählen und einmal antippen. Danach darf der Bildschirm
       dunkel bleiben — gebraucht wird nur der Lautsprecher.</p>
    <select id="raumWahl" aria-label="Wartezimmer">
      <?php foreach ($k['wartezimmer'] as $w): ?>
        <option value="<?= h($w['id']) ?>"><?= h($w['name']) ?></option>
      <?php endforeach; ?>
    </select><br>
    <button type="button" id="startKnopf">Ton freigeben und starten</button>
  </div>
</div>

<div class="schicht weg" id="betrieb">
  <div class="innen">
    <div class="lampe" id="lampe"></div>
    <div class="raum" id="raumName"></div>
    <div class="lage" id="lage">verbunden</div>
    <div class="letzter" id="letzter"></div>
  </div>
</div>

<div class="hinweis">Diese Seite geöffnet lassen. Zum Ändern des Wartezimmers die Seite neu laden.</div>

<script>
(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const konfig = <?= json_encode([
      'wartezimmer' => $k['wartezimmer'],
      'gong'        => (bool) $k['gong'],
      'stimme'      => $k['stimme'],
  ], JSON_UNESCAPED_UNICODE) ?>;

  const TAKT_MS = 2000;          // so oft wird nachgefragt
  let raum = null, tonFrei = false, audioCtx = null, stimme = null;
  let letzteAufrufId = null, letzteDurchsageId = null;
  let fehlerZaehler = 0, sageUhren = [];
  // Beim allerersten Nachfragen wird der vorgefundene Stand nur uebernommen,
  // nicht angesagt — sonst wiederholt ein neu gestartetes Geraet einen alten
  // Aufruf. Danach wird jeder neue Aufruf angesagt, auch der erste echte.
  let ersterDurchlauf = true;

  const geraetId = (() => {
    let id = null;
    try { id = localStorage.getItem('praxisruf-geraet'); } catch (e) {}
    if (!id) {
      id = Math.random().toString(16).slice(2) + Date.now().toString(16);
      try { localStorage.setItem('praxisruf-geraet', id); } catch (e) {}
    }
    return id.slice(0, 32);
  })();

  try {
    const gemerkt = localStorage.getItem('praxisruf-raum');
    if (gemerkt) $('raumWahl').value = gemerkt;
  } catch (e) {}

  /* ---------- Sprachausgabe ---------- */
  function stimmeWaehlen() {
    const alle = speechSynthesis.getVoices();
    if (!alle.length) return;
    for (const w of (konfig.stimme.bevorzugt || [])) {
      const t = alle.find((v) => v.name.toLowerCase().includes(w.toLowerCase()));
      if (t) { stimme = t; return; }
    }
    stimme = alle.find((v) => v.lang === 'de-DE')
          || alle.find((v) => v.lang && v.lang.startsWith('de')) || null;
  }
  speechSynthesis.onvoiceschanged = stimmeWaehlen;
  stimmeWaehlen();

  function sprechen(text) {
    if (!tonFrei) return;
    const u = new SpeechSynthesisUtterance(text);
    u.lang = konfig.stimme.sprache || 'de-DE';
    if (stimme) u.voice = stimme;
    u.rate = konfig.stimme.tempo || 0.88;
    u.pitch = konfig.stimme.tonhoehe || 1.0;
    u.volume = konfig.stimme.lautstaerke || 1.0;
    speechSynthesis.speak(u);
  }

  // Beendet die laufende Ansage UND geloescht die noch geplanten Uhren,
  // damit nach einem neuen Aufruf nicht der vorherige Name nachklingt.
  function ansageAbbrechen() {
    sageUhren.forEach(clearTimeout);
    sageUhren = [];
    try { speechSynthesis.cancel(); } catch (e) {}
  }

  function gong() {
    if (!tonFrei || !konfig.gong) return;
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      if (audioCtx.state === 'suspended') audioCtx.resume();
      for (const [hz, versatz] of [[784, 0], [1046.5, 0.16]]) {
        const t = audioCtx.currentTime + versatz;
        const osz = audioCtx.createOscillator(), amp = audioCtx.createGain();
        osz.type = 'sine'; osz.frequency.value = hz;
        amp.gain.setValueAtTime(0.0001, t);
        amp.gain.exponentialRampToValueAtTime(0.25, t + 0.02);
        amp.gain.exponentialRampToValueAtTime(0.0001, t + 1.1);
        osz.connect(amp).connect(audioCtx.destination);
        osz.start(t); osz.stop(t + 1.2);
      }
    } catch (e) {}
  }

  /* ---------- Aufruf und Durchsage ---------- */
  function aufrufAnsagen(a) {
    const voll = ((a.anrede ? a.anrede + ' ' : '') + a.name).trim();
    $('letzter').textContent = 'zuletzt: ' + voll;
    lampe('spricht');

    ansageAbbrechen();
    gong();
    const satz = voll + ', bitte in ' + a.sprechzimmer + '.';
    sageUhren.push(setTimeout(() => sprechen(satz), 1250));
    if (a.wiederholen !== false) sageUhren.push(setTimeout(() => sprechen(satz), 5200));
    sageUhren.push(setTimeout(() => lampe(''), 8000));
  }

  function durchsageSpielen(id) {
    if (!tonFrei) return;
    ansageAbbrechen();
    lampe('spricht');
    const ton = new Audio('api.php?was=ton&id=' + encodeURIComponent(id));
    ton.addEventListener('ended', () => lampe(''));
    ton.addEventListener('error', () => lampe(''));
    ton.play().catch(() => lampe(''));
  }

  function lampe(art) {
    $('lampe').className = 'lampe' + (art ? ' ' + art : '');
  }

  /* ---------- Nachfragen ---------- */
  async function nachfragen() {
    const adresse = 'api.php?was=stand&rolle=lautsprecher'
                  + '&raum=' + encodeURIComponent(raum)
                  + '&geraet=' + encodeURIComponent(geraetId);
    try {
      const antwort = await fetch(adresse, { cache: 'no-store' });
      if (antwort.status === 401) {
        $('lage').textContent = 'Anmeldung abgelaufen — Seite neu laden';
        lampe('fehler');
        return;
      }
      const d = await antwort.json();
      fehlerZaehler = 0;
      if ($('lampe').className === 'lampe fehler') lampe('');
      $('lage').textContent = 'verbunden';

      if (d.aufruf && d.aufruf.id !== letzteAufrufId) {
        letzteAufrufId = d.aufruf.id;
        const fuerUns = d.aufruf.wartezimmer === 'alle' || d.aufruf.wartezimmer === raum;
        if (!ersterDurchlauf && fuerUns) aufrufAnsagen(d.aufruf);
      }

      if (d.durchsage && d.durchsage.id !== letzteDurchsageId) {
        letzteDurchsageId = d.durchsage.id;
        const fuerUns = d.durchsage.ziel === 'alle' || d.durchsage.ziel === raum;
        if (!ersterDurchlauf && fuerUns) durchsageSpielen(d.durchsage.id);
      }

      ersterDurchlauf = false;
    } catch (e) {
      if (++fehlerZaehler >= 2) {
        lampe('fehler');
        $('lage').textContent = 'keine Verbindung zum Server';
      }
    }
  }

  /* ---------- Bildschirm wachhalten ---------- */
  async function wachHalten() {
    try {
      if ('wakeLock' in navigator) {
        await navigator.wakeLock.request('screen');
        document.addEventListener('visibilitychange', async () => {
          if (document.visibilityState === 'visible') {
            try { await navigator.wakeLock.request('screen'); } catch (e) {}
          }
        });
      }
    } catch (e) {}
  }

  /* ---------- Start ---------- */
  $('startKnopf').addEventListener('click', async () => {
    raum = $('raumWahl').value;
    try { localStorage.setItem('praxisruf-raum', raum); } catch (e) {}

    tonFrei = true;
    try {
      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      await audioCtx.resume();
      const stumm = new SpeechSynthesisUtterance(' ');
      stumm.volume = 0;
      speechSynthesis.speak(stumm);
    } catch (e) {}

    const w = konfig.wartezimmer.find((x) => x.id === raum);
    $('raumName').textContent = w ? w.name : raum;
    document.title = (w ? w.name : 'Lautsprecher') + ' — Praxis-Ruf';

    $('start').classList.add('weg');
    $('betrieb').classList.remove('weg');

    wachHalten();
    gong();                         // kurze Probe: der Lautsprecher ist zu hören
    nachfragen();
    setInterval(nachfragen, TAKT_MS);
  });
})();
</script>
</body>
</html>
