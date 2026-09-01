'use strict';

/*
 * Praxis-Ruf — Relay-Server
 *
 * Nimmt Aufrufe und Durchsagen von der Bedienseite entgegen und verteilt sie
 * per Server-Sent Events an die Wartezimmer-Bildschirme.
 *
 * Randbedingungen (siehe CLAUDE.md):
 *   - Nur Node-Standardmodule, keine Abhängigkeiten.
 *   - Nichts auf die Festplatte. Namen sind Gesundheitsdaten (Art. 9 DSGVO)
 *     und stehen ausschliesslich im Arbeitsspeicher — auch nicht im Protokoll.
 *   - Kein Internet, kein gemeinsamer Zustand mit der zweiten Instanz.
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const os = require('os');
const crypto = require('crypto');

const WURZEL = __dirname;
const OEFFENTLICH = path.join(WURZEL, 'public');

const AUDIO_LEBENSDAUER_MS = 2 * 60 * 1000;   // Durchsagen verfallen nach 2 Minuten
const AUDIO_MAX_BYTES = 5 * 1024 * 1024;      // eine einzelne Durchsage
const AUDIO_SPEICHER_MAX = 4;                 // gleichzeitig vorgehaltene Durchsagen
const AUFRUF_MAX_BYTES = 8 * 1024;            // JSON-Körper eines Aufrufs
const HERZSCHLAG_MS = 20 * 1000;              // hält SSE-Verbindungen offen
const NAME_MAX_ZEICHEN = 60;

const ANREDEN = ['', 'Herr', 'Frau'];

/* ------------------------------------------------------------------ *
 * Konfiguration
 * ------------------------------------------------------------------ */

function abbruch(text) {
  console.error('\n  Praxis-Ruf startet nicht:\n  ' + text + '\n');
  process.exit(1);
}

function konfigLaden() {
  const pfad = path.join(WURZEL, 'config.json');
  let roh;
  try {
    roh = fs.readFileSync(pfad, 'utf8');
  } catch (fehler) {
    abbruch('config.json wurde nicht gefunden (erwartet in ' + pfad + ').');
  }

  let k;
  try {
    k = JSON.parse(roh.replace(/^\uFEFF/, ''));
  } catch (fehler) {
    abbruch('config.json ist fehlerhaft — ' + fehler.message +
            '\n  Häufig fehlt ein Komma oder ein Anführungszeichen.');
  }

  if (!k.sprechzimmer || !k.sprechzimmer.name) {
    abbruch('In config.json fehlt "sprechzimmer": { "name": "..." }.');
  }
  if (!Array.isArray(k.wartezimmer) || k.wartezimmer.length === 0) {
    abbruch('In config.json fehlt die Liste "wartezimmer".');
  }
  for (const w of k.wartezimmer) {
    if (!w || typeof w.id !== 'string' || !/^[a-z0-9_-]+$/i.test(w.id)) {
      abbruch('Jedes Wartezimmer braucht eine einfache "id", z. B. "wz1".');
    }
    if (w.id === 'alle') abbruch('Die Wartezimmer-id "alle" ist reserviert.');
  }

  return {
    praxis: k.praxis || 'Praxis-Ruf',
    port: Number(k.port) || 8080,
    sprechzimmer: {
      name: String(k.sprechzimmer.name),
      kurz: String(k.sprechzimmer.kurz || k.sprechzimmer.name)
    },
    andereSprechzimmer: Array.isArray(k.andereSprechzimmer) ? k.andereSprechzimmer.map(String) : [],
    wartezimmer: k.wartezimmer.map((w) => ({ id: w.id, name: String(w.name || w.id) })),
    nurNachname: k.nurNachname === true,
    wiederholen: k.wiederholen !== false,
    gong: k.gong !== false,
    anzeigeDauerSekunden: Number(k.anzeigeDauerSekunden) > 0 ? Number(k.anzeigeDauerSekunden) : 45,
    stimme: { tempo: Number(k.stimme && k.stimme.tempo) || 0.88 }
  };
}

const konfig = konfigLaden();
const istWartezimmer = (id) => konfig.wartezimmer.some((w) => w.id === id);

/* ------------------------------------------------------------------ *
 * Zustand — ausschliesslich im Arbeitsspeicher
 * ------------------------------------------------------------------ */

const bildschirme = new Set();   // { raum, res }
const durchsagen = new Map();    // id -> { typ, daten, zeit }

