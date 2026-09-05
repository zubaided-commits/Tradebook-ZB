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
      'wartezimmer'     => array_map(static fn (array $w): array => $w + [
          // "Wartezimmer 1" spricht eine deutsche Stimme sonst als
          // "Wartezimmer erste" aus.
          'ansage' => zahlenAusschreiben((string) $w['name']),
      ], $k['wartezimmer']),
      'gong'            => (bool) $k['gong'],
      'stimme'          => $k['stimme'],
      'wachton'         => (bool) $k['wachton'],
      'wachtonSekunden' => (int) $k['wachtonSekunden'],
      'wachtonHertz'    => (float) $k['wachtonHertz'],
      'wachtonStaerke'  => (float) $k['wachtonStaerke'],
      'gongStaerke'     => (float) $k['gongStaerke'],
      'anzeigeDauer'    => (int) $k['anzeigeDauerSekunden'],
      'gongPause'       => (float) $k['gongPauseSekunden'],
      'wiederholPause'  => (float) $k['wiederholPauseSekunden'],
      'phrasenPause'    => (float) $k['phrasenPauseSekunden'],
  ], JSON_UNESCAPED_UNICODE) ?>;

  const TAKT_MS = 2000;          // so oft wird nachgefragt
  // Der Gong klingt aus, bevor der Name faellt; die Wiederholung setzt
  // erst nach einer Atempause ein. Beide Werte stehen in config.php.
  const GONGPAUSE_MS      = Math.round(Math.max(0, konfig.gongPause || 2) * 1000);
  const WIEDERHOLPAUSE_MS = Math.round(Math.max(0, konfig.wiederholPause || 2) * 1000);
  // Die kurze Atempause mitten in der Ansage, zwischen Namen und Zimmer.
  const PHRASENPAUSE_MS   = Math.round(Math.max(0, konfig.phrasenPause || 0.5) * 1000);
  // So alt darf ein Aufruf hoechstens sein, wenn das Geraet ihn zum ersten
  // Mal sieht — sonst wird er stillschweigend uebergangen.
  const ALTERSGRENZE_S = Math.max(20, konfig.anzeigeDauer || 45);
  // Laenger als das keine Antwort vom Server: Das war eine Unterbrechung,
  // kein normaler Takt. Danach wird nicht alles nachgeholt.
  const LUECKE_S = 15;
  // Wer so lange in der Schlange stand, wird nicht mehr ausgerufen — bis
  // dahin ist die Patientin ohnehin anders geholt worden.
  const WARTEGRENZE_S = 120;
  // Mehr als das wartet nie: Bei einem Rueckstau zaehlen die neuesten.
  const SCHLANGE_MAX = 3;
  let raum = null, tonFrei = false, audioCtx = null, stimme = null;
  let letzteDurchsageId = null;
  // Welche Aufrufe schon angesagt wurden — nach Kennung, damit keiner
  // doppelt kommt und keiner verlorengeht.
  let angesagt = new Set();
  // Wann kam die letzte Antwort vom Server, und wie weit geht die Uhr des
  // Servers gegenueber dieser Seite vor? Beides wird gebraucht, um das
  // Alter eines Aufrufs zu beurteilen, ohne sich auf die Uhr des Rechners
  // zu verlassen.
  let letzterErfolg = 0;
  let zeitversatz = 0;
  let hinweisBis = 0;
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

  // Ist das Fenster minimiert oder verdeckt, bremst der Browser nach
  // wenigen Minuten alle Zeitgeber auf einen Aufruf pro Minute herunter —
  // ein Patientenaufruf kaeme dann bis zu eine Minute zu spaet oder gar
  // nicht. Wovon der Browser eine Ausnahme macht: Seiten, die gerade Ton
  // ausgeben. Darum laeuft im Betrieb dauerhaft ein sehr leiser, tiefer
  // Ton in Schleife. Er ist im Raum nicht zu hoeren, haelt die Seite aber
  // wach — und nebenbei auch einen Bluetooth-Lautsprecher, der sich sonst
  // nach einigen Minuten Stille abschaltet.
  const WACH_WAV = 'data:audio/wav;base64,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';
  const wachElement = new Audio();
  wachElement.preload = 'auto';
  wachElement.loop = true;

  function wachHaltenTon() {
    if (!konfig.wachton) return;
    try {
      wachElement.src = WACH_WAV;
      const v = wachElement.play();
      if (v && v.catch) v.catch(() => {});
    } catch (e) {}
  }
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
    // Bei gleichem Namen hat die oertliche Stimme Vorrang: Stimmen, die
    // erst im Netz erzeugt werden, schicken den Namen der Patientin zum
    // Hersteller. Das soll niemand versehentlich bekommen — wer eine
    // solche Stimme will, waehlt sie unten ausdruecklich aus.
    const oertlich = deutsch.filter((v) => v.localService !== false);
    for (const w of (konfig.stimme.bevorzugt || [])) {
      const passt = (v) => v.name.toLowerCase().includes(String(w).toLowerCase());
      const t = oertlich.find(passt) || deutsch.find(passt);
      if (t) { stimme = t; return; }
    }
    stimme = oertlich.find((v) => v.lang.replace('_', '-') === 'de-DE')
          || oertlich[0]
          || deutsch.find((v) => v.lang.replace('_', '-') === 'de-DE')
          || deutsch[0];
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
          + v.name.replace(/</g, '&lt;')
          // Deutlich machen, welche Stimme den Namen aus der Praxis
          // hinaus zum Hersteller schickt.
          + (v.localService === false ? ' — über Internet' : '')
          + '</option>').join('');
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
      // Eine noch laufende Probe wird beendet, damit bei mehrfachem Waehlen
      // nicht zwei Stimmen durcheinanderreden. cancel() aber nur, wenn
      // wirklich etwas laeuft: im Leerlauf bringt es manche Browser aus dem
      // Tritt, und der naechste Versuch bliebe stumm.
      try {
        if (speechSynthesis.speaking || speechSynthesis.pending) speechSynthesis.cancel();
      } catch (e) {}
      tonFrei = true;
      sprechenFolge(['Frau Subaida Jar', 'bitte in Sprechzimmer eins.']);
    });
  }

  // Eine Ansage in Teilen sprechen, mit einer Atempause dazwischen. Aus
  // einem durchlaufenden Satz wird so ein Sprechen in zwei Boegen — erst
  // der Name, dann das Zimmer. Jeder Teil bekommt eine eigene Sprachmelodie
  // mit eigenem Anfang und Ende, statt in einem Zug heruntergelesen zu
  // werden. Das ist der groesste Unterschied zwischen "abgelesen" und
  // "gesprochen", den sich ohne andere Stimme herausholen laesst.
  function sprechenFolge(teile, fertig) {
    let i = 0;
    const weiter = () => {
      if (i >= teile.length) { if (fertig) fertig(); return; }
      sprechen(teile[i++], () => {
        if (i >= teile.length) { if (fertig) fertig(); return; }
        sageUhren.push(setTimeout(weiter, PHRASENPAUSE_MS));
      });
    };
    weiter();
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
  async function gong() {
    if (!tonFrei || !konfig.gong) return;
    try {
      audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
      // Erst abwarten, bis der Tonkanal wirklich laeuft. Frueher wurden die
      // Toene sofort eingeplant; war der Kanal in dem Moment noch angehalten,
      // verfiel die Einplanung und der Gong blieb aus.
      if (audioCtx.state === 'suspended') {
        try { await audioCtx.resume(); } catch (e) {}
      }
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

  /* ---------- Warteschlange ---------- */
  // Rufen beide Sprechzimmer fast gleichzeitig, darf die zweite Ansage der
  // ersten nicht ins Wort fallen. Jede Ansage wird darum eingereiht und
  // erst begonnen, wenn die vorige samt ihrer Wiederholung fertig ist.
  let warteschlange = [];
  let laeuftGerade  = false;
  let tonFertigRuf  = null;   // Rueckmeldung der gerade laufenden Aufnahme

  function einreihen(auftrag) {
    warteschlange.push(auftrag);
    // Staut es sich, zaehlen die neuesten Aufrufe: Wer ganz hinten steht,
    // waere ohnehin erst nach Minuten an der Reihe.
    if (warteschlange.length > SCHLANGE_MAX) {
      warteschlange = warteschlange.slice(-SCHLANGE_MAX);
    }
    naechsteAnsage();
  }

  // Wie alt ist ein Aufruf, gemessen an der Uhr des Servers?
  function alterSekunden(a) {
    if (!a || !a.zeit || !zeitversatz) return 0;
    return (Date.now() / 1000 + zeitversatz) - a.zeit;
  }

  function naechsteAnsage() {
    if (laeuftGerade) return;

    // Was waehrend der Wartezeit zu alt geworden ist, wird uebergangen.
    // Das Alter wird deshalb nicht nur beim Einreihen geprueft, sondern
    // auch, wenn ein Aufruf endlich an der Reihe waere.
    let auftrag = warteschlange.shift();
    while (auftrag && auftrag.art === 'aufruf' && alterSekunden(auftrag.a) > WARTEGRENZE_S) {
      auftrag = warteschlange.shift();
    }
    if (!auftrag) { ansageLaeuft = false; lampe(''); return; }

    laeuftGerade = true;
    ansageLaeuft = true;
    lampe('spricht');

    let fertigGemeldet = false;
    const fertig = () => {
      if (fertigGemeldet) return;      // onend und Uhr koennen beide kommen
      fertigGemeldet = true;
      laeuftGerade = false;
      // Zwischen zwei Ansagen dieselbe Atempause wie vor einer Wiederholung,
      // damit sie nicht ineinanderlaufen.
      sageUhren.push(setTimeout(naechsteAnsage, WIEDERHOLPAUSE_MS));
    };

    if (auftrag.art === 'durchsage') durchsageSpielen(auftrag.id, fertig);
    else                            aufrufAnsagen(auftrag.a, fertig);
  }

  /* ---------- Aufruf und Durchsage ---------- */
  function aufrufAnsagen(a, fertig) {
    const voll = ((a.anrede ? a.anrede + ' ' : '') + a.name).trim();
    $('letzter').textContent = 'zuletzt: ' + voll;

    // Angezeigt wird der echte Name, gesprochen die fuer eine deutsche
    // Stimme umgeschriebene Fassung (aus 'Zubaida' wird 'Subaida'). Fehlt
    // sie — etwa bei einem Aufruf aus einer aelteren Fassung —, wird der
    // Name genommen, wie er ist.
    const gesprochen = ((a.anrede ? a.anrede + ' ' : '') + (a.ansage || a.name)).trim();
    // In zwei Boegen, mit Atempause dazwischen: erst der Name, dann das
    // Zimmer. Die Zahl im Zimmernamen ist ausgeschrieben — sonst sagt eine
    // deutsche Stimme "Sprechzimmer erste" statt "Sprechzimmer eins".
    const teile = [gesprochen, 'bitte in ' + (a.zimmerAnsage || a.sprechzimmer) + '.'];

    gong();
    // Der Gong klingt aus, dann folgt die Ansage — nicht uebereinander.
    sageUhren.push(setTimeout(() => {
      sprechenFolge(teile, () => {
        if (a.wiederholen === false) { fertig(); return; }
        // Die Wiederholung setzt erst nach einer Atempause ein, gemessen
        // ab dem Ende der ersten Ansage — nicht nach fester Uhr. So bleibt
        // der Abstand gleich, ob der Name kurz oder lang ist.
        sageUhren.push(setTimeout(() => sprechenFolge(teile, fertig), WIEDERHOLPAUSE_MS));
      });
    }, GONGPAUSE_MS));
  }

  // Auch vor einer gesprochenen Durchsage geht der Gong voran: Wer im
  // Wartezimmer sitzt, wird erst aufmerksam und hoert die Ansage dann von
  // Anfang an, statt die ersten Worte zu verpassen.
  function durchsageSpielen(id, fertig) {
    tonFertigRuf = fertig;
    gong();
    sageUhren.push(setTimeout(() => {
      try {
        tonElement.src = 'api.php?was=ton&id=' + encodeURIComponent(id);
        tonElement.load();
        const versprechen = tonElement.play();
        if (versprechen && versprechen.catch) versprechen.catch(tonFertig);
      } catch (e) { tonFertig(); }
      // Faengt die Aufnahme gar nicht erst an oder meldet sie kein Ende,
      // bleibt die Warteschlange sonst fuer immer stehen.
      sageUhren.push(setTimeout(tonFertig, 90000));
    }, GONGPAUSE_MS));
  }

  function tonFertig() {
    const ruf = tonFertigRuf;
    tonFertigRuf = null;
    if (ruf) ruf();
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

      // War die Verbindung laenger weg als ein paar Takte, ist das eine
      // Unterbrechung — kein normaler Betrieb.
      const jetzt = Date.now();
      const luecke = letzterErfolg ? (jetzt - letzterErfolg) / 1000 : 0;
      const neustart = ersterDurchlauf || luecke > LUECKE_S;
      letzterErfolg = jetzt;
      if (d.zeit) zeitversatz = d.zeit - jetzt / 1000;

      if (jetzt > hinweisBis) $('lage').textContent = 'verbunden';

      // Nicht nur der zuletzt gemeldete Aufruf wird angesehen, sondern die
      // ganze Liste: Der Server merkt sich als "aktuell" immer nur einen
      // Aufruf. Rufen beide Sprechzimmer innerhalb derselben zwei Sekunden,
      // ueberschreibt der zweite den ersten, und der erste waere nie
      // angesagt worden. Im Verlauf stehen beide.
      const liste = Array.isArray(d.verlauf) ? d.verlauf.slice().reverse() : [];

      // Erst sammeln, was neu ist — entschieden wird danach.
      const neue = [];
      for (const a of liste) {
        if (!a || !a.id || angesagt.has(a.id)) continue;
        angesagt.add(a.id);
        if (a.wartezimmer !== 'alle' && a.wartezimmer !== raum) continue;
        neue.push(a);
      }
      // Die Liste ist begrenzt; alte Kennungen muessen nicht ewig mitlaufen.
      if (angesagt.size > 200) angesagt = new Set(liste.map((a) => a && a.id));

      if (neue.length) {
        const frisch = (a) => !d.zeit || !a.zeit || (d.zeit - a.zeit) <= ALTERSGRENZE_S;

        if (ersterDurchlauf) {
          // Beim Start wird nichts nachgeholt: Was vorher lief, ist
          // erledigt oder von Hand geholt worden.
          neue.length = 0;
        } else if (neustart) {
          // Nach einer Unterbrechung nicht den ganzen Rueckstand ausrufen.
          // Hoert die Praxis nichts, ruft sie denselben Namen mehrfach —
          // hinterher kaeme sonst eine ganze Kette von Ansagen, die
          // niemanden mehr betreffen. Ausgerufen wird nur der letzte
          // Aufruf, und auch der nur, wenn er noch aktuell ist.
          const letzter = neue[neue.length - 1];
          const uebersprungen = neue.length - (frisch(letzter) ? 1 : 0);
          neue.length = 0;
          if (frisch(letzter)) neue.push(letzter);
          if (uebersprungen > 0) {
            $('lage').textContent = 'Verbindung war weg — '
              + uebersprungen + ' ältere ' + (uebersprungen === 1 ? 'Aufruf' : 'Aufrufe')
              + ' übersprungen';
            hinweisBis = jetzt + 30000;
          }
        } else {
          // Im laufenden Betrieb: nur was noch aktuell ist.
          for (let i = neue.length - 1; i >= 0; i--) {
            if (!frisch(neue[i])) neue.splice(i, 1);
          }
        }

        // Mehrfach derselbe Name im selben Schwung — etwa weil im
        // Sprechzimmer mehrmals gedrueckt wurde — wird einmal ausgerufen.
        const gesehen = new Set();
        for (let i = neue.length - 1; i >= 0; i--) {
          const schluessel = (neue[i].name || '') + '|' + (neue[i].sprechzimmer || '');
          if (gesehen.has(schluessel)) neue.splice(i, 1);
          else gesehen.add(schluessel);
        }

        for (const a of neue) einreihen({ art: 'aufruf', a });
      }

      if (d.durchsage && d.durchsage.id !== letzteDurchsageId) {
        letzteDurchsageId = d.durchsage.id;
        const fuerUns = d.durchsage.ziel === 'alle' || d.durchsage.ziel === raum;
        // Eine Durchsage aus der Zeit der Unterbrechung ist überholt.
        if (!ersterDurchlauf && !neustart && fuerUns) {
          einreihen({ art: 'durchsage', id: d.durchsage.id });
        }
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
    sprechen('Lautsprecher ' + (w ? (w.ansage || w.name) : '') + ' ist bereit.');

    try {
      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      await audioCtx.resume();
    } catch (e) {}

    $('raumName').textContent = w ? w.name : raum;
    document.title = (w ? w.name : 'Lautsprecher') + ' — Praxis-Ruf';

    $('start').classList.add('weg');
    $('betrieb').classList.remove('weg');

    wachHalten();
    wachHaltenTon();      // Dauerton: haelt Seite und Lautsprecher wach
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
