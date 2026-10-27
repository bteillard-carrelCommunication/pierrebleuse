// Mesure de continuité : molette réelle (Lenis) + journal par frame de la position des photos
const [S, base, tag] = [process.argv[2], process.argv[3], process.argv[4]];
const fs = await import('node:fs');
const list = await (await fetch('http://localhost:9333/json/list')).json();
const ws = new WebSocket(list.find(t => t.type === 'page').webSocketDebuggerUrl);
await new Promise(r => ws.onopen = r);
let id = 0; const pend = {};
ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pend[d.id]) { pend[d.id](d); delete pend[d.id]; }
  if (d.method === 'Runtime.exceptionThrown') console.log('EXC', JSON.stringify(d.params.exceptionDetails).slice(0, 600)); };
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend[i] = r; ws.send(JSON.stringify({ id: i, method, params })); });
const ev = async (expr) => (await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true })).result?.result?.value;
const wait = (ms) => new Promise(r => setTimeout(r, ms));
await send('Runtime.enable'); await send('Page.enable'); await send('Network.enable'); await send('Network.setCacheDisabled', { cacheDisabled: true });
await send('Emulation.clearDeviceMetricsOverride');
await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
await send('Emulation.setTouchEmulationEnabled', { enabled: false });
await send('Page.addScriptToEvaluateOnNewDocument', { source: `
  window.__log = [];
  (function loop() {
    const c = [...document.querySelectorAll('[data-hero-canvas]')].map(e => e.getBoundingClientRect().top);
    window.__log.push([performance.now(), window.scrollY, ...c]);
    requestAnimationFrame(loop);
  })();` });
await send('Page.navigate', { url: base + '/index.html' }); await wait(5000);
await ev('window.__log.length = 0; 1');
// 1. trackpad : deltaY 40 toutes les 16 ms jusqu'en bas
const docH = await ev('document.documentElement.scrollHeight - innerHeight');
let sent = 0;
while (sent < docH + 400) { await send('Input.dispatchMouseEvent', { type: 'mouseWheel', x: 700, y: 450, deltaX: 0, deltaY: 40 }); sent += 40; await wait(16); }
await wait(2000);
const log = await ev('JSON.stringify(window.__log)');
fs.writeFileSync(`${S}/wheel-${tag}.json`, log);
const rows = JSON.parse(log);
// analyse : pour chaque photo, rapport vitesse photo / vitesse page, sur les frames où la page bouge
for (let k = 0; k < rows[0].length - 2; k++) {
  const r = [];
  for (let i = 1; i < rows.length; i++) {
    const ds = rows[i][1] - rows[i - 1][1];
    const dt = rows[i][0] - rows[i - 1][0];
    const top = rows[i][2 + k];
    if (ds > 0.5 && top < 900 && top > -900) r.push({ t: rows[i][0], ratio: -(rows[i][2 + k] - rows[i - 1][2 + k]) / ds, top, v: ds / dt * 1000 });
  }
  if (!r.length) continue;
  let maxJump = 0, minRatio = 9, maxRatio = -9;
  for (let i = 1; i < r.length; i++) maxJump = Math.max(maxJump, Math.abs(r[i].ratio - r[i - 1].ratio));
  for (const x of r) { minRatio = Math.min(minRatio, x.ratio); maxRatio = Math.max(maxRatio, x.ratio); }
  const stopped = r.filter(x => Math.abs(x.ratio) < 0.05).length;
  console.log(`[${tag}] photo ${k + 1} : ${r.length} frames en mouvement à l'écran | rapport vitesse photo/page min ${minRatio.toFixed(2)} max ${maxRatio.toFixed(2)} | frames figées (rapport<0,05) : ${stopped} | plus grand saut du rapport d'une frame à l'autre : ${maxJump.toFixed(2)}`);
}
ws.close(); process.exit(0);
