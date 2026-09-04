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
    <select id="stimmWahl" aria-label="Stimme"><option value="">Stimme: automatisch</option></select><br>
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

<div class="hinweis">Diese Seite geöffnet lassen. Zum Ändern des Wartezimmers die Seite neu laden. &nbsp;·&nbsp; Fassung <?= h(FASSUNG) ?></div>

<script>
(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const konfig = <?= json_encode([
      'wartezimmer'     => $k['wartezimmer'],
      'gong'            => (bool) $k['gong'],
      'stimme'          => $k['stimme'],
      'wachton'         => (bool) $k['wachton'],
      'wachtonSekunden' => (int) $k['wachtonSekunden'],
      'wachtonHertz'    => (float) $k['wachtonHertz'],
      'wachtonStaerke'  => (float) $k['wachtonStaerke'],
      'gongStaerke'     => (float) $k['gongStaerke'],
      'gongPause'       => (float) $k['gongPauseSekunden'],
      'wiederholPause'  => (float) $k['wiederholPauseSekunden'],
  ], JSON_UNESCAPED_UNICODE) ?>;

  const TAKT_MS = 2000;          // so oft wird nachgefragt
  // Der Gong klingt aus, bevor der Name faellt; die Wiederholung setzt
  // erst nach einer Atempause ein. Beide Werte stehen in config.php.
  const GONGPAUSE_MS      = Math.round(Math.max(0, konfig.gongPause || 2) * 1000);
  const WIEDERHOLPAUSE_MS = Math.round(Math.max(0, konfig.wiederholPause || 2) * 1000);
  let raum = null, tonFrei = false, audioCtx = null, stimme = null;
  let letzteAufrufId = null, letzteDurchsageId = null;
  let fehlerZaehler = 0, sageUhren = [];
  // Safari kann eine SpeechSynthesisUtterance mitten im Sprechen (oder
  // sogar davor) unbemerkt aus dem Speicher raeumen, wenn nirgendwo eine
  // Referenz darauf gehalten wird — speak() kehrt sofort zurueck, und eine
  // rein lokale Variable in der aufrufenden Funktion ist danach nichts
  // mehr, das den Garbage Collector aufhaelt. Es wird kein Fehler
  // ausgeloest, es kommt nur einfach kein Ton. Diese Variable haelt die
  // jeweils aktuelle Ansage fest, bis sie fertig gesprochen ist.
  let aktiveAnsage = null;
  // Waehrend einer laufenden Ansage — auch in den Pausen zwischen Gong,
  // Name und Wiederholung — darf der Weckton nicht dazwischenfunken.
  let ansageLaeuft = false;
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

  /* ---------- Stummschalter umgehen (iOS/Safari) ---------- */
  // Safari behandelt selbst erzeugten Ton (Web Audio API, Sprachausgabe)
  // manchmal als "Umgebungsklang" und unterdrueckt ihn beim seitlichen
  // Stummschalter des iPad/iPhone — obwohl echte Medienwiedergabe (ein
  // <audio>-Element) davon unberuehrt bleibt. Ein kurzes, lautloses
  // <audio>-Element wird darum genau im Klick — also innerhalb der vom
  // Nutzer ausgeloesten Geste — abgespielt. Das stellt bei Safari auf die
  // Kategorie "Wiedergabe" um, die den Stummschalter ignoriert; alle
  // danach erzeugten Toene (Gong, Weckton, Sprachausgabe) profitieren
  // davon mit.
  const STUMM_WAV = 'data:audio/wav;base64,UklGRrQBAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YZABAACAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA';

  // EIN einziges Element fuer alles, was ueber <audio> laeuft. iOS/Safari
  // erteilt die Erlaubnis zum Abspielen naemlich dem Element, das waehrend
  // der Nutzergeste gelaufen ist — nicht der Seite als Ganzes. Ein spaeter
  // mit new Audio() erzeugtes Objekt darf ohne neue Geste nicht abspielen,
  // sein play() wird stillschweigend abgelehnt. Darum wird dieses Element
  // im Klick freigegeben und danach fuer jede Durchsage wiederverwendet.
  const tonElement = new Audio();
  tonElement.preload = 'auto';
  tonElement.addEventListener('ended', () => tonFertig());
  tonElement.addEventListener('error', () => tonFertig());

  function tonFreigeben() {
    try {
      tonElement.src = STUMM_WAV;
      const versprechen = tonElement.play();
      if (versprechen && versprechen.catch) versprechen.catch(() => {});
    } catch (e) {}
  }

  /* ---------- Sprachausgabe ---------- */
  // Welche Stimmen es gibt, bestimmt allein das Geraet — eine Webseite kann
  // keine mitbringen oder nachladen. Gewaehlt wird darum unter dem, was da
  // ist: erst die in config.php bevorzugten, sonst irgendeine deutsche. Wer
  // eine andere will, waehlt sie im Startbildschirm aus; die Wahl bleibt auf
  // diesem Geraet gespeichert.
  function deutscheStimmen() {
    let alle = [];
    try { alle = speechSynthesis.getVoices() || []; } catch (e) {}
    return alle.filter((v) => v.lang && v.lang.replace('_', '-').toLowerCase().startsWith('de'));
  }

  function gewuenschteStimme() {
    try { return localStorage.getItem('praxisruf-stimme') || ''; } catch (e) { return ''; }
  }

  function stimmeWaehlen() {
    const deutsch = deutscheStimmen();
    if (!deutsch.length) { stimme = null; return; }

    const gewuenscht = gewuenschteStimme();
    if (gewuenscht) {
      const eigene = deutsch.find((v) => v.name === gewuenscht);
      if (eigene) { stimme = eigene; return; }
    }
    for (const w of (konfig.stimme.bevorzugt || [])) {
      const t = deutsch.find((v) => v.name.toLowerCase().includes(String(w).toLowerCase()));
      if (t) { stimme = t; return; }
    }
    stimme = deutsch.find((v) => v.lang.replace('_', '-') === 'de-DE') || deutsch[0];
  }

  function stimmenAnbieten() {
    const feld = $('stimmWahl');
    if (!feld) return;
    const deutsch = deutscheStimmen();
    if (!deutsch.length) { feld.style.display = 'none'; return; }
    feld.style.display = '';
    const gewuenscht = gewuenschteStimme();
    feld.innerHTML = '<option value="">Stimme: automatisch</option>'
      + deutsch.map((v) => '<option value="' + v.name.replace(/"/g, '&quot;') + '">'
          + v.name.replace(/</g, '&lt;') + '</option>').join('');
    feld.value = deutsch.some((v) => v.name === gewuenscht) ? gewuenscht : '';
  }

  speechSynthesis.onvoiceschanged = () => { stimmeWaehlen(); stimmenAnbieten(); };
  stimmeWaehlen();
  stimmenAnbieten();
  // Manche Geraete melden ihre Stimmen erst kurz nach dem Laden nach.
  setTimeout(() => { stimmeWaehlen(); stimmenAnbieten(); }, 900);

  if ($('stimmWahl')) {
    $('stimmWahl').addEventListener('change', () => {
      try { localStorage.setItem('praxisruf-stimme', $('stimmWahl').value); } catch (e) {}
      stimmeWaehlen();
      // Sofort vorhoeren — das Antippen des Auswahlfelds gilt als Geste.
      tonFrei = true;
      sprechen('Frau Subaida Jar, bitte in Sprechzimmer eins.');
    });
  }

  function sprechen(text, fertig) {
    if (!tonFrei) { if (typeof fertig === 'function') fertig(); return; }
    const u = new SpeechSynthesisUtterance(text);
    u.lang = konfig.stimme.sprache || 'de-DE';
    // Eine einmal gemerkte Stimme kann ungueltig werden, sobald das Geraet
    // seine Stimmenliste neu aufbaut — dann wirft das Zuweisen. Ohne
    // Absicherung ginge daran die ganze Ansage verloren; so faellt sie nur
    // auf die Standardstimme des Geraets zurueck.
    try { if (stimme) u.voice = stimme; } catch (e) { stimme = null; }
    u.rate = konfig.stimme.tempo || 0.88;
    u.pitch = konfig.stimme.tonhoehe || 1.0;
    u.volume = konfig.stimme.lautstaerke || 1.0;
    // Nach dem Ende geht es weiter (Wiederholung, Lampe aus). Damit das
    // auch dann geschieht, wenn ein Geraet gar kein Ende meldet — das kommt
    // vor —, laeuft zusaetzlich eine Uhr mit reichlich Vorlauf. Was zuerst
    // eintrifft, gewinnt; der zweite Weg tut dann nichts mehr.
    let erledigt = false;
    const abschluss = () => {
      if (erledigt) return;
      erledigt = true;
      if (aktiveAnsage === u) aktiveAnsage = null;
      if (typeof fertig === 'function') fertig();
    };
    u.onend = abschluss;
    u.onerror = abschluss;
    // Grobe Schaetzung der Sprechdauer, grosszuegig bemessen.
    const geschaetzt = 1500 + text.length * 110 / (u.rate || 1);
    sageUhren.push(setTimeout(abschluss, geschaetzt));

    aktiveAnsage = u;   // Referenz halten, siehe Erklaerung oben
    speechSynthesis.speak(u);
  }

  // Beendet die laufende Ansage UND geloescht die noch geplanten Uhren,
  // damit nach einem neuen Aufruf nicht der vorherige Name nachklingt.
  function ansageAbbrechen() {
    sageUhren.forEach(clearTimeout);
    sageUhren = [];
    ansageLaeuft = false;
    try { tonElement.pause(); } catch (e) {}
    // cancel() nur, wenn tatsaechlich etwas laeuft oder ansteht. Ein
    // cancel() im Leerlauf bringt Safaris Sprachausgabe durcheinander —
    // der naechste speak()-Aufruf bleibt dann ohne Fehlermeldung stumm.
    try {
      if (speechSynthesis.speaking || speechSynthesis.pending) speechSynthesis.cancel();
    } catch (e) {}
  }

  // Ein einzelner Ton klingt nach Piepser. Gespielt wird darum ein Grundton
  // mit zwei leisen Obertoenen — das ergibt einen vollen, weichen Klang wie
  // bei einem Glockenspiel. Der Einsatz ist bewusst weich (kein Knacken),
  // das Ausklingen lang.
  function klangGeben(t, hz, dauer, staerke) {
    for (const [faktor, anteil] of [[1, 1], [2, 0.17], [3, 0.05]]) {
      const osz = audioCtx.createOscillator(), amp = audioCtx.createGain();
      osz.type = 'sine';
      osz.frequency.value = hz * faktor;
      const spitze = Math.max(0.0002, staerke * anteil);
      amp.gain.setValueAtTime(0.0001, t);
      amp.gain.exponentialRampToValueAtTime(spitze, t + 0.05);      // weicher Einsatz
      amp.gain.exponentialRampToValueAtTime(0.0001, t + dauer);     // langes Ausklingen
      osz.connect(amp).connect(audioCtx.destination);
      osz.start(t);
      osz.stop(t + dauer + 0.05);
    }
  }

  // Zwei Toene im Abstand einer Quarte, aufsteigend und ineinander
  // ausklingend: ruhig und freundlich statt alarmierend — ein Klang, den
  // man den ganzen Tag ueber immer wieder hoeren kann.
  function gong() {
    if (!tonFrei || !konfig.gong) return;
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      if (audioCtx.state === 'suspended') audioCtx.resume();
      const jetzt = audioCtx.currentTime;
      const staerke = Math.min(Math.max(konfig.gongStaerke || 0.16, 0.01), 0.5);
      klangGeben(jetzt,        523.25, 2.0, staerke);          // C5
      klangGeben(jetzt + 0.40, 698.46, 2.4, staerke * 0.92);   // F5
    } catch (e) {}
  }

  /* ---------- Weckton fuer Funklautsprecher ---------- */
  // Viele Bluetooth-Lautsprecher schalten sich nach einigen Minuten Stille ab
  // und schneiden nach dem Aufwachen die ersten Silben ab. Ein sehr leiser,
  // tiefer Ton haelt Verstaerker und Funkstrecke wach. Er ist im Raum nicht
  // zu hoeren, der Lautsprecher sieht aber ein Signal.
  let wachtonUhr = null;

  function wachtonSpielen() {
    if (!tonFrei || !konfig.wachton || !audioCtx) return;
    // Waehrend einer Ansage nicht dazwischenfunken — auch nicht in den
    // stillen Pausen zwischen Gong, Name und Wiederholung.
    if (ansageLaeuft || speechSynthesis.speaking) return;
    try {
      if (audioCtx.state === 'suspended') audioCtx.resume();
      const t = audioCtx.currentTime;
      const osz = audioCtx.createOscillator(), amp = audioCtx.createGain();
      osz.type = 'sine';
      osz.frequency.value = konfig.wachtonHertz || 60;
      const staerke = Math.min(Math.max(konfig.wachtonStaerke || 0.02, 0.001), 0.2);
      amp.gain.setValueAtTime(0.0001, t);
      amp.gain.exponentialRampToValueAtTime(staerke, t + 0.06);
      amp.gain.exponentialRampToValueAtTime(0.0001, t + 0.5);
      osz.connect(amp).connect(audioCtx.destination);
      osz.start(t);
      osz.stop(t + 0.55);
    } catch (e) {}
  }

  function wachtonStarten() {
    if (!konfig.wachton) return;
    clearInterval(wachtonUhr);
    const abstand = Math.max(10, konfig.wachtonSekunden || 60) * 1000;
    wachtonUhr = setInterval(wachtonSpielen, abstand);
  }

  /* ---------- Aufruf und Durchsage ---------- */
  function aufrufAnsagen(a) {
    const voll = ((a.anrede ? a.anrede + ' ' : '') + a.name).trim();
    $('letzter').textContent = 'zuletzt: ' + voll;
    lampe('spricht');

    ansageAbbrechen();
    ansageLaeuft = true;
    gong();

    // Angezeigt wird der echte Name, gesprochen die fuer eine deutsche
    // Stimme umgeschriebene Fassung (aus 'Zubaida' wird 'Subaida'). Fehlt
    // sie — etwa bei einem Aufruf aus einer aelteren Fassung —, wird der
    // Name genommen, wie er ist.
    const gesprochen = ((a.anrede ? a.anrede + ' ' : '') + (a.ansage || a.name)).trim();
    const satz = gesprochen + ', bitte in ' + a.sprechzimmer + '.';

    const schluss = () => { ansageLaeuft = false; lampe(''); };

    // Der Gong klingt aus, dann folgt die Ansage — nicht uebereinander.
    sageUhren.push(setTimeout(() => {
      sprechen(satz, () => {
        if (a.wiederholen === false) { schluss(); return; }
        // Die Wiederholung setzt erst nach einer Atempause ein, gemessen
        // ab dem Ende der ersten Ansage — nicht nach fester Uhr. So bleibt
        // der Abstand gleich, ob der Name kurz oder lang ist.
        sageUhren.push(setTimeout(() => sprechen(satz, schluss), WIEDERHOLPAUSE_MS));
      });
    }, GONGPAUSE_MS));
  }

  // Auch vor einer gesprochenen Durchsage geht der Gong voran: Wer im
  // Wartezimmer sitzt, wird erst aufmerksam und hoert dann die Ansage von
  // Anfang an, statt die ersten Worte zu verpassen.
  function durchsageSpielen(id) {
    if (!tonFrei) return;
    ansageAbbrechen();
    ansageLaeuft = true;
    lampe('spricht');
    gong();
    sageUhren.push(setTimeout(() => {
      try {
        tonElement.src = 'api.php?was=ton&id=' + encodeURIComponent(id);
        tonElement.load();
        const versprechen = tonElement.play();
        if (versprechen && versprechen.catch) versprechen.catch(tonFertig);
      } catch (e) { tonFertig(); }
    }, GONGPAUSE_MS));
  }

  function tonFertig() { ansageLaeuft = false; lampe(''); }

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
    // Als aller erste Handlung, noch synchron innerhalb der Klick-Geste —
    // das ist bei Safari entscheidend, ein spaeterer Aufruf (z. B. nach
    // einem await) wirkt nicht mehr zuverlaessig.
    tonFreigeben();

    raum = $('raumWahl').value;
    try { localStorage.setItem('praxisruf-raum', raum); } catch (e) {}

    tonFrei = true;
    const w = konfig.wartezimmer.find((x) => x.id === raum);

    // Die Bereitmeldung wird laut und mit echtem Text gesprochen, noch
    // waehrend die Nutzergeste gilt. Das ist der zuverlaessigste Zeitpunkt,
    // zu dem iOS eine Sprachausgabe zulaesst — und zugleich die Probe fuers
    // Personal: Wer den Gong hoert, aber diesen Satz nicht, weiss sofort,
    // dass auf diesem Geraet die Sprachausgabe fehlt. Eine lautlose
    // Probe-Ansage taugt dafuer nicht: Safari behandelt sie teils als
    // ueberhaupt keine Ansage, und der Ton bliebe spaeter stumm.
    sprechen('Lautsprecher ' + (w ? w.name : '') + ' ist bereit.');

    try {
      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      await audioCtx.resume();
    } catch (e) {}

    $('raumName').textContent = w ? w.name : raum;
    document.title = (w ? w.name : 'Lautsprecher') + ' — Praxis-Ruf';

    $('start').classList.add('weg');
    $('betrieb').classList.remove('weg');

    wachHalten();
    wachtonStarten();
    gong();                         // kurze Probe: der Lautsprecher ist zu hören
    nachfragen();
    setInterval(nachfragen, TAKT_MS);

    // Läuft diese Seite als Browser-Tab auf einem PC, der auch für anderes
    // benutzt wird (z. B. am Empfang), bremst Chrome das Nachfragen im
    // Hintergrund aus — nach einigen Minuten außerhalb des sichtbaren
    // Bereichs bis auf einmal pro Minute. Damit ein Aufruf nicht liegen
    // bleibt, wird beim Zurückkehren sofort nachgefragt, statt auf den
    // nächsten Takt zu warten.
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') nachfragen();
    });
  });
})();
</script>
</body>
</html>
