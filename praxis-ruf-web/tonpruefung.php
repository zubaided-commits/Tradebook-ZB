<?php
declare(strict_types=1);
/**
 * Tonpruefung — Diagnoseseite.
 *
 * Auf einem iPad oder iPhone gibt es keine Entwicklerwerkzeuge: Bleibt eine
 * Seite stumm, sieht man nirgends, woran es liegt. Diese Seite probiert die
 * drei Wege, auf denen der Lautsprecher Ton erzeugt, einzeln durch und
 * schreibt jeden Schritt sichtbar mit — einmal direkt auf Tastendruck und
 * einmal zeitversetzt, denn genau darin unterscheiden sich die Regeln von
 * iOS. Das Ergebnis laesst sich abfotografieren und weitergeben.
 *
 * Die Seite gehoert nicht zum taeglichen Betrieb; sie wird nur zur
 * Fehlersuche aufgerufen.
 */
require __DIR__ . '/inc.php';
anmeldungVerlangen();
$k = konfig();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Tonprüfung — <?= h($k['praxis']) ?></title>
<style>
  :root{--gruen-tief:#024629;--gruen:#15633d;--papier:#f6f8f7;--linie:#d8e0db;
        --grau:#5d6b64;--rot:#b4553f;--messing:#e0b458}
  *{box-sizing:border-box;margin:0;padding:0}
  body{background:var(--papier);color:#17251e;padding:18px 14px 40px;
       font-family:"Segoe UI",system-ui,-apple-system,Arial,sans-serif;
       -webkit-text-size-adjust:100%}
  h1{font-size:19px;font-weight:600;margin-bottom:3px}
  .unter{font-size:13px;color:var(--grau);margin-bottom:16px;line-height:1.5}
  .karte{background:#fff;border:1px solid var(--linie);border-radius:13px;
         padding:14px;margin-bottom:12px}
  .karte h2{font-size:14px;font-weight:600;margin-bottom:9px}
  button{display:block;width:100%;font:inherit;font-size:16px;font-weight:600;
         padding:15px 12px;margin-bottom:9px;border:0;border-radius:11px;
         background:var(--gruen-tief);color:#fff;cursor:pointer;text-align:left}
  button:last-child{margin-bottom:0}
  button.spaet{background:var(--gruen)}
  button.neben{background:#fff;color:var(--gruen-tief);border:1.5px solid var(--linie)}
  button small{display:block;font-weight:400;font-size:12.5px;opacity:.8;margin-top:3px}
  /* Der Bericht muss auf einem Foto lesbar sein: fester Zeichensatz,
     genug Kontrast, keine zu kleine Schrift. */
  #bericht{background:#0f1a14;color:#d7e6dd;border-radius:11px;padding:12px;
           font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
           font-size:12.5px;line-height:1.65;white-space:pre-wrap;word-break:break-word;
           min-height:150px;max-height:60vh;overflow:auto}
  #bericht .gut{color:#8fe0b0}
  #bericht .schlecht{color:#ff9b86}
  #bericht .zeit{color:#7b8f85}
  .info{font-size:12px;color:var(--grau);line-height:1.6;white-space:pre-wrap;
        word-break:break-word}
  a.zurueck{display:inline-block;margin-top:16px;color:var(--gruen-tief);font-size:14px}
</style>
</head>
<body>

<h1>Tonprüfung</h1>
<p class="unter">Der Reihe nach antippen und dabei zuhören. Unten wird
   mitgeschrieben, was das Gerät jeweils gemeldet hat. Diesen Bericht
   abfotografieren und weitergeben.</p>

<div class="karte">
  <h2>Schritt 1 — zuerst antippen</h2>
  <button type="button" id="k0">1 · Ton freigeben
    <small>Muss einmal passieren, bevor die Seite überhaupt Ton machen darf.</small></button>
</div>

<div class="karte">
  <h2>Schritt 2 — direkt auf Tastendruck</h2>
  <button type="button" id="k1">2 · Gong <small>Web Audio — der Ton, den Sie bisher hören.</small></button>
  <button type="button" id="k2">3 · Sprachausgabe <small>Sagt einen Satz. Der Teil, der fehlt.</small></button>
  <button type="button" id="k3">4 · Tondatei <small>Ein Piepton als richtige Audiodatei.</small></button>
</div>

<div class="karte">
  <h2>Schritt 3 — erst 3 Sekunden später</h2>
  <p class="unter" style="margin-bottom:11px">So macht es der Lautsprecher im
     Betrieb: nicht auf Tastendruck, sondern wenn ein Aufruf eintrifft. iOS
     behandelt beides unterschiedlich streng.</p>
  <button type="button" class="spaet" id="k4">5 · Sprachausgabe nach 3 Sekunden</button>
  <button type="button" class="spaet" id="k5">6 · Tondatei nach 3 Sekunden</button>
</div>

<div class="karte">
  <h2>Schritt 4 — Ton vom Server</h2>
  <p class="unter" style="margin-bottom:11px">Genau der Weg, den eine
     gesprochene Durchsage nimmt: hochladen, dann vom Server zurückholen und
     abspielen. Safari holt solchen Ton stückweise — das wird hier mitgeprüft.</p>
  <button type="button" class="spaet" id="k7">7 · Durchsage hoch- und wieder abspielen</button>
</div>

<div class="karte">
  <h2>Bericht</h2>
  <div id="bericht"></div>
  <button type="button" class="neben" id="k6" style="margin-top:10px">Bericht leeren</button>
</div>

<div class="karte">
  <h2>Gerät</h2>
  <div class="info" id="geraet"></div>
</div>

<a class="zurueck" href="lautsprecher.php">← zurück zum Lautsprecher</a>

<script>
(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const PIEP = 'data:audio/wav;base64,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';

  /* ---------- Bericht ---------- */
  const feld = $('bericht');
  function melde(text, art) {
    const t = new Date();
    const uhr = String(t.getMinutes()).padStart(2, '0') + ':'
              + String(t.getSeconds()).padStart(2, '0') + '.'
              + String(t.getMilliseconds()).padStart(3, '0');
    const zeile = document.createElement('div');
    zeile.innerHTML = '<span class="zeit">' + uhr + '</span>  '
                    + '<span class="' + (art || '') + '"></span>';
    zeile.lastChild.textContent = text;
    feld.appendChild(zeile);
    feld.scrollTop = feld.scrollHeight;
  }
  $('k6').addEventListener('click', () => { feld.innerHTML = ''; lageZeigen(); });

  /* ---------- Ton freigeben ---------- */
  // Dasselbe Element, das spaeter die Tondateien abspielt: iOS erteilt die
  // Erlaubnis dem Element, das waehrend der Geste lief, nicht der Seite.
  const tonElement = new Audio();
  tonElement.preload = 'auto';
  for (const ereignis of ['play', 'playing', 'ended', 'pause', 'stalled', 'suspend']) {
    tonElement.addEventListener(ereignis, () => melde('   Tondatei: ' + ereignis));
  }
  tonElement.addEventListener('error', () => {
    const f = tonElement.error;
    melde('   Tondatei FEHLER: Code ' + (f ? f.code : '?')
        + (f && f.message ? ' — ' + f.message : ''), 'schlecht');
  });

  let audioCtx = null;

  $('k0').addEventListener('click', async () => {
    melde('1 · Ton freigeben — angetippt');
    try {
      tonElement.src = PIEP;
      const v = tonElement.play();
      if (v && v.then) {
        v.then(() => melde('   play() angenommen', 'gut'))
         .catch((f) => melde('   play() ABGELEHNT: ' + f.name + ' — ' + f.message, 'schlecht'));
      } else {
        melde('   play() ohne Rückmeldung (ältere Fassung)');
      }
    } catch (f) { melde('   Ausnahme: ' + f.message, 'schlecht'); }

    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      await audioCtx.resume();
      melde('   AudioContext: ' + audioCtx.state, audioCtx.state === 'running' ? 'gut' : 'schlecht');
    } catch (f) { melde('   AudioContext-Ausnahme: ' + f.message, 'schlecht'); }
    lageZeigen();
  });

  /* ---------- 2 · Gong ---------- */
  function gong() {
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      if (audioCtx.state === 'suspended') audioCtx.resume();
      melde('   AudioContext: ' + audioCtx.state, audioCtx.state === 'running' ? 'gut' : 'schlecht');
      const t = audioCtx.currentTime;
      const osz = audioCtx.createOscillator(), amp = audioCtx.createGain();
      osz.type = 'sine'; osz.frequency.value = 784;
      amp.gain.setValueAtTime(0.0001, t);
      amp.gain.exponentialRampToValueAtTime(0.25, t + 0.02);
      amp.gain.exponentialRampToValueAtTime(0.0001, t + 1.0);
      osz.connect(amp).connect(audioCtx.destination);
      osz.start(t); osz.stop(t + 1.1);
      melde('   Gong gestartet — jetzt müsste es klingen', 'gut');
    } catch (f) { melde('   Gong-Ausnahme: ' + f.message, 'schlecht'); }
  }
  $('k1').addEventListener('click', () => { melde('2 · Gong — angetippt'); gong(); });

  /* ---------- Sprachausgabe ---------- */
  let aktiveAnsage = null;   // Referenz halten, sonst raeumt Safari sie weg

  function sprechen(text, woher) {
    if (!('speechSynthesis' in window)) {
      melde('   speechSynthesis gibt es auf diesem Gerät NICHT', 'schlecht');
      return;
    }
    melde('   vorher: speaking=' + speechSynthesis.speaking
        + ' pending=' + speechSynthesis.pending
        + ' paused=' + speechSynthesis.paused);
    try {
      const u = new SpeechSynthesisUtterance(text);
      u.lang = konfigSprache;
      u.volume = 1; u.rate = 0.9; u.pitch = 1;
      if (gewaehlteStimme) {
        u.voice = gewaehlteStimme;
        melde('   Stimme: ' + gewaehlteStimme.name + ' (' + gewaehlteStimme.lang + ')');
      } else {
        melde('   keine Stimme gewählt — Gerät nimmt seine eigene');
      }
      u.onstart = () => melde('   ' + woher + ': START — es wird gesprochen', 'gut');
      u.onend   = () => melde('   ' + woher + ': ENDE', 'gut');
      u.onerror = (e) => melde('   ' + woher + ': FEHLER — ' + (e.error || 'unbekannt'), 'schlecht');
      aktiveAnsage = u;
      speechSynthesis.speak(u);
      melde('   speak() aufgerufen');
      // Kommt binnen 2 s kein START, hat das Geraet die Ansage verschluckt.
      setTimeout(() => {
        if (!speechSynthesis.speaking && !speechSynthesis.pending) {
          melde('   ⚠ nach 2 s kein START — die Ansage wurde verschluckt', 'schlecht');
        }
      }, 2000);
    } catch (f) { melde('   Ausnahme: ' + f.message, 'schlecht'); }
  }

  $('k2').addEventListener('click', () => {
    melde('3 · Sprachausgabe — angetippt');
    sprechen('Frau Muster, bitte in Sprechzimmer eins.', 'Sprachausgabe');
  });

  $('k4').addEventListener('click', () => {
    melde('5 · Sprachausgabe nach 3 Sekunden — angetippt, warte …');
    setTimeout(() => {
      melde('   3 Sekunden um, jetzt sprechen:');
      sprechen('Frau Muster, bitte in Sprechzimmer eins.', 'Sprachausgabe spät');
    }, 3000);
  });

  /* ---------- Tondatei ---------- */
  function tondatei(woher) {
    try {
      tonElement.src = PIEP;
      tonElement.load();
      const v = tonElement.play();
      if (v && v.then) {
        v.then(() => melde('   ' + woher + ': play() angenommen', 'gut'))
         .catch((f) => melde('   ' + woher + ': play() ABGELEHNT — ' + f.name
                           + ': ' + f.message, 'schlecht'));
      } else {
        melde('   ' + woher + ': play() ohne Rückmeldung');
      }
    } catch (f) { melde('   Ausnahme: ' + f.message, 'schlecht'); }
  }
  $('k3').addEventListener('click', () => { melde('4 · Tondatei — angetippt'); tondatei('Tondatei'); });
  $('k5').addEventListener('click', () => {
    melde('6 · Tondatei nach 3 Sekunden — angetippt, warte …');
    setTimeout(() => { melde('   3 Sekunden um:'); tondatei('Tondatei spät'); }, 3000);
  });

  /* ---------- 7 · Durchsage ueber den Server ---------- */
  // Laedt denselben Piepton als Durchsage hoch und spielt ihn vom Server
  // zurueck — der vollstaendige Weg einer Sprachdurchsage, samt der
  // stueckweisen Abholung, die Safari dabei verlangt.
  $('k7').addEventListener('click', async () => {
    melde('7 · Durchsage über den Server — angetippt');
    try {
      const roh = atob(PIEP.split(',')[1]);
      const felder = new Uint8Array(roh.length);
      for (let i = 0; i < roh.length; i++) felder[i] = roh.charCodeAt(i);
      const brocken = new Blob([felder], { type: 'audio/wav' });

      melde('   lade ' + brocken.size + ' Bytes hoch …');
      const antwort = await fetch('api.php?was=durchsage&ziel=alle',
        { method: 'POST', headers: { 'Content-Type': 'audio/wav' }, body: brocken });
      const d = await antwort.json();
      if (!antwort.ok || !d.id) {
        melde('   Hochladen FEHLGESCHLAGEN: ' + antwort.status + ' '
            + JSON.stringify(d), 'schlecht');
        return;
      }
      melde('   hochgeladen, Kennung ' + d.id, 'gut');

      // Erst so pruefen, wie Safari es tut: ein winziger Ausschnitt.
      const probe = await fetch('api.php?was=ton&id=' + encodeURIComponent(d.id),
        { headers: { 'Range': 'bytes=0-1' } });
      melde('   Ausschnitt-Anfrage beantwortet mit ' + probe.status
          + (probe.status === 206 ? ' (206 — richtig)' : ' (erwartet wäre 206)'),
        probe.status === 206 ? 'gut' : 'schlecht');

      tonElement.src = 'api.php?was=ton&id=' + encodeURIComponent(d.id);
      tonElement.load();
      const v = tonElement.play();
      if (v && v.then) {
        v.then(() => melde('   Serverton: play() angenommen', 'gut'))
         .catch((f) => melde('   Serverton: play() ABGELEHNT — ' + f.name
                           + ': ' + f.message, 'schlecht'));
      }
    } catch (f) { melde('   Ausnahme: ' + f.message, 'schlecht'); }
  });

  /* ---------- Stimmen und Gerät ---------- */
  const konfigSprache = <?= json_encode($k['stimme']['sprache'] ?? 'de-DE') ?>;
  const bevorzugt = <?= json_encode($k['stimme']['bevorzugt'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
  let gewaehlteStimme = null;

  function stimmenLesen() {
    if (!('speechSynthesis' in window)) return [];
    let alle = [];
    try { alle = speechSynthesis.getVoices() || []; } catch (e) {}
    gewaehlteStimme = null;
    for (const w of bevorzugt) {
      const t = alle.find((v) => v.name.toLowerCase().includes(String(w).toLowerCase()));
      if (t) { gewaehlteStimme = t; break; }
    }
    if (!gewaehlteStimme) {
      gewaehlteStimme = alle.find((v) => v.lang === konfigSprache)
                     || alle.find((v) => v.lang && v.lang.replace('_', '-').startsWith('de'))
                     || null;
    }
    return alle;
  }
  if ('speechSynthesis' in window) {
    speechSynthesis.onvoiceschanged = () => { stimmenLesen(); lageZeigen(); };
  }

  function lageZeigen() {
    const alle = stimmenLesen();
    const deutsche = alle.filter((v) => v.lang && v.lang.replace('_', '-').startsWith('de'));
    const zeilen = [
      'Browser: ' + navigator.userAgent,
      'speechSynthesis vorhanden: ' + ('speechSynthesis' in window ? 'ja' : 'NEIN'),
      'Stimmen insgesamt: ' + alle.length,
      'davon deutsch: ' + deutsche.length
        + (deutsche.length ? ' — ' + deutsche.map((v) => v.name + ' (' + v.lang + ')').join(', ') : ''),
      'gewählte Stimme: ' + (gewaehlteStimme
        ? gewaehlteStimme.name + ' (' + gewaehlteStimme.lang + ')' : 'keine'),
      'AudioContext: ' + (audioCtx ? audioCtx.state : 'noch nicht erstellt'),
      'Seite über https: ' + (location.protocol === 'https:' ? 'ja' : 'NEIN — ' + location.protocol),
      'Fassung: <?= h(FASSUNG) ?>',
    ];
    $('geraet').textContent = zeilen.join('\n');
  }

  lageZeigen();
  melde('Bereit. Bitte mit „1 · Ton freigeben" beginnen.');
  // Manche Geraete melden die Stimmen erst kurz nach dem Laden nach.
  setTimeout(lageZeigen, 800);
  setTimeout(lageZeigen, 2500);
})();
</script>
</body>
</html>
