'use strict';
/**
 * Praxis-Ruf — Einzelplatz-Variante
 *
 * Läuft auf dem PC EINES Sprechzimmers. Jedes Sprechzimmer hat seine eigene,
 * unabhängige Installation. Die Wartezimmer-Bildschirme hören beiden zu.
 *
 * Es wird NICHTS auf die Festplatte geschrieben — keine Namen, keine Liste,
 * kein Protokoll. Alles steht nur im Arbeitsspeicher und ist nach dem
 * Beenden weg.
 *
 * Node.js 18+.  Start:  node server.js
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const os = require('os');

const PUBLIC = path.join(__dirname, 'public');
const config = JSON.parse(fs.readFileSync(path.join(__dirname, 'config.json'), 'utf8'));
const PORT = process.env.PORT || config.port || 8080;

const clients = new Set();     // offene Bildschirm-Verbindungen
const clips = new Map();       // Sprachdurchsagen, max. 2 Minuten im RAM

const newId = () => crypto.randomBytes(6).toString('hex');

function broadcast(ereignis, ziel) {
  const zeile = `data: ${JSON.stringify(ereignis)}\n\n`;
  let zahl = 0;
  for (const c of clients) {
    if (ziel !== 'alle' && c.raum !== ziel) continue;
    try { c.res.write(zeile); zahl++; } catch (e) {}
  }
  return zahl;
}

function readBody(req, limit = 8 * 1024 * 1024) {
  return new Promise((resolve, reject) => {
    const teile = []; let n = 0;
    req.on('data', (d) => {
      n += d.length;
      if (n > limit) { reject(new Error('zu gross')); req.destroy(); return; }
      teile.push(d);
    });
    req.on('end', () => resolve(Buffer.concat(teile)));
    req.on('error', reject);
  });
}

// Liefert den JSON-Koerper einer Anfrage. Ein leerer Koerper ergibt {} —
// POST /api/clear wird laut Doku ohne Koerper aufgerufen.
async function koerperJson(req) {
  const roh = (await readBody(req)).toString('utf8').trim();
  return roh ? JSON.parse(roh) : {};
}

function json(res, obj, code = 200) {
  res.writeHead(code, {
    'Content-Type': 'application/json; charset=utf-8',
    'Cache-Control': 'no-store',
    'Access-Control-Allow-Origin': '*'
  });
  res.end(JSON.stringify(obj));
}

const MIME = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
               '.css': 'text/css; charset=utf-8', '.json': 'application/json; charset=utf-8',
               '.ico': 'image/x-icon', '.png': 'image/png', '.svg': 'image/svg+xml' };

function serveStatic(req, res, urlPath) {
  let rel = decodeURIComponent(urlPath.split('?')[0]);
  if (rel === '/') rel = '/praxis.html';
  const datei = path.join(PUBLIC, path.normalize(rel).replace(/^(\.\.[\/\\])+/, ''));
  if (!datei.startsWith(PUBLIC)) { res.writeHead(403); return res.end(); }
  fs.readFile(datei, (err, data) => {
    if (err) { res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' }); return res.end('Nicht gefunden'); }
    res.writeHead(200, {
      'Content-Type': MIME[path.extname(datei)] || 'application/octet-stream',
      'Cache-Control': 'no-store',
      'Access-Control-Allow-Origin': '*'
    });
    res.end(data);
  });
}

function raumZahlen() {
  const m = {};
  for (const c of clients) m[c.raum] = (m[c.raum] || 0) + 1;
  return m;
}

let letzteMeldung = '';
function meldeStatus() {
  const m = raumZahlen();
  const text = config.wartezimmer.map(w => `${w.name}: ${m[w.id] ? 'verbunden' : '—'}`).join('    ');
  if (text !== letzteMeldung) { letzteMeldung = text; console.log('  Bildschirme   ' + text); }
}

function eigeneIP() {
  for (const netz of Object.values(os.networkInterfaces())) {
    for (const n of netz) if (n.family === 'IPv4' && !n.internal) return n.address;
  }
  return '127.0.0.1';
}
const basisAdresse = () => `http://${eigeneIP()}:${PORT}`;

async function handler(req, res) {
  const url = new URL(req.url, 'http://localhost');
  const p = url.pathname;

  if (req.method === 'OPTIONS') {
    res.writeHead(204, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type'
    });
    return res.end();
  }

  // --- Bildschirm meldet sich an ---
  if (p === '/api/events') {
    const raum = url.searchParams.get('raum') || 'wz1';
    res.writeHead(200, {
      'Content-Type': 'text/event-stream; charset=utf-8',
      'Cache-Control': 'no-cache, no-transform',
      'Connection': 'keep-alive',
      'Access-Control-Allow-Origin': '*',
      'X-Accel-Buffering': 'no'
    });
    res.write(': verbunden\n\n');
    const c = { res, raum };
    clients.add(c);
    meldeStatus();
    const puls = setInterval(() => { try { res.write(': ping\n\n'); } catch (e) {} }, 20000);
    req.on('close', () => { clearInterval(puls); clients.delete(c); meldeStatus(); });
    return;
  }

  if (p === '/api/config') return json(res, config);
  if (p === '/api/status') return json(res, { raeume: raumZahlen() });

  // --- Aufruf ---
  if (p === '/api/call' && req.method === 'POST') {
    let b;
    try { b = await koerperJson(req); }
    catch (e) { return json(res, { fehler: 'Fehlerhafte Anfrage' }, 400); }
    const roh = String(b.name || '').trim();
    if (!roh) return json(res, { fehler: 'Kein Name angegeben' }, 400);

    const name = config.nurNachname ? roh.split(' ').slice(-1)[0] : roh.slice(0, 60);
    const ziel = b.wartezimmer || 'alle';
    const aufruf = {
      type: 'aufruf',
      id: newId(),
      anrede: String(b.anrede || '').slice(0, 10),
      name,
      sprechzimmer: config.sprechzimmer.name,
      sprechzimmerKurz: config.sprechzimmer.kurz || '',
      wiederholen: config.wiederholen !== false,
      ts: Date.now()
    };
    return json(res, { ok: true, aufruf, erreicht: broadcast(aufruf, ziel) });
  }

  // --- Live-Durchsage annehmen ---
  if (p === '/api/announce' && req.method === 'POST') {
    const ziel = url.searchParams.get('ziel') || 'alle';
    const buf = await readBody(req);
    if (!buf.length) return json(res, { fehler: 'Keine Aufnahme empfangen' }, 400);
    const id = newId();
    clips.set(id, { buf, typ: req.headers['content-type'] || 'audio/webm' });
    setTimeout(() => clips.delete(id), 120000);
    const erreicht = broadcast({ type: 'durchsage', url: `${basisAdresse()}/api/audio/${id}`, ts: Date.now() }, ziel);
    return json(res, { ok: true, erreicht });
  }

  // --- Live-Durchsage ausliefern ---
  if (p.startsWith('/api/audio/')) {
    const clip = clips.get(p.split('/').pop());
    if (!clip) { res.writeHead(404, { 'Access-Control-Allow-Origin': '*' }); return res.end(); }
    res.writeHead(200, {
      'Content-Type': clip.typ, 'Content-Length': clip.buf.length,
      'Cache-Control': 'no-store', 'Access-Control-Allow-Origin': '*'
    });
    return res.end(clip.buf);
  }

  // --- Anzeige zurücksetzen ---
  if (p === '/api/clear' && req.method === 'POST') {
    let b = {};
    try { b = await koerperJson(req); } catch (e) {}
    broadcast({ type: 'leeren' }, b.wartezimmer || 'alle');
    return json(res, { ok: true });
  }

  if (p.startsWith('/api/')) return json(res, { fehler: 'Unbekannt' }, 404);
  return serveStatic(req, res, req.url);
}

http.createServer((req, res) => {
  handler(req, res).catch((e) => {
    console.error('  Fehler:', e.message);
    if (!res.headersSent) json(res, { fehler: 'Serverfehler' }, 500);
  });
}).listen(PORT, '0.0.0.0', () => {
  const ip = eigeneIP();
  const quellen = [ip + ':' + PORT, ...(config.andereSprechzimmer || [])]
    .map(a => (a.includes(':') ? a : a + ':' + PORT)).join(',');

  console.log('');
  console.log('  Praxis-Ruf  —  ' + config.sprechzimmer.name);
  console.log('  ' + '-'.repeat(64));
  console.log('  Für die Ärztin an DIESEM PC:');
  console.log(`      http://localhost:${PORT}/praxis.html`);
  console.log('');
  console.log('  Für die Wartezimmer-Bildschirme:');
  for (const w of config.wartezimmer) {
    console.log(`      ${w.name}`);
    console.log(`      http://${ip}:${PORT}/wartezimmer.html?raum=${w.id}&quellen=${quellen}`);
  }
  console.log('  ' + '-'.repeat(64));
  console.log('  Dieser PC hat die Adresse ' + ip);
  console.log('  Zum Beenden Strg + C drücken oder das Fenster schliessen.');
  console.log('');
  meldeStatus();
});