function verteilen(ziel, ereignis, daten) {
  const nutzlast = 'event: ' + ereignis + '\ndata: ' + JSON.stringify(daten) + '\n\n';
  let erreicht = 0;
  for (const b of bildschirme) {
    if (ziel !== 'alle' && b.raum !== ziel) continue;
    try {
      b.res.write(nutzlast);
      erreicht++;
    } catch (fehler) {
      bildschirme.delete(b);
    }
  }
  return erreicht;
}

function raumZaehlung() {
  const raeume = {};
  for (const w of konfig.wartezimmer) raeume[w.id] = 0;
  for (const b of bildschirme) {
    if (raeume[b.raum] !== undefined) raeume[b.raum]++;
  }
  return raeume;
}

setInterval(() => {
  for (const b of bildschirme) {
    try { b.res.write(': herzschlag\n\n'); } catch (fehler) { bildschirme.delete(b); }
  }
}, HERZSCHLAG_MS);

setInterval(() => {
  const jetzt = Date.now();
  for (const [id, eintrag] of durchsagen) {
    if (jetzt - eintrag.zeit > AUDIO_LEBENSDAUER_MS) durchsagen.delete(id);
  }
}, 30 * 1000);

/* ------------------------------------------------------------------ *
 * Kleine Helfer
 * ------------------------------------------------------------------ */

function kopfzeilen(res, code, weitere) {
  res.writeHead(code, Object.assign({
    'Access-Control-Allow-Origin': '*',
    'Cache-Control': 'no-store'
  }, weitere));
}

function jsonAntwort(res, code, objekt) {
  const koerper = Buffer.from(JSON.stringify(objekt), 'utf8');
  kopfzeilen(res, code, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': koerper.length
  });
  res.end(koerper);
}

function textAntwort(res, code, text) {
  const koerper = Buffer.from(text, 'utf8');
  kopfzeilen(res, code, {
    'Content-Type': 'text/plain; charset=utf-8',
    'Content-Length': koerper.length
  });
  res.end(koerper);
}

function koerperLesen(req, maxBytes) {
  return new Promise((erfuellen, ablehnen) => {
    const teile = [];
    let laenge = 0;
    req.on('data', (stueck) => {
      laenge += stueck.length;
      if (laenge > maxBytes) {
        ablehnen(new Error('zu gross'));
        req.destroy();
        return;
      }
      teile.push(stueck);
    });
    req.on('end', () => erfuellen(Buffer.concat(teile)));
    req.on('error', ablehnen);
  });
}

// Steuerzeichen entfernen, Leerraum zusammenziehen, Länge begrenzen.
function nameSaeubern(roh) {
  return String(roh == null ? '' : roh)
    .replace(/[\u0000-\u001f\u007f]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, NAME_MAX_ZEICHEN);
}

function nurNachnameNehmen(name) {
  const teile = name.split(' ').filter(Boolean);
  return teile.length ? teile[teile.length - 1] : name;
}

/* ------------------------------------------------------------------ *
 * Statische Dateien aus public/
 * ------------------------------------------------------------------ */

const TYPEN = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.ico': 'image/x-icon'
};

function dateiAusliefern(res, pfadAusUrl) {
  let relativ;
  try {
    relativ = decodeURIComponent(pfadAusUrl).replace(/^\/+/, '');
  } catch (fehler) {
    return textAntwort(res, 400, 'Ungültige Adresse');
  }

  const ziel = path.resolve(OEFFENTLICH, relativ);
  if (ziel !== OEFFENTLICH && !ziel.startsWith(OEFFENTLICH + path.sep)) {
    return textAntwort(res, 403, 'Verboten');
  }

  fs.readFile(ziel, (fehler, inhalt) => {
    if (fehler) return textAntwort(res, 404, 'Nicht gefunden');
    kopfzeilen(res, 200, {
      'Content-Type': TYPEN[path.extname(ziel).toLowerCase()] || 'application/octet-stream',
      'Content-Length': inhalt.length
    });
    res.end(inhalt);
  });
}

/* ------------------------------------------------------------------ *
 * Endpunkte
 * ------------------------------------------------------------------ */

