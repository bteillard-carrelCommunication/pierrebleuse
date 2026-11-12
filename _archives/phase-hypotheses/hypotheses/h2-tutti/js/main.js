/*
 * main.js — amorçage de l'hypothèse 2 « Tutti ».
 * Lenis (souris/trackpad seulement) + ticker GSAP = une seule boucle ; les
 * formations sont précalculées à la mise en page ; le scroll ne fait
 * qu'interpoler (règle 11). API de test : window.TUTTI.
 */
(function () {
  'use strict';
  const T = window.Tutti, S = window.TuttiSections;
  const { CFG, TOUCH, REDUCED, clamp01, lerp, smooth01, easeG } = T;

  const N = CFG.N;
  const P = new T.ParticleSet(N);
  const sched = new T.Scheduler(P);
  const secs = Array.from(document.querySelectorAll('[data-tutti]')).map((el) => new S.Section(el, N));
  const byId = Object.fromEntries(secs.map((s) => [s.id, s]));
  const lines = Array.from(document.querySelectorAll('[data-lines]')).map((el) => new S.Lines(el));
  const events = Array.from(document.querySelectorAll('[data-ev]'));
  const evDots = events.map((e) => e.querySelector('[data-ev-dot]'));
  const planEl = document.querySelector('[data-plan]');
  const legend = document.querySelector('.legend');
  const hint = document.querySelector('.hint');
  const chefIdx = P.byFamily[0][0];
  const eventIdx = P.byFamily[0].slice(1, 6);
  const ZERO = new Float32Array(N);   // progression nulle (avant la construction du calendrier), allouée une fois

  const state = { s: 0, t: 0, frozen: false, vh: innerHeight, vw: innerWidth, pointer: null, offsets: {}, F: {}, S1: 0, docEnd: 0, lit: [0, 0, 0, 0, 0], fps: 0 };

  // 1. Scroll inertiel à la molette ; rien en tactile (défilement natif pur)
  const noLenis = /nolenis/.test(location.search);   // harnais de test : scroll natif
  const lenis = (!TOUCH && !noLenis && window.Lenis) ? new Lenis({ duration: 1.2, smoothWheel: !REDUCED, easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)) }) : null;
  gsap.ticker.lagSmoothing(0);
  gsap.ticker.fps(60);
  if (lenis) gsap.ticker.add((time) => lenis.raf(time * 1000));

  /* ---------- formations ---------- */
  const shuffle = (arr, R) => { const a = arr.slice(); for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(R() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; } return a; };
  const gauss = (R) => (R() + R() + R() - 1.5) * 1.2;

  function catmull(pts, n) {
    const out = [];
    const get = (i) => pts[Math.max(0, Math.min(pts.length - 1, i))];
    for (let seg = 0; seg < pts.length - 1; seg++) {
      const p0 = get(seg - 1), p1 = get(seg), p2 = get(seg + 1), p3 = get(seg + 2);
      for (let k = 0; k < n; k++) {
        const t = k / n, t2 = t * t, t3 = t2 * t;
        out.push({
          x: 0.5 * ((2 * p1.x) + (-p0.x + p2.x) * t + (2 * p0.x - 5 * p1.x + 4 * p2.x - p3.x) * t2 + (-p0.x + 3 * p1.x - 3 * p2.x + p3.x) * t3),
          y: 0.5 * ((2 * p1.y) + (-p0.y + p2.y) * t + (2 * p0.y - 5 * p1.y + 4 * p2.y - p3.y) * t2 + (-p0.y + 3 * p1.y - 3 * p2.y + p3.y) * t3),
        });
      }
    }
    out.push(pts[pts.length - 1]);
    return out;
  }

  function buildFormations() {
    const vh = state.vh, vw = state.vw;
    const R = T.rng(CFG.seed ^ 0x9e3779b9);
    const F = {};
    const mk = (name, host, fill, opts) => { const local = new Float32Array(2 * N); fill(local); return (F[name] = new T.Formation(name, host, local, opts)); };

    // libre : dispersés sur l'écran
    mk('free', 'screen', (L) => { for (let i = 0; i < N; i++) { L[2 * i] = vw * (0.04 + 0.92 * R()); L[2 * i + 1] = vh * (0.08 + 0.87 * R()); } }, { isFree: true });

    // sujet de la photo 1 : tirés dans le masque, plus denses sur le visage
    const o = byId.ouverture;
    const focalUV = { x: 0.52, y: 0.40 };
    const inside = (sec, p, m = 8) => p.x > m && p.x < sec.w - m && p.y > m && p.y < sec.h - m;
    mk('subject', 'ouverture', (L) => {
      const pts = o.maskPx ? T.sampleMask(o.maskPx, N * 2, focalUV, 0.16, 4, CFG.seed + 1) : [];
      let k = 0;
      for (let i = 0; i < N; i++) {
        let placed = false;
        while (k + 1 < pts.length) { const p = o.uvToLocal(pts[k], pts[k + 1]); k += 2; if (inside(o, p)) { L[2 * i] = p.x; L[2 * i + 1] = p.y; placed = true; break; } }
        if (!placed) { const p = o.uvToLocal(focalUV.x + (R() - 0.5) * 0.1, focalUV.y + (R() - 0.5) * 0.18); L[2 * i] = p.x; L[2 * i + 1] = p.y; }
      }
    });
    o.focal = o.uvToLocal(focalUV.x, focalUV.y);
    P.assignDelays(F.subject.local, o.focal);

    // fin de l'ouverture : cordes en 4 colonnes (stries du DA), bois + cuivres sur les bokeh, percussions dans l'ombre
    const portrait1 = o.portrait();
    const cols1 = portrait1
      ? [[0.065, 0.03, 1], [0.13, 0.026, -1], [0.86, 0.026, 1], [0.93, 0.03, -1]]              // écran : de part et d'autre, derrière le sujet
      : [[0.1995, 0.0175, 1], [0.2435, 0.0165, -1], [0.287, 0.017, 1], [0.333, 0.02, -1]];     // UV photo (maquette bleuse-2)
    o.cols = cols1; o.colSpace = portrait1 ? 1 : 0;
    mk('fin1', 'ouverture', (L) => {
      const cordes = P.byFamily[0];
      const rows = Math.ceil(cordes.length / 4);
      cordes.forEach((i, j) => {
        const c = cols1[j % 4], row = Math.floor(j / 4);
        const cx = portrait1 ? c[0] * o.w : o.uvToLocal(c[0], 0).x;
        L[2 * i] = cx + (R() - 0.5) * 2;
        L[2 * i + 1] = o.h * (0.04 + 0.92 * ((row + 0.5) / rows)) + (R() - 0.5) * 6;
      });
      const bokeh = [[0.665, 0.14, 0.11], [0.80, 0.52, 0.09]];
      [...P.byFamily[1], ...P.byFamily[2]].forEach((i, j) => {
        const b = bokeh[j % 2], a = R() * Math.PI * 2, r = Math.sqrt(R()) * b[2];
        let p = o.uvToLocal(b[0] + Math.cos(a) * r * 0.62, b[1] + Math.sin(a) * r);
        if (!inside(o, p, 12)) p = { x: o.w * (0.55 + 0.4 * R()), y: o.h * (0.05 + 0.3 * R()) };
        L[2 * i] = p.x; L[2 * i + 1] = p.y;
      });
      P.byFamily[3].forEach((i) => { L[2 * i] = o.w * (0.7 + 0.28 * R()); L[2 * i + 1] = o.h * (0.08 + 0.84 * R()); });
    });

    // plan de salle : éventail, cordes devant, bois, cuivres, percussions au fond ; le chef au centre bas
    const planH = planEl.offsetHeight || vh;
    const portrait2 = vw / planH < 0.9;
    const cx = vw / 2, cy = planH * 0.50, flat = portrait2 ? 0.8 : 0.62;
    const Rmax = portrait2 ? Math.min(0.46 * vw, 0.36 * planH) : Math.min(0.40 * vw, 0.40 * planH);
    mk('plan', 'respiration', (L) => {
      const placeArc = (list, rf, spanDeg) => {
        const n = list.length, span = (spanDeg * Math.PI) / 180;
        list.forEach((i, j) => {
          const th = Math.PI / 2 - span / 2 + span * ((j + 0.5) / n) + (R() - 0.5) * 0.02;
          const rr = Rmax * rf * (1 + (R() - 0.5) * 0.03);
          L[2 * i] = cx + rr * Math.cos(th);
          L[2 * i + 1] = cy + planH * 0.03 - flat * rr * Math.sin(th);
        });
      };
      const cordes = P.byFamily[0].filter((i) => i !== chefIdx);
      const per = Math.ceil(cordes.length / 3);
      placeArc(cordes.slice(0, per), 0.42, 168);
      placeArc(cordes.slice(per, 2 * per), 0.58, 172);
      placeArc(cordes.slice(2 * per), 0.74, 176);
      placeArc(P.byFamily[1], 0.86, 120);
      placeArc(P.byFamily[2], 0.96, 100);
      placeArc(P.byFamily[3], 1.06, 78);
      L[2 * chefIdx] = cx; L[2 * chefIdx + 1] = cy + planH * 0.09;
    }, { still: new Set([chefIdx]) });
    byId.respiration.planCenter = { x: cx, y: cy };

    // colonnes de la photo 2 : grille de la maquette bleuse-4 (14 stries, vide autour du chef)
    const o3 = byId.orchestre; const portrait3 = o3.portrait();
    let grid = [];
    if (portrait3) { const K = 8, period = 1 / K; for (let k = 0; k < K; k++) grid.push([(k + 0.5) * period, period / 4]); }
    else { const period = 60.25 / 992, width = 30.5 / 992, offset = -1.5 / 992; for (let k = 0; k < 17; k++) { if (k === 9 || k === 10 || k === 11) continue; grid.push([offset + k * period + width / 2, width / 2]); } }
    o3.cols = grid; o3.colSpace = portrait3 ? 1 : 0;
    const K = grid.length;
    const order = [...P.byFamily[0], ...P.byFamily[1], ...P.byFamily[2], ...P.byFamily[3]].sort((a, b) => P.delay[a] - P.delay[b]);
    const colOf = new Int16Array(N), members = grid.map(() => []);
    order.forEach((i, j) => { const k = j % K; colOf[i] = k; members[k].push(i); });
    o3.colOf = colOf; o3.members = members;
    // cellule d'une colonne (une période de la grille) en px locaux : la barre de lumière d'un point
    // couvre sa cellule, le shader y découpe la colonne (largeur, plume) ; demi-période en unités du shader
    const unit = portrait3 ? o3.w : (o3.cov ? o3.cov.mw : o3.w);
    const halfPeriod = portrait3 ? 1 / (2 * grid.length) : 30.125 / 992;
    o3.cellW = 2 * halfPeriod * unit * 1.2;
    o3.colFeather = grid.map((c) => 0.9 * (halfPeriod - c[1]));   // plume maximale : jusqu'au bord de la cellule
    // hauteur constante des barres : 2,2 fois l'espacement des membres → la colonne est couverte
    // sans trou une fois tous posés, et « se remplit » segment par segment avant
    o3.barH = grid.map((c, k) => (2.2 * o3.h) / Math.max(1, members[k].length));
    o3.fillSum = new Float32Array(K);
    mk('columns2', 'orchestre', (L) => {
      members.forEach((list, k) => {
        const c = grid[k], cxk = portrait3 ? c[0] * o3.w : o3.uvToLocal(c[0], 0).x, m = list.length;
        shuffle(list, R).forEach((i, j) => {
          L[2 * i] = cxk;
          L[2 * i + 1] = o3.h * ((j + 0.5) / m) + (R() - 0.5) * 0.4 * o3.h / m;   // rangs décalés : pas de treillis
        });
      });
    });

    // constellation : les 5 temps forts sur leurs ancres, les autres le long d'une ligne de temps qui ondule
    const ag = byId.agenda;
    const anchors = evDots.map((d) => { const r = d.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 + window.scrollY - ag.top }; });
    ag.anchors = anchors;
    const portrait4 = vw / vh < 0.9;
    mk('constellation', 'agenda', (L) => {
      eventIdx.forEach((i, k) => { L[2 * i] = anchors[k].x; L[2 * i + 1] = anchors[k].y; });
      let ctrl;
      if (portrait4) {
        ctrl = [{ x: anchors[0].x + 30, y: anchors[0].y - 0.12 * vh }];
        anchors.forEach((a, k) => { ctrl.push(a); if (k < anchors.length - 1) ctrl.push({ x: a.x + (k % 2 ? -22 : 34), y: (a.y + anchors[k + 1].y) / 2 }); });
        ctrl.push({ x: anchors[4].x + 20, y: anchors[4].y + 0.12 * vh });
      } else {
        ctrl = [{ x: 0.03 * vw, y: anchors[0].y + 0.03 * vh }, ...anchors, { x: 0.97 * vw, y: anchors[4].y - 0.03 * vh }];
      }
      const poly = catmull(ctrl, 40);
      const cum = [0];
      for (let i = 1; i < poly.length; i++) cum.push(cum[i - 1] + Math.hypot(poly[i].x - poly[i - 1].x, poly[i].y - poly[i - 1].y));
      const total = cum[cum.length - 1];
      const at = (u) => { const d = u * total; let i = 1; while (i < cum.length - 1 && cum[i] < d) i++; const a = poly[i - 1], b = poly[i], f = (d - cum[i - 1]) / Math.max(1e-6, cum[i] - cum[i - 1]); const dx = b.x - a.x, dy = b.y - a.y, l = Math.hypot(dx, dy) || 1; return { x: a.x + dx * f, y: a.y + dy * f, nx: -dy / l, ny: dx / l }; };
      const others = []; for (let i = 0; i < N; i++) if (!eventIdx.includes(i)) others.push(i);
      shuffle(others, R).forEach((i, j) => {           // pupitres mêlés le long de la ligne de temps
        const u = clamp01((j + 0.5) / others.length + (R() - 0.5) * 0.6 / others.length);
        const p = at(u), off = gauss(R) * (portrait4 ? 16 : 20);
        L[2 * i] = p.x + p.nx * off; L[2 * i + 1] = p.y + p.ny * off;
      });
    });

    // taches de lumière au sol de la photo 3, puis les deux voiles
    const pd = byId.pied;
    mk('spots', 'pied', (L) => {
      const pts = pd.photoPx ? T.sampleBright(pd.photoPx, N * 2, 0.5, 1.0, 0.55, CFG.seed + 5) : [];
      let k = 0;
      for (let i = 0; i < N; i++) {
        let placed = false;
        while (k + 1 < pts.length) { const p = pd.uvToLocal(pts[k], pts[k + 1]); k += 2; if (inside(pd, p)) { L[2 * i] = p.x; L[2 * i + 1] = p.y; placed = true; break; } }
        if (!placed) { L[2 * i] = pd.w * R(); L[2 * i + 1] = pd.h * (0.55 + 0.4 * R()); }
      }
    });
    const portrait5 = pd.portrait();
    const veils = portrait5 ? [[0.0, 0.17, 1], [0.83, 1.0, -1]] : [[0.0, 0.509, 1], [0.772, 1.0, -1]];
    pd.veils = veils; pd.veilSpace = portrait5 ? 1 : 0;
    mk('veils', 'pied', (L) => {
      const place = (list, v) => list.forEach((i) => {
        const u = v[0] + (v[1] - v[0]) * (0.06 + 0.88 * R());
        L[2 * i] = portrait5 ? u * pd.w : pd.uvToLocal(u, 0).x;
        L[2 * i + 1] = pd.h * (0.04 + 0.92 * R());
      });
      place(P.byFamily[0], veils[0]);
      place([...P.byFamily[1], ...P.byFamily[2], ...P.byFamily[3]], veils[1]);
    });
    state.F = F;
  }

  /* ---------- calendrier de scroll (px) ---------- */
  function buildSchedule() {
    const vh = state.vh, F = state.F;
    const r2 = byId.respiration, o3 = byId.orchestre, ag = byId.agenda, pd = byId.pied;
    const S1 = CFG.opening.course * vh;
    const docEnd = Math.max(1, document.documentElement.scrollHeight - vh);
    state.S1 = S1; state.docEnd = docEnd;
    const list = [
      { name: 'gather',   from: F.free,          to: F.subject,       s0: 0,                                        s1: 0.62 * S1 },
      { name: 'fin1',     from: F.subject,       to: F.fin1,          s0: 0.72 * S1,                                s1: 1.00 * S1 },
      // le plan de salle se forme une fois le texte de l'ouverture entré (1,24 S1), sur 0,6 écran (règle 10)
      { name: 'plan',     from: F.fin1,          to: F.plan,          s0: Math.max(1.26 * S1, r2.top - 0.95 * vh), s1: 0 },
      { name: 'columns',  from: F.plan,          to: F.columns2,      s0: o3.top - 0.8 * vh,                        s1: o3.top + 0.02 * vh },
      { name: 'constel',  from: F.columns2,      to: F.constellation, s0: ag.top - 0.6 * vh,                        s1: ag.top + 0.12 * vh },
      { name: 'spots',    from: F.constellation, to: F.spots,         s0: pd.top - 0.95 * vh,                       s1: pd.top - 0.32 * vh },
      // voiles sur 0,5 écran, achevés avant l'entrée de la ligne de pied (docEnd − 0,13 vh)
      { name: 'veils',    from: F.spots,         to: F.veils,         s0: pd.top,                                   s1: Math.min(docEnd - 0.14 * vh, pd.top + 0.5 * vh) },
    ];
    list[2].s1 = Math.max(list[2].s0 + 0.6 * vh, r2.top - 0.3 * vh);
    sched.setTransitions(list);

    // allumage des temps forts (après la constellation) : départs irréguliers, chaque point a son anacrouse
    const offs = [0, 0.05, 0.11, 0.15, 0.22];
    const portrait4 = state.vw / vh < 0.9;
    state.litFn = (k) => (s) => {
      const start = Math.max(list[4].s1 + (portrait4 ? 0 : offs[k] * vh), ag.top + ag.anchors[k].y - 0.84 * vh);
      return easeG(clamp01((s - start) / (0.2 * vh)));
    };
    // déclencheurs des textes (règle 8 : jamais pendant qu'une image se transforme)
    // - ouverture : après la 4e colonne (achevée à 0,99 S1) ; la cascade (0,26 S1) finit avant que
    //   le haut du bloc ne sorte du cadre ralenti (ordinateur : bloc à 60 % de la photo)
    // - photo 2 : après les colonnes (o3.top + 0,02 vh), cascade 0,34 vh achevée avant le départ des points
    // - pied : entre la pose des points sur les taches (pd.top − 0,32 vh) et les voiles (pd.top)
    for (const ln of lines) {
      const el = ln.el, rv = el.dataset.reveal;
      const top = el.getBoundingClientRect().top + window.scrollY;
      const ev = el.closest('[data-ev]');
      if (rv === 'ouverture') ln.progress = (s) => clamp01((s - 0.98 * S1) / (0.26 * S1));
      else if (rv === 'orchestre') ln.progress = (s) => clamp01((s - (o3.top + 0.04 * vh)) / (0.34 * vh));
      else if (rv === 'pied') ln.progress = (s) => clamp01((s - (pd.top - 0.31 * vh)) / (0.3 * vh));
      else if (ev) { const k = events.indexOf(ev); ln.progress = state.litFn(k); }
      else if (el.closest('.agenda__head')) ln.progress = (s) => clamp01((s + 0.78 * vh - top) / (0.3 * vh));
      else if (el.closest('.footer-bar')) ln.progress = (s) => clamp01((s - (docEnd - 0.13 * vh)) / (0.11 * vh));
      else ln.progress = (s) => clamp01((s + 0.88 * vh - top) / (0.3 * vh));
    }
  }

  /* ---------- mise en page ---------- */
  let layoutKey = '';
  function relayout(force) {
    state.vh = innerHeight; state.vw = innerWidth;
    secs.forEach((sec) => sec.measure(state.vh));
    secs.forEach((sec) => { try { sec.layout(); } catch (e) { console.warn('[Tutti] layout', e); } });
    const docH = document.documentElement.scrollHeight;
    const key = `${state.vw}x${state.vh}x${docH}x${devicePixelRatio}`;
    if (!force && key === layoutKey) return;
    layoutKey = key;
    lines.forEach((ln) => ln.split());
    secs.forEach((sec) => sec.measure(state.vh));   // les hauteurs ont pu changer avec le découpage
    if (!state.booted) return;                       // les formations attendent les textures (masques, photo 3)
    try { buildFormations(); buildSchedule(); } catch (e) { console.warn('[Tutti] formations', e); }
  }
  let pending = false;
  function schedule() { if (pending) return; pending = true; requestAnimationFrame(() => { pending = false; relayout(false); }); }
  addEventListener('resize', schedule);
  addEventListener('load', schedule);
  if (document.fonts) document.fonts.ready.then(schedule);
  if (window.ResizeObserver) new ResizeObserver(schedule).observe(document.body);

  /* ---------- pointeur = souffle ---------- */
  let ptr = null;
  addEventListener('pointermove', (e) => { if (e.pointerType !== 'touch') ptr = { x: e.clientX, y: e.clientY }; }, { passive: true });
  addEventListener('pointerleave', () => { ptr = null; });
  addEventListener('blur', () => { ptr = null; });
  addEventListener('touchmove', (e) => { const t = e.touches[0]; if (t) ptr = { x: t.clientX, y: t.clientY }; }, { passive: true });
  addEventListener('touchend', () => { ptr = null; }, { passive: true });

  /* ---------- mise à jour d'une section ---------- */
  function positionsFor(sec, s) {
    if (!REDUCED) return sec.pageTop(s);
    const own = { ouverture: 'fin1', respiration: 'plan', orchestre: 'columns2', agenda: 'constellation', pied: 'veils' }[sec.id];
    const F = state.F[own]; if (F) P.pos.set(F.local);
    return 0;
  }

  function updateOuverture(sec, s, t) {
    const vh = state.vh, u = sec.shader.uniforms;
    const p = REDUCED ? 1 : clamp01(s / state.S1);
    const fade = REDUCED ? smooth01(clamp01((s + 0.04 * vh) / (0.35 * vh))) : 1;
    const subject = REDUCED ? fade : easeG(clamp01((p - 0.58) / 0.2));
    u.uSubject = subject;
    u.uBg = REDUCED ? fade : easeG(clamp01((p - 0.62) / 0.3));
    u.uCut = REDUCED ? 1 : easeG(clamp01((p - 0.3) / 0.3));
    u.uColSpace = sec.colSpace; u.uColBehind = sec.colSpace;
    // colonnes : départs 0,70 / 0,73 / 0,76 / 0,79 de la course, durée 0,2 → la 4e est achevée à 0,99
    for (let k = 0; k < 4; k++) {
      const c = sec.cols[k], q = REDUCED ? fade : easeG(clamp01((p - 0.70 - 0.03 * k) / 0.2));
      u.uCol[4 * k] = c[0]; u.uCol[4 * k + 1] = c[1]; u.uCol[4 * k + 2] = q; u.uCol[4 * k + 3] = c[2];
    }
    // fenêtres : chaque point arrivé ouvre une fenêtre douce, qui grandit (× 6) et fusionne
    const offY = positionsFor(sec, s);
    const q0 = sched.q[0] || ZERO;
    const grow = easeG(clamp01((p - 0.46) / 0.26)), r0 = CFG.window.r0, G = CFG.window.grow;
    let any = false;
    for (let i = 0; i < N; i++) {
      const sp = sec.winSprites[i];
      sec.alphaMul[i] = 1 - 0.45 * subject; sec.sizeMul[i] = 1;
      // la lumière précède le point : la fenêtre s'allume pendant qu'il approche (règles 1 et 7)
      const a = REDUCED ? 0 : smooth01((q0[i] - 0.1) / 0.85);
      if (a <= 0.001 || subject >= 0.999) { sp.visible = false; continue; }
      any = true; sp.visible = true;
      sp.position.set(P.pos[2 * i], P.pos[2 * i + 1] - offY);
      const r = r0 * (1 + (G - 1) * grow) * lerp(0.5, 1, a);
      sp.scale.set((r * 5) / 128);
      sp.alpha = a;
    }
    if (any || sec.winDirty) { sec.app.renderer.render(sec.winLayer, { renderTexture: sec.winRT, clear: true }); sec.winDirty = any; }
    // halo d'anticipation sur le visage, qui s'efface quand le sujet apparaît (règle 1) ; sa
    // respiration (± 0,4 %, 8 s) est coupée en mouvement réduit comme toute ambiance
    const h = sec.halo, br = REDUCED ? 1 : 1 + 0.004 * Math.sin((t * Math.PI * 2) / 8);
    h.position.set(sec.focal.x, sec.focal.y);
    h.width = h.height = 0.62 * Math.min(sec.w, sec.h) * br;
    h.tint = 0xE9C58F; h.alpha = 0.055 * (1 - subject) * (REDUCED ? 1 : 0.85 + 0.15 * Math.sin((t * Math.PI * 2) / 8 + 1));
    sec.drawDots(P, t, offY, !REDUCED);
    if (hint) hint.style.opacity = (0.45 * (1 - clamp01(s / (0.15 * vh)))).toFixed(3);
  }

  function updateRespiration(sec, s, t) {
    const offY = positionsFor(sec, s);
    const q2 = sched.q[2] || ZERO, q3 = sched.q[3] || ZERO;
    const formed = REDUCED ? 1 : q2[chefIdx] * (1 - q3[chefIdx]);
    for (let i = 0; i < N; i++) { sec.alphaMul[i] = 1; sec.sizeMul[i] = 1; }
    sec.sizeMul[chefIdx] = lerp(1, 6.5 / P.radius[chefIdx], formed);
    const c = sec.planCenter, h = sec.halo, br = REDUCED ? 1 : 1 + 0.004 * Math.sin((t * Math.PI * 2) / 9);
    h.position.set(c.x, c.y); h.width = h.height = 0.9 * Math.min(sec.w, state.vh) * br;
    h.tint = 0x8A84C4; h.alpha = 0.07 * formed * (REDUCED ? 1 : 0.85 + 0.15 * Math.sin((t * Math.PI * 2) / 9));
    if (legend) legend.style.opacity = formed.toFixed(3);
    sec.drawDots(P, t, offY, !REDUCED);
  }

  function updateOrchestre(sec, s, t) {
    const vh = state.vh, u = sec.shader.uniforms;
    const offY = positionsFor(sec, s);
    const q3 = sched.q[3] || ZERO, q4 = sched.q[4] || ZERO;
    const settle = REDUCED ? 1 : easeG(sched.progress(3, s));
    u.uReveal = clamp01((s - (sec.top - 0.95 * vh)) / (0.6 * vh));
    u.uSettle = settle;
    u.uColSpace = sec.colSpace;
    const tgt = state.F.columns2.local, K = sec.cols.length, fill = sec.fillSum;
    fill.fill(0);
    for (let i = 0; i < N; i++) {
      const sp = sec.winSprites[i], a = REDUCED ? 1 : q3[i];
      // g : le point se pose (anacrouse) ; quand il repart (q4), sa barre s'éteint sur place
      const g = easeG(clamp01((a - 0.55) / 0.45)) * (1 - (REDUCED ? 0 : q4[i]));
      const k = sec.colOf[i];
      fill[k] += g;
      sec.sizeMul[i] = 1;
      sec.alphaMul[i] = 1 - 0.5 * g;                     // posé sur la photo, le point s'atténue (pas de treillis)
      if (g <= 0.001) { sp.visible = false; continue; }
      const mv = smooth01((a - 0.55) / 0.45);
      sp.visible = true;
      // la barre de lumière naît dans la colonne, à la hauteur où le point arrive, et s'y pose avec lui
      sp.position.set(tgt[2 * i], lerp(P.pos[2 * i + 1] - offY, tgt[2 * i + 1], mv));
      sp.width = sec.cellW; sp.height = sec.barH[k];
      sp.alpha = g;
    }
    // finesse de chaque colonne = part de ses membres posés : plume large → 1-2 px, lumière → découpe
    for (let k = 0; k < K; k++) {
      const c = sec.cols[k];
      u.uCols[4 * k] = c[0]; u.uCols[4 * k + 1] = c[1];
      u.uCols[4 * k + 2] = clamp01(fill[k] / Math.max(1, sec.members[k].length));
      u.uCols[4 * k + 3] = sec.colFeather[k];
    }
    u.uNCols = K;
    sec.app.renderer.render(sec.winLayer, { renderTexture: sec.winRT, clear: true });
    sec.halo.alpha = 0;
    sec.drawDots(P, t, offY, !REDUCED);
  }

  function updateAgenda(sec, s, t) {
    const offY = positionsFor(sec, s);
    if (!sec.evHalos) {
      sec.evHalos = eventIdx.map(() => { const h = new PIXI.Sprite(S.textures().halo); h.anchor.set(0.5); h.blendMode = PIXI.BLEND_MODES.ADD; h.tint = 0xF2DDB2; sec.app.stage.addChildAt(h, 1); return h; });
    }
    for (let i = 0; i < N; i++) { sec.alphaMul[i] = 1; sec.sizeMul[i] = 1; }
    eventIdx.forEach((i, k) => {
      const lit = !state.litFn || !sec.anchors ? 0
        : REDUCED ? clamp01((s + 0.85 * state.vh - (sec.top + sec.anchors[k].y)) / (0.2 * state.vh)) : state.litFn(k)(s);
      state.lit[k] = lit;
      sec.sizeMul[i] = lerp(1, 7 / P.radius[i], lit);
      sec.alphaMul[i] = lerp(1, 1.15, lit);
      const h = sec.evHalos[k], br = REDUCED ? 1 : 1 + 0.004 * Math.sin((t * Math.PI * 2) / 7 + k);
      h.position.set(P.pos[2 * i], P.pos[2 * i + 1] - offY);
      h.width = h.height = 150 * br; h.alpha = 0.16 * lit;
    });
    sec.halo.alpha = 0;
    sec.drawDots(P, t, offY, !REDUCED);
  }

  function updatePied(sec, s, t) {
    const vh = state.vh, u = sec.shader.uniforms;
    const offY = positionsFor(sec, s);
    u.uReveal = clamp01((s - (sec.top - 0.95 * vh)) / (0.6 * vh));
    const pv = REDUCED ? 1 : sched.progress(6, s);
    u.uVeilSpace = sec.veilSpace;
    for (let k = 0; k < 2; k++) {
      const v = sec.veils[k], q = REDUCED ? 1 : easeG(clamp01((pv - 0.15 * k) / 0.85));
      u.uVeil[4 * k] = v[0]; u.uVeil[4 * k + 1] = v[1]; u.uVeil[4 * k + 2] = q; u.uVeil[4 * k + 3] = v[2];
    }
    for (let i = 0; i < N; i++) { sec.alphaMul[i] = 1 - 0.25 * pv; sec.sizeMul[i] = 1; }
    sec.halo.alpha = 0;
    sec.drawDots(P, t, offY, !REDUCED);
  }

  const UPDATERS = { ouverture: updateOuverture, respiration: updateRespiration, orchestre: updateOrchestre, agenda: updateAgenda, pied: updatePied };

  /* ---------- boucle ---------- */
  let frames = 0, fpsT = 0;
  gsap.ticker.add((time, deltaTime) => {
    const dt = Math.min(deltaTime, 100) / 1000;
    if (!state.frozen) state.t += dt;
    const t = state.t, s = window.scrollY, vh = state.vh;
    state.s = s;
    frames++; fpsT += dt; if (fpsT >= 1) { state.fps = frames / fpsT; frames = 0; fpsT = 0; }
    // ralenti de la première photo (ordinateur) : position = fonction exacte du scroll
    const o = byId.ouverture;
    if (o && o.L) {
      const dpr = devicePixelRatio || 1;
      const y = Math.round(o.lingerY(s) * dpr) / dpr;
      if (y !== o.y) { o.y = y; o.frame.style.transform = `translate3d(0, ${y}px, 0)`; }
    }
    const off = state.offsets;
    off.screen = { x: 0, y: s };
    for (const sec of secs) off[sec.id] = { x: 0, y: sec.pageTop(s) };
    state.pointer = ptr ? { x: ptr.x, y: ptr.y + s } : null;
    if (!state.F.free || !sched.transitions.length) return;   // formations pas encore construites : écran noir
    if (!REDUCED) sched.update(s, t, dt, off, state.pointer, true);
    for (const sec of secs) {
      if (!sec.ready || sec.failed || !sec.isVisible(s, vh)) continue;
      try { UPDATERS[sec.id](sec, s, t); sec.render(); } catch (e) { sec.failed = true; sec.el.classList.add('is-fallback'); console.warn('[Tutti]', sec.id, e); }
    }
    for (const ln of lines) ln.update(s);
  });

  /* ---------- amorçage ---------- */
  relayout(true);
  let okCount = 0;
  Promise.all(secs.map((sec) => sec.init().then(() => { okCount++; }, (err) => {
    console.warn('[Tutti] section sans WebGL, photo seule :', sec.id, err);
    sec.failed = true; sec.el.classList.add('is-fallback');
    if (sec.isPhoto && sec.img) sec.texSize = { w: sec.img.naturalWidth || 992, h: sec.img.naturalHeight || 619 };
    if (sec.app) { try { sec.app.destroy(false); } catch (e) { /* déjà détruit */ } sec.app = null; }
  }))).then(() => {
    if (!okCount) document.documentElement.classList.add('no-webgl');
    state.booted = true;
    relayout(true);
    document.documentElement.classList.add('tutti-ready');
  });

  window.TUTTI = {
    state, P, sched, secs, byId, CFG, lines,
    freeze(on = true) { state.frozen = !!on; },
    relayout: () => relayout(true),
  };
})();
