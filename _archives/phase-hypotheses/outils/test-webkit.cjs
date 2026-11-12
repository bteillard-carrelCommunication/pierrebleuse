const { webkit, devices } = require(process.argv[2] + '/node_modules/playwright');
(async () => {
  const out = process.argv[3];
  for (const [name, ctxOpts] of [['iphone', devices['iPhone 15'] || devices['iPhone 14']], ['desktop', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }]]) {
    const browser = await webkit.launch();
    const ctx = await browser.newContext(ctxOpts);
    const page = await ctx.newPage();
    const logs = [];
    page.on('console', (m) => { if (m.type() === 'error' || m.type() === 'warning') logs.push(`${m.type()}: ${m.text()}`.slice(0, 300)); });
    page.on('pageerror', (e) => logs.push('pageerror: ' + String(e).slice(0, 300)));
    page.on('requestfailed', (r) => logs.push('requestfailed: ' + r.url()));
    await page.goto('http://localhost:8765/maquette-v1.html', { waitUntil: 'load' });
    await page.waitForTimeout(4000);
    const st = await page.evaluate(() => heroes.map((h) => ({ preset: h.section.dataset.preset, ready: h.ready, comp: h.compKey, canvas: [h.canvas.width, h.canvas.height], gl: h.app && h.app.renderer.context.webGLVersion, touch: getComputedStyle(h.canvas).touchAction, pe: getComputedStyle(h.canvas).pointerEvents })));
    console.log(name, JSON.stringify(st));
    // effet complet forcé sur chaque hero, plein cadre, capture
    for (let i = 0; i < st.length; i++) {
      await page.evaluate((i) => { const h = heroes[i]; h.params.smoothing = 0; h.geo.p0 = -1e7; h.geo.p1 = -1e7 + 1; window.scrollTo(0, h.geo.top + (h.geo.L ? h.geo.c : 0)); }, i);
      await page.waitForTimeout(1200);
      await page.screenshot({ path: `${out}/${name}-${st[i].preset}.png` });
    }
    console.log(name, 'logs:', logs.length ? logs : 'aucun');
    await browser.close();
  }
})().catch((e) => { console.error('ERREUR', e); process.exit(1); });