function ereignisStrom(req, res, adresse) {
  const raum = adresse.searchParams.get('raum') || '';
  if (!istWartezimmer(raum)) {
    return textAntwort(res, 400, 'Unbekanntes Wartezimmer. Fehlt "?raum=wz1" in der Adresse?');
  }

  req.socket.setTimeout(0);
  req.socket.setNoDelay(true);
  req.socket.setKeepAlive(true);

  kopfzeilen(res, 200, {
    'Content-Type': 'text/event-stream; charset=utf-8',
    'Connection': 'keep-alive',
    'X-Accel-Buffering': 'no'
  });
  res.write('retry: 3000\n\n');

  const bildschirm = { raum, res };
  bildschirme.add(bildschirm);

  // Der Bildschirm erfährt sofort, mit welchem Sprechzimmer er verbunden ist.
  res.write('event: bereit\ndata: ' + JSON.stringify({
    sprechzimmer: konfig.sprechzimmer,
    praxis: konfig.praxis
  }) + '\n\n');

  const abmelden = () => bildschirme.delete(bildschirm);
  req.on('close', abmelden);
  req.on('error', abmelden);
  res.on('error', abmelden);
}

async function aufruf(req, res) {
  let koerper;
  try {
    koerper = await koerperLesen(req, AUFRUF_MAX_BYTES);
  } catch (fehler) {
    return jsonAntwort(res, 413, { ok: false, fehler: 'Anfrage zu gross' });
  }

  let eingang;
  try {
    eingang = JSON.parse(koerper.toString('utf8'));
  } catch (fehler) {
    return jsonAntwort(res, 400, { ok: false, fehler: 'Fehlerhafte Anfrage' });
  }

  const name = nameSaeubern(eingang && eingang.name);
  if (!name) return jsonAntwort(res, 400, { ok: false, fehler: 'Kein Name angegeben' });

  const anrede = ANREDEN.includes(eingang.anrede) ? eingang.anrede : '';
  const ziel = String(eingang.wartezimmer || '');
  if (ziel !== 'alle' && !istWartezimmer(ziel)) {
    return jsonAntwort(res, 400, { ok: false, fehler: 'Unbekanntes Wartezimmer' });
  }

  const angezeigt = konfig.nurNachname ? nurNachnameNehmen(name) : name;
  const aufrufDaten = {
    name: angezeigt,
    anrede,
    sprechzimmer: konfig.sprechzimmer,
    wartezimmer: ziel,
    zeit: Date.now()
  };

  const erreicht = verteilen(ziel, 'aufruf', aufrufDaten);
  // Bewusst ohne Namen protokolliert — Gesundheitsdaten gehören nicht ins Log.
  console.log('Aufruf -> ' + ziel + ', erreichte Bildschirme: ' + erreicht);
  jsonAntwort(res, 200, { ok: true, erreicht, aufruf: aufrufDaten });
}

async function durchsage(req, res, adresse) {
  const ziel = adresse.searchParams.get('ziel') || 'alle';
  if (ziel !== 'alle' && !istWartezimmer(ziel)) {
    return jsonAntwort(res, 400, { ok: false, fehler: 'Unbekanntes Ziel' });
  }

  let daten;
  try {
    daten = await koerperLesen(req, AUDIO_MAX_BYTES);
  } catch (fehler) {
    return jsonAntwort(res, 413, { ok: false, fehler: 'Durchsage zu lang' });
  }
  if (!daten.length) return jsonAntwort(res, 400, { ok: false, fehler: 'Leere Aufnahme' });

  const typ = String(req.headers['content-type'] || 'audio/webm').split(';')[0].trim();
  if (!/^audio\//.test(typ)) {
    return jsonAntwort(res, 415, { ok: false, fehler: 'Kein Audioformat' });
  }

  const id = crypto.randomBytes(9).toString('hex');
  durchsagen.set(id, { typ: req.headers['content-type'] || typ, daten, zeit: Date.now() });

  // Älteste Aufnahmen fallen heraus, damit der Speicher nicht wächst.
  while (durchsagen.size > AUDIO_SPEICHER_MAX) {
    durchsagen.delete(durchsagen.keys().next().value);
  }

  const erreicht = verteilen(ziel, 'durchsage', { id, zeit: Date.now() });
  console.log('Durchsage -> ' + ziel + ', erreichte Bildschirme: ' + erreicht);
  jsonAntwort(res, 200, { ok: true, erreicht, id });
}

function audioAusliefern(res, id) {
  const eintrag = durchsagen.get(id);
  if (!eintrag || Date.now() - eintrag.zeit > AUDIO_LEBENSDAUER_MS) {
    durchsagen.delete(id);
    return textAntwort(res, 404, 'Durchsage nicht mehr vorhanden');
  }
  kopfzeilen(res, 200, {
    'Content-Type': eintrag.typ,
    'Content-Length': eintrag.daten.length
  });
  res.end(eintrag.daten);
}

/* ------------------------------------------------------------------ *
 * Server
 * ------------------------------------------------------------------ */

const server = http.createServer((req, res) => {
  const adresse = new URL(req.url, 'http://localhost');
  const pfad = adresse.pathname;

  if (req.method === 'OPTIONS') {
    kopfzeilen(res, 204, {
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type',
      'Access-Control-Max-Age': '86400'
    });
    return res.end();
  }

  if (req.method === 'GET' && pfad === '/api/events') return ereignisStrom(req, res, adresse);

  if (req.method === 'GET' && pfad === '/api/config') {
    return jsonAntwort(res, 200, {
      praxis: konfig.praxis,
      sprechzimmer: konfig.sprechzimmer,
      wartezimmer: konfig.wartezimmer,
      nurNachname: konfig.nurNachname,
      wiederholen: konfig.wiederholen,
      gong: konfig.gong,
      anzeigeDauerSekunden: konfig.anzeigeDauerSekunden,
      stimme: konfig.stimme
    });
  }

  if (req.method === 'GET' && pfad === '/api/status') {
    return jsonAntwort(res, 200, { raeume: raumZaehlung(), sprechzimmer: konfig.sprechzimmer });
  }

  if (req.method === 'GET' && pfad.startsWith('/api/audio/')) {
    return audioAusliefern(res, pfad.slice('/api/audio/'.length));
  }

  if (req.method === 'POST' && pfad === '/api/call') return aufruf(req, res);
  if (req.method === 'POST' && pfad === '/api/announce') return durchsage(req, res, adresse);
  if (req.method === 'POST' && pfad === '/api/clear') {
    return jsonAntwort(res, 200, { ok: true, erreicht: verteilen('alle', 'leeren', {}) });
  }

  if (req.method !== 'GET') return textAntwort(res, 405, 'Methode nicht erlaubt');

  if (pfad === '/') {
    kopfzeilen(res, 302, { 'Location': '/praxis.html' });
    return res.end();
  }
  dateiAusliefern(res, pfad);
});

/* ------------------------------------------------------------------ *
 * Start
 * ------------------------------------------------------------------ */

// family ist je nach Node-Fassung 'IPv4' oder 4.
function istIPv4(netz) {
  return netz.family === 'IPv4' || netz.family === 4;
}

function eigeneAdresse() {
  for (const liste of Object.values(os.networkInterfaces())) {
    for (const netz of liste || []) {
      if (istIPv4(netz) && !netz.internal) return netz.address;
    }
  }
  return 'localhost';
}

function mitPort(adresse) {
  return /:\d+$/.test(adresse) ? adresse : adresse + ':' + konfig.port;
}

server.on('error', (fehler) => {
  if (fehler.code === 'EADDRINUSE') {
    abbruch('Port ' + konfig.port + ' ist belegt.\n  Läuft Praxis-Ruf schon? ' +
            'Im Task-Manager den Prozess "Node.js" beenden und neu starten.');
  }
  abbruch(fehler.message);
});

server.listen(konfig.port, () => {
  const eigene = eigeneAdresse();
  const quellen = [mitPort(eigene)].concat(konfig.andereSprechzimmer.map(mitPort)).join(',');

  console.log('');
  console.log('  ' + konfig.praxis + ' — Praxis-Ruf, ' + konfig.sprechzimmer.name);
  console.log('  ' + '-'.repeat(60));
  console.log('');
  console.log('  Für die Ärztin an DIESEM PC:');
  console.log('      http://localhost:' + konfig.port + '/praxis.html');
  console.log('');
  console.log('  Für die Wartezimmer-Bildschirme:');
  for (const w of konfig.wartezimmer) {
    console.log('      ' + w.name);
    console.log('      http://' + mitPort(eigene) + '/wartezimmer.html?raum=' +
                w.id + '&quellen=' + quellen);
  }
  console.log('');
  console.log('  Dieses Fenster offen lassen. Es wird nichts gespeichert.');
  console.log('');
});
